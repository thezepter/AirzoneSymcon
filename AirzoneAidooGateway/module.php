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
                $instanceID = $this->GetZoneInstanceID($system['SystemID'], $system['ZoneID']);
                $values[] = [
                    'SystemID' => $system['SystemID'],
                    'SystemName' => $system['SystemName'],
                    'ZoneID' => $system['ZoneID'],
                    'ZoneName' => $system['ZoneName'],
                    'Status' => $instanceID > 0 ? 'Created' : 'Not Created',
                    'instanceID' => $instanceID,
                    'create' => [
                        'moduleID' => '{B8E5A8F1-9C2D-4E3F-8A7B-1D5C9E4F2A8B}',
                        'name' => $system['ZoneName'],
                        'configuration' => [
                            'GatewayIP' => $this->ReadPropertyString('GatewayIP'),
                            'SystemID' => $system['SystemID'],
                            'ZoneID' => $system['ZoneID'],
                            'UseLocalConnection' => true
                        ]
                    ]
                ];
            }
            
            $form['actions'][1]['values'] = $values;
        }

        return json_encode($form);
    }

    public function GetSystems(): array
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $systems = [];
        
        // For Aidoo Pro: Get all zones for system 1
        for ($zoneId = 1; $zoneId <= 4; $zoneId++) {
            $endpoint = "http://{$gatewayIP}:3000/api/v1/hvac?systemid=1&zoneid={$zoneId}";
            $data = $this->makeApiCall($endpoint);
            
            if ($data !== false && isset($data['data']) && !empty($data['data'])) {
                $zoneData = $data['data'][0];
                $systems[] = [
                    'SystemID' => '1',
                    'SystemName' => 'Airzone System',
                    'ZoneID' => (string)$zoneData['zoneID'],
                    'ZoneName' => $zoneData['name'] ?? "Zone {$zoneId}",
                    'Status' => 'Available',
                    'instanceID' => 0
                ];
                IPS_LogMessage('AirzoneGateway', "Found zone: " . ($zoneData['name'] ?? "Zone {$zoneId}"));
            } else {
                IPS_LogMessage('AirzoneGateway', "No data for zone {$zoneId} at endpoint: $endpoint");
            }
        }
        
        if (empty($systems)) {
            IPS_LogMessage('AirzoneGateway', "No zones found for gateway IP: $gatewayIP");
        }
        
        return $systems;
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
        // In a real IP-Symcon environment, this would check for existing instances
        // For development purposes, we return 0 (no existing instance)
        return 0;
    }
}
