<?php

declare(strict_types=1);
	class HomeassistantConfiguator extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterAttributeString("elements", "");

			//Connect to available homeassistant splitter
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
	    public function ReceiveData($JSONString)
#=====================================================================================
		{ // msg from splitter
		    $data = json_decode($JSONString);
			$this->SendDebug(__FUNCTION__, $data->Buffer, 0);
		    $this->WriteAttributeString("elements", $data->Buffer);
			//$this->ReloadEntityList($data->Buffer);
			return true;
		}

#=====================================================================================
		public function GetConfigurationForm() 
#=====================================================================================
		{
			$form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
			// Add entity selection list
			$form['actions'][0]['values'] = array();
			$response = $this->SendDataToParent(json_encode([
				'DataID' => "{163D93EA-4256-36DE-F3EC-46D7507CFC9B}",
				'Buffer' => utf8_encode("get_states"),
			]));
			$this->SendDebug(__FUNCTION__, $response, 0);
			$this->ReloadEntityList($response);
			return json_encode($form);
		}

#=====================================================================================
		public function ReloadEntityList(string $data) {
#=====================================================================================

			// Get Home Assistant entities
			//$this->SendDebug(__FUNCTION__, $data, 0);
			$entities = json_decode($data,true);
			// Create entity selection list
			$list = array();
			$guid = "{761BE863-4B98-0846-0CF6-EC37CBBD61CF}";
			$Instances = IPS_GetInstanceListByModuleID($guid);
			foreach ($entities as $entity) {
				$ID	= 0;
				foreach ($Instances as $Instance){
					//$this->SendDebug("Created Instances", IPS_GetObject($Instance)['ObjectName'] , 0);
					if (IPS_GetProperty($Instance,'entity_id') == $entity['entity_id'])
					{
						$ID = $Instance;
					}
				}
				(isset($entity['attributes']['device_class']) ? $device_class = $entity['attributes']['device_class'] : $device_class = 'none');
				$list[] = array(
					'instanceID' => $ID,
					'entity_id' => $entity['entity_id'],
					'name' => $entity['attributes']['friendly_name'],
					'state' => $entity['state'],
					"device_class" => $device_class,
					'create' =>
					[
						"moduleID" => "{761BE863-4B98-0846-0CF6-EC37CBBD61CF}",
						"configuration" => [
							"entity_id" => $entity['entity_id'],
							"name" => $entity['attributes']['friendly_name'],
							'websocketID' => 0
						]
					]
				);
			}
			$this->UpdateFormField('Configuration','values',json_encode($list));
		}

		public function ConfiguratorRequestAllEntities() 
		{
			$response = $this->SendDataToParent(json_encode([
				'DataID' => "{163D93EA-4256-36DE-F3EC-46D7507CFC9B}",
				'Buffer' => utf8_encode("states"),
			]));
		}
	}