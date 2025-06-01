<?php

declare(strict_types=1);

class AirzoneAidooDiscovery extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('NetworkRange', '192.168.2.0/24');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetStatus(IS_ACTIVE);
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'NetworkRange',
                    'caption' => 'Network Range (CIDR)'
                ]
            ],
            'actions' => [
                [
                    'type' => 'Button',
                    'caption' => 'Search for Airzone Devices',
                    'onClick' => 'AIRZONEDISCOVERY_SearchDevices($id);'
                ],
                [
                    'type' => 'List',
                    'name' => 'FoundDevices',
                    'caption' => 'Found Devices',
                    'columns' => [
                        ['caption' => 'IP Address', 'name' => 'ip', 'width' => '150px'],
                        ['caption' => 'Status', 'name' => 'status', 'width' => '100px']
                    ]
                ]
            ]
        ]);
    }

    public function SearchDevices()
    {
        $networkRange = $this->ReadPropertyString('NetworkRange');
        $devices = [];
        
        // Test your known gateway first
        $testIPs = ['192.168.2.61'];
        
        // Add network range IPs
        if (strpos($networkRange, '/') !== false) {
            list($network, $cidr) = explode('/', $networkRange);
            $base = substr($network, 0, strrpos($network, '.'));
            for ($i = 1; $i <= 254; $i++) {
                $testIPs[] = $base . '.' . $i;
            }
        }
        
        foreach ($testIPs as $ip) {
            if ($this->TestAirzoneDevice($ip)) {
                $devices[] = [
                    'ip' => $ip,
                    'status' => 'Found'
                ];
            }
        }
        
        return $devices;
    }

    private function TestAirzoneDevice(string $ip): bool
    {
        $url = "http://{$ip}:3000/api/v1/hvac?systemid=1&zoneid=1";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200 && $response !== false);
    }
}
