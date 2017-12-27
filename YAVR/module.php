<?

class YAVR extends IPSModule
{
    private $json;

    public $InputMapping = array(
        1 => [
            'HDMI1',
            'hdmi1'
        ],
        2 => [
            'HDMI2',
            'hdmi2'
        ],
        3 => [
            'HDMI3',
            'hdmi3'
        ],
        4 => [
            'HDMI4',
            'hdmi4'
        ],
        5 => [
            'HDMI5',
            'hdmi5'
        ],
        6 => [
            'HDMI6',
            'hdmi6'
        ],
        7 => [
            'HDMI7',
            'hdmi7'
        ],
        8 => [
            'HDMI8',
            'hdmi8'
        ],
        9 => [
            'HDMI9',
            'hdmi9'
        ],
        11 => [
            'AV1',
            'av1'
        ],
        12 => [
            'AV2',
            'av2'
        ],
        13 => [
            'AV3',
            'av3'
        ],
        14 => [
            'AV4',
            'av4'
        ],
        15 => [
            'AV5',
            'av5'
        ],
        16 => [
            'AV6',
            'av6'
        ],
        17 => [
            'AV7',
            'av7'
        ],
        18 => [
            'AV8',
            'av8'
        ],
        10 => [
            'AV9',
            'av9'
        ],
        20 => [
            'AUDIO',
            'audio'
        ],
        21 => [
            'AUDIO1',
            'audio1'
        ],
        22 => [
            'AUDIO2',
            'audio2'
        ],
        23 => [
            'AUDIO3',
            'audio3'
        ],
        24 => [
            'AUDIO4',
            'audio4'
        ],
        25 => [
            'AUDIO5',
            'audio5'
        ],
        26 => [
            'AUDIO6',
            'audio6'
        ],
        27 => [
            'AUDIO7',
            'audio7'
        ],
        28 => [
            'AUDIO8',
            'audio8'
        ],
        29 => [
            'AUDIO9',
            'audio9'
        ],
        101 => [
            'Napster',
            'napster'
        ],
        102 => [
            'NET RADIO',
            'net_radio'
        ],
        103 => [
            'PC',
            'pc'
        ],
        104 => [
            'iPod',
            'ipod'
        ],
        105 => [
            'Bluetooth',
            'bluetooth'
        ],
        106 => [
            'UAW'
        ],
        107 => [
            'USB',
            'usb'
        ],
        108 => [
            'iPod (USB)'
        ],
        109 => [
            'TUNER',
            'tuner'
        ],
        110 => [
            'Phono',
            'phono'
        ],
        111 => [
            'V-AUX',
            'AUX',
            'aux'
        ],
        112 => [
            'Spotify',
            'spotify'
        ],
        113 => [
            'AirPlay',
            'airplay'
        ],
        114 => [
            'SERVER',
            'server'
        ],
        115 => [
            'JUKE',
            'juke'
        ],
        116 => [
            'MusicCast Link',
            'mc_link'
        ],
        117 => [
            'Qobuz',
            'qobuz'
        ],
        118 => [
            'TIDAL',
            'tidal'
        ],
        119 => [
            'VIDEO AUX',
            'v_aux'
        ],
        120 => [
            'Main Zone Sync',
            'main_sync'
        ],
        121 => [
            'Deezer',
            'deezer'
        ]
    );


    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString("Host", "");
        $this->RegisterPropertyString("Zone", "Main_Zone");
        $this->RegisterPropertyInteger("UpdateInterval", 5);

        if (!IPS_VariableProfileExists('Volume.YAVR')) IPS_CreateVariableProfile('Volume.YAVR', 2);
        IPS_SetVariableProfileDigits('Volume.YAVR', 1);
        IPS_SetVariableProfileIcon('Volume.YAVR', 'Intensity');
        IPS_SetVariableProfileText('Volume.YAVR', "", " dB");
        IPS_SetVariableProfileValues('Volume.YAVR', -80, 16, 0.5);

        $this->UpdateScenesProfile();
        $this->UpdateInputsProfile();
    }

    public function Destroy()
    {
        parent::Destroy();
        if (!IPS_VariableProfileExists("YAVR.Scenes{$this->InstanceID}")) IPS_DeleteVariableProfile("YAVR.Scenes{$this->InstanceID}");
        if (!IPS_VariableProfileExists("YAVR.Input{$this->InstanceID}")) IPS_DeleteVariableProfile("YAVR.Inputs{$this->InstanceID}");
    }

    public function GetInputId($key)
    {
        foreach ($this->InputMapping AS $id => $inputs) {
            if (in_array($key, $inputs)) {
                return $id;
            }
        }

        throw new Exception("Invalid input $key");
    }

    public function GetInputKey($id)
    {
        $map = array_flip($this->InputMapping);
        if (array_key_exists($id, $map)) {
            return $map[$id][0];
        } else {
            throw new Exception("Invalid input id $id");
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->UpdateScenesProfile();
        $this->UpdateInputsProfile();

        $stateId = $this->RegisterVariableBoolean("STATE", "Zustand", "~Switch", 1);
        $this->EnableAction("STATE");
        $muteId = $this->RegisterVariableBoolean("MUTE", "Mute", "~Switch", 3);
        IPS_SetIcon($muteId, 'Speaker');
        $this->EnableAction("MUTE");
        $volumeId = $this->RegisterVariableFloat("VOLUME", "Volume", "Volume.YAVR", 2);
        $this->EnableAction("VOLUME");
        $sceneId = $this->RegisterVariableInteger("SCENE", "Szene", "YAVR.Scenes{$this->InstanceID}", 8);
        $this->EnableAction("SCENE");
        IPS_SetIcon($sceneId, 'HollowArrowRight');
        $inputId = $this->RegisterVariableInteger("INPUT", "Eingang", "YAVR.Inputs{$this->InstanceID}", 9);
        $this->EnableAction("INPUT");
        IPS_SetIcon($inputId, 'ArrowRight');

        $this->RequestData();
        $this->RegisterTimer('INTERVAL', $this->ReadPropertyInteger('UpdateInterval'), 'YAVR_RequestData($id)');
    }

    protected function UpdateScenesProfile($scenes = array())
    {
        if (!IPS_VariableProfileExists("YAVR.Scenes{$this->InstanceID}")) IPS_CreateVariableProfile("YAVR.Scenes{$this->InstanceID}", 1);
        IPS_SetVariableProfileAssociation("YAVR.Scenes{$this->InstanceID}", 0, "Auswahl", '', 0x000000);
        if (count($scenes) > 0) {
            foreach ($scenes as $key => $name) {
                IPS_SetVariableProfileAssociation("YAVR.Scenes{$this->InstanceID}", $key, $name, '', 0x000000);
            }
        }
    }

    protected function UpdateInputsProfile($inputs = array())
    {
        if (!IPS_VariableProfileExists("YAVR.Inputs{$this->InstanceID}")) IPS_CreateVariableProfile("YAVR.Inputs{$this->InstanceID}", 1);
        IPS_SetVariableProfileAssociation("YAVR.Inputs{$this->InstanceID}", 0, "Auswahl", '', 0x000000);
        if (count($inputs) > 0) {
            foreach ($inputs as $key => $name) {
                IPS_SetVariableProfileAssociation("YAVR.Inputs{$this->InstanceID}", $key, $name, '', 0x000000);
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

    protected function RegisterTimer($ident, $interval, $script)
    {
        $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

        if ($id && IPS_GetEvent($id)['EventType'] <> 1) {
            IPS_DeleteEvent($id);
            $id = 0;
        }

        if (!$id) {
            $id = IPS_CreateEvent(1);
            IPS_SetParent($id, $this->InstanceID);
            IPS_SetIdent($id, $ident);
        }

        IPS_SetName($id, $ident);
        IPS_SetHidden($id, true);
        IPS_SetEventScript($id, "\$id = \$_IPS['TARGET'];\n$script;");

        if (!IPS_EventExists($id)) throw new Exception("Ident with name $ident is used for wrong object type");

        if (!($interval > 0)) {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);
            IPS_SetEventActive($id, false);
        } else {
            IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $interval);
            IPS_SetEventActive($id, true);
        }
    }

    public function RequestData()
    {
        if ($this->isJson()) {
            $data = $this->RequestJSON('getStatus');
            if ($data === false) return false;

            $power = $data->power == 'on';
            SetValueBoolean($this->GetIDForIdent('STATE'), $power);
            $input = (string)$data->input;

            SetValueInteger($this->GetIDForIdent('INPUT'), $this->GetInputId($input));
            SetValueFloat($this->GetIDForIdent('VOLUME'), $data->volume);
            SetValueBoolean($this->GetIDForIdent('MUTE'), $data->mute);

            return $data;
        } else {
            $data = $this->Request("<Basic_Status>GetParam</Basic_Status>", 'GET');
            if ($data === false) return false;
            $data = $data->Basic_Status;
            $power = $data->Power_Control->Power == 'On';
            SetValueBoolean($this->GetIDForIdent('STATE'), $power);
            $input = (string)$data->Input->Input_Sel;
            SetValueInteger($this->GetIDForIdent('INPUT'), $this->GetInputId($input));
            $volume = round($data->Volume->Lvl->Val / 10, 1);
            SetValueFloat($this->GetIDForIdent('VOLUME'), $volume);
            $mute = $data->Volume->Mute == 'On';
            SetValueBoolean($this->GetIDForIdent('MUTE'), $mute);
            return $data;
        }
    }

    private function isJson()
    {
        $host = $this->ReadPropertyString('Host');

        if(!$host) {
            return false;
        }

        // check if json api is available
        if (is_null($this->json)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://$host:80/YamahaExtendedControl/v1/system/getDeviceInfo");
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->json = ($status === 200);
        }

        return $this->json;
    }

    public function Request($partial, $cmd)
    {
        $host = $this->ReadPropertyString('Host');
        $zone = $this->ReadPropertyString('Zone');

        if(!$host) {
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
            if ($cmd == 'PUT') return true;
            return simplexml_load_string($result)->{$zone};
        }
    }

    public function RequestJSON($method, $system = false)
    {
        $host = $this->ReadPropertyString('Host');
        $zone = $this->ReadPropertyString('Zone');

        $zoneMapper = [
            'Main_Zone' => 'main',
            'Zone_2' => 'zone2',
            'Zone_3' => 'zone3',
            'Zone_4' => 'zone4'
        ];

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

    public function GetValue($key)
    {
        return GetValue($this->GetIDForIdent($key));
    }

    public function SetState($state)
    {
        SetValueBoolean($this->GetIDForIdent('STATE'), $state);
        $state = $state ? 'On' : 'Standby';
        return $this->isJson()
            ? $this->RequestJSON('setPower?power=' . strtolower($state))
            : $this->Request("<Power_Control><Power>{$state}</Power></Power_Control>", 'PUT');
    }

    public function SetMute($state)
    {
        SetValueBoolean($this->GetIDForIdent('MUTE'), $state);
        $state = $state ? 'On' : 'Off';

        return $this->isJson()
            ? $this->RequestJSON('setMute?enable=' . ($state === 'On' ? 'true' : 'false'))
            : $this->Request("<Volume><Mute>{$state}</Mute></Volume>", 'PUT');
    }

    public function SetScene($scene)
    {
        if($this->isJson()) {
            $scenes = $this->ListScenes();
            $scene = $scenes[$scene];
        }

        return $this->isJson()
            ? $this->RequestJSON('setSoundProgram?program=' . ($scene))
            : $this->Request("<Scene><Scene_Sel>{$scene}</Scene_Sel></Scene>", 'PUT');
    }

    public function SetInput($input)
    {
        $input_id = $this->GetInputId($input);
        SetValueInteger($this->GetIDForIdent('INPUT'), $input_id);

        if($this->isJson()) {
            $inputs = $this->InputMapping[$input_id];
            $input = end($inputs);
        }

        return $this->isJson()
            ? $this->RequestJSON('setInput?input=' . ($input))
            : $this->Request("<Input><Input_Sel>{$input}</Input_Sel></Input>", 'PUT');
    }

    public function SetVolume($volume)
    {
        if($this->isJson()) {
            return $this->RequestJSON('setVolume?volume=' . $volume);
        }

        if ($volume < -80) $volume = -80;
        if ($volume > 16) $volume = -20; // dont use maximum 16 - if wrong parameter it will not be to loud
        SetValueFloat($this->GetIDForIdent('VOLUME'), $volume);
        $volume = $volume * 10;
        return $this->Request("<Volume><Lvl><Val>{$volume}</Val><Exp>1</Exp><Unit>dB</Unit></Lvl></Volume>", 'PUT');
    }

    public function ListScenes()
    {
        $result = array();

        if ($this->isJson()) {
            $data = $this->RequestJSON('getFeatures', true);
            if ($data === false) return false;
            $result = isset($data->zone->sound_program_list) ? (array)$data->zone->sound_program_list : [];
        } else {
            $data = $this->Request("<Scene><Scene_Sel_Item>GetParam</Scene_Sel_Item></Scene>", 'GET');
            if ($data === false) return false;
            $data = (array)$data->Scene->Scene_Sel_Item;
            foreach ($data as $id => $item) {
                $item = (array)$item;
                if ($item['RW'] == 'W') $result[str_replace('Scene ', '', $item['Param'])] = $item['Title'];
            }
        }

        $this->UpdateScenesProfile($result);
        return $result;
    }


    public function ListInputs()
    {
        $result = array();
        if ($this->isJson()) {
            $data = $this->RequestJSON('getFeatures', true);
            if ($data === false) return false;
            $data = (array)$data->zone->input_list;
            foreach ($data as $input) {
                $result[$this->GetInputId($input)] = $input;
            }
        } else {
            $data = $this->Request("<Input><Input_Sel_Item>GetParam</Input_Sel_Item></Input>", 'GET');
            if ($data === false) return false;
            $data = (array)$data->Input->Input_Sel_Item;
            foreach ($data as $id => $item) {
                $item = (array)$item;
                $result[$this->GetInputId($item['Param'])] = $item['Title'];
            }
        }

        $this->UpdateInputsProfile($result);
        return $result;
    }
}

?>
