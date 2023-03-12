<?php

declare(strict_types=1);
class HomeassistantSplitter extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->ConnectParent("{D68FD31F-0E90-7019-F16C-1949BD3079EF}");

        // Register properties for configuration
        $this->RegisterPropertyString('host', '192.168.1.1');
        $this->RegisterPropertyInteger('port', 8123);
        //ssl not yet supported
        $this->RegisterPropertyBoolean('ssl', false);
        $this->RegisterPropertyString('access_token', '');
        //$this->RegisterPropertyString('event_filter', '');
        $this->RegisterAttributeInteger("id",0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Update WebSocket client configuration
        $this->GetConfigurationForParent();
    }
#=====================================================================================
    public function GetConfigurationForParent()
#=====================================================================================
        {
            $Config['URL'] = ($this->ReadPropertyBoolean('ssl') ? 'wss://' : 'ws://') . $this->ReadPropertyString('host') . ':' . $this->ReadPropertyInteger('port') . '/api/websocket';
            $Config['VerifyCertificate'] = $this->ReadPropertyBoolean('ssl');
            return json_encode($Config);
        }

#=====================================================================================
    public function ForwardData($JSONString)
#=====================================================================================
    { // msg from child
        $data = json_decode($JSONString);
        
        $this->SendDebug(__FUNCTION__, $data->Buffer, 0);
        switch ($data->Buffer) {
            case 'get_states':
                /*
                $id = $this->ReadAttributeInteger('id');
                $send['Buffer'] = '{"id": '.$id.',"type": "get_states"}';
                $send['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
                // send to ws port
                $response = $this->SendDataToParent(json_encode($send));
                $this->WriteAttributeInteger('id',$id + 1);
                */
                $response = $this->GetEntities();
                $this->SendDebug(__FUNCTION__, $response, 0);
            break;
        }
        return $response;
    }

#=====================================================================================
    public function ReceiveData($JSONString)
#=====================================================================================
    { // msg from ws port
        $data = json_decode($JSONString);
        $message = utf8_decode($data->Buffer);
        $this->SendDebug(__FUNCTION__, $message, 0);
        $json = json_decode($message);
        
        if (isset($json->type))
        {
            switch ($json->type) {
                case 'auth_required':
                    // auth required
                    $authMessage = json_encode(array(
                        'type' => 'auth',
                        'access_token' => $this->ReadPropertyString('access_token')
                    ));
                    $this->SendDebug(__FUNCTION__ . ' ' . $json->type, $authMessage, 0);
                    $send['Buffer'] = $authMessage;
                    $send['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
                    $this->SendDataToParent(json_encode($send));
                    break;

                case 'result':
                    // response to a command
                    $this->SendDebug(__FUNCTION__ . ' ' . $json->type, ($json->success ? 'Success' : 'Failure'), 0);
                    $send['Buffer'] = $message;
                    $send['DataID'] = '{48562F97-BBA9-3E2F-2E24-3333C9405454}';
                    $this->SendDataToChildren(json_encode($send));
                    break;

                case 'event':
                    //async events
                    $this->SendDebug(__FUNCTION__ . ' ' . $json->type, $message, 0);
                    //$this->SendDataToChildren($message);
                    break;
                default:
            }
        }
    }

	public function MessageSink($timestamp, $senderID, $message, $data)
	{
		switch ($message) {
			case IPS_KERNELMESSAGE:
				if ($data[0] === KR_READY) {
					$websocket_url = $this->ReadPropertyString('websocket_url');
					$this->SendDebug("WebSocketURL", $websocket_url, 0);
					$this->RegisterWebSocket($websocket_url);
				}
				break;
			case IM_CHANGESTATUS:
				if ($senderID === $this->ParentID) {
					$this->SendDebug("IM_CHANGESTATUS", "Status: " . $data[0], 0);
					if ($data[0] === 102) { // IP-Symcon is trying to reconnect
						$this->SetSummary("Reconnecting...");
					} elseif ($data[0] === 104) { // IP-Symcon has reconnected
						$this->SetSummary("Connected");
						$this->RegisterWebSocket($this->ReadPropertyString('websocket_url'));
					} elseif ($data[0] === 200) { // IP-Symcon has successfully connected
						$this->SetSummary("Connected");
						$this->RegisterWebSocket($this->ReadPropertyString('websocket_url'));
					}
				}
				break;
			case WM_DISCONNECT:
				if ($senderID === $this->ParentID) {
					$this->SetSummary("Disconnected");
				}
				break;
			default:
				$this->SendDebug("MessageSink", "Unhandled message: " . $message, 0);
				break;
		}
	}
#=====================================================================================
    private function GetEntities()
#=====================================================================================
    {
        $api_url = $this->ReadPropertyString('host') . ':' . $this->ReadPropertyInteger('port') . '/api';
        $access_token = $this->ReadPropertyString('access_token');

        $headers = array(
            "Authorization: Bearer $access_token",
            "Content-Type: application/json",
        );

        $entities_url = $api_url . "/states";

        $curl = curl_init($entities_url);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        if (!$response) {
            die("Error: " . curl_error($curl));
        }

        $entities = json_decode($response, true);

        curl_close($curl);

        return ($response);
    }

}