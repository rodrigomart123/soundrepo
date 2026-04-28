<?php
// db.php
$host = 'localhost';
$db   = 'soundrepo';
$user = 'root'; // Utilizador padrão do XAMPP
$pass = '';     // Senha padrão do XAMPP é vazia
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Cria a conexão
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Se der erro, mostra uma mensagem bonita (estilo Windows Error)
    die("
        <div style='font-family: Segoe UI; padding: 20px; color: white; background: #b91d47;'>
            <h2>Erro Crítico de Base de Dados</h2>
            <p>Não foi possível ligar ao SoundRepo DB.</p>
            <p>Detalhe: " . $e->getMessage() . "</p>
        </div>
    ");
}
?>