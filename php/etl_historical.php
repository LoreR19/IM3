<?php
/* ============================================================================
   ETL HISTORICAL - Extract, Transform, Load für historische Wetterdaten 2025
   
   Dieses Skript ruft rückwirkend alle Wetterdaten für das Jahr 2025 ab
   und speichert sie in der Datenbank.
   
   Verwendung: php etl_historical.php
   oder im Browser: https://im3hs25.lorenaritschard.ch/php/etl_historical.php
   ============================================================================ */

// Erhöhe Timeout für lange Operationen
set_time_limit(300);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// ============================================================================
// KONFIGURATION
// ============================================================================

$locations = [
    'Bern' => ['lat' => 46.9481, 'lon' => 7.4474],
    'Zürich' => ['lat' => 47.3769, 'lon' => 8.5417]
];

// Zeitraum: Ganzes Jahr 2025 (bis heute, falls wir noch in 2025 sind)
$startDate = '2025-01-01';
$today = date('Y-m-d');
$endDate = ($today < '2025-12-31') ? $today : '2025-12-31';

// ============================================================================
// EXTRACT - Historische Daten von Open-Meteo Archive API abrufen
// ============================================================================

function extractHistoricalData($lat, $lon, $startDate, $endDate) {
    // Open-Meteo Archive API für historische Daten
    $baseUrl = 'https://archive-api.open-meteo.com/v1/archive';
    
    $params = [
        'latitude' => $lat,
        'longitude' => $lon,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'daily' => 'temperature_2m_mean,precipitation_sum,weather_code',
        'timezone' => 'Europe/Zurich'
    ];
    
    $url = $baseUrl . '?' . http_build_query($params);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL-Fehler: $error");
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP-Fehler: $httpCode - Response: $response");
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON-Dekodierungsfehler: ' . json_last_error_msg());
    }
    
    return $data;
}

// ============================================================================
// TRANSFORM - Rohdaten in Datenbankformat umwandeln
// ============================================================================

function mapWeatherCode($code) {
    if ($code === null) return "unbekannt";
    
    switch (true) {
        case ($code == 0):
            return "sonnig";
        case ($code >= 1 && $code <= 3):
            return "bewölkt";
        case ($code >= 45 && $code <= 48):
            return "neblig";
        case ($code >= 51 && $code <= 67):
            return "regnerisch";
        case ($code >= 71 && $code <= 77):
            return "schneit";
        case ($code >= 80 && $code <= 82):
            return "regnerisch";
        case ($code >= 85 && $code <= 86):
            return "schneit";
        case ($code >= 95 && $code <= 99):
            return "gewitter";
        default:
            return "bewölkt";
    }
}

function transformData($rawData, $ortName) {
    $transformed = [];
    
    if (!isset($rawData['daily']) || !isset($rawData['daily']['time'])) {
        return $transformed;
    }
    
    $daily = $rawData['daily'];
    $dates = $daily['time'];
    $temps = $daily['temperature_2m_mean'] ?? [];
    $precip = $daily['precipitation_sum'] ?? [];
    $weatherCodes = $daily['weather_code'] ?? [];
    
    for ($i = 0; $i < count($dates); $i++) {
        // Überspringe Tage ohne Daten
        if (!isset($temps[$i]) || $temps[$i] === null) {
            continue;
        }
        
        $transformed[] = [
            'ort' => $ortName,
            'temperatur' => round($temps[$i], 1),
            'niederschlag' => round($precip[$i] ?? 0, 1),
            'weather_code' => mapWeatherCode($weatherCodes[$i] ?? null),
            'timestamp' => $dates[$i] . ' 12:00:00' // Mittag als Referenzzeit
        ];
    }
    
    return $transformed;
}

// ============================================================================
// LOAD - Daten in die Datenbank einfügen
// ============================================================================

function loadData($pdo, $data) {
    // Zuerst bestehende Daten für den Zeitraum löschen (optional)
    // $pdo->exec("DELETE FROM `Wetter-Tagebuch` WHERE timestamp >= '2025-01-01'");
    
    $sql = "INSERT INTO `Wetter-Tagebuch` (`ort`, `temperatur`, `niederschlag`, `weather_code`, `timestamp`) 
            VALUES (:ort, :temperatur, :niederschlag, :weather_code, :timestamp)
            ON DUPLICATE KEY UPDATE
                `temperatur` = VALUES(`temperatur`),
                `niederschlag` = VALUES(`niederschlag`),
                `weather_code` = VALUES(`weather_code`)";
    
    $stmt = $pdo->prepare($sql);
    $insertedCount = 0;
    
    foreach ($data as $item) {
        try {
            $stmt->execute([
                ':ort' => $item['ort'],
                ':temperatur' => $item['temperatur'],
                ':niederschlag' => $item['niederschlag'],
                ':weather_code' => $item['weather_code'],
                ':timestamp' => $item['timestamp']
            ]);
            $insertedCount++;
        } catch (PDOException $e) {
            error_log("Insert-Fehler für {$item['ort']} am {$item['timestamp']}: " . $e->getMessage());
        }
    }
    
    return $insertedCount;
}

// ============================================================================
// HAUPTPROGRAMM - ETL ausführen
// ============================================================================

try {
    $results = [
        'status' => 'running',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'locations' => [],
        'total_records' => 0
    ];
    
    // Datenbankverbindung herstellen
    $pdo = new PDO($dsn, $username, $password, $options);
    
    $allData = [];
    
    // Für jeden Ort Daten abrufen und transformieren
    foreach ($locations as $ortName => $coords) {
        echo "<!-- Verarbeite $ortName... -->\n";
        
        // EXTRACT
        $rawData = extractHistoricalData(
            $coords['lat'], 
            $coords['lon'], 
            $startDate, 
            $endDate
        );
        
        // TRANSFORM
        $transformedData = transformData($rawData, $ortName);
        
        $results['locations'][$ortName] = [
            'records_extracted' => count($transformedData),
            'sample' => array_slice($transformedData, 0, 3) // Erste 3 als Beispiel
        ];
        
        $allData = array_merge($allData, $transformedData);
    }
    
    // LOAD
    $insertedCount = loadData($pdo, $allData);
    
    $results['status'] = 'success';
    $results['total_records'] = $insertedCount;
    $results['message'] = "Erfolgreich $insertedCount Datensätze für 2025 eingefügt!";
    
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
