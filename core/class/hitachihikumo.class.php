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

require_once dirname(__FILE__) . '/Hikumo.php';
require_once dirname(__FILE__) . '/hitachihikumoCmd.php';

class hitachihikumo extends eqLogic {

    protected $hiKumo;

    public function __construct()
    {
        $this->hiKumo = new HiKumo();
        $this->hiKumo->setEmail(trim(config::byKey('accountmail', 'hitachihikumo')));
        $this->hiKumo->setPassword(trim(config::byKey('accountpass', 'hitachihikumo')));
    }

    /**
     * Used by the cronjob to update the values from Hi-Kumo API
     * they could have changed with remote control, mobile application, ...
     */
    public static function cron5()
    {
        $devicesInfos = (new hitachihikumo())->hiKumo->getDevices();

        foreach ($devicesInfos as $id => $deviceInfos) {
            $eqLogic = self::byLogicalId($id, 'hitachihikumo');
            if($eqLogic instanceof eqLogic && $eqLogic->getIsEnable()) {
                $eqLogic->createAndUpdateCmd(false, $deviceInfos);
            }
        }
    }

    public function syncDevices()
    {
        $jeedomDevices = eqLogic::byType('hitachihikumo');
        $devices = $this->hiKumo->getDevices();

        foreach($devices as $id => $device) {
            foreach ($jeedomDevices as $jeedomDevice) {
                if ($id == $jeedomDevice->getLogicalId()) { //Already exists
                    continue 2;
                }
            }

            $eqlogic = new hitachihikumo();
            $eqlogic->setName($device['label']);
            $eqlogic->setIsEnable(1);
            $eqlogic->setIsVisible(0);
            $eqlogic->setLogicalId($id);
            $eqlogic->setEqType_name('hitachihikumo');
            $eqlogic->setConfiguration('url', $device['url']);
            $eqlogic->setConfiguration('type', $device['type']);
            $eqlogic->save();
        }
    }

	public static function templateWidget()
    {
		$return = ['action' => ['other' => []]];

		$return['action']['other']['boutonOnOff'] = [
			'template' => 'tmplicon',
			'display' => ['#icon#' => '<i class="icon jeedom-prise"></i>'],
			'replace' => [
				'#_icon_on_#' => '<i class="icon jeedom-prise"></i>',
				'#_icon_off_#' => '<i class="icon fas fa-times"></i>',
				'#_time_widget_#' => '0',
            ],
        ];

		$return['action']['other']['ecoOnOff'] = [
			'template' => 'tmplicon',
			'display' => ['#icon#' => '<img src="/plugins/hitachihikumo/img/eco-on.png" style="margin-bottom: 20px" />'],
			'replace' => [
				'#_icon_on_#' => '<img src="/plugins/hitachihikumo/img/eco-on.png" style="margin-bottom: 20px" />',
				'#_icon_off_#' => '<img src="/plugins/hitachihikumo/img/eco-off.png" style="margin-bottom: 20px" />',
				'#_time_widget_#' => '0',
            ],
        ];

		return $return;
    }

	public function postSave() {
      	if($this->getIsEnable() == 1)
			self::createAndUpdateCmd();
	}

    public function createAndUpdateCmd($createCmd = true, $deviceInfos = []) {
        $this->__construct(); // WFT, Reflection ?
        if(empty($deviceInfos)) {
            $deviceInfos = $this->hiKumo->getDeviceById($this->getLogicalId());
        }

        // Create/update INFO commands
        $infosCommands = [
            'status' => [
                'type' => 'info', 'subType' => 'binary', 'name' => 'Connection',
                'order' => 1, 'visible' => 1, 'historized' => 0,
                'display' => ['forceReturnLineBefore' => 0],
                'template' => ['dashboard' => 'default']
            ],
            'state' => [
                'type' => 'info', 'subType' => 'binary', 'name' => 'State',
                'order' => 5, 'visible' => 0, 'historized' => 0,
                'display' => ['forceReturnLineBefore' => 0],
                'generic_type' => 'ENERGY_STATE',
                'configuration' => ['repeatEventManagement' => 'never'],
                'template' => ['dashboard' => 'default']
            ],
            'ecoMode' => [
                'type' => 'info', 'subType' => 'binary', 'name' => 'Eco mode',
                'order' => 6, 'visible' => 0, 'historized' => 0,
                'display' => ['forceReturnLineBefore' => 0],
                'configuration' => ['repeatEventManagement' => 'never'],
                'template' => ['dashboard' => 'default']
            ],
            'targetTemperature' => [
                'type' => 'info', 'subType' => 'string', 'name' => 'Target temperature',
                'order' => 10, 'visible' => 0, 'historized' => 0,
                'display' => ['forceReturnLineBefore' => 0],
                'template' => ['dashboard' => 'default'], 'unite' => '°C'
            ],
            'currentTemperature' => [
                'type' => 'info', 'subType' => 'numeric', 'name' => 'Current temperature',
                'order' => 15, 'visible' => 1, 'historized' => 1,
                'display' => ['forceReturnLineBefore' => 0],
                'template' => ['dashboard' => 'line'], 'unite' => '°C'
            ],
        ];

        // Fan speed and mode only on splits (for the moment)
        if($deviceInfos['type'] === 'HitachiAirToAirHeatPump') {
            $infosCommands['mode'] = [
                'type' => 'info', 'subType' => 'string', 'name' => 'Mode',
                'order' => 25, 'visible' => 0, 'historized' => 0,
                'display' => ['forceReturnLineBefore' => 0],
                'template' => ['dashboard' => 'default']
            ];
            $infosCommands['fanSpeed'] = [
                'type' => 'info', 'subType' => 'string', 'name' => 'Fan speed',
                'order' => 20, 'visible' => 0, 'historized' => 0,
                'display' => ['forceReturnLineBefore' => 0],
                'template' => ['dashboard' => 'default']
            ];
        }

        foreach($infosCommands as $commandKey => $infosCommand) {
            if(isset($deviceInfos[$commandKey])) {
                if($createCmd) {
                    self::_saveEqLogic($commandKey, $infosCommand); // create cmd INFO
                }
                if($commandKey === 'status') {
                    $deviceInfos[$commandKey] = in_array(strtolower($deviceInfos[$commandKey]), ['available', 'run']) ? 1 : 0;
                }
                if($commandKey === 'state') {
                    $deviceInfos[$commandKey] = in_array(strtolower($deviceInfos[$commandKey]), ['on', 'run']) ? 1 : 0;
                }
                if($commandKey === 'ecoMode') {
                    $deviceInfos[$commandKey] = $deviceInfos[$commandKey] ? 1 : 0;
                }

                $this->checkAndUpdateCmd($commandKey, $deviceInfos[$commandKey]); // update with current value
            }
        }

        // Create/update ACTION commands
        if($createCmd) {
            $cmdActions = [
                'on' => [
                    'type' => 'action', 'subType' => 'other', 'name' => 'On',
                    'order' => 2, 'visible' => 1, 'historized' => 0,
                    'display' => ['forceReturnLineBefore' => 0, 'forceReturnLineAfter' => 0],
                    'value' => $this->getCmd(null, 'state')->getId(),
                    'generic_type' => 'ENERGY_ON',
                    'template' => ['dashboard' => 'hitachihikumo::boutonOnOff', 'mobile' => 'hitachihikumo::boutonOnOff']
                ],
                'off' => [
                    'type' => 'action', 'subType' => 'other', 'name' => 'Off',
                    'order' => 3, 'visible' => 1, 'historized' => 0,
                    'display' => [ 'forceReturnLineBefore' => 0, 'forceReturnLineAfter' => 0],
                    'value' => $this->getCmd(null, 'state')->getId(),
                    'generic_type' => 'ENERGY_OFF',
                    'template' => ['dashboard' => 'hitachihikumo::boutonOnOff', 'mobile' => 'hitachihikumo::boutonOnOff']
                ],
                'ecoOn' => [
                    'type' => 'action', 'subType' => 'other', 'name' => 'Eco on',
                    'order' => 4, 'visible' => 1, 'historized' => 0,
                    'display' => ['forceReturnLineBefore' => 0, 'forceReturnLineAfter' => 0],
                    'value' => $this->getCmd(null, 'ecoMode')->getId(),
                    'template' => ['dashboard' => 'hitachihikumo::ecoOnOff', 'mobile' => 'hitachihikumo::ecoOnOff']
                ],
                'ecoOff' => [
                    'type' => 'action', 'subType' => 'other', 'name' => 'Eco off',
                    'order' => 4, 'visible' => 1, 'historized' => 0,
                    'display' => [ 'forceReturnLineBefore' => 0, 'forceReturnLineAfter' => 0],
                    'value' => $this->getCmd(null, 'ecoMode')->getId(),
                    'template' => ['dashboard' => 'hitachihikumo::ecoOnOff', 'mobile' => 'hitachihikumo::ecoOnOff']
                ],
                'setTemperature' => [
                    'type' => 'action', 'subType' => 'slider', 'name' => 'Target temperature',
                    'order' => 30, 'visible' => 1, 'historized' => 0,
                    'display' => ['forceReturnLineBefore' => 1, 'forceReturnLineAfter' => 1],
                    'value' => $this->getCmd(null, 'targetTemperature')->getId(),
                    'unite' => '°C',
                    'configuration' => ['minValue' => 16, 'maxValue' => 30],
                    'template' => ['dashboard' => 'hitachihikumo::setTemperature', 'mobile' => 'hitachihikumo::setTemperature']
                ],
            ];

            // Fan speed only on splits (for the moment)
            if($deviceInfos['type'] === 'HitachiAirToAirHeatPump') {
                $cmdActions['setMode'] = [
                    'type' => 'action', 'subType' => 'select', 'name' => 'Mode',
                    'order' => 35, 'visible' => 1, 'historized' => 0,
                    'display' => ['forceReturnLineBefore' => 0],
                    'configuration' => ['listValue' => 'auto|Automatic;cooling|Cooling;heating|Heating;fan|Fan;dehumidify|Dehumidify;circulator|Circulator'],
                    'value' => $this->getCmd(null, 'mode')->getId(),
                    'template' => ['dashboard' => 'default']
                ];
                $cmdActions['setFanSpeed'] = [
                    'type' => 'action', 'subType' => 'select', 'name' => 'Fan speed',
                    'order' => 40, 'visible' => 1, 'historized' => 0,
                    'display' => ['forceReturnLineBefore' => 1, 'forceReturnLineAfter' => 1],
                    'configuration' => ['listValue' => 'auto|Automatic;silent|Silent;lo|Low;med|Medium;hi|High'],
                    'value' => $this->getCmd(null, 'fanSpeed')->getId(),
                    'template' => ['dashboard' => 'default']
                ];
            }

            foreach($cmdActions as $keyAction => $action) {
                self::_saveEqLogic($keyAction, $action);
            }
        }

        // create refresh action
        $refresh = $this->getCmd(null, 'refresh');
        if (!is_object($refresh)) {
            $refresh = new hitachihikumoCmd();
            $refresh->setName(__('Refresh', __FILE__));
        }
        $refresh->setEqLogic_id($this->getId());
        $refresh->setOrder(999);
        $refresh->setLogicalId('refresh');
        $refresh->setType('action');
        $refresh->setSubType('other');
        $refresh->save();
    }

    private function _saveEqLogic($key, $cmdProperties) {
        $newCmd = $this->getCmd(null, $key);

        if (!is_object($newCmd)) {
            $newCmd = new hitachihikumoCmd();
            $newCmd->setName(__($cmdProperties['name'], __FILE__));
        }
        $newCmd->setLogicalId($key);
        $newCmd->setEqLogic_id($this->getId());
        $newCmd->setType($cmdProperties['type']);
        $newCmd->setSubType($cmdProperties['subType']);
        $newCmd->setOrder($cmdProperties['order']);
        $newCmd->setIsVisible($cmdProperties['visible']);
        $newCmd->setIsHistorized($cmdProperties['historized']);

        if(array_key_exists('template', $cmdProperties)) {
            foreach($cmdProperties['template'] as $templateKey => $templateVal) {
                $newCmd->setTemplate($templateKey, $templateVal);
            }
        }
        if(array_key_exists('display', $cmdProperties)) {
            foreach($cmdProperties['display'] as $displayKey => $displayVal) {
                $newCmd->setDisplay($displayKey, $displayVal);
            }
        }
        if(array_key_exists('configuration', $cmdProperties)) {
            foreach($cmdProperties['configuration'] as $configKey => $configVal) {
                $newCmd->setConfiguration($configKey, $configVal);
            }
        }

        if(isset($cmdProperties['unite']))
        $newCmd->setUnite($cmdProperties['unite']);
        if(isset($cmdProperties['generic_type']))
        $newCmd->setGeneric_type($cmdProperties['generic_type']);
        if(isset($cmdProperties['value']))
        $newCmd->setValue($cmdProperties['value']);

        $newCmd->save();
    }

    public function sendCmd($cmd, $value = '')
    {
        $this->__construct(); // WTF, reflection ?

        switch ($cmd) {
            case 'on':
                if($this->getConfiguration('type') === 'HitachiDHW') {
                    $this->hiKumo->switchOnDHW($this->getLogicalId());
                } elseif($this->getConfiguration('type') === 'HitachiAirToWaterHeatingZone') {
                    $this->hiKumo->switchOnHeatZone($this->getLogicalId());
                } else { // Splits
                    $this->hiKumo->switchOn($this->getLogicalId());
                }
                break;
            case 'off':
                if($this->getConfiguration('type') === 'HitachiDHW') {
                    $this->hiKumo->switchOffDHW($this->getLogicalId());
                } elseif($this->getConfiguration('type') === 'HitachiAirToWaterHeatingZone') {
                    $this->hiKumo->switchOffHeatZone($this->getLogicalId());
                } else { // Splits
                    $this->hiKumo->switchOff($this->getLogicalId());
                }
                break;
            case 'ecoOn':
                $this->hiKumo->setEco(true);
                break;
            case 'ecoOff':
                $this->hiKumo->setEco(false);
                break;
            case 'setTemperature':
                $this->hiKumo->setTemperature($this->getLogicalId(), $value);
                break;
            case 'setFanSpeed':
                $this->hiKumo->setFanSpeed($this->getLogicalId(), $value);
                break;
            case 'setMode':
                $this->hiKumo->setMode($this->getLogicalId(), $value);
                break;
            default:
                log::add('hitachihikumo', 'warn', 'Command not recognized ('.$cmd.') for element: '.$this->getName());
                throw new Error('Command not recognized ('.$cmd.') for element: '.$this->getName());
        }
    }
}

