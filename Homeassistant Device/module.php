<?php

declare(strict_types=1);
class HomeassistantDevice extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('entity_id', "");
        $this->RegisterPropertyString('name', "");
        $this->RegisterPropertyInteger('websocketID', 0);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{A9E0ED8B-75CE-F2EA-0533-330978F392A9}');
        $this->SetReceiveDataFilter('.*' . $this->ReadPropertyString('entity_id') . '.*');
    }

    public function Send()  //string $data ='states/entity_id'
    {
        //if ($data =='states/entity_id') {
            $data ='states/' . $this->ReadPropertyString('entity_id');
        //}
        $return = $this->SendDataToParent(json_encode([
            'DataID' => "{163D93EA-4256-36DE-F3EC-46D7507CFC9A}",
            'Buffer' => utf8_encode($data),
            'Command' => 'get',
        ]));
        $this->SendDebug(__FUNCTION__, $return, 0);
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);

        $data = json_decode(utf8_decode($data->Buffer));
        $this->SendDebug(__FUNCTION__, json_encode($data->new_state), 0);
        if (isset($data->new_state->attributes->unit_of_measurement)) {
            switch ($data->new_state->attributes->unit_of_measurement) {
                case 'kWh':
                    $this->RegisterVariableFloat('consumption', $this->Translate('Consumption'), '~Electricity', 10);
                    $this->SetValue('consumption', $data->new_state->state);
                    break;

                case 'W':
                    $this->RegisterVariableFloat('power', $this->Translate('Power'), '~Watt', 10);
                    $this->SetValue('power', $data->new_state->state);
                    break;
            }
        }
        foreach ($data->new_state->attributes as $attribute => $value) {
            switch ($attribute) {
                case 'voltage':
                    $this->RegisterVariableFloat($attribute, $this->Translate('Voltage'), '~Volt', 20);
                    $this->SetValue($attribute, $value);
                    break;

                case 'power':
                    $this->RegisterVariableFloat($attribute, $this->Translate('Power'), '~Watt', 20);
                    $this->SetValue($attribute, $value);
                    break;

                case 'current':
                    $this->RegisterVariableFloat($attribute, $this->Translate('Current'), '~Ampere', 20);
                    $this->SetValue($attribute, $value/1000);
                    break;

                case 'device_class':
                    $this->RegisterVariableString('deviceclass', $this->Translate('Device Class'), '', 90);
                    $this->SetValue('deviceclass', $value);
                    break;
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'ligth':
                $return = $this->SendDataToParent(json_encode([
                    'DataID' => "{163D93EA-4256-36DE-F3EC-46D7507CFC9A}",
                    'Buffer' => utf8_encode($data),
                    'Command' => 'post',
                    'Entity' => $this->ReadPropertyString('entity_id'),
                    'Data' => '',
                ]));
                $this-SendPost($this->ReadPropertyString('entity_id'));
                break;
        }
    }
}
