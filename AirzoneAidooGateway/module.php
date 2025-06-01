<?php

declare(strict_types=1);

class AirzoneAidooGateway extends IPSModule
{
    public function Create()
    {
        parent::Create();
        
        // Properties
        $this->RegisterPropertyString('GatewayIP', '192.168.2.61');
        $this->RegisterPropertyInteger('UpdateInterval', 30);
        
        // Timer
        $this->RegisterTimer('UpdateTimer', 0, 'AIRZONEGATEWAY_UpdateZones($id);');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        if (empty($gatewayIP)) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        
        // Test connection
        if ($this->TestConnection()) {
            $this->SetStatus(IS_ACTIVE);
            $this->CreateZoneVariables();
            $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);
        } else {
            $this->SetStatus(IS_EBASE + 1); // Connection error
        }
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'GatewayIP',
                    'caption' => 'Gateway IP Address'
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'UpdateInterval',
                    'caption' => 'Update Interval (seconds)',
                    'minimum' => 10,
                    'maximum' => 300
                ]
            ],
            'actions' => [
                [
                    'type' => 'Button',
                    'caption' => 'Test Connection',
                    'onClick' => 'AIRZONEGATEWAY_TestConnection($id);'
                ],
                [
                    'type' => 'Button',
                    'caption' => 'Update Zones',
                    'onClick' => 'AIRZONEGATEWAY_UpdateZones($id);'
                ]
            ]
        ]);
    }

    public function TestConnection(): bool
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

    public function UpdateZones()
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        // Update all 4 zones
        for ($zone = 1; $zone <= 4; $zone++) {
            $this->UpdateZoneData($zone);
        }
    }

    private function UpdateZoneData(int $zoneID)
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $url = "http://{$gatewayIP}:3000/api/v1/hvac?systemid=1&zoneid={$zoneID}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response !== false) {
            $data = json_decode($response, true);
            if (isset($data['data'])) {
                $this->UpdateZoneVariables($zoneID, $data['data']);
            }
        }
    }

    private function UpdateZoneVariables(int $zoneID, array $data)
    {
        $zoneName = $this->GetZoneName($zoneID);
        
        // Update temperature variables
        if (isset($data['roomTemp'])) {
            $this->SetValue("Zone{$zoneID}_Temperature", (float)$data['roomTemp']);
        }
        
        if (isset($data['setpoint'])) {
            $this->SetValue("Zone{$zoneID}_Setpoint", (float)$data['setpoint']);
        }
        
        if (isset($data['on'])) {
            $this->SetValue("Zone{$zoneID}_Power", (bool)$data['on']);
        }
        
        if (isset($data['mode'])) {
            $this->SetValue("Zone{$zoneID}_Mode", (int)$data['mode']);
        }
        
        if (isset($data['fanSpeed'])) {
            $this->SetValue("Zone{$zoneID}_FanSpeed", (int)$data['fanSpeed']);
        }
    }

    private function CreateZoneVariables()
    {
        $zoneNames = [1 => 'Badezimmer', 2 => 'Diele', 3 => 'Gata', 4 => 'Oma'];
        
        foreach ($zoneNames as $zoneID => $zoneName) {
            // Temperature (read-only)
            $this->RegisterVariableFloat("Zone{$zoneID}_Temperature", "{$zoneName} - Temperature", "~Temperature", $zoneID * 10 + 1);
            
            // Setpoint (editable)
            $this->RegisterVariableFloat("Zone{$zoneID}_Setpoint", "{$zoneName} - Target Temperature", "~Temperature", $zoneID * 10 + 2);
            $this->EnableAction("Zone{$zoneID}_Setpoint");
            
            // Power (editable)
            $this->RegisterVariableBoolean("Zone{$zoneID}_Power", "{$zoneName} - Power", "~Switch", $zoneID * 10 + 3);
            $this->EnableAction("Zone{$zoneID}_Power");
            
            // Mode (editable)
            if (!IPS_VariableProfileExists('Airzone.Mode')) {
                IPS_CreateVariableProfile('Airzone.Mode', 1);
                IPS_SetVariableProfileAssociation('Airzone.Mode', 0, 'Stop', '', 0x808080);
                IPS_SetVariableProfileAssociation('Airzone.Mode', 1, 'Cool', '', 0x0080FF);
                IPS_SetVariableProfileAssociation('Airzone.Mode', 2, 'Heat', '', 0xFF4000);
                IPS_SetVariableProfileAssociation('Airzone.Mode', 3, 'Fan', '', 0x00FF00);
                IPS_SetVariableProfileAssociation('Airzone.Mode', 4, 'Dry', '', 0xFFFF00);
                IPS_SetVariableProfileAssociation('Airzone.Mode', 5, 'Auto', '', 0x8000FF);
            }
            $this->RegisterVariableInteger("Zone{$zoneID}_Mode", "{$zoneName} - Mode", "Airzone.Mode", $zoneID * 10 + 4);
            $this->EnableAction("Zone{$zoneID}_Mode");
            
            // Fan Speed (editable)
            if (!IPS_VariableProfileExists('Airzone.FanSpeed')) {
                IPS_CreateVariableProfile('Airzone.FanSpeed', 1);
                IPS_SetVariableProfileAssociation('Airzone.FanSpeed', 0, 'Auto', '', 0x808080);
                IPS_SetVariableProfileAssociation('Airzone.FanSpeed', 1, 'Low', '', 0x00FF00);
                IPS_SetVariableProfileAssociation('Airzone.FanSpeed', 2, 'Medium', '', 0xFFFF00);
                IPS_SetVariableProfileAssociation('Airzone.FanSpeed', 3, 'High', '', 0xFF4000);
            }
            $this->RegisterVariableInteger("Zone{$zoneID}_FanSpeed", "{$zoneName} - Fan Speed", "Airzone.FanSpeed", $zoneID * 10 + 5);
            $this->EnableAction("Zone{$zoneID}_FanSpeed");
        }
    }

    public function RequestAction($Ident, $Value)
    {
        // Extract zone ID from variable identifier
        if (preg_match('/Zone(\d+)_(.+)/', $Ident, $matches)) {
            $zoneID = (int)$matches[1];
            $parameter = $matches[2];
            
            $this->SetZoneParameter($zoneID, $parameter, $Value);
            $this->SetValue($Ident, $Value);
        }
    }

    private function SetZoneParameter(int $zoneID, string $parameter, $value)
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $url = "http://{$gatewayIP}:3000/api/v1/hvac";
        
        $data = [
            'systemID' => 1,
            'zoneID' => $zoneID
        ];
        
        switch ($parameter) {
            case 'Setpoint':
                $data['setpoint'] = (float)$value;
                break;
            case 'Power':
                $data['on'] = (bool)$value;
                break;
            case 'Mode':
                $data['mode'] = (int)$value;
                break;
            case 'FanSpeed':
                $data['fanSpeed'] = (int)$value;
                break;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 5
        ]);
        
        curl_exec($ch);
        curl_close($ch);
        
        // Update zone data after change
        $this->UpdateZoneData($zoneID);
    }

    private function GetZoneName(int $zoneID): string
    {
        $names = [1 => 'Badezimmer', 2 => 'Diele', 3 => 'Gata', 4 => 'Oma'];
        return $names[$zoneID] ?? "Zone {$zoneID}";
    }
}