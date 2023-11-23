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

/* * ***************************Includes********************************* */
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
            $eqlogic->save();
        }
    }

	public static function templateWidget() {
		$return = array('action' => array('other' => array()));
		$return['action']['other']['boutonOnOff'] = array(
			'template' => 'tmplicon',
			'display' => array(
				'#icon#' => '<i class=\'icon jeedom-prise\'></i>',
			),
			'replace' => array(
				'#_icon_on_#' => '<i class=\'icon jeedom-prise\'></i>',
				'#_icon_off_#' => '<i class=\'icon fas fa-times\'></i>',
				'#_time_widget_#' => '0'
				)
			);

		$return['action']['other']['pump'] = array(
			'template' => 'tmplicon',
			'display' => array(
				'#icon#' => '<i class=\'icon fas fa-undo\'></i>',
			),
			'replace' => array(
				'#_icon_on_#' => '<i class=\'icon fas fa-undo\'></i>',
				'#_icon_off_#' => '<i class=\'icon fas fa-times\'></i>',
				'#_time_widget_#' => '0'
				)
			);

		return $return;
    }

	public static function cron5() {
		$eqLogics = self::byType('hitachihikumo');
	}

	// Function launched before the equipment creation
	public function preInsert() {
	}

	// Function launched after the equipment creation
	public function postInsert() {
	}

	// Function launched before the equipment update
	public function preUpdate() {
	}

	// Function launched after the equipment update
	public function postUpdate() {
	}

	// Function launched before the equipment save (create or update)
	public function preSave() {
	}

	// Function launched after the equipment save (create or update)
	public function postSave() {
      	if($this->getIsEnable() == 1)
			self::createAndUpdateCmd();
	}

	// Executed before equipment deletion
	public function preRemove() {
	}

	// Executed after equipment deletion
	public function postRemove() {
	}

    public function createAndUpdateCmd($bCreateCmd = true) {
        $this->__construct(); // WFT, Reflection ?
        $deviceInfos = $this->hiKumo->getDeviceById($this->getLogicalId());

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
            'targetTemperature' => [
                'type' => 'info', 'subType' => 'string', 'name' => 'Target temperature',
                'order' => 10, 'visible' => 0, 'historized' => 0,
                'display' => ['forceReturnLineBefore' => 0],
                'template' => ['dashboard' => 'default'], 'unite' => '°C'
            ],
            'roomTemperature' => [
                'type' => 'info', 'subType' => 'numeric', 'name' => 'Room temperature',
                'order' => 15, 'visible' => 1, 'historized' => 1,
                'display' => ['forceReturnLineBefore' => 0],
                'template' => ['dashboard' => 'line'], 'unite' => '°C'
            ],
            'fanSpeed' => [
                'type' => 'info', 'subType' => 'string', 'name' => 'Fan speed',
                'order' => 20, 'visible' => 0, 'historized' => 0,
                'display' => ['forceReturnLineBefore' => 0],
                'template' => ['dashboard' => 'default']
            ],
            'mode' => [
                'type' => 'info', 'subType' => 'string', 'name' => 'Mode',
                'order' => 25, 'visible' => 0, 'historized' => 0,
                'display' => ['forceReturnLineBefore' => 0],
                'template' => ['dashboard' => 'default']
            ],
        ];

        foreach($infosCommands as $commandKey => $infosCommand) {
            if(isset($deviceInfos[$commandKey])) {
                if($bCreateCmd) {
                    self::_saveEqLogic($commandKey, $infosCommand); // create cmd INFO
                }

                if($commandKey === 'status') {
                    $deviceInfos[$commandKey] = $deviceInfos[$commandKey] === 'available' ? 1 : 0;
                }
                if($commandKey === 'state') {
                    $deviceInfos[$commandKey] = strtolower($deviceInfos[$commandKey]) === 'on' ? 1 : 0;
                }
                $this->checkAndUpdateCmd($commandKey, $deviceInfos[$commandKey]); // update with current value
            }
        }

        // Create/update ACTION commands
        if($bCreateCmd) {
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
                    'display' => [ 'forceReturnLineBefore' => 0, 'forceReturnLineAfter' => 1],
                    'value' => $this->getCmd(null, 'state')->getId(),
                    'generic_type' => 'ENERGY_OFF',
                    'template' => ['dashboard' => 'hitachihikumo::boutonOnOff', 'mobile' => 'hitachihikumo::boutonOnOff']
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
                'setMode' => [
                    'type' => 'action', 'subType' => 'select', 'name' => 'Mode',
                    'order' => 35, 'visible' => 1, 'historized' => 0,
                    'display' => ['forceReturnLineBefore' => 0],
                    'configuration' => ['listValue' => 'auto|Automatic;cooling|Cooling;heating|Heating;fan|Fan;dehumidify|Dehumidify;circulator|Circulator'],
                    'value' => $this->getCmd(null, 'mode')->getId(),
                    'template' => ['dashboard' => 'default']
                ],
                'setFanSpeed' => [
                    'type' => 'action', 'subType' => 'select', 'name' => 'Fan speed',
                    'order' => 40, 'visible' => 1, 'historized' => 0,
                    'display' => ['forceReturnLineBefore' => 1, 'forceReturnLineAfter' => 1],
                    'configuration' => ['listValue' => 'auto|Automatic;silent|Silent;lo|Low;med|Medium;hi|High'],
                    'value' => $this->getCmd(null, 'fanSpeed')->getId(),
                    'template' => ['dashboard' => 'default']
                    ],
            ];

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
                $this->hiKumo->switchOn($this->getLogicalId());
                break;
            case 'off':
                $this->hiKumo->switchOff($this->getLogicalId());
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

