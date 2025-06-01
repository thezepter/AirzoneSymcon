<?php

declare(strict_types=1);

class AirzoneAidoo extends IPSModule
{
    // API Endpoints
    const API_LOCAL_BASE = 'http://%s:3000/api/v1';
    const API_CLOUD_BASE = 'https://www.airzonecloud.com/users/sign_in';
    
    // Status codes
    const STATUS_INST_IP_IS_EMPTY = 201;
    const STATUS_INST_IP_IS_INVALID = 202;
    const STATUS_INST_CONNECTION_TIMEOUT = 203;
    const STATUS_INST_AUTHENTICATION_FAILED = 204;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        $this->RegisterPropertyString('GatewayIP', '');
        $this->RegisterPropertyString('CloudUsername', '');
        $this->RegisterPropertyString('CloudPassword', '');
        $this->RegisterPropertyBoolean('UseLocalConnection', true);
        $this->RegisterPropertyInteger('UpdateInterval', 30);
        $this->RegisterPropertyString('SystemID', '');
        $this->RegisterPropertyString('ZoneID', '');

        // Variables
        $this->RegisterVariableBoolean('Power', $this->Translate('Power'), '~Switch', 1);
        $this->EnableAction('Power');
        
        $this->RegisterVariableFloat('Temperature', $this->Translate('Temperature'), '~Temperature', 2);
        $this->RegisterVariableFloat('SetTemperature', $this->Translate('Set Temperature'), '~Temperature', 3);
        $this->EnableAction('SetTemperature');
        
        $this->RegisterVariableInteger('Mode', $this->Translate('Mode'), 'AIRZONE.Mode', 4);
        $this->EnableAction('Mode');
        
        $this->RegisterVariableInteger('FanSpeed', $this->Translate('Fan Speed'), 'AIRZONE.FanSpeed', 5);
        $this->EnableAction('FanSpeed');
        
        $this->RegisterVariableFloat('Humidity', $this->Translate('Humidity'), '~Humidity', 6);

        // Profiles
        $this->CreateProfiles();

        // Timer
        $this->RegisterTimer('UpdateTimer', 0, 'AIRZONE_Update($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

        // Validate configuration
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');
        
        if ($useLocal && empty($gatewayIP)) {
            $this->SetStatus(self::STATUS_INST_IP_IS_EMPTY);
            return;
        }

        if ($useLocal && !filter_var($gatewayIP, FILTER_VALIDATE_IP)) {
            $this->SetStatus(self::STATUS_INST_IP_IS_INVALID);
            return;
        }

        // Set update timer
        $interval = $this->ReadPropertyInteger('UpdateInterval');
        if ($interval > 0) {
            $this->SetTimerInterval('UpdateTimer', $interval * 1000);
        } else {
            $this->SetTimerInterval('UpdateTimer', 0);
        }

        // Test connection
        if ($this->TestConnection()) {
            $this->SetStatus(IS_ACTIVE);
            $this->Update();
        } else {
            $this->SetStatus(self::STATUS_INST_CONNECTION_TIMEOUT);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Power':
                $this->SetPower($Value);
                break;
            case 'SetTemperature':
                $this->SetTemperature($Value);
                break;
            case 'Mode':
                $this->SetMode($Value);
                break;
            case 'FanSpeed':
                $this->SetFanSpeed($Value);
                break;
            default:
                throw new Exception('Invalid Ident');
        }
    }

    public function Update()
    {
        $data = $this->GetSystemData();
        if ($data !== false) {
            $this->UpdateVariables($data);
        }
    }



    public function SetPower(bool $power)
    {
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        
        $data = [
            'systemID' => $systemID,
            'zoneID' => $zoneID,
            'data' => [
                'power' => $power ? 1 : 0
            ]
        ];

        if ($this->SendCommand('PUT', '/integration', $data)) {
            $this->SetValue('Power', $power);
            return true;
        }
        return false;
    }

    public function SetTemperature(float $temperature)
    {
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        
        $data = [
            'systemID' => $systemID,
            'zoneID' => $zoneID,
            'data' => [
                'setpoint' => $temperature
            ]
        ];

        if ($this->SendCommand('PUT', '/integration', $data)) {
            $this->SetValue('SetTemperature', $temperature);
            return true;
        }
        return false;
    }

    public function SetMode(int $mode)
    {
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        
        $modeMapping = [
            0 => 'stop',
            1 => 'cool',
            2 => 'heat',
            3 => 'fan',
            4 => 'dry',
            5 => 'auto'
        ];

        if (!isset($modeMapping[$mode])) {
            return false;
        }

        $data = [
            'systemID' => $systemID,
            'zoneID' => $zoneID,
            'data' => [
                'mode' => $modeMapping[$mode]
            ]
        ];

        if ($this->SendCommand('PUT', '/integration', $data)) {
            $this->SetValue('Mode', $mode);
            return true;
        }
        return false;
    }

    public function SetFanSpeed(int $speed)
    {
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        
        $data = [
            'systemID' => $systemID,
            'zoneID' => $zoneID,
            'data' => [
                'fanSpeed' => $speed
            ]
        ];

        if ($this->SendCommand('PUT', '/integration', $data)) {
            $this->SetValue('FanSpeed', $speed);
            return true;
        }
        return false;
    }

    private function CreateProfiles()
    {
        // Mode profile
        if (!IPS_VariableProfileExists('AIRZONE.Mode')) {
            IPS_CreateVariableProfile('AIRZONE.Mode', 1);
            IPS_SetVariableProfileText('AIRZONE.Mode', '', '');
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 0, $this->Translate('Stop'), '', 0x808080);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 1, $this->Translate('Cool'), '', 0x0080FF);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 2, $this->Translate('Heat'), '', 0xFF8000);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 3, $this->Translate('Fan'), '', 0x80FF80);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 4, $this->Translate('Dry'), '', 0xFFFF00);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 5, $this->Translate('Auto'), '', 0x8080FF);
        }

        // Fan Speed profile
        if (!IPS_VariableProfileExists('AIRZONE.FanSpeed')) {
            IPS_CreateVariableProfile('AIRZONE.FanSpeed', 1);
            IPS_SetVariableProfileText('AIRZONE.FanSpeed', '', '');
            IPS_SetVariableProfileAssociation('AIRZONE.FanSpeed', 0, $this->Translate('Auto'), '', 0x808080);
            IPS_SetVariableProfileAssociation('AIRZONE.FanSpeed', 1, $this->Translate('Low'), '', 0x80FF80);
            IPS_SetVariableProfileAssociation('AIRZONE.FanSpeed', 2, $this->Translate('Medium'), '', 0xFFFF00);
            IPS_SetVariableProfileAssociation('AIRZONE.FanSpeed', 3, $this->Translate('High'), '', 0xFF8000);
        }
    }

    public function TestConnection(): bool
    {
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');
        
        if ($useLocal) {
            return $this->TestLocalConnection();
        } else {
            return $this->TestCloudConnection();
        }
    }

    private function TestLocalConnection(): bool
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $url = sprintf(self::API_LOCAL_BASE, $gatewayIP) . '/ping';
        
        $response = $this->SendHTTPRequest('GET', $url);
        return $response !== false;
    }

    private function TestCloudConnection(): bool
    {
        $username = $this->ReadPropertyString('CloudUsername');
        $password = $this->ReadPropertyString('CloudPassword');
        
        if (empty($username) || empty($password)) {
            return false;
        }

        // Cloud authentication would be implemented here
        // For now, return true if credentials are provided
        return true;
    }

    private function GetSystemData()
    {
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');
        
        if ($useLocal) {
            return $this->GetLocalSystemData();
        } else {
            return $this->GetCloudSystemData();
        }
    }

    private function GetLocalSystemData()
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        
        $url = sprintf(self::API_LOCAL_BASE, $gatewayIP) . '/hvac';
        $params = [
            'systemid' => $systemID,
            'zoneid' => $zoneID
        ];
        
        $url .= '?' . http_build_query($params);
        
        $response = $this->SendHTTPRequest('GET', $url);
        
        // Extract data from Aidoo Pro response format
        if ($response && isset($response['data']) && !empty($response['data'])) {
            return $response['data'][0];
        }
        
        return false;
    }

    private function GetCloudSystemData()
    {
        // Cloud API implementation would go here
        // This is a placeholder for cloud functionality
        return false;
    }

    private function SendCommand(string $method, string $endpoint, array $data = null)
    {
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');
        
        if ($useLocal) {
            $gatewayIP = $this->ReadPropertyString('GatewayIP');
            $url = sprintf(self::API_LOCAL_BASE, $gatewayIP) . $endpoint;
            
            return $this->SendHTTPRequest($method, $url, $data);
        } else {
            // Cloud command implementation would go here
            return false;
        }
    }

    private function SendHTTPRequest(string $method, string $url, array $data = null)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return false;
        }

        return json_decode($response, true);
    }

    private function UpdateVariables(array $data)
    {
        // Aidoo Pro data format mapping
        if (isset($data['on'])) {
            $this->SetValue('Power', (bool)$data['on']);
        }
        
        if (isset($data['roomTemp'])) {
            $this->SetValue('Temperature', (float)$data['roomTemp']);
        }
        
        if (isset($data['setpoint'])) {
            $this->SetValue('SetTemperature', (float)$data['setpoint']);
        }
        
        if (isset($data['mode'])) {
            // Aidoo Pro mode mapping: 1=Stop, 2=Cool, 3=Heat, 4=Fan, 5=Dry, 7=Auto
            $modeMapping = [
                1 => 0, // Stop
                2 => 1, // Cool  
                3 => 2, // Heat
                4 => 3, // Fan
                5 => 4, // Dry
                7 => 5  // Auto
            ];
            
            if (isset($modeMapping[$data['mode']])) {
                $this->SetValue('Mode', $modeMapping[$data['mode']]);
            }
        }
        
        if (isset($data['speed'])) {
            $this->SetValue('FanSpeed', (int)$data['speed']);
        }
        
        if (isset($data['humidity'])) {
            $this->SetValue('Humidity', (float)$data['humidity']);
        }
    }
}
