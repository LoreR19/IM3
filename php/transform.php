<?php
// -------------------------
// 1. JSON-Daten laden
// -------------------------
$jsonData = '[{
    "latitude": 46.94,
    "longitude": 7.44,
    "current": {
        "time": "2025-10-06T09:30",
        "temperature_2m": 10.7,
        "rain": 0,
        "weather_code": 2
    }
}]';

$dataArray = json_decode($jsonData, true);

// -------------------------
// 2. Hilfsfunktionen
// -------------------------
function convertFahrenheitToCelsius($f) {
    return ($f - 32) * 5 / 9;
}

function getLocationFromCoords($lat, $lon) {
    $lat = round($lat, 2);
    $lon = round($lon, 2);

    if ($lat >= 46.9 && $lat <= 47.0 && $lon >= 7.4 && $lon <= 7.5) {
        return "Bern";
    } elseif ($lat >= 46.8 && $lat <= 46.9 && $lon >= 9.5 && $lon <= 9.6) {
        return "Chur";
    } elseif ($lat >= 47.3 && $lat <= 47.4 && $lon >= 8.5 && $lon <= 8.6) {
        return "Zürich";
    } else {
        return "Unbekannt";
    }
}

function mapWeatherCode($code) {
    switch ($code) {
        case 0: return "sonnig";
        case 1: return "teilweise bewölkt";
        case 2: return "bewölkt";
        case 3: return "regnerisch";
        case 4: return "schneit";
        default: return "unbekannt";
    }
}

// -------------------------
// 3. Datenbankverbindung (PDO)
// -------------------------
require_once 'config.php';
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

// -------------------------
// 4. Transformation & Insert
// -------------------------
foreach ($dataArray as $item) {
    $transformedData = [
        'ort' => getLocationFromCoords($item['latitude'], $item['longitude']),
        'temperatur' => round($item['current']['temperature_2m'], 2), // ggf. convertFahrenheitToCelsius()
        'niederschlag' => round($item['current']['rain'], 2),
        'weather_code' => mapWeatherCode($item['current']['weather_code']),
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Insert in Datenbank
    $sql = "INSERT INTO `Wetter-Tagebuch` (ort, temperatur, niederschlag, weather_code)
            VALUES (:ort, :temperatur, :niederschlag, :weather_code)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ort' => $transformedData['ort'],
        ':temperatur' => $transformedData['temperatur'],
        ':niederschlag' => $transformedData['niederschlag'],
        ':weather_code' => $transformedData['weather_code']
    ]);
}

echo "Daten erfolgreich transformiert und eingetragen!";
