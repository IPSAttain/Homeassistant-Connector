<?php

declare(strict_types=1);
	class HomeassistantDevice extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->ConnectParent('{A9E0ED8B-75CE-F2EA-0533-330978F392A9}');
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
		}

		public function Send()
		{
			$this->SendDataToParent(json_encode(['DataID' => '{163D93EA-4256-36DE-F3EC-46D7507CFC9A}']));
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage('Device RECV', utf8_decode($data->Buffer));
		}
	}