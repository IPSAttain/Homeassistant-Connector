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
        $this->RegisterAttributeInteger("id", 1);
        $this->RegisterAttributeInteger("state_change_id", 0);
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
        switch ($data->Command) {
            case 'get':
                $response = $this->ApiGet($data->Buffer);
                break;

            case 'post':
                $response = $this->ApiPost($data->Buffer);
                break;
        }
        
        $this->SendDebug(__FUNCTION__, $response, 0);
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

        if (isset($json->type)) {
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

                case 'auth_ok':
                    // auth OK
                    $this->WriteAttributeInteger("id", 1);
                    $this->SubscribeEvents();
                    break;

                case 'result':
                    // response to a command
                    $this->SendDebug(__FUNCTION__ . ' ' . $json->type, ($json->success ? 'Success' : 'Failure'), 0);
                    if ($this->ReadAttributeInteger('state_change_id') == $json->id)
                    {
                        $this->SendDebug(__FUNCTION__,'State Change Registered',0);
                    }
                    break;

                case 'event':
                    //async events
                    $event_id = $this->ReadAttributeInteger('state_change_id');
                    switch ($event_id) {
                        case $json->id:
                            //procceed
                            $send['Buffer'] = json_encode($json->event->data);
                            $send['DataID'] = '{48562F97-BBA9-3E2F-2E24-3333C9405453}';
                            //$this->SendDebug(__FUNCTION__ . ' ' . 'Send to child', json_encode($json->event->data), 0);
                            $this->SendDataToChildren(json_encode($send));

                            break;
                        case 0:
                            //could be after module create
                            $this->WriteAttributeInteger("state_change_id", $json->id);
                            break;
                        default:
                            //more than one subsciption to Events
                            $this->SendDebug(__FUNCTION__,'State Change Deleted',0);
                            $this->UnSubscribeEvents();
                    }
                    break;
                default:
            }
        }
    }


#=====================================================================================
    public function RequestEvents()
#=====================================================================================
    {
        $response = $this->RequestApi('events');
        $this->SendDebug(__FUNCTION__, $response, 0);
    }

#=====================================================================================
    public function SubscribeEvents()
#=====================================================================================
    {
        $data = array();
        $data['id'] = $this->ReadAttributeInteger("id");
        $data['type'] = 'subscribe_events';
        $data['event_type'] = 'state_changed';
        $this->RequestWebSocket(json_encode($data));
        $this->SendDebug(__FUNCTION__, json_encode($data), 0);
        $this->WriteAttributeInteger("state_change_id", $data['id']);
        $this->WriteAttributeInteger("id", $data['id'] + 1);
    }

#=====================================================================================
    public function UnSubscribeEvents()
#=====================================================================================
    {
        $data = array();
        $data['id'] = $this->ReadAttributeInteger("id");
        $data['type'] = 'unsubscribe_events';
        $data['subscription'] = $this->ReadAttributeInteger("state_change_id");
        $this->RequestWebSocket(json_encode($data));
        $this->SendDebug(__FUNCTION__, json_encode($data), 0);
        $this->WriteAttributeInteger("state_change_id", 0);
        $this->WriteAttributeInteger("id", $data['id'] + 1);
    }

#=====================================================================================
    private function RequestWebSocket($message)
#=====================================================================================
    {
        $send['Buffer'] = $message;
        $send['DataID'] = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
        $this->SendDataToParent(json_encode($send));
    }

#=====================================================================================
    private function ApiGet(string $endpoint)
#=====================================================================================
    {
        $entities_url = $this->ReadPropertyString('host') . ':' . $this->ReadPropertyInteger('port') . '/api/' . $endpoint;
        $access_token = $this->ReadPropertyString('access_token');

        $headers = array(
            "Authorization: Bearer $access_token",
            "Content-Type: application/json",
        );

        $curl = curl_init($entities_url);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,5);
        curl_setopt($curl,CURLOPT_TIMEOUT,5);


        $response = curl_exec($curl);

        if (!$response) {
            die("Error: " . curl_error($curl));
        }

        $entities = json_decode($response, true);

        curl_close($curl);

        return ($response);
    }

#=====================================================================================
    private function ApiPost(string $endpoint)
#=====================================================================================
    {
        // turn a light off by make a service call to homeassistant with curl






        $entities_url = $this->ReadPropertyString('host') . ':' . $this->ReadPropertyInteger('port') . '/api/services/light/' . $endpoint;
        $access_token = $this->ReadPropertyString('access_token');

        $headers = array(
            "Authorization: Bearer $access_token",
            "Content-Type: application/json",
        );
        //$url = 'http://your-homeassistant-url:8123/api/services/light/turn_on';

        $data = array(
            'entity_id' => 'light.living_room',
            'brightness' => 255
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        curl_close($ch);
        
        return ($response);
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
}
