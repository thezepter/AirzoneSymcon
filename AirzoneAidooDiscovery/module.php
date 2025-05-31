<?php

declare(strict_types=1);

class AirzoneAidooDiscovery extends IPSModule
{
    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        $this->RegisterPropertyString('DiscoveryIP', '192.168.1.0/24');
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
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        
        $devices = $this->DiscoverDevices();
        
        if (!empty($devices)) {
            $values = [];
            foreach ($devices as $device) {
                $instanceID = $this->GetInstanceIDByIP($device['ip']);
                $values[] = [
                    'IP' => $device['ip'],
                    'Name' => $device['name'],
                    'Model' => $device['model'],
                    'Status' => $instanceID > 0 ? 'Created' : 'Not Created',
                    'instanceID' => $instanceID,
                    'create' => [
                        'moduleID' => '{B8E5A8F1-9C2D-4E3F-8A7B-1D5C9E4F2A8B}',
                        'configuration' => [
                            'GatewayIP' => $device['ip'],
                            'UseLocalConnection' => true
                        ]
                    ]
                ];
            }
            
            $form['actions'][0]['values'] = $values;
        }

        return json_encode($form);
    }

    public function DiscoverDevices(): array
    {
        $discoveryIP = $this->ReadPropertyString('DiscoveryIP');
        $devices = [];

        // Parse CIDR notation
        if (strpos($discoveryIP, '/') !== false) {
            list($network, $cidr) = explode('/', $discoveryIP);
            $range = $this->cidrToRange($network, (int)$cidr);
        } else {
            // Single IP
            $range = [$discoveryIP];
        }

        foreach ($range as $ip) {
            $device = $this->CheckDevice($ip);
            if ($device !== false) {
                $devices[] = $device;
            }
        }

        return $devices;
    }

    private function CheckDevice(string $ip): array|false
    {
        $url = "http://{$ip}/api/v1/ping";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $data = json_decode($response, true);
            
            return [
                'ip' => $ip,
                'name' => $data['name'] ?? 'Airzone Gateway',
                'model' => $data['model'] ?? 'Aidoo',
                'version' => $data['version'] ?? 'Unknown'
            ];
        }

        return false;
    }

    private function cidrToRange(string $cidr, int $netmask): array
    {
        $range = [];
        $cidrLong = ip2long($cidr);
        $netmaskLong = (0xFFFFFFFF << (32 - $netmask)) & 0xFFFFFFFF;
        $networkLong = $cidrLong & $netmaskLong;
        $broadcastLong = $networkLong | (~$netmaskLong & 0xFFFFFFFF);

        for ($i = $networkLong + 1; $i < $broadcastLong; $i++) {
            $range[] = long2ip($i);
        }

        return $range;
    }

    private function GetInstanceIDByIP(string $ip): int
    {
        $instanceIDs = IPS_GetInstanceListByModuleID('{B8E5A8F1-9C2D-4E3F-8A7B-1D5C9E4F2A8B}');
        
        foreach ($instanceIDs as $instanceID) {
            $gatewayIP = IPS_GetProperty($instanceID, 'GatewayIP');
            if ($gatewayIP === $ip) {
                return $instanceID;
            }
        }

        return 0;
    }
}
