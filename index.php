<?php
// IP-Symcon Airzone Aidoo Module Demo Interface
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Airzone Aidoo IP-Symcon Modul</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .module-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            border-left: 4px solid #3498db;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .module-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .module-card ul {
            list-style: none;
            margin-bottom: 20px;
        }
        
        .module-card li {
            padding: 5px 0;
            color: #666;
            position: relative;
            padding-left: 20px;
        }
        
        .module-card li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #27ae60;
            font-weight: bold;
        }
        
        .config-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .btn-success:hover {
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }
        
        .api-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .api-info h4 {
            color: #0c5460;
            margin-bottom: 10px;
        }
        
        .api-info code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-active {
            background: #27ae60;
        }
        
        .status-inactive {
            background: #e74c3c;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .feature-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            text-align: center;
        }
        
        .feature-icon {
            font-size: 2em;
            margin-bottom: 10px;
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌡️ Airzone Aidoo Integration</h1>
            <p>IP-Symcon Modul für Klima- und Heizungssteuerung</p>
        </div>
        
        <div class="content">
            <div class="module-grid">
                <div class="module-card">
                    <h3>🏠 AirzoneAidoo Hauptmodul</h3>
                    <ul>
                        <li>Temperatursteuerung und -überwachung</li>
                        <li>Modi: Kühlen, Heizen, Lüfter, Entfeuchten, Auto</li>
                        <li>Lüftergeschwindigkeitsregelung</li>
                        <li>Stromverbrauchsüberwachung</li>
                        <li>Luftfeuchtigkeitsmessung</li>
                    </ul>
                    <button class="btn">Modul konfigurieren</button>
                </div>
                
                <div class="module-card">
                    <h3>🔍 Discovery Modul</h3>
                    <ul>
                        <li>Automatische Geräteerkennung im Netzwerk</li>
                        <li>CIDR-Notation Unterstützung</li>
                        <li>Gateway-Status prüfung</li>
                        <li>Einfache Instanzerstellung</li>
                    </ul>
                    <button class="btn">Geräte suchen</button>
                </div>
                
                <div class="module-card">
                    <h3>🌐 Gateway Modul</h3>
                    <ul>
                        <li>System- und Zonenverwaltung</li>
                        <li>Mehrere Zonen pro System</li>
                        <li>Zentrale Konfiguration</li>
                        <li>Statusüberwachung</li>
                    </ul>
                    <button class="btn">Gateway einrichten</button>
                </div>
            </div>
            
            <div class="features-grid">
                <div class="feature-box">
                    <div class="feature-icon">🔗</div>
                    <h4>Lokale Verbindung</h4>
                    <p>Direkte IP-Verbindung zum Gateway für beste Performance</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon">☁️</div>
                    <h4>Cloud Alternative</h4>
                    <p>Airzonecloud.com Anbindung als Backup-Option</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon">🔄</div>
                    <h4>Auto-Update</h4>
                    <p>Konfigurierbare Aktualisierungsintervalle</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon">🌍</div>
                    <h4>Mehrsprachig</h4>
                    <p>Deutsche und englische Übersetzungen</p>
                </div>
            </div>
            
            <div class="config-form">
                <h3>Beispiel-Konfiguration</h3>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="useLocal" checked>
                        <label for="useLocal">Lokale Gateway-Verbindung verwenden</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="gatewayIP">Gateway IP-Adresse:</label>
                    <input type="text" id="gatewayIP" placeholder="192.168.1.100" pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$">
                </div>
                
                <div class="form-group">
                    <label for="systemID">System ID:</label>
                    <input type="text" id="systemID" placeholder="1">
                </div>
                
                <div class="form-group">
                    <label for="zoneID">Zonen ID:</label>
                    <input type="text" id="zoneID" placeholder="1">
                </div>
                
                <div class="form-group">
                    <label for="updateInterval">Aktualisierungsintervall (Sekunden):</label>
                    <input type="number" id="updateInterval" min="0" max="3600" value="30">
                </div>
                
                <button class="btn">Verbindung testen</button>
                <button class="btn btn-success">Jetzt aktualisieren</button>
            </div>
            
            <div class="api-info">
                <h4>🔧 API Integration Details</h4>
                <p><strong>Basis-URL:</strong> <code>http://[GATEWAY-IP]/api/v1</code></p>
                <p><strong>Endpoint:</strong> <code>PUT /integration</code> - Gerätesteuerung</p>
                <p><strong>Endpoint:</strong> <code>GET /integration</code> - Statusabfrage</p>
                <p><strong>Dokumentation:</strong> <a href="https://developers.airzonecloud.com/docs/local-api/" target="_blank">developers.airzonecloud.com</a></p>
                
                <h4 style="margin-top: 20px;">📊 Unterstützte Parameter</h4>
                <ul style="margin-top: 10px;">
                    <li><strong>power:</strong> 0/1 - Gerät ein/aus</li>
                    <li><strong>setpoint:</strong> Solltemperatur in °C</li>
                    <li><strong>mode:</strong> stop, cool, heat, fan, dry, auto</li>
                    <li><strong>fanSpeed:</strong> 0-3 (Auto, Niedrig, Mittel, Hoch)</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <p style="color: #6c757d;">
                    <span class="status-indicator status-active"></span>
                    Modul erfolgreich entwickelt und einsatzbereit für IP-Symcon
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Einfache Formvalidierung
        document.getElementById('gatewayIP').addEventListener('input', function(e) {
            const ip = e.target.value;
            const pattern = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            
            if (pattern.test(ip) || ip === '') {
                e.target.style.borderColor = '#ddd';
            } else {
                e.target.style.borderColor = '#e74c3c';
            }
        });
        
        // Button-Interaktionen
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const originalText = this.textContent;
                this.textContent = 'Ausgeführt ✓';
                this.style.background = '#27ae60';
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.background = '';
                }, 2000);
            });
        });
    </script>
</body>
</html>