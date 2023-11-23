<?php

class hitachihikumoCmd extends cmd {
    public function execute($_options = [])
    {
        $eqLogic = $this->getEqLogic();
        log::add("hitachihikumo", "debug", "LogicalId action => " . $this->getLogicalId());
        Log::add('hitachihikumo', 'debug', '$_options[] done: ' . json_encode($_options));

        if($eqLogic->getIsEnable() == 0)
            return;

        switch ($this->getLogicalId()) {
            case 'refresh':
                $eqLogic->createAndUpdateCmd(false);
                break;
            case 'on':
                $eqLogic->sendCmd('on');
                $eqLogic->checkAndUpdateCmd('state', 1);
                break;
            case 'off':
                $eqLogic->sendCmd('off');
                $eqLogic->checkAndUpdateCmd('state', 0);
                break;
            case 'setTemperature':
                $temperature = $_options['text'] ?? $_options['slider']; // scenario compatibility
                if($temperature < 16 || $temperature > 30)
                    return;
                $eqLogic->sendCmd('setTemperature', $temperature);
                break;
            case 'setMode':
                $mode = $_options['select'] ?? $_options['slider']; // scenario compatibility
                $eqLogic->checkAndUpdateCmd('mode', $mode);
                $eqLogic->sendCmd('setMode', $mode);
                break;

            case 'setFanSpeed':
                $fanSpeed = $_options['select'] ?? $_options['slider']; // scenario compatibility
                $eqLogic->checkAndUpdateCmd('fan', $fanSpeed);
                $eqLogic->sendCmd('setFanSpeed', $fanSpeed);
                break;
            default:
                throw new Error('Invalid command to execute: '.print_r($_options, true));
                log::add(
                    'hitachihikumo',
                    'warn',
                    'Error while executing cmd '.$this->getLogicalId().'('.print_r($_options, true).')'
                );
                break;
        }
    }

    public function getWidgetTemplateCode($_version = 'dashboard', $_clean = true, $_widgetName = '') {
        $data = null;
        if ($_version != 'scenario')
            return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);

        if ($this->getLogicalId() == 'setFanSpeed')
            $data = getTemplate('core', 'scenario', 'cmd.setFanSpeed', 'hitachihikumo');

        if (!is_null($data)) {
            if (version_compare(jeedom::version(),'4.2.0','>=')) {
                if(!is_array($data)) return array('template' => $data, 'isCoreWidget' => false);
            } else return $data;
        }

        return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
    }
}
