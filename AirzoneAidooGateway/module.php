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
        $url = "http://{$gatewayIP}/api/v1/systems";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $data = json_decode($response, true);
            return $data['systems'] ?? [];
        }

        return [];
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
