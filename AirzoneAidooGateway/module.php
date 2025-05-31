<?php

declare(strict_types=1);

class AirzoneAidooGateway extends IPSModule
{
    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        $this->RegisterPropertyString('GatewayIP', '');
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        
        // Timer
        $this->RegisterTimer('UpdateTimer', 0, 'AIRZONEGATEWAY_GetSystems($_IPS[\'TARGET\']);');
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

        // Validate IP
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        if (empty($gatewayIP) || !filter_var($gatewayIP, FILTER_VALIDATE_IP)) {
            $this->SetStatus(201); // Invalid IP
            return;
        }

        // Set timer
        $interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetTimerInterval('UpdateTimer', $interval * 1000);

        $this->SetStatus(IS_ACTIVE);
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        
        $systems = $this->GetSystems();
        
        if (!empty($systems)) {
            $values = [];
            foreach ($systems as $system) {
                foreach ($system['zones'] as $zone) {
                    $instanceID = $this->GetZoneInstanceID($system['systemID'], $zone['zoneID']);
                    $values[] = [
                        'SystemID' => $system['systemID'],
                        'SystemName' => $system['name'],
                        'ZoneID' => $zone['zoneID'],
                        'ZoneName' => $zone['name'],
                        'Status' => $instanceID > 0 ? 'Created' : 'Not Created',
                        'instanceID' => $instanceID,
                        'create' => [
                            'moduleID' => '{B8E5A8F1-9C2D-4E3F-8A7B-1D5C9E4F2A8B}',
                            'configuration' => [
                                'GatewayIP' => $this->ReadPropertyString('GatewayIP'),
                                'SystemID' => $system['systemID'],
                                'ZoneID' => $zone['zoneID'],
                                'UseLocalConnection' => true
                            ]
                        ]
                    ];
                }
            }
            
            $form['actions'][1]['values'] = $values;
        }

        return json_encode($form);
    }

    public function GetSystems(): array
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        // Based on Home Assistant implementation, try multiple endpoints
        $endpoints = [
            "http://{$gatewayIP}:3000/api/v1/hvac",
            "http://{$gatewayIP}:3000/api/v1/systems", 
            "http://{$gatewayIP}:3000/api/v1/zones",
            "http://{$gatewayIP}:3000/api/v1/status"
        ];
        
        foreach ($endpoints as $endpoint) {
            $data = $this->makeApiCall($endpoint);
            if ($data !== false) {
                IPS_LogMessage('AirzoneGateway', "Success with endpoint: $endpoint");
                return $this->parseSystemData($data, $endpoint);
            }
        }
        
        IPS_LogMessage('AirzoneGateway', "All endpoints failed for IP: $gatewayIP");
        return [];
    }
    
    private function parseSystemData(array $data, string $endpoint): array
    {
        // Parse different response formats based on endpoint
        if (strpos($endpoint, '/hvac') !== false && isset($data['data'])) {
            return $this->parseHvacData($data['data']);
        }
        
        if (strpos($endpoint, '/systems') !== false && isset($data['systems'])) {
            return $data['systems'];
        }
        
        if (strpos($endpoint, '/zones') !== false && isset($data['zones'])) {
            return $this->parseZonesData($data['zones']);
        }
        
        if (strpos($endpoint, '/status') !== false) {
            return $this->parseStatusData($data);
        }
        
        // Default fallback
        return [
            [
                'systemID' => '1',
                'name' => 'Airzone System',
                'zones' => [
                    [
                        'zoneID' => '1',
                        'name' => 'Zone 1'
                    ]
                ]
            ]
        ];
    }
    
    private function parseHvacData(array $hvacData): array
    {
        $systems = [];
        $systemId = 1;
        
        foreach ($hvacData as $zone) {
            $zoneId = $zone['zone_id'] ?? $zone['id'] ?? count($systems) + 1;
            $zoneName = $zone['name'] ?? "Zone $zoneId";
            
            $systems[] = [
                'systemID' => (string)$systemId,
                'name' => 'Airzone System',
                'zones' => [
                    [
                        'zoneID' => (string)$zoneId,
                        'name' => $zoneName
                    ]
                ]
            ];
        }
        
        return $systems;
    }
    
    private function parseZonesData(array $zonesData): array
    {
        return [
            [
                'systemID' => '1',
                'name' => 'Airzone System',
                'zones' => array_map(function($zone) {
                    return [
                        'zoneID' => (string)($zone['id'] ?? $zone['zone_id'] ?? '1'),
                        'name' => $zone['name'] ?? 'Zone ' . ($zone['id'] ?? '1')
                    ];
                }, $zonesData)
            ]
        ];
    }
    
    private function parseStatusData(array $statusData): array
    {
        return [
            [
                'systemID' => '1', 
                'name' => 'Airzone System',
                'zones' => [
                    [
                        'zoneID' => '1',
                        'name' => 'Main Zone'
                    ]
                ]
            ]
        ];
    }
    
    private function makeApiCall(string $url): array|false
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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            IPS_LogMessage('AirzoneGateway', "Curl Error for $url: $error");
            return false;
        }
        
        if ($httpCode !== 200) {
            IPS_LogMessage('AirzoneGateway', "HTTP Error: $httpCode for URL: $url Response: $response");
            return false;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            IPS_LogMessage('AirzoneGateway', "JSON Error: " . json_last_error_msg());
            return false;
        }
        
        return $data;
    }

    private function GetZoneInstanceID(string $systemID, string $zoneID): int
    {
        $instanceIDs = IPS_GetInstanceListByModuleID('{B8E5A8F1-9C2D-4E3F-8A7B-1D5C9E4F2A8B}');
        
        foreach ($instanceIDs as $instanceID) {
            $instanceSystemID = IPS_GetProperty($instanceID, 'SystemID');
            $instanceZoneID = IPS_GetProperty($instanceID, 'ZoneID');
            
            if ($instanceSystemID === $systemID && $instanceZoneID === $zoneID) {
                return $instanceID;
            }
        }

        return 0;
    }
}
