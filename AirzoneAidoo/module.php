<?php

declare(strict_types=1);

class AirzoneAidoo extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Properties for Gateway Connection
        $this->RegisterPropertyString('GatewayIP', '192.168.2.61');
        $this->RegisterPropertyString('SystemID', '1');
        $this->RegisterPropertyString('ZoneID', '1');
        $this->RegisterPropertyBoolean('UseLocalConnection', true);
        $this->RegisterPropertyInteger('UpdateInterval', 30);
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');

        // Variables
        $this->RegisterVariableBoolean('Power', $this->Translate('Power'), '~Switch', 1);
        $this->EnableAction('Power');

        $this->RegisterVariableFloat('Temperature', $this->Translate('Temperature'), '~Temperature', 2);
        $this->RegisterVariableFloat('TargetTemperature', $this->Translate('Target Temperature'), '~Temperature', 3);
        $this->EnableAction('TargetTemperature');

        $this->RegisterVariableInteger('Mode', $this->Translate('Mode'), 'Airzone.Mode', 4);
        $this->EnableAction('Mode');

        $this->RegisterVariableInteger('FanSpeed', $this->Translate('Fan Speed'), 'Airzone.FanSpeed', 5);
        $this->EnableAction('FanSpeed');

        $this->RegisterVariableFloat('Humidity', $this->Translate('Humidity'), '~Humidity', 6);

        // Timer for updates
        $this->RegisterTimer('UpdateTimer', 0, 'AIRZONEAIDOO_UpdateData($id);');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');

        if (empty($gatewayIP) && $useLocal) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($this->TestConnection()) {
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_EBASE + 1);
        }

        $interval = $this->ReadPropertyInteger('UpdateInterval');
        if ($interval > 0) {
            $this->SetTimerInterval('UpdateTimer', $interval * 1000);
        } else {
            $this->SetTimerInterval('UpdateTimer', 0);
        }

        $this->SetStatus(IS_ACTIVE);
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        return json_encode($form);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Power':
                $this->SetPower($Value);
                break;
            case 'TargetTemperature':
                $this->SetTargetTemperature($Value);
                break;
            case 'Mode':
                $this->SetMode($Value);
                break;
            case 'FanSpeed':
                $this->SetFanSpeed($Value);
                break;
        }
    }

    public function UpdateData()
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');

        $url = "http://{$gatewayIP}:3000/api/v1/hvac?systemid={$systemID}&zoneid={$zoneID}";

        $data = $this->MakeApiCall($url);
        if ($data && isset($data['data'])) {
            $this->ProcessZoneData($data['data']);
        }
    }

    public function SetPower(bool $value)
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');

        $url = "http://{$gatewayIP}:3000/api/v1/hvac";
        $postData = [
            'systemID' => (int)$systemID,
            'zoneID' => (int)$zoneID,
            'on' => $value
        ];

        $data = $this->MakeApiCall($url, $postData);
        if ($data) {
            $this->SetValue('Power', $value);
        }
    }

    public function SetTargetTemperature(float $value)
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');

        $url = "http://{$gatewayIP}:3000/api/v1/hvac";
        $postData = [
            'systemID' => (int)$systemID,
            'zoneID' => (int)$zoneID,
            'setpoint' => $value
        ];

        $data = $this->MakeApiCall($url, $postData);
        if ($data) {
            $this->SetValue('TargetTemperature', $value);
        }
    }

    public function SetMode(int $value)
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');

        $url = "http://{$gatewayIP}:3000/api/v1/hvac";
        $postData = [
            'systemID' => (int)$systemID,
            'zoneID' => (int)$zoneID,
            'mode' => $value
        ];

        $data = $this->MakeApiCall($url, $postData);
        if ($data) {
            $this->SetValue('Mode', $value);
        }
    }

    public function SetFanSpeed(int $value)
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');

        $url = "http://{$gatewayIP}:3000/api/v1/hvac";
        $postData = [
            'systemID' => (int)$systemID,
            'zoneID' => (int)$zoneID,
            'fanSpeed' => $value
        ];

        $data = $this->MakeApiCall($url, $postData);
        if ($data) {
            $this->SetValue('FanSpeed', $value);
        }
    }

    private function CreateProfiles()
    {
        if (!IPS_VariableProfileExists('Airzone.Mode')) {
            IPS_CreateVariableProfile('Airzone.Mode', 1);
            IPS_SetVariableProfileText('Airzone.Mode', '', '');
            IPS_SetVariableProfileAssociation('Airzone.Mode', 0, $this->Translate('Stop'), '', 0x808080);
            IPS_SetVariableProfileAssociation('Airzone.Mode', 1, $this->Translate('Cool'), '', 0x0080FF);
            IPS_SetVariableProfileAssociation('Airzone.Mode', 2, $this->Translate('Heat'), '', 0xFF4000);
            IPS_SetVariableProfileAssociation('Airzone.Mode', 3, $this->Translate('Fan'), '', 0x00FF00);
            IPS_SetVariableProfileAssociation('Airzone.Mode', 4, $this->Translate('Dry'), '', 0xFFFF00);
            IPS_SetVariableProfileAssociation('Airzone.Mode', 5, $this->Translate('Auto'), '', 0x8000FF);
        }

        if (!IPS_VariableProfileExists('Airzone.FanSpeed')) {
            IPS_CreateVariableProfile('Airzone.FanSpeed', 1);
            IPS_SetVariableProfileText('Airzone.FanSpeed', '', '');
            IPS_SetVariableProfileAssociation('Airzone.FanSpeed', 0, $this->Translate('Auto'), '', 0x808080);
            IPS_SetVariableProfileAssociation('Airzone.FanSpeed', 1, $this->Translate('Low'), '', 0x00FF00);
            IPS_SetVariableProfileAssociation('Airzone.FanSpeed', 2, $this->Translate('Medium'), '', 0xFFFF00);
            IPS_SetVariableProfileAssociation('Airzone.FanSpeed', 3, $this->Translate('High'), '', 0xFF4000);
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
        $url = "http://{$gatewayIP}:3000/api/v1/hvac?systemid=1&zoneid=1";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && $response !== false);
    }

    private function TestCloudConnection(): bool
    {
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');

        if (!$useLocal) {
            $username = $this->ReadPropertyString('Username');
            $password = $this->ReadPropertyString('Password');
            $systemID = $this->ReadPropertyString('SystemID');

            if (empty($username) || empty($password)) {
                return false;
            }

            $authData = $this->AuthenticateCloud($username, $password);
            if (!$authData) {
                return false;
            }

            $url = "https://www.airzonecloud.com/api/v1/hvac?systemid={$systemID}&zoneid=1";
            $headers = [
                'Authorization: Bearer ' . $authData['access_token'],
                'Accept: application/json'
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return ($httpCode === 200 && $response !== false);
        }

        return true;
    }

    private function AuthenticateCloud(string $username, string $password): array|false
    {
        $url = 'https://www.airzonecloud.com/api/v1/auth';
        $postData = [
            'email' => $username,
            'password' => $password
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response !== false) {
            $data = json_decode($response, true);
            if (isset($data['access_token'])) {
                return $data;
            }
        }

        return false;
    }

    private function MakeApiCall(string $url, array $postData = null): array|false
    {
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');

        if ($useLocal) {
            return $this->MakeLocalApiCall($url, $postData);
        } else {
            return $this->MakeCloudApiCall($url, $postData);
        }
    }

    private function MakeLocalApiCall(string $url, array $postData = null): array|false
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ],
            CURLOPT_USERAGENT => 'IP-Symcon Airzone Module'
        ]);

        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            return false;
        }

        if ($httpCode !== 200) {
            return false;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $data;
    }

    private function MakeCloudApiCall(string $url, array $postData = null): array|false
    {
        $username = $this->ReadPropertyString('Username');
        $password = $this->ReadPropertyString('Password');

        $authData = $this->AuthenticateCloud($username, $password);
        if (!$authData) {
            return false;
        }

        $headers = [
            'Authorization: Bearer ' . $authData['access_token'],
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers
        ]);

        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return false;
        }

        return json_decode($response, true);
    }

    private function ProcessZoneData(array $data)
    {
        if (isset($data['roomTemp'])) {
            $this->SetValue('Temperature', (float)$data['roomTemp']);
        }

        if (isset($data['setpoint'])) {
            $this->SetValue('TargetTemperature', (float)$data['setpoint']);
        }

        if (isset($data['on'])) {
            $this->SetValue('Power', (bool)$data['on']);
        }

        if (isset($data['mode'])) {
            $this->SetValue('Mode', (int)$data['mode']);
        }

        if (isset($data['fanSpeed'])) {
            $this->SetValue('FanSpeed', (int)$data['fanSpeed']);
        }

        if (isset($data['humidity'])) {
            $this->SetValue('Humidity', (float)$data['humidity']);
        }
    }
}