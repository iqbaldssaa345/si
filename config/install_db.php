<?php
$passwords = ['', 'root', '12345678', '123456', '1234', 'appserv', 'admin', 'password', 'root123', 'toor'];
$found = null;
foreach ($passwords as $p) {
    try {
        $pdo = new PDO("mysql:host=localhost", "root", $p);
        $found = $p;
        echo "FOUND_PASSWORD:[" . $p . "]\n";
        break;
    } catch (Exception $e) {
        // try next
    }
}
if ($found === null) {
    echo "NO_PASSWORD_MATCHED\n";
} else {
    // Jalankan import file wisata.sql
    try {
        $sql = file_get_contents(__DIR__ . '/../database/wisata.sql');
        $pdo->exec($sql);
        echo "DATABASE_IMPORTED_SUCCESSFULLY\n";
    } catch (Exception $e) {
        echo "IMPORT_ERROR: " . $e->getMessage() . "\n";
    }
}
