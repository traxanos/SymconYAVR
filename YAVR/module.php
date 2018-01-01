<?php
class YAVR extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyString('Zone', 'Main_Zone');
        $this->RegisterPropertyInteger('UpdateInterval', 5);
        $this->RegisterPropertyString('InputsMapping', '');
        $this->RegisterPropertyString('ScenesMapping', '');

        if (!IPS_VariableProfileExists('Volume.YAVR')) {
            IPS_CreateVariableProfile('Volume.YAVR', 2);
        }
        IPS_SetVariableProfileDigits('Volume.YAVR', 1);
        IPS_SetVariableProfileIcon('Volume.YAVR', 'Intensity');
        IPS_SetVariableProfileText('Volume.YAVR', '', ' dB');
        IPS_SetVariableProfileValues('Volume.YAVR', -80, 16, 0.5);

        $this->RegisterTimer('Update', 0, 'YAVR_RequestData($_IPS[\'TARGET\'], 0);');

        if ($oldInterval = @$this->GetIDForIdent('INTERVAL')) {
            IPS_DeleteEvent($oldInterval);
        }
    }

    public function Destroy()
    {
        parent::Destroy();
        if (!IPS_VariableProfileExists("YAVR.Scenes{$this->InstanceID}")) {
            IPS_DeleteVariableProfile("YAVR.Scenes{$this->InstanceID}");
        }
        if (!IPS_VariableProfileExists("YAVR.Input{$this->InstanceID}")) {
            IPS_DeleteVariableProfile("YAVR.Inputs{$this->InstanceID}");
        }
    }

    public function GetInputId(string $value)
    {
        $inputs = json_decode($this->ReadPropertyString('InputsMapping'));
        $inputs2 = array();
        foreach ($inputs as $id => $data) {
            $inputs2[$data->title] = $id;
        }
        if (array_key_exists($value, $inputs2)) {
            return $inputs2[$value];
        } else {
            throw new Exception("Invalid input $key");
        }
    }

    public function GetInputKey(int $value)
    {
        $inputs = json_decode($this->ReadPropertyString('InputsMapping'));
        $inputs2 = array();
        foreach ($inputs as $id => $data) {
            $inputs2[$id] = $data->id;
        }
        if (array_key_exists($id, $inputs2)) {
            return $inputs2[$value];
        } else {
            throw new Exception("Invalid input id $id");
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if ($this->ReadPropertyString('ScenesMapping') == '') {
            $this->UpdateScenes();
        }
        if ($this->ReadPropertyString('InputsMapping') == '') {
            $this->UpdateInputs();
        }

        $stateId = $this->RegisterVariableBoolean("STATE", "Zustand", "~Switch", 1);
        $this->EnableAction("STATE");
        $muteId = $this->RegisterVariableBoolean("MUTE", "Mute", "~Switch", 3);
        IPS_SetIcon($muteId, 'Speaker');
        $this->EnableAction("MUTE");
        $volumeId = $this->RegisterVariableFloat("VOLUME", "Volume", "Volume.YAVR", 2);
        $this->EnableAction("VOLUME");

        $this->RequestData();
        $this->SetTimerInterval('Update', $this->ReadPropertyInteger('UpdateInterval') * 1000);
    }

    protected function UpdateScenesProfile()
    {
        $scenes = json_decode($this->ReadPropertyString('ScenesMapping'));
        if (!IPS_VariableProfileExists("YAVR.Scenes{$this->InstanceID}")) {
            IPS_CreateVariableProfile("YAVR.Scenes{$this->InstanceID}", 1);
        }
        IPS_SetVariableProfileAssociation("YAVR.Scenes{$this->InstanceID}", 0, "Auswahl", '', 0x000000);
        if (count($scenes) > 0) {
            foreach ($scenes as $key => $name) {
                IPS_SetVariableProfileAssociation("YAVR.Scenes{$this->InstanceID}", $key, $name, '', 0x000000);
            }
        }
    }

    protected function UpdateInputsProfile()
    {
        $inputs = json_decode($this->ReadPropertyString('InputsMapping'));
        if (!IPS_VariableProfileExists("YAVR.Inputs{$this->InstanceID}")) {
            IPS_CreateVariableProfile("YAVR.Inputs{$this->InstanceID}", 1);
        }
        IPS_SetVariableProfileAssociation("YAVR.Inputs{$this->InstanceID}", 0, "Auswahl", '', 0x000000);
        if (count($inputs) > 0) {
            foreach ($inputs as $key => $data) {
                IPS_SetVariableProfileAssociation("YAVR.Inputs{$this->InstanceID}", $key, $data->title, '', 0x000000);
            }
        }
    }

    public function RequestAction($ident, $value)
    {
        switch ($ident) {
      case 'STATE':
         $value = $value == 1;
         $this->SetState($value);
         break;
      case 'SCENE':
         if ($value > 0) {
             $value = "Scene $value";
             $this->SetScene($value);
         }
         break;
      case 'INPUT':
         if ($value > 0) {
             $value = $this->GetInputKey($value);
             $this->SetInput($value);
         }
         break;
      case 'MUTE':
         $value = $value == 1;
         $this->SetMute($value);
         break;
      case 'VOLUME':
         $this->SetVolume($value);
         break;
    }
    }

    public function RequestData()
    {
        $data = $this->Request("<Basic_Status>GetParam</Basic_Status>", 'GET');
        if ($data === false) {
            return false;
        }
        $data = $data->Basic_Status;
        $power = $data->Power_Control->Power == 'On';
        SetValueBoolean($this->GetIDForIdent('STATE'), $power);
        if ($inputId = @$this->GetIDForIdent('INPUT')) {
            $input = (string)$data->Input->Input_Sel;
            SetValueInteger($inputId, $this->GetInputId($input));
        }
        $volume = round($data->Volume->Lvl->Val / 10, 1);
        SetValueFloat($this->GetIDForIdent('VOLUME'), $volume);
        $mute = $data->Volume->Mute == 'On';
        SetValueBoolean($this->GetIDForIdent('MUTE'), $mute);
        return $data;
    }

    public function Request($partial, $cmd = 'GET')
    {
        $host = $this->ReadPropertyString('Host');
        $zone = $this->ReadPropertyString('Zone');
        $cmd = strtoupper($cmd);
        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
        $xml .= "<YAMAHA_AV cmd=\"{$cmd}\">";
        $xml .= "<{$zone}>{$partial}</{$zone}>";
        $xml .= "</YAMAHA_AV>";
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, "http://$host:80/YamahaRemoteControl/ctrl");
        curl_setopt($client, CURLOPT_USERAGENT, "SymconYAVR");
        curl_setopt($client, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($client, CURLOPT_TIMEOUT, 5);
        curl_setopt($client, CURLOPT_POST, true);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($client, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($client);
        $status = curl_getinfo($client, CURLINFO_HTTP_CODE);
        curl_close($client);

        if ($status == '0') {
            $this->SetStatus(201);
            return false;
        } elseif ($status != '200') {
            $this->SetStatus(202);
            return false;
        } else {
            $this->SetStatus(102);
            if ($cmd == 'PUT') {
                return true;
            }
            return simplexml_load_string($result)->$zone;
        }
    }

    public function GetValue(string $key)
    {
        return GetValue($this->GetIDForIdent($key));
    }

    public function SetState(bool $state)
    {
        SetValueBoolean($this->GetIDForIdent('STATE'), $state);
        $state = $state ? 'On' : 'Standby';
        return $this->Request("<Power_Control><Power>{$state}</Power></Power_Control>", 'PUT');
    }

    public function SetMute(bool $state)
    {
        SetValueBoolean($this->GetIDForIdent('MUTE'), $state);
        $state = $state ? 'On' : 'Off';
        return $this->Request("<Volume><Mute>{$state}</Mute></Volume>", 'PUT');
    }

    public function SetScene(string $scene)
    {
        return $this->Request("<Scene><Scene_Sel>{$scene}</Scene_Sel></Scene>", 'PUT');
    }

    public function SetInput(string $input)
    {
        SetValueInteger($this->GetIDForIdent('INPUT'), $this->GetInputId($input));
        return $this->Request("<Input><Input_Sel>{$input}</Input_Sel></Input>", 'PUT');
    }

    public function SetVolume($volume)
    {
        if ($volume < -80) {
            $volume = -80;
        }
        if ($volume > 16) {
            $volume = -20;
        } // dont use maximum 16 - if wrong parameter it will not be to loud
        SetValueFloat($this->GetIDForIdent('VOLUME'), $volume);
        $volume = $volume * 10;
        return $this->Request("<Volume><Lvl><Val>{$volume}</Val><Exp>1</Exp><Unit>dB</Unit></Lvl></Volume>", 'PUT');
    }

    public function UpdateScenes()
    {
        $result = array();
        $counter = 0;
        $data = $this->Request("<Scene><Scene_Sel_Item>GetParam</Scene_Sel_Item></Scene>", 'GET');
        if ($data === false) {
            return false;
        }
        $data = (array)$data->Scene->Scene_Sel_Item;
        foreach ($data as $id => $item) {
            $counter++;
            $item = (array)$item;
            if ($item['RW'] == 'W') {
                $result[str_replace('Scene ', '', $item['Param'])] = htmlspecialchars_decode((string)$item['Title']);
            }
        }
        IPS_SetProperty($this->InstanceID, 'ScenesMapping', json_encode($result));
        IPS_ApplyChanges($this->InstanceID);

        $this->UpdateScenesProfile();

        $sceneId = $this->RegisterVariableInteger("SCENE", "Szene", "YAVR.Scenes{$this->InstanceID}", 8);
        $this->EnableAction("SCENE");
        IPS_SetIcon($sceneId, 'HollowArrowRight');

        $resultText = "ID\tName\n";
        foreach ($result as $id => $data) {
            $resultText .= "$id\t{$data}\n";
        }
        return $resultText;
    }


    public function UpdateInputs()
    {
        $result = array();
        $counter = 0;
        $data = $this->Request("<Input><Input_Sel_Item>GetParam</Input_Sel_Item></Input>", 'GET');
        if ($data === false) {
            return false;
        }
        $data = (array)$data->Input->Input_Sel_Item;
        foreach ($data as $id => $item) {
            $counter++;
            $item = (array)$item;
            if ($item['RW'] == 'RW') {
                $result[$counter] = array("id" => (string)$item['Param'], "title" => (string)$item['Param']);
            }
        }
        IPS_SetProperty($this->InstanceID, 'InputsMapping', json_encode($result));
        IPS_ApplyChanges($this->InstanceID);

        $this->UpdateInputsProfile();

        $inputId = $this->RegisterVariableInteger("INPUT", "Eingang", "YAVR.Inputs{$this->InstanceID}", 9);
        $this->EnableAction("INPUT");
        IPS_SetIcon($inputId, 'ArrowRight');

        $resultText = "Symcon ID\tAVR ID\t\tName\n";
        foreach ($result as $id => $data) {
            $resultText .= "$id\t\t{$data['id']}\t\t{$data['title']}\n";
        }
        return $resultText;
    }
}
