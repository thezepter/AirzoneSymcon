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
        $devices = [];
        
        // First try mDNS discovery for Airzone devices
        $mdnsDevices = $this->DiscoverViaMDNS();
        $devices = array_merge($devices, $mdnsDevices);
        
        // Test your known gateway
        if ($this->TestAirzoneDevice('192.168.2.61')) {
            $devices[] = [
                'ip' => '192.168.2.61',
                'status' => 'Found (Known Gateway)'
            ];
        }
        
        // Network scan as fallback
        $networkRange = $this->ReadPropertyString('NetworkRange');
        if (strpos($networkRange, '/') !== false) {
            list($network, $cidr) = explode('/', $networkRange);
            $base = substr($network, 0, strrpos($network, '.'));
            
            // Scan limited range for performance
            for ($i = 1; $i <= 100; $i++) {
                $ip = $base . '.' . $i;
                if ($ip !== '192.168.2.61' && $this->TestAirzoneDevice($ip)) {
                    $devices[] = [
                        'ip' => $ip,
                        'status' => 'Found (Network Scan)'
                    ];
                }
            }
        }
        
        return $devices;
    }

    private function DiscoverViaMDNS(): array
    {
        $devices = [];
        
        // Use avahi-browse to find _http._tcp.local services
        $command = 'timeout 5 avahi-browse -t -r _http._tcp 2>/dev/null';
        $output = [];
        exec($command, $output);
        
        foreach ($output as $line) {
            // Look for Airzone device patterns: AZP, AZPFAN, AZW5GR, AZWS
            if (preg_match('/(AZP|AZPFAN|AZW5GR|AZWS)[A-Z0-9]+\.local/', $line, $matches)) {
                $hostname = $matches[0];
                
                // Resolve .local hostname to IP
                $ip = $this->ResolveHostname($hostname);
                if ($ip && $this->TestAirzoneDevice($ip)) {
                    $devices[] = [
                        'ip' => $ip,
                        'status' => 'Found (mDNS: ' . $hostname . ')'
                    ];
                }
            }
        }
        
        return $devices;
    }

    private function ResolveHostname(string $hostname): string|false
    {
        // Try to resolve .local hostname to IP
        $ip = gethostbyname($hostname);
        
        // gethostbyname returns the hostname if resolution fails
        if ($ip === $hostname) {
            return false;
        }
        
        return $ip;
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
