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

require_once __DIR__ .'/../../../../core/php/core.inc.php';


class HiKumo {

    public const AIRCLOUD_API_URL ='https://ha117-1.overkiz.com/enduser-mobile-web/enduserAPI';
    public const AVAILABLE_MODES = ['cooling', 'heating', 'auto', 'fan', 'dehumidify', 'circulator'];
    // fan values: API pull => API push
    public const AVAILABLE_FAN_SPEED = [
        'auto' => 'auto',
        'silent' => 'silent',
        'low' => 'lo',
        'medium' => 'med',
        'high' => 'hi'
    ];

    private $email = '';

    private $password = '';

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
            if(
                !in_array($hitachiDevice['definition']['widgetName'], [
                    'HitachiAirToAirHeatPump', // air/air: split
                    'HitachiDHW', // air/water: Domestic Hot Water (boiler)
                    'HitachiAirToWaterHeatingZone', // air/water: radiators, floor heatings, ...
                ])
            ) {
                continue;
            }

            $deviceId = $hitachiDevice['oid'];
            $devices[$deviceId]['label'] = $hitachiDevice['label'];
            $devices[$deviceId]['url'] = $hitachiDevice['deviceURL'];
            $devices[$deviceId]['type'] = $hitachiDevice['definition']['widgetName'];

            foreach($hitachiDevice['states'] as $state) {
                switch($state['name']) {
                    case 'core:StatusState': // air/air splits
                    case 'modbus:StatusDHWState': // air/water DHW run|stop
                    case 'modbus:ControlCircuit1State': // air/water zones run|stop
                    case 'modbus:ControlCircuit2State': // air/water zones run|stop
                        $devices[$deviceId]['status'] = $state['value'];
                        break;
                    case 'ovp:MainOperationState':
                    case 'modbus:ControlDHWState':
                    case 'modbus:StatusCircuit1State':
                    case 'modbus:StatusCircuit2State':
                        $devices[$deviceId]['state'] = $state['value'];
                        break;
                    case 'core:TargetTemperatureState':
                    case 'modbus:ControlDHWSettingTemperatureState':
                    case 'modbus:ThermostatSettingStatusZone1State':
                    case 'modbus:ThermostatSettingStatusZone2State':
                        $devices[$deviceId]['targetTemperature'] = $state['value'];
                        break;
                    case 'ovp:RoomTemperatureState':
                    case 'core:DHWTemperatureState':
                    case 'modbus:RoomAmbientTemperatureStatusZone1State':
                    case 'modbus:RoomAmbientTemperatureStatusZone2State':
                        $devices[$deviceId]['currentTemperature'] = $state['value'];
                        break;
                    case 'ovp:ModeChangeState': // cooling|heating|auto cooling|auto heating|fan|dehumidify|circulator
                    case 'modbus:DHWModeState': // standard|high demand
                        $devices[$deviceId]['mode'] = strtolower($state['value']);

                        if(stripos($devices[$deviceId]['mode'], 'auto') !== false) {
                            $devices[$deviceId]['mode'] = 'auto';
                        }
                        break;
                    case 'ovp:FanSpeedState': // auto|silent|lo|med|high
                        $devices[$deviceId]['fanSpeed'] = self::AVAILABLE_FAN_SPEED[$state['value']];
                        break;
                }
            }
        }

        // Eco mode
        $preferences = $this->queryAPI('/enduser/preferences');

        $ecoMode = false;
        foreach($preferences as $preference) {
            switch ($preference['name']) {
                case 'pref.gogreen':
                    $ecoMode = $preference['value'] === 'true';
                    cache::set('hitachihikumo::ecoId', $preference['oid']);
                    break;
            }
        }

        foreach ($devices as $id => $device) {
            $devices[$id]['ecoMode'] = $ecoMode;
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

    /** {
       "label":"ECS",
       "actions":[{
       "commands":[{"name":"setControlDHW","parameters":["run"]}],
       "deviceURL":"modbus://0123-4567-8901/1234567/1#4"
       }],
       "internal":false
    } */
    public function switchOnDHW(string $deviceId): bool
    {
        $device = $this->getDeviceById($deviceId);

        return $this->sendCommand(
            'setControlDHW', ['run'], $device['url'], 'Set DHW ('.$device['label'].') to ON'
        );
    }

    /** {
       "label":"ECS",
       "actions":[{
       "commands":[{"name":"setControlDHW","parameters":["stop"]}],
       "deviceURL":"modbus://0123-4567-8901/1234567/1#4"
       }],
       "internal":false
    } */
    public function switchOffDHW(string $deviceId): bool
    {
        $device = $this->getDeviceById($deviceId);

        return $this->sendCommand(
            'setControlDHW', ['stop'], $device['url'], 'Set DHW ('.$device['label'].') to OFF'
        );
    }

    /** {
        "label":"ON modbus://0123-4567-8901/1234567/1#3",
        "actions":[{
            "commands":[
                {"name":"globalControl", "parameters":["manu","heat",20]},
                {"name":"setAutoManuMode","parameters":["manu"]},
                {"name":"setHolidayMode","parameters":["off"]}
            ],
            "deviceURL":"modbus://0839-7209-2314/8682532/1#3"}],
            "internal":false
    } */
    public function switchOnHeatZone(string $deviceId): bool
    {
        $device = $this->getDeviceById($deviceId);

        return $this->sendCommand(
            'globalControl',
            ['manu', 'heat', '-1'],
            $device['url'],
            'Set heat zone ('.$device['label'].') to ON'
        );
    }

    /** {
        "label":"OFF modbus://0123-4567-8901/1234567/1#3",
        "actions":[{
            "commands":[
                {"name":"globalControl","parameters":["off","fake",-1]},
                {"name":"setAutoManuMode","parameters":["manu"]},
                {"name":"setHolidayMode","parameters":["off"]}
            ],
        "deviceURL":"modbus://0839-7209-2314/8682532/1#3"}],
        "internal":false
    } */
    public function switchOffHeatZone(string $deviceId): bool
    {
        $device = $this->getDeviceById($deviceId);

        return $this->sendCommand(
            'globalControl',
            ['off', 'fake', -1],
            $device['url'],
            'Set heat zone ('.$device['label'].') to ON'
        );
    }


    public function setEco(bool $onOff): bool
    {
        $ecoMode = $onOff ? 'true' : 'false';
        $ecoId = cache::byKey('hitachihikumo::ecoId')->getValue('');
        $url = '/enduser/preferences/'.$ecoId.'/'.$ecoMode;

        $result = $this->queryAPI($url, 'PUT');

        if(empty($result)) {
            return true;
        }

        log::add('hitachihikumo', 'error', 'PUT '.$url.' failed: '.print_r($result, true));

        return false;
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
            'userId' => $this->email, 'userPassword' => $this->password,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true); // enable tracking

        log::add('hitachihikumo', 'debug', '>>> POST /login (requesting a token) with email: '.$this->email);
        $response = curl_exec($ch);

        curl_close ($ch);

        list($headers, $content) = explode("\r\n\r\n", $response, 2);
        log::add('hitachihikumo', 'debug', '<<< POST /login: '.$content);

        $contentData = json_decode($content);

        if(json_last_error() !== JSON_ERROR_NONE) {
            log::add('hitachihikumo', 'error', 'POST '.$url.', invalid JSON: '.$content);
            die('POST /login, invalid JSON response from AirCloud server: '.$content);
        }

        if(isset($contentData->errorCode) && $contentData->errorCode === 'AUTHENTICATION_ERROR') {
            log::add('hitachihikumo', 'error', 'POST '.$url.', authentication invalid credentials (OR IP BANNED): '.$content);
            die('Hitachi Hi-Kumo authentication failed, login or password incorrect (OR IP BANNED)');
        }

        if(!isset($contentData->success) || $contentData->success !== true) {
            log::add('hitachihikumo', 'error', 'POST '.$url.', authentication failed: '.$content);
            die('Authentication failed: '.$content);
        }

        preg_match('/JSESSIONID=\K[0-9A-F]+/', $headers, $token);
        log::add('hitachihikumo', 'debug', '<<< POST /login new token: '.$token[0]);


        if(!empty($token)) {
            cache::set('hitachihikumo::token', $token[0]);
        } else {
            log::add('hitachihikumo', 'error', 'POST '.$url.', authentication failed (empty token) '.$content);
            die('Authentication failed (empty token): '.$content);
        }
    }

    protected function queryAPI(
        string $endpoint,
        string $method = 'GET',
        $body = null,
        bool $currentlyRetrying = false
    ): array {
        $token = cache::byKey('hitachihikumo::token')->getValue(null);
        if(empty($token) || $currentlyRetrying === true) {
            $this->requestToken();
        }

        $headers = ['Cookie: JSESSIONID='.$token];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::AIRCLOUD_API_URL.$endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            $headers[] = 'Content-Type: application/json; charset=UTF-8';
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_PUT, true);
            $headers[] = 'Content-Type: application/json; charset=UTF-8';
        }

        if(!is_null($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        log::add('hitachihikumo', 'debug', '>>> '.$method.' '.$endpoint.(!is_null($body) ?? ' : '.print_r($body, true)));
        $content = curl_exec($ch);
        log::add('hitachihikumo', 'debug', '<<< '.$method.' '.$endpoint.' : '.$content);

        curl_close ($ch);

        $contentData = json_decode($content, true);

        if(json_last_error() !== JSON_ERROR_NONE) {
            log::add('hitachihikumo', 'error', $method.' '.$endpoint.', authentication failed: '.$content);
            die($method.' '.$endpoint.', invalid JSON response from AirCloud server: '.$content);
        }

        if(isset($contentData['errorCode'])) {
            switch ($contentData['errorCode']) {
                case 'RESOURCE_ACCESS_DENIED':
                    if($currentlyRetrying === false) {
                        log::add('hitachihikumo', 'debug', $method.' '.$endpoint.' failed, retying with a new token: '.$content);
                        cache::byKey('hitachihikumo::token')->remove();
                        $contentData = $this->queryAPI($endpoint, $method, $body, true);
                    } else {
                        log::add('hitachihikumo', 'error', $method.' '.$endpoint.' failed even with a new token: '.$content);
                        die($method.' '.$endpoint.' failed even with a new token: '.$content);
                    }
                    break;
                default:
                    log::add('hitachihikumo', 'error', $method.' '.$endpoint.', error: '.$content);
                    die($method.' '.$endpoint.' error: '.$content);
            }
        }

        return $contentData;
    }
}

