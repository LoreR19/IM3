<?php
/* ============================================================================
   HANDLUNGSANWEISUNG (load.php)
   1) Binde 001_config.php (PDO-Config) ein.
   2) Binde transform.php ein → erhalte TRANSFORM-JSON.
   3) json_decode(..., true) → Array mit Datensätzen.
   4) Stelle PDO-Verbindung her (ERRMODE_EXCEPTION, FETCH_ASSOC).
   5) Bereite INSERT/UPSERT-Statement mit Platzhaltern vor.
   6) Iteriere über Datensätze und führe execute(...) je Zeile aus.
   7) Optional: Transaktion verwenden (beginTransaction/commit) für Performance.
   8) Bei Erfolg: knappe Bestätigung ausgeben (oder still bleiben, je nach Kontext).
   9) Bei Fehlern: Exception fangen → generische Fehlermeldung/Code (kein Stacktrace).
  10) Keine Debug-Ausgaben in Produktion; sensible Daten nicht loggen.
   ============================================================================ */

include_once('config.php');
// Transformations-Skript als 'transform.php' einbinden und Ergebnis zuweisen
$transformedData = include('transform.php');

// Überprüfen, ob $transformedData ein Array ist
if (!is_array($transformedData)) {
    throw new Exception("Fehler: Transformierte Daten sind ungültig.");
}

print_r($transformedData);


// Binde die Datenbankkonfiguration ein

try {
    // Erstellt eine neue PDO-Instanz mit der Konfiguration aus config.php
    $pdo = new PDO($dsn, $username, $password, $options);

    // SQL-Query mit Platzhaltern für das Einfügen von Daten
    $sql = "INSERT INTO `Wetter-Tagebuch` (`ort`, `temperatur`, `niederschlag`, `weather_code`) 
    VALUES (:ort, :temperatur, :niederschlag, :weather_code)
    ON DUPLICATE KEY UPDATE
        `temperatur` = VALUES(`temperatur`),
        `niederschlag` = VALUES(`niederschlag`),
        `weather_code` = VALUES(`weather_code`)";

    // Bereitet die SQL-Anweisung vor
    $stmt = $pdo->prepare($sql);

    // Fügt jedes Element im Array in die Datenbank ein
    foreach ($transformedData as $item) {
        // Überprüfe, ob alle erforderlichen Schlüssel existieren
        if (!isset($item['ort'], $item['temperatur'], $item['niederschlag'], $item['weather_code'])) {
            throw new Exception("Fehler: Ein Datensatz enthält nicht alle erforderlichen Schlüssel.");
        }

        $stmt->execute([
            ':ort' => $item['ort'],
            ':temperatur' => $item['temperatur'],
            ':niederschlag' => $item['niederschlag'],
            ':weather_code' => $item['weather_code']
        ]);
    }

    echo "Daten erfolgreich transformiert und eingetragen!";
} catch (PDOException $e) {
    die("Verbindung zur Datenbank konnte nicht hergestellt werden: " . $e->getMessage());
} catch (Exception $e) {
    die($e->getMessage());
}