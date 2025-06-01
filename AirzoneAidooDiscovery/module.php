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
        $devices = [];
        
        // Try mDNS discovery first
        $mdnsDevices = $this->DiscoverViaMDNS();
        if (!empty($mdnsDevices)) {
            $devices = array_merge($devices, $mdnsDevices);
        }
        
        // Fallback: Network scan
        $networkDevices = $this->DiscoverViaNetwork();
        if (!empty($networkDevices)) {
            $devices = array_merge($devices, $networkDevices);
        }
        
        // Remove duplicates based on IP
        $uniqueDevices = [];
        $seenIPs = [];
        foreach ($devices as $device) {
            if (!in_array($device['ip'], $seenIPs)) {
                $uniqueDevices[] = $device;
                $seenIPs[] = $device['ip'];
            }
        }
        
        return $uniqueDevices;
    }

    private function DiscoverViaMDNS(): array
    {
        $devices = [];
        
        // Use avahi-browse for mDNS discovery
        $command = 'avahi-browse -t -r _http._tcp 2>/dev/null | grep -i airzone';
        $output = [];
        exec($command, $output);
        
        foreach ($output as $line) {
            if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
                $ip = $matches[1];
                $device = $this->CheckDevice($ip);
                if ($device !== false) {
                    $device['discovery_method'] = 'mDNS';
                    $devices[] = $device;
                }
            }
        }
        
        return $devices;
    }

    private function DiscoverViaNetwork(): array
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
                $device['discovery_method'] = 'Network Scan';
                $devices[] = $device;
            }
        }

        return $devices;
    }

    private function CheckDevice(string $ip): array|false
    {
        // Test multiple endpoints to detect Airzone devices
        $testUrls = [
            "http://{$ip}:3000/api/v1/hvac?systemid=1&zoneid=1",
            "http://{$ip}:3000/api/v1/ping",
            "http://{$ip}:3000/api/v1/info"
        ];
        
        foreach ($testUrls as $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json']
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $httpCode === 200) {
                $data = json_decode($response, true);
                
                // Check if this looks like an Airzone device
                if (isset($data['data']) || isset($data['roomTemp']) || isset($data['name'])) {
                    return [
                        'ip' => $ip,
                        'name' => $data['name'] ?? "Airzone Gateway ({$ip})",
                        'model' => $data['model'] ?? 'Aidoo Pro',
                        'version' => $data['version'] ?? 'Unknown'
                    ];
                }
            }
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
