<?php
// -------------------------
// 1. JSON-Daten laden
// -------------------------
include('extract.php');
$jsonData = json_encode(fetchWeatherData());



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
    } elseif ($lat >= 47.3 && $lat <= 47.4 && $lon >= 8.5 && $lon <= 8.6) {
        return "Zürich";
    } else {
        return "Unbekannt";
    }
}

function mapWeatherCode($code) {
    switch ($code) {
        case 0: return "sonnig";
        case 95, 96, 99: return "gewitter";
        case 1, 2, 3: return "bewölkt";
        case 51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82: return "regnerisch";
        case 71, 73, 75, 77, 85, 86: return "schneit";
        case 45, 48: return "neblig";
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

// Decode JSON data into an array
$dataArray = json_decode($jsonData, true);

// Initialize an array to store transformed data
$transformedDataArray = [];

// Iterate over each item in the data array
foreach ($dataArray as $item) {
    $transformedDataArray[] = [
        'ort' => getLocationFromCoords($item['latitude'], $item['longitude']),
        'temperatur' => round($item['current']['temperature_2m'], 2), // ggf. convertFahrenheitToCelsius()
        'niederschlag' => round($item['current']['rain'], 2),
        'weather_code' => mapWeatherCode($item['current']['weather_code'])
    ];
}

echo "Transformierte Daten:\n";
print_r($transformedDataArray);

// Transformierte Daten zurückgeben
return $transformedDataArray;

echo "Daten erfolgreich transformiert und eingetragen!";
