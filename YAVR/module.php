<?
class YAVR extends IPSModule {

    private $isJson;

    public function Create() {
        parent::Create();
        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyString('Zone', 'Main_Zone');
        $this->RegisterPropertyInteger('UpdateInterval', 5);
        $this->RegisterPropertyString('InputsMapping', '');
        $this->RegisterPropertyString('ScenesMapping', '');

        if (!IPS_VariableProfileExists('Volume.YAVR')) IPS_CreateVariableProfile('Volume.YAVR', 2);
        IPS_SetVariableProfileDigits('Volume.YAVR', 1);
        IPS_SetVariableProfileIcon('Volume.YAVR', 'Intensity');
        IPS_SetVariableProfileText('Volume.YAVR', '', ' dB');
        IPS_SetVariableProfileValues('Volume.YAVR', -80, 16, 0.5);

        $this->RegisterTimer('Update', 0, 'YAVR_RequestData($_IPS[\'TARGET\'], 0);');

        if($oldInterval = @$this->GetIDForIdent('INTERVAL')) IPS_DeleteEvent($oldInterval);
    }

    public function Destroy() {
        parent::Destroy();
        if (!IPS_VariableProfileExists("YAVR.Scenes{$this->InstanceID}")) IPS_DeleteVariableProfile("YAVR.Scenes{$this->InstanceID}");
        if (!IPS_VariableProfileExists("YAVR.Input{$this->InstanceID}")) IPS_DeleteVariableProfile("YAVR.Inputs{$this->InstanceID}");
    }

    public function GetInputId(string $value) {
        $inputs = json_decode($this->ReadPropertyString('InputsMapping'));
        $inputs2 = array();

        foreach($inputs as $id => $data) {
            $inputs2[$data->title] = $id;
        }

        if(array_key_exists($value, $inputs2)) {
            return $inputs2[$value];
        } else {
            throw new Exception("Invalid input $value");
        }
    }

    public function GetInputKey(int $value) {
        $inputs = json_decode($this->ReadPropertyString('InputsMapping'));
        $inputs2 = array();
        foreach($inputs as $id => $data) {
            $inputs2[$id] = $data->id;
        }
        if(array_key_exists($id, $inputs2)) {
            return $inputs2[$value];
        } else {
            throw new Exception("Invalid input id $id");
        }
    }

    public function ApplyChanges() {
        parent::ApplyChanges();

        if($this->ReadPropertyString('ScenesMapping') == '') $this->UpdateScenes();
        if($this->ReadPropertyString('InputsMapping') == '') $this->UpdateInputs();

        $stateId = $this->RegisterVariableBoolean("STATE", "Zustand", "~Switch", 1);
        $this->EnableAction("STATE");
        $muteId = $this->RegisterVariableBoolean("MUTE", "Mute", "~Switch", 3);
        IPS_SetIcon($muteId, 'Speaker');
        $this->EnableAction("MUTE");
        $volumeId = $this->RegisterVariableFloat("VOLUME", "Volume", "Volume.YAVR", 2);
        $this->EnableAction("VOLUME");

        $this->SetVolumeRange();
        $this->RequestData();
        $this->SetTimerInterval('Update', $this->ReadPropertyInteger('UpdateInterval') * 1000);
    }

    protected function UpdateScenesProfile() {
        $scenes = json_decode($this->ReadPropertyString('ScenesMapping'));
        if (!IPS_VariableProfileExists("YAVR.Scenes{$this->InstanceID}")) IPS_CreateVariableProfile("YAVR.Scenes{$this->InstanceID}", 1);
        IPS_SetVariableProfileAssociation("YAVR.Scenes{$this->InstanceID}", 0, "Auswahl", '', 0x000000);
        if (count($scenes) > 0) {
            foreach ($scenes as $key => $name) {
                IPS_SetVariableProfileAssociation("YAVR.Scenes{$this->InstanceID}", $key, $name, '', 0x000000);
            }
        }
    }

    protected function UpdateInputsProfile() {
        $inputs = json_decode($this->ReadPropertyString('InputsMapping'));
        if (!IPS_VariableProfileExists("YAVR.Inputs{$this->InstanceID}")) IPS_CreateVariableProfile("YAVR.Inputs{$this->InstanceID}", 1);
        IPS_SetVariableProfileAssociation("YAVR.Inputs{$this->InstanceID}", 0, "Auswahl", '', 0x000000);
        if (count($inputs) > 0) {
            foreach ($inputs as $key => $data) {
                IPS_SetVariableProfileAssociation("YAVR.Inputs{$this->InstanceID}", $key, $data->title, '', 0x000000);
            }
        }
    }

    public function RequestAction($ident, $value) {
        switch ($ident) {
            case 'STATE':
                $value = $value == 1;
                $this->SetState($value);
                break;
            case 'SCENE':
                if($value > 0) {
                    $value = $this->isJson() ? $value : "Scene $value";
                    $this->SetScene($value);
                }
                break;
            case 'INPUT':
                if($value > 0) {
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

    public function RequestData() {
        if ($this->isJson()) {
            $data = $this->RequestJSON('getStatus');
            if ($data === false) return false;

            $power = $data->power == 'on';
            SetValueBoolean($this->GetIDForIdent('STATE'), $power);
            if ($inputId = @$this->GetIDForIdent('INPUT')) {
                $input = (string)$data->input;
                SetValueInteger($inputId, $this->GetInputId($input));
            }
            SetValueFloat($this->GetIDForIdent('VOLUME'), $data->volume);
            SetValueBoolean($this->GetIDForIdent('MUTE'), $data->mute);
        } else {
            $data = $this->RequestXML("<Basic_Status>GetParam</Basic_Status>", 'GET');
            if ($data === false) return false;
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
        }

        return $data;
    }

    private function isJson() {
        $host = $this->ReadPropertyString('Host');

        if(!$host) {
            return false;
        }

        // check if json api is available
        if (is_null($this->isJson)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://$host:80/YamahaExtendedControl/v1/system/getDeviceInfo");
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->isJson = ($status === 200);
        }

        return $this->isJson;
    }

    public function RequestXML($partial, $cmd = 'GET') {
        $host = $this->ReadPropertyString('Host');
        $zone = $this->ReadPropertyString('Zone');

        if(!$host) {
            $this->SetStatus(201);
            return false;
        }
        
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
            if($cmd == 'PUT') return true;
            return simplexml_load_string($result)->$zone;
        }
    }

    public function RequestJSON($method, $system = false) {
        $host = $this->ReadPropertyString('Host');
        $zone = $this->ReadPropertyString('Zone');

        if(!$host) {
            $this->SetStatus(201);
            return false;
        }

        $zoneMapper = array(
            'Main_Zone' => 'main',
            'Zone_2' => 'zone2',
            'Zone_3' => 'zone3',
            'Zone_4' => 'zone4'
        );

        $zone = $zoneMapper[$zone];
        $zone_request = $system ? 'system' : $zone;

        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, "http://$host:80/YamahaExtendedControl/v1/$zone_request/$method");
        curl_setopt($client, CURLOPT_USERAGENT, "SymconYAVR");
        curl_setopt($client, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($client, CURLOPT_TIMEOUT, 5);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($client);
        $status = curl_getinfo($client, CURLINFO_HTTP_CODE);
        curl_close($client);

        $result = @json_decode($result);

        if ($system && isset($result->zone)) {
            foreach ($result->zone AS $zone_data) {
                if ($zone_data->id == $zone) {
                    $result->zone = $zone_data;
                    break;
                }
            }
        }

        if ($status == '0') {
            $this->SetStatus(201);
            return false;
        } elseif ($status != '200') {
            $this->SetStatus(202);
            return false;
        } else {
            $this->SetStatus(102);
            return $result;
        }
    }

    public function GetValue(string $key) {
        return GetValue($this->GetIDForIdent($key));
    }

    public function SetState(bool $state) {
        SetValueBoolean($this->GetIDForIdent('STATE'), $state);
        $state = $state ? 'On' : 'Standby';
        return $this->isJson()
            ? $this->RequestJSON('setPower?power=' . strtolower($state))
            : $this->RequestXML("<Power_Control><Power>{$state}</Power></Power_Control>", 'PUT');
    }

    public function SetMute(bool $state) {
        SetValueBoolean($this->GetIDForIdent('MUTE'), $state);
        $state = $state ? 'On' : 'Off';
        return $this->isJson()
            ? $this->RequestJSON('setMute?enable=' . ($state === 'On' ? 'true' : 'false'))
            : $this->RequestXML("<Volume><Mute>{$state}</Mute></Volume>", 'PUT');
    }

    public function SetScene(string $scene) {
        SetValueInteger($this->GetIDForIdent('SCENE'), (int) $scene);

        if($this->isJson()) {
            $data = $this->RequestJSON('getFeatures', true);
            $scenes = isset($data->zone->sound_program_list) ? (array)$data->zone->sound_program_list : array();
            $scene_id = (int)$scene-1;
            $scene = $scenes[$scene_id];
        }
        return $this->isJson()
            ? $this->RequestJSON('setSoundProgram?program=' . ($scene))
            : $this->RequestXML("<Scene><Scene_Sel>{$scene}</Scene_Sel></Scene>", 'PUT');
    }

    public function SetInput(string $input) {
        SetValueInteger($this->GetIDForIdent('INPUT'), $this->GetInputId($input));
        return $this->isJson()
            ? $this->RequestJSON('setInput?input=' . ($input))
            : $this->RequestXML("<Input><Input_Sel>{$input}</Input_Sel></Input>", 'PUT');
    }

    public function SetVolume($volume) {
        if($this->isJson()) {
            SetValueFloat($this->GetIDForIdent('VOLUME'), $volume);
            return $this->RequestJSON('setVolume?volume=' . $volume);
        }
        else {
            if ($volume < -80) $volume = -80;
            if ($volume > 16) $volume = -20; // dont use maximum 16 - if wrong parameter it will not be to loud
            SetValueFloat($this->GetIDForIdent('VOLUME'), $volume);
            $volume = $volume * 10;
            return $this->RequestXML("<Volume><Lvl><Val>{$volume}</Val><Exp>1</Exp><Unit>dB</Unit></Lvl></Volume>", 'PUT');
        }
    }

    public function UpdateScenes() {
        $result = array();
        $resultText = "ID\tName\n";

        if($this->isJson()) {
            $data = $this->RequestJSON('getFeatures', true);
            if($data === false) return false;
            $data = isset($data->zone->sound_program_list) ? (array)$data->zone->sound_program_list : array();
            foreach ($data as $id => $scene) {
                $result[$id+1] = htmlspecialchars_decode((string)$scene);
            }
        }
        else {
            $data = $this->RequestXML("<Scene><Scene_Sel_Item>GetParam</Scene_Sel_Item></Scene>", 'GET');
            if($data === false) return false;
            $data = (array)$data->Scene->Scene_Sel_Item;
            foreach ($data as $id => $item) {
                $item = (array)$item;
                if ($item['RW'] == 'W') $result[str_replace('Scene ', '', $item['Param'])] = htmlspecialchars_decode((string)$item['Title']);
            }
        }

        IPS_SetProperty($this->InstanceID, 'ScenesMapping', json_encode($result));
        IPS_ApplyChanges($this->InstanceID);

        $this->UpdateScenesProfile();

        $sceneId = $this->RegisterVariableInteger("SCENE", "Szene", "YAVR.Scenes{$this->InstanceID}", 8);
        $this->EnableAction("SCENE");
        IPS_SetIcon($sceneId, 'HollowArrowRight');

        foreach($result as $id => $data) {
            $resultText .= "$id\t{$data}\n";
        }

        return $resultText;
    }


    public function UpdateInputs() {
        $result = array();
        $resultText = "Symcon ID\tAVR ID\t\tName\n";
        $counter = 0;

        if($this->isJson()) {
            $data = $this->RequestJSON('getFeatures', true);
            if($data === false) return false;
            $data = (array)$data->zone->input_list;
            foreach ($data as $id => $input) {
                $counter++;
                $result[$counter] = array("id" => (string)$input, "title" => (string)$input);
            }
        }
        else {
            $data = $this->RequestXML("<Input><Input_Sel_Item>GetParam</Input_Sel_Item></Input>", 'GET');
            if($data === false) return false;
            $data = (array)$data->Input->Input_Sel_Item;
            foreach ($data as $id => $item) {
                $counter++;
                $item = (array)$item;
                if ($item['RW'] == 'RW') $result[$counter] = array("id" => (string)$item['Param'], "title" => (string)$item['Param']);
            }
        }

        IPS_SetProperty($this->InstanceID, 'InputsMapping', json_encode($result));
        IPS_ApplyChanges($this->InstanceID);

        $this->UpdateInputsProfile();

        $inputId = $this->RegisterVariableInteger("INPUT", "Eingang", "YAVR.Inputs{$this->InstanceID}", 9);
        $this->EnableAction("INPUT");
        IPS_SetIcon($inputId, 'ArrowRight');

        foreach($result as $id => $data) {
            $resultText .= "$id\t\t{$data['id']}\t\t{$data['title']}\n";
        }

        return $resultText;
    }

    private function SetVolumeRange() {
        if($this->isJson()) {
            $features = $this->RequestJSON('getFeatures', true);
            if(isset($features->zone->range_step) && is_array($features->zone->range_step)) {
                foreach($features->zone->range_step AS $range_step) {
                    if($range_step->id == 'volume') {
                        IPS_SetVariableProfileValues('Volume.YAVR', $range_step->min, $range_step->max, $range_step->step);
                        break;
                    }
                }
            }

        }
    }
}