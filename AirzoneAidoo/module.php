<?php

declare(strict_types=1);

class AirzoneAidoo extends IPSModule
{
    // API Endpoints
    const API_LOCAL_BASE = 'http://%s:3000/api/v1';
    const API_CLOUD_BASE = 'https://www.airzonecloud.com/users/sign_in';
    
    // Status codes
    const STATUS_INST_IP_IS_EMPTY = 201;
    const STATUS_INST_IP_IS_INVALID = 202;
    const STATUS_INST_CONNECTION_TIMEOUT = 203;
    const STATUS_INST_AUTHENTICATION_FAILED = 204;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        $this->RegisterPropertyString('GatewayIP', '');
        $this->RegisterPropertyString('CloudUsername', '');
        $this->RegisterPropertyString('CloudPassword', '');
        $this->RegisterPropertyBoolean('UseLocalConnection', true);
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyString('SystemID', '');
        $this->RegisterPropertyString('ZoneID', '');

        // Profiles
        $this->CreateProfiles();

        // Variables
        $this->RegisterVariableBoolean('Power', $this->Translate('Power'), '~Switch', 1);
        $this->EnableAction('Power');
        
        $this->RegisterVariableFloat('Temperature', $this->Translate('Temperature'), '~Temperature', 2);
        $this->RegisterVariableFloat('SetTemperature', $this->Translate('Set Temperature'), '~Temperature', 3);
        $this->EnableAction('SetTemperature');
        
        $this->RegisterVariableInteger('Mode', $this->Translate('Mode'), 'AIRZONE.Mode', 4);
        $this->EnableAction('Mode');
        
        $this->RegisterVariableInteger('FanSpeed', $this->Translate('Fan Speed'), 'AIRZONE.FanSpeed', 5);
        $this->EnableAction('FanSpeed');
        
        $this->RegisterVariableFloat('Humidity', $this->Translate('Humidity'), '~Humidity.F', 6);

        // Timer
        $this->RegisterTimer('UpdateTimer', 0, 'IPS_RequestAction($_IPS[\'TARGET\'], "Update", "");');
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

        // Validate configuration
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');
        
        if ($useLocal && empty($gatewayIP)) {
            $this->SetStatus(self::STATUS_INST_IP_IS_EMPTY);
            return;
        }

        if ($useLocal && !filter_var($gatewayIP, FILTER_VALIDATE_IP)) {
            $this->SetStatus(self::STATUS_INST_IP_IS_INVALID);
            return;
        }

        // Set update timer
        $interval = $this->ReadPropertyInteger('UpdateInterval');
        if ($interval > 0) {
            $this->SetTimerInterval('UpdateTimer', $interval * 1000);
        } else {
            $this->SetTimerInterval('UpdateTimer', 0);
        }

        // Set status to active - connection will be tested when update timer runs
        $this->SetStatus(IS_ACTIVE);
    }

    public function RequestAction($Ident, $Value)
    {
        error_log("RequestAction called: Ident={$Ident}, Value={$Value}");
        
        switch ($Ident) {
            case 'Power':
                error_log("Calling SetPower with value: {$Value}");
                $result = $this->SetPower($Value);
                error_log("SetPower result: " . ($result ? 'success' : 'failed'));
                break;
            case 'SetTemperature':
                error_log("Calling SetTemperature with value: {$Value}");
                $result = $this->SetTemperature($Value);
                error_log("SetTemperature result: " . ($result ? 'success' : 'failed'));
                break;
            case 'Mode':
                error_log("RequestAction Mode: Eingehender Wert = {$Value}");
                $result = $this->SetMode($Value);
                error_log("SetMode result: " . ($result ? 'success' : 'failed'));
                break;
            case 'FanSpeed':
                error_log("Calling SetFanSpeed with value: {$Value}");
                $result = $this->SetFanSpeed($Value);
                error_log("SetFanSpeed result: " . ($result ? 'success' : 'failed'));
                break;
            case 'Update':
                $this->Update();
                break;
            default:
                error_log("Unknown Ident: {$Ident}");
                throw new Exception('Invalid Ident');
        }
    }

    public function Update()
    {
        $data = $this->GetSystemData();
        if ($data !== false) {
            $this->UpdateVariables($data);
        }
    }

    public function TestControl()
    {
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        // API-URL
        $apiUrl = "http://{$gatewayIP}:3000/api/v1/hvac";

        // Daten für die PUT-Anfrage (genau wie Ihr Script)
        $putData = array(
            "systemID" => (int)$systemID,
            "zoneID" => (int)$zoneID,
            "on" => 1 // 1, um die Klimaanlage einzuschalten
        );

        // JSON-Daten erstellen
        $jsonData = json_encode($putData);

        // Setup cURL für die PUT-Anfrage (genau wie Ihr Script)
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'PUT', // Verwende die PUT-Methode
            CURLOPT_POSTFIELDS => $jsonData
        ));

        // Send the PUT request
        $response = curl_exec($ch);

        // Check for errors
        if ($response === FALSE) {
            echo "cURL Error: " . curl_error($ch) . "\n";
            curl_close($ch);
            return false;
        }

        // Close the cURL handler
        curl_close($ch);

        // Ausgabe der Antwort (genau wie Ihr Script)
        echo "API-Antwort:\n";
        echo $response;
        
        return true;
    }

    public function TestPowerOn()
    {
        echo "Test: Zone einschalten<br>";
        return $this->SetPower(true);
    }

    public function TestModeCool()
    {
        echo "Test: Modus auf Kühlen setzen<br>";
        return $this->TestMode(2);
    }

    public function TestModeHeat()
    {
        echo "Test: Modus auf Heizen setzen<br>";
        return $this->TestMode(3);
    }

    public function TestModeFan()
    {
        echo "Test: Modus auf Lüften setzen<br>";
        return $this->TestMode(4);
    }

    public function TestModeAuto()
    {
        echo "Test: Modus auf Automatik setzen<br>";
        return $this->TestMode(7);
    }

    public function TestPowerOff()
    {
        echo "Test: Zone ausschalten<br>";
        
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        // API-URL
        $apiUrl = "http://{$gatewayIP}:3000/api/v1/hvac";

        // Daten für die PUT-Anfrage
        $putData = array(
            "systemID" => (int)$systemID,
            "zoneID" => (int)$zoneID,
            "on" => 0 // 0, um die Klimaanlage auszuschalten
        );

        echo "Sende: " . json_encode($putData) . "<br>";

        // JSON-Daten erstellen
        $jsonData = json_encode($putData);

        // Setup cURL für die PUT-Anfrage
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData
        ));

        // Send the PUT request
        $response = curl_exec($ch);

        // Check for errors
        if ($response === FALSE) {
            echo "cURL Error: " . curl_error($ch) . "<br>";
            curl_close($ch);
            return false;
        }

        // Close the cURL handler
        curl_close($ch);

        // Ausgabe der Antwort
        echo "API-Antwort: " . $response . "<br>";
        
        // Auch über das Modul versuchen
        echo "Über Modul: ";
        $result = $this->SetPower(false);
        echo ($result ? "Erfolg" : "Fehler") . "<br>";
        
        return true;
    }

    public function TestSetTemp($temperature = 22.0)
    {
        echo "Test: Temperatur auf {$temperature}°C setzen<br>";
        return $this->SetTemperature($temperature);
    }

    public function TestMode($mode = 2)
    {
        $modeNames = [
            1 => 'Stop',
            2 => 'Kühlen', 
            3 => 'Heizen',
            4 => 'Lüften',
            5 => 'Entfeuchten',
            7 => 'Automatik'
        ];
        
        $modeName = isset($modeNames[$mode]) ? $modeNames[$mode] : 'Unbekannt';
        echo "Test: Modus auf {$modeName} (#{$mode}) setzen<br>";
        
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        $modeMapping = [
            1 => 1,  // Stop
            2 => 2,  // Cooling
            3 => 3,  // Heating
            4 => 4,  // Fan
            5 => 5,  // Dry
            7 => 7   // Auto
        ];

        if (!isset($modeMapping[$mode])) {
            echo "Ungültiger Modus: {$mode}<br>";
            return false;
        }

        // API-URL
        $apiUrl = "http://{$gatewayIP}:3000/api/v1/hvac";

        // Daten für die PUT-Anfrage
        $putData = array(
            "systemID" => (int)$systemID,
            "zoneID" => (int)$zoneID,
            "mode" => $modeMapping[$mode]
        );

        echo "Sende an API: " . json_encode($putData) . "<br>";

        // JSON-Daten erstellen
        $jsonData = json_encode($putData);

        // Setup cURL für die PUT-Anfrage
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData
        ));

        // Send the PUT request
        $response = curl_exec($ch);

        // Check for errors
        if ($response === FALSE) {
            echo "cURL Error: " . curl_error($ch) . "<br>";
            curl_close($ch);
            return false;
        }

        // Close the cURL handler
        curl_close($ch);

        echo "API-Antwort: " . $response . "<br>";

        // Prüfe Antwort
        $responseData = json_decode($response, true);
        if ($responseData) {
            if (isset($responseData['data'][0])) {
                echo "Erfolg - Variable wird aktualisiert<br>";
                $this->SetValue('Mode', $mode);
                return true;
            } else {
                echo "Unerwartete API-Antwort<br>";
                return false;
            }
        } else {
            echo "Keine gültige JSON-Antwort<br>";
            return false;
        }
    }



    public function SetPower(bool $power)
    {
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        // API-URL
        $apiUrl = "http://{$gatewayIP}:3000/api/v1/hvac";

        // Daten für die PUT-Anfrage
        $putData = array(
            "systemID" => (int)$systemID,
            "zoneID" => (int)$zoneID,
            "on" => $power ? 1 : 0
        );

        // JSON-Daten erstellen
        $jsonData = json_encode($putData);

        // Setup cURL für die PUT-Anfrage
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData
        ));

        // Send the PUT request
        $response = curl_exec($ch);

        // Check for errors
        if ($response === FALSE) {
            error_log("SetPower cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        // Close the cURL handler
        curl_close($ch);

        // Prüfe Antwort und aktualisiere Variable
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['data'][0])) {
            $this->SetValue('Power', $power);
            return true;
        }
        
        return false;
    }

    public function SetTemperature(float $temperature)
    {
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        // API-URL
        $apiUrl = "http://{$gatewayIP}:3000/api/v1/hvac";

        // Daten für die PUT-Anfrage
        $putData = array(
            "systemID" => (int)$systemID,
            "zoneID" => (int)$zoneID,
            "setpoint" => $temperature
        );

        // JSON-Daten erstellen
        $jsonData = json_encode($putData);

        // Setup cURL für die PUT-Anfrage
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData
        ));

        // Send the PUT request
        $response = curl_exec($ch);

        // Check for errors
        if ($response === FALSE) {
            error_log("SetTemperature cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        // Close the cURL handler
        curl_close($ch);

        // Prüfe Antwort und aktualisiere Variable
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['data'][0])) {
            $this->SetValue('SetTemperature', $temperature);
            return true;
        }
        
        return false;
    }

    public function SetMode(int $mode)
    {
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        // Korrigierte Zuordnung: IP-Symcon Profile Wert → API Wert
        $modeMapping = [
            0 => 1,  // Stop
            1 => 2,  // Cooling
            2 => 3,  // Heating
            3 => 4,  // Fan
            4 => 5,  // Dry
            5 => 7   // Auto
        ];

        if (!isset($modeMapping[$mode])) {
            error_log("SetMode: Ungültiger Modus {$mode}");
            return false;
        }

        // API-URL
        $apiUrl = "http://{$gatewayIP}:3000/api/v1/hvac";

        // Daten für die PUT-Anfrage
        $putData = array(
            "systemID" => (int)$systemID,
            "zoneID" => (int)$zoneID,
            "mode" => $modeMapping[$mode]
        );

        error_log("SetMode: Sende " . json_encode($putData));

        // JSON-Daten erstellen
        $jsonData = json_encode($putData);

        // Setup cURL für die PUT-Anfrage
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData
        ));

        // Send the PUT request
        $response = curl_exec($ch);

        // Check for errors
        if ($response === FALSE) {
            error_log("SetMode cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        // Close the cURL handler
        curl_close($ch);

        error_log("SetMode: API-Antwort " . $response);

        // Prüfe Antwort und aktualisiere Variable
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['data'][0])) {
            $this->SetValue('Mode', $mode);
            error_log("SetMode: Erfolgreich, Variable auf {$mode} gesetzt");
            return true;
        }
        
        error_log("SetMode: Fehler in API-Antwort");
        return false;
    }

    public function SetFanSpeed(int $speed)
    {
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        
        // API-URL
        $apiUrl = "http://{$gatewayIP}:3000/api/v1/hvac";

        // Daten für die PUT-Anfrage
        $putData = array(
            "systemID" => (int)$systemID,
            "zoneID" => (int)$zoneID,
            "fanspeed" => $speed
        );

        // JSON-Daten erstellen
        $jsonData = json_encode($putData);

        // Setup cURL für die PUT-Anfrage
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData
        ));

        // Send the PUT request
        $response = curl_exec($ch);

        // Check for errors
        if ($response === FALSE) {
            error_log("SetFanSpeed cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        // Close the cURL handler
        curl_close($ch);

        // Prüfe Antwort und aktualisiere Variable
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['data'][0])) {
            $this->SetValue('FanSpeed', $speed);
            return true;
        }
        
        return false;
    }

    private function CreateProfiles()
    {
        // Mode profile
        if (!IPS_VariableProfileExists('AIRZONE.Mode')) {
            IPS_CreateVariableProfile('AIRZONE.Mode', 1);
            IPS_SetVariableProfileText('AIRZONE.Mode', '', '');
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 0, 'Stop', '', 0x808080);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 1, 'Kühlen', '', 0x0080FF);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 2, 'Heizen', '', 0xFF8000);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 3, 'Lüften', '', 0x80FF80);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 4, 'Entfeuchten', '', 0xFFFF00);
            IPS_SetVariableProfileAssociation('AIRZONE.Mode', 5, 'Automatik', '', 0x8080FF);
        }

        // Fan Speed profile
        if (!IPS_VariableProfileExists('AIRZONE.FanSpeed')) {
            IPS_CreateVariableProfile('AIRZONE.FanSpeed', 1);
            IPS_SetVariableProfileText('AIRZONE.FanSpeed', '', '');
            IPS_SetVariableProfileAssociation('AIRZONE.FanSpeed', 0, $this->Translate('Auto'), '', 0x808080);
            IPS_SetVariableProfileAssociation('AIRZONE.FanSpeed', 1, $this->Translate('Low'), '', 0x80FF80);
            IPS_SetVariableProfileAssociation('AIRZONE.FanSpeed', 2, $this->Translate('Medium'), '', 0xFFFF00);
            IPS_SetVariableProfileAssociation('AIRZONE.FanSpeed', 3, $this->Translate('High'), '', 0xFF8000);
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
        $url = sprintf(self::API_LOCAL_BASE, $gatewayIP) . '/ping';
        
        $response = $this->SendHTTPRequest('GET', $url);
        return $response !== false;
    }

    private function TestCloudConnection(): bool
    {
        $username = $this->ReadPropertyString('CloudUsername');
        $password = $this->ReadPropertyString('CloudPassword');
        
        if (empty($username) || empty($password)) {
            return false;
        }

        // Cloud authentication would be implemented here
        // For now, return true if credentials are provided
        return true;
    }

    private function GetSystemData()
    {
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');
        
        if ($useLocal) {
            return $this->GetLocalSystemData();
        } else {
            return $this->GetCloudSystemData();
        }
    }

    private function GetLocalSystemData()
    {
        $gatewayIP = $this->ReadPropertyString('GatewayIP');
        $systemID = $this->ReadPropertyString('SystemID');
        $zoneID = $this->ReadPropertyString('ZoneID');
        
        // Direkte cURL-Anfrage für Update (GET)
        $apiUrl = "http://{$gatewayIP}:3000/api/v1/hvac?systemid={$systemID}&zoneid={$zoneID}";
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'GET'
        ));

        $response = curl_exec($ch);
        
        if ($response === FALSE) {
            error_log("GetLocalSystemData cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['data']) && !empty($responseData['data'])) {
            return $responseData['data'][0];
        }
        
        return false;
    }

    private function GetCloudSystemData()
    {
        // Cloud API implementation would go here
        // This is a placeholder for cloud functionality
        return false;
    }

    private function SendCommand(string $method, string $endpoint, array $data = null)
    {
        $useLocal = $this->ReadPropertyBoolean('UseLocalConnection');
        
        if ($useLocal) {
            $gatewayIP = $this->ReadPropertyString('GatewayIP');
            $url = sprintf(self::API_LOCAL_BASE, $gatewayIP) . $endpoint;
            
            return $this->SendHTTPRequest($method, $url, $data);
        } else {
            // Cloud command implementation would go here
            return false;
        }
    }

    private function SendHTTPRequest(string $method, string $url, array $data = null)
    {
        // Setup cURL genau wie im funktionierenden TestControl
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data ? json_encode($data) : null
        ));

        // Send the request
        $response = curl_exec($ch);

        // Check for errors
        if ($response === FALSE) {
            error_log("Airzone API cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        // Close the cURL handler
        curl_close($ch);

        // Debug logging
        error_log("Airzone API Call: {$method} {$url}, Data: " . json_encode($data) . ", Response: {$response}");

        return json_decode($response, true);
    }

    private function UpdateVariables(array $data)
    {
        // Aidoo Pro data format mapping
        if (isset($data['on'])) {
            $this->SetValue('Power', (bool)$data['on']);
        }
        
        if (isset($data['roomTemp'])) {
            $this->SetValue('Temperature', (float)$data['roomTemp']);
        }
        
        if (isset($data['setpoint'])) {
            $this->SetValue('SetTemperature', (float)$data['setpoint']);
        }
        
        if (isset($data['mode'])) {
            // Aidoo Pro mode mapping: 1=Stop, 2=Cool, 3=Heat, 4=Fan, 5=Dry, 7=Auto
            $modeMapping = [
                1 => 0, // Stop
                2 => 1, // Cool  
                3 => 2, // Heat
                4 => 3, // Fan
                5 => 4, // Dry
                7 => 5  // Auto
            ];
            
            if (isset($modeMapping[$data['mode']])) {
                $this->SetValue('Mode', $modeMapping[$data['mode']]);
            }
        }
        
        if (isset($data['speed'])) {
            $this->SetValue('FanSpeed', (int)$data['speed']);
        }
        
        if (isset($data['humidity'])) {
            $this->SetValue('Humidity', (float)$data['humidity']);
        }
    }
}
