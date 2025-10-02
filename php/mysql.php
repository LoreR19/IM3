<?php

require_once 'config.php';

try {
//Erstellt eine neue PDO-Verbindung
// WICHTIG IMMER PDO VERWENDEN (NOTENRELEVANT/SICHERHEIT)
$pdo = new PDO($dsn, $username, $passwort, $options);

$sql = "SELECT * FROM `User`";

$stmt = $pdo->query($sql);

$users = $stmt->fetchAll();

foreach ($users as $user) {
    echo $user ['name'] . "<br>";

}

}
catch ( PDOException $e) {
//Behandelt Verbindungsfehler
die("Datenbankverbindungsfehler: " . $e->getMessage())
}