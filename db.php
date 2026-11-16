<?php
// Connexion à la base SQLite. Si salles.db n'existe pas encore,
// il est créé et rempli à partir de schema.sql.

function getPdo(): PDO
{
    $dbFile = __DIR__ . '/salles.db';
    $premiereFois = !file_exists($dbFile);

    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($premiereFois) {
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    }

    return $pdo;
}
