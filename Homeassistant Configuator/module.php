<?php

declare(strict_types=1);
class HomeassistantConfiguator extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        //Connect to homeassistant splitter
        $this->ConnectParent("{A9E0ED8B-75CE-F2EA-0533-330978F392A9}");
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
    }

#=====================================================================================
    public function GetConfigurationForm()
    #=====================================================================================
    {
        $values = json_decode($this->GetFormData());
        $this->SendDebug(__FUNCTION__, json_encode($values), 0);
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Add entity selection list
        $form['actions'][0]['values'] = $values;
        return json_encode($form);
    }

#=====================================================================================
    private function GetFormData()
    #=====================================================================================
    {
        // Get Home Assistant entities
        $response = $this->SendDataToParent(json_encode([
            'DataID' => "{163D93EA-4256-36DE-F3EC-46D7507CFC9B}",
            'Buffer' => utf8_encode("states"),
            'Command' => 'get',
        ]));
        $this->SendDebug(__FUNCTION__, $response, 0);
        $entities = json_decode($response, true);
        // Create entity selection list
        $list = array();
        $guid = "{761BE863-4B98-0846-0CF6-EC37CBBD61CF}";
        $Instances = IPS_GetInstanceListByModuleID($guid);
        foreach ($entities as $entity) {
            $ID	= 0;
            foreach ($Instances as $Instance) {
                //$this->SendDebug("Created Instances", IPS_GetObject($Instance)['ObjectName'] , 0);
                if (IPS_GetProperty($Instance, 'entity_id') == $entity['entity_id']) {
                    $ID = $Instance;
                }
            }
            isset($entity['attributes']['device_class']) ? $device_class = $entity['attributes']['device_class'] : $device_class = 'none';
            isset($entity['attributes']['friendly_name']) ? $friendly_name = $entity['attributes']['friendly_name'] : $friendly_name = 'none';
            $list[] = array(
                'instanceID' => $ID,
                'entity_id' => $entity['entity_id'],
                'name' => $friendly_name,
                'state' => $entity['state'],
                'device_class' => $device_class,
                'last_changed' => $entity['last_changed'],
                'create' =>
                [
                    "moduleID" => "{761BE863-4B98-0846-0CF6-EC37CBBD61CF}",
                    "configuration" => [
                        "entity_id" => $entity['entity_id'],
                        "name" => $friendly_name,
                        'websocketID' => 0
                    ]
                ]
            );
        }
        return json_encode($list);
    }

#=====================================================================================
    public function ReceiveData($JSONString)
#=====================================================================================
    { // msg from splitter
        $data = json_decode($JSONString);
        $this->SendDebug(__FUNCTION__, $data->Buffer, 0);
        return true;
    }
}
