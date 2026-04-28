<?php
$host = 'localhost';
$db   = 'soundrepo';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    $artistColumns = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Artists' AND COLUMN_NAME = ?");

    $artistColumns->execute(['ImagePath']);
    if (!$artistColumns->fetchColumn()) {
        $pdo->exec("ALTER TABLE Artists ADD COLUMN ImagePath VARCHAR(500) NULL AFTER Name");
    }

    $artistColumns->execute(['ImageLookupChecked']);
    if (!$artistColumns->fetchColumn()) {
        $pdo->exec("ALTER TABLE Artists ADD COLUMN ImageLookupChecked TINYINT(1) NOT NULL DEFAULT 0 AFTER ImagePath");
    }

    $musicColumns = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Musics' AND COLUMN_NAME = ?");

    $musicColumns->execute(['TrackNumber']);
    if (!$musicColumns->fetchColumn()) {
        $pdo->exec("ALTER TABLE Musics ADD COLUMN TrackNumber INT NULL AFTER Genre");
    }
} catch (\PDOException $e) {
    die("
        <div style='font-family: Segoe UI; padding: 20px; color: white; background: #b91d47;'>
            <h2>Erro Crítico de Base de Dados</h2>
            <p>Não foi possível ligar ao SoundRepo DB.</p>
            <p>Detalhe: " . $e->getMessage() . "</p>
        </div>
    ");
}
?>
