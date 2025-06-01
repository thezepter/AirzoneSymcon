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
                    'caption' => 'Search Zones',
                    'onClick' => 'AIRZONEGATEWAY_SearchZones($id);'
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

    public function SearchZones()
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $zoneNames = [1 => 'Badezimmer', 2 => 'Diele', 3 => 'Gata', 4 => 'Oma'];
        
        foreach ($zoneNames as $zoneID => $zoneName) {
            // Check if zone exists
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
                $existingInstanceID = $this->GetZoneInstanceID('1', (string)$zoneID);
                
                if ($existingInstanceID === 0) {
                    // Create new zone instance
                    $instanceID = IPS_CreateInstance('{B8E5A8F1-9C2D-4E3F-8A7B-1D5C9E4F2A8B}');
                    
                    if ($instanceID > 0) {
                        IPS_SetName($instanceID, $zoneName);
                        IPS_SetProperty($instanceID, 'GatewayIP', $gatewayIP);
                        IPS_SetProperty($instanceID, 'SystemID', '1');
                        IPS_SetProperty($instanceID, 'ZoneID', (string)$zoneID);
                        IPS_SetProperty($instanceID, 'UseLocalConnection', true);
                        IPS_ApplyChanges($instanceID);
                        
                        echo "Zone {$zoneName} (ID: {$zoneID}) created\n";
                    }
                } else {
                    echo "Zone {$zoneName} (ID: {$zoneID}) already exists\n";
                }
            }
        }
    }

    private function GetZoneInstanceID(string $systemID, string $zoneID): int
    {
        $instances = IPS_GetInstanceListByModuleID('{B8E5A8F1-9C2D-4E3F-8A7B-1D5C9E4F2A8B}');
        
        foreach ($instances as $instanceID) {
            try {
                $instanceSystemID = IPS_GetProperty($instanceID, 'SystemID');
                $instanceZoneID = IPS_GetProperty($instanceID, 'ZoneID');
                
                if ($instanceSystemID === $systemID && $instanceZoneID === $zoneID) {
                    return $instanceID;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return 0;
    }
}