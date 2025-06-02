# Airzone Aidoo IP-Symcon Module

Ein IP-Symcon Modul zur Integration und Steuerung von Airzone Aidoo Klimaanlagen über die lokale API.

## Überblick

Dieses Modul ermöglicht die vollständige Integration von Airzone Aidoo Systemen in IP-Symcon. Es unterstützt die Steuerung mehrerer Zonen mit verschiedenen Klimafunktionen über die lokale Gateway-Verbindung.
Getestet mit einem: Airzone Webserver HUB Airzone Cloud Dual 2.4-5 GHz/Ethernet - ZX6WSPHUB

![azx6wsphub](https://github.com/user-attachments/assets/3fe8f464-3fdd-4145-ad7e-c374112d8a36)


## Funktionen

### Kernfunktionen
- **Zonenverwaltung**: Steuerung mehrerer Klimazonen
- **Temperaturkontrolle**: Solltemperatur einstellen und Ist-Temperatur überwachen
- **Betriebsmodi**: Stop, Kühlen, Heizen, Lüften, Entfeuchten, Automatik
- **Lüftersteuerung**: Verschiedene Geschwindigkeitsstufen
- **Automatische Updates**: Kontinuierliche Synchronisation alle 60 Sekunden

### Unterstützte Modi
- **Stop** (1): Anlage ausgeschaltet
- **Kühlen** (2): Kühlbetrieb
- **Heizen** (3): Heizbetrieb  
- **Lüften** (4): Nur Ventilation
- **Entfeuchten** (5): Entfeuchtungsmodus
- **Automatik** (7): Automatische Regelung

## Installation

### Voraussetzungen
- IP-Symcon 6.0 oder höher
- Airzone Aidoo Gateway mit lokaler API-Unterstützung
- Netzwerkverbindung zwischen IP-Symcon und Aidoo Gateway

### Module laden
1. In IP-Symcon über **Module Store** installieren oder
2. Git-Repository direkt einbinden:
   ```
   https://github.com/thezepter/AirzoneSymcon
   ```

### Gateway konfigurieren
1. **AirzoneAidooGateway** Instanz erstellen
2. Gateway IP-Adresse eingeben (z.B. `192.168.2.61`)
3. Port auf `3000` setzen (Standard)
4. Verbindung testen

![image](https://github.com/user-attachments/assets/58fd4f57-bad5-4085-b5dd-cd1c8a38b83e)


### Geräte hinzufügen
1. **AirzoneAidoo** Instanz für jede Zone erstellen
2. SystemID und ZoneID entsprechend der Aidoo-Konfiguration setzen
3. Gateway-Instanz als Eltern-Gateway zuweisen

## Konfiguration

### Gateway-Einstellungen
```
Gateway IP: 192.168.2.61
Port: 3000
Update-Intervall: 60 Sekunden
```

### Zone-Einstellungen
```
SystemID: 1 (Standard)
ZoneID: 1-4 (je nach Zone)
Gateway IP: [wird vom Gateway übernommen]
Debug-Modus: Optional aktivieren
```

## API-Referenz

### Grundlegende API-Aufrufe

#### Status abrufen
```http
GET http://192.168.2.61:3000/api/v1/hvac
```

#### Gerät ein-/ausschalten
```http
PUT http://192.168.2.61:3000/api/v1/hvac
Content-Type: application/json

{"systemID": 1, "zoneID": 1, "on": 1}
```

#### Temperatur einstellen
```http
PUT http://192.168.2.61:3000/api/v1/hvac
Content-Type: application/json

{"systemID": 1, "zoneID": 1, "setpoint": 22.5}
```

#### Modus wechseln
```http
PUT http://192.168.2.61:3000/api/v1/hvac
Content-Type: application/json

{"systemID": 1, "zoneID": 1, "mode": 4}
```

#### Lüftergeschwindigkeit
```http
PUT http://192.168.2.61:3000/api/v1/hvac
Content-Type: application/json

{"systemID": 1, "zoneID": 1, "fanSpeed": 3}
```

## Verfügbare Funktionen

### IP-Symcon Aktionen
- `SetPower(bool $on)`: Gerät ein-/ausschalten
- `SetTemperature(float $temp)`: Solltemperatur setzen
- `SetMode(int $mode)`: Betriebsmodus wechseln
- `SetFanSpeed(int $speed)`: Lüftergeschwindigkeit ändern
- `Update()`: Manuelle Aktualisierung der Werte

### Test-Funktionen (für Debugging)
- `TestModeStop()`: Test Stopp-Modus
- `TestModeCooling()`: Test Kühl-Modus
- `TestModeHeating()`: Test Heiz-Modus
- `TestModeFan()`: Test Lüfter-Modus
- `TestModeDry()`: Test Entfeuchtungs-Modus
- `TestModeAuto()`: Test Automatik-Modus

## Fehlerbehebung

### Häufige Probleme

#### Keine Verbindung zum Gateway
- Gateway IP-Adresse überprüfen
- Port 3000 erreichbar?
- Firewall-Einstellungen prüfen

#### Modi funktionieren nicht korrekt
- SystemID und ZoneID korrekt konfiguriert?
- Debug-Modus aktivieren für detaillierte Logs
- Test-Funktionen verwenden zur Diagnose

#### Automatische Updates funktionieren nicht
- Timer-Intervall überprüfen (Standard: 60 Sekunden)
- Gateway-Verbindung stabil?
- IP-Symcon Logs auf Fehler prüfen

### Debug-Modus
Debug-Logging kann in der Modulkonfiguration aktiviert werden. Dies schreibt detaillierte Informationen in das IP-Symcon Logbuch:

```
Airzone: API-Aufruf: PUT http://192.168.2.61:3000/api/v1/hvac
Airzone: Gesendet: {"systemID":1,"zoneID":1,"mode":4}
Airzone: Antwort: {"success":true,"data":{...}}
```

## Beispielkonfiguration

### Typische 4-Zonen-Anlage
```
Gateway: 192.168.2.61:3000

Zone 1 (Badezimmer): SystemID=1, ZoneID=1
Zone 2 (Diele): SystemID=1, ZoneID=2  
Zone 3 (Gata): SystemID=1, ZoneID=3
Zone 4 (Oma): SystemID=1, ZoneID=4
```

## Entwicklungsstand

### ✅ Fertiggestellt
- AirzoneAidoo Hauptmodul (Zonensteuerung)
- AirzoneAidooGateway (Gateway-Verwaltung)
- Vollständige API-Integration
- Automatische Updates
- Test-Funktionen
- Deutsche Lokalisierung

### 🚧 In Entwicklung
- AirzoneAidooDiscovery (Automatische Geräteerkennung)
- Erweiterte Fehlerbehandlung
- Zusätzliche Sensordaten

## Technische Details

### Modulstruktur
```
AirzoneAidoo/
├── module.php          # Hauptmodul (Zonensteuerung)
├── module.json         # Modulkonfiguration
├── form.json          # Konfigurationsformular
└── locale.json        # Deutsche Übersetzungen

AirzoneAidooGateway/
├── module.php          # Gateway-Verwaltung
├── module.json         # Gateway-Konfiguration
└── form.json          # Gateway-Formular

AirzoneAidooDiscovery/
├── module.php          # Geräte-Discovery (in Entwicklung)
├── module.json         # Discovery-Konfiguration
└── form.json          # Discovery-Formular

library.json            # Bibliotheksdefinition
```

### API-Kommunikation
Das Modul verwendet cURL für die direkte HTTP-Kommunikation mit dem Aidoo Gateway. Alle API-Aufrufe sind als PUT-Requests mit JSON-Body implementiert, entsprechend der Aidoo API-Spezifikation.

### Variablen-Profile
- `AIRZONE.Mode`: Modi mit deutschen Bezeichnungen
- `AIRZONE.FanSpeed`: Lüftergeschwindigkeiten (Auto, 1-5)

## Lizenz

[Hier Lizenzinformationen einfügen]

## Support

Bei Problemen oder Fragen:
1. Debug-Modus aktivieren
2. IP-Symcon Logs prüfen
3. Issue im GitHub-Repository erstellen

## Changelog

### Version 1.0.0
- Erste vollständige Version
- Grundlegende Zonensteuerung
- Gateway-Integration
- Automatische Updates
- Deutsche Lokalisierung
