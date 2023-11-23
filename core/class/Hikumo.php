<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once __DIR__  . '/../../../../core/php/core.inc.php';


class HiKumo {

    public const AIRCLOUD_API_URL ='https://ha117-1.overkiz.com/enduser-mobile-web/enduserAPI';
    public const AVAILABLE_MODES = ['cooling', 'heating', 'auto', 'fan', 'dehumidify', 'circulator'];
    public const AVAILABLE_FAN_SPEED = ['auto', 'silent', 'lo', 'med', 'hi'];

    private string $token = '';

    private string $email = '';

    private string $password = '';

    public function setEmail(string $email): HiKumo
    {
        $this->email = $email;

        return $this;
    }

    public function setPassword(string $password): HiKumo
    {
        $this->password = $password;

        return $this;
    }

    public function getDeviceById(string $deviceID)
    {
        return $this->getDevices()[$deviceID];
    }

    public function getDevices(): array
    {
        $devices = [];
        $hitachiDevices = $this->queryAPI('/setup')['devices'];

        foreach($hitachiDevices as $hitachiDevice) {
            // Don't need the box infos and not compatibles modules
            if($hitachiDevice['definition']['widgetName'] !== 'HitachiAirToAirHeatPump') {
                continue;
            }

            $deviceId = $hitachiDevice['oid'];
            $devices[$deviceId]['label'] = $hitachiDevice['label'];
            $devices[$deviceId]['url'] = $hitachiDevice['deviceURL'];

            foreach($hitachiDevice['states'] as $state) {
                switch($state['name']) {
                    case 'core:StatusState': //
                        $devices[$deviceId]['status'] = $state['value'];
                        break;
                    case 'ovp:MainOperationState': // on|off
                        $devices[$deviceId]['state'] = $state['value'];
                        break;
                    case 'core:AutoManuModeState': // auto|manu
                        $devices[$deviceId]['autoManuModeState'] = $state['value'];
                        break;
                    case 'core:HolidaysModeState': // on|off
                        $devices[$deviceId]['HolidaysModeState'] = $state['value'];
                        break;
                    case 'ovp:ModeChangeState': // cooling|heating|auto cooling|auto heating|fan|dehumidify|circulator
                        $devices[$deviceId]['mode'] = strtolower($state['value']);
                        if(stripos($devices[$deviceId]['mode'], 'auto') !== false) {
                            $devices[$deviceId]['mode'] = 'auto';
                        }
                        break;
                    case 'ovp:FanSpeedState': // auto|silent|lo|med|high
                        $devices[$deviceId]['fanSpeed'] = $state['value'];
                        break;
                    case 'core:TargetTemperatureState':
                        $devices[$deviceId]['targetTemperature'] = $state['value'];
                        break;
                    case 'ovp:RoomTemperatureState':
                        $devices[$deviceId]['roomTemperature'] = $state['value'];
                        break;
                }
            }
        }

        return $devices;
    }

    public function switchOn(string $deviceId): bool
    {
        $device = $this->getDeviceById($deviceId);

        return $this->sendCommand(
            'setMainOperation', ['on'], $device['url'], 'Set unit ('.$device['label'].') to ON'
        );
    }

    public function switchOff(string $deviceId): bool
    {
        $device = $this->getDeviceById($deviceId);

        return $this->sendCommand(
            'setMainOperation', ['off'], $device['url'], 'Set unit ('.$device['label'].') to OFF'
        );
    }

    public function setTemperature(string $deviceId, int $value): bool
    {
        $device = $this->getDeviceById($deviceId);
        if(stripos($device['mode'], 'auto')) { // auto mode need temperature - 25
            $value = $value - 25;
        }

        return $this->sendCommand(
            'globalControl',
            [$device['state'], $value, $device['fanSpeed'], $device['mode']],
            $device['url'],
            'Set unit ('.$device['label'].') temperature to '.$value
        );
    }

    public function setMode(string $deviceId, string $mode): bool
    {
        $device = $this->getDeviceById($deviceId);

        if(!in_array($mode, self::AVAILABLE_MODES)) {
            throw new InvalidArgumentException('Invalid parameter mode ('.$mode.') on device '.$device['label']);
        }

        if(stripos($mode, 'auto')) { // auto mode need temperature - 25
            $device['targetTemperature'] = $device['targetTemperature'] - 25;
        }

        return $this->sendCommand(
            'globalControl',
            [$device['state'], $device['targetTemperature'], $device['fanSpeed'], $mode],
            $device['url'],
            'Set unit ('.$device['label'].') mode to '.$mode
        );
    }

    public function setFanSpeed(string $deviceId, string $fanSpeed)
    {
        $device = $this->getDeviceById($deviceId);

        if(!in_array($fanSpeed, self::AVAILABLE_FAN_SPEED)) {
            throw new InvalidArgumentException('Invalid parameter fanSpeed ('.$fanSpeed.') on device '.$device['label']);
        }

        if(stripos($device['mode'], 'auto')) { // auto mode need temperature - 25
            $device['targetTemperature'] = $device['targetTemperature'] - 25;
        }

        return $this->sendCommand(
            'globalControl',
            [$device['state'], $device['targetTemperature'], $fanSpeed, $device['mode']],
            $device['url'],
            'Set unit ('.$device['label'].') fan speed to '.$fanSpeed
        );
    }

    protected function sendCommand(
        string $action, array $params, string $deviceUrl, string $label
    ): bool {
        $content = [
            'label' => $label,
            'actions' => [
                [
                    'deviceURL' => $deviceUrl,
                    'commands' => [['name' => $action, 'parameters' => $params]]
                ],
            ]
        ];

        $result = $this->queryAPI('/exec/apply', 'POST', json_encode($content));

        if(!isset($result['execId'])) {
            log::add('hitachihikumo', 'error', 'POST /exec/apply command failed ('.$label.'): '.print_r($content, true));
            throw new Exception('POST /exec/apply, command failed ('.$label.'): '.print_r($content, true));
        }

        return true;
    }

    protected function requestToken(): void
    {
        $url = self::AIRCLOUD_API_URL.'/login';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER  ,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'userId' => $this->email, 'userPassword' => $this->password]
        ));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        curl_close ($ch);

        list($headers, $content) = explode("\r\n\r\n", $response, 2);

        $contentData = json_decode($content);

        if(json_last_error() !== JSON_ERROR_NONE) {
            log::add('hitachihikumo', 'error', 'POST '.$url.', invalid JSON: '.$content);
            die('POST /login, invalid JSON response from AirCloud server: '.$content);
        }

        if(isset($contentData->errorCode) && $contentData->errorCode === 'AUTHENTICATION_ERROR') {
            log::add('hitachihikumo', 'error', 'POST '.$url.', authentication invalid credentials: '.$content);
            die('Hitachi Hi-Kumo authentication failed, login or password incorrect');
        }

        if(!isset($contentData->success) || $contentData->success !== true) {
            log::add('hitachihikumo', 'error', 'POST '.$url.', authentication failed: '.$content);
            die('Authentication failed: '.$content);
        }

        preg_match('/JSESSIONID=\K[0-9A-F]+/', $headers, $token);

        if(!empty($token)) {
            $this->token = $token[0];
        } else {
            log::add('hitachihikumo', 'error', 'POST '.$url.', authentication failed (empty token) '.$content);
            die('Authentication failed (empty token): '.$content);
        }
    }

    protected function queryAPI(string $endpoint, string $method = 'GET', $body = null): array
    {
        if(empty($this->token)) {
            $this->requestToken();
        }

        $headers = ['Cookie: JSESSIONID='.$this->token];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::AIRCLOUD_API_URL.$endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            $headers[] = 'Content-Type: application/json; charset=UTF-8';
        }
        if(!is_null($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $content = curl_exec($ch);
        curl_close ($ch);

        $contentData = json_decode($content, true);

        if(json_last_error() !== JSON_ERROR_NONE) {
            log::add('hitachihikumo', 'error', $method.' '.$endpoint.', authentication failed: '.$content);
            die($method.' '.$endpoint.', invalid JSON response from AirCloud server: '.$content);
        }

        if(isset($content->errorCode)) {
            log::add('hitachihikumo', 'error', $method.' '.$endpoint.', error: '.$content);
            die($method.' '.$endpoint.' error: '.$content);
        }

        return $contentData;
    }
}

