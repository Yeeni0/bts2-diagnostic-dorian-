<?php
// API JSON des salles
//   GET  api.php                                  -> liste des salles
//   GET  api.php?capaciteMin=10                   -> salles d'au moins 10 places
//   POST api.php {action:"add",    nom, capacite} -> ajout
//   POST api.php {action:"edit",   id, nom, capacite} -> modification
//   POST api.php {action:"delete", id}            -> suppression
// Toute erreur renvoie {ok:false, message:"..."} : la page ne plante jamais.

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

const CAPACITE_MAX = 500;

function repondre(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function erreur(string $message, int $code = 400): void
{
    repondre($code, ['ok' => false, 'message' => $message]);
}

function texte($valeur): string
{
    return (is_string($valeur) || is_int($valeur) || is_float($valeur)) ? trim((string) $valeur) : '';
}

function listeSalles(PDO $pdo): array
{
    return $pdo->query('SELECT id, nom, capacite FROM salle ORDER BY id')->fetchAll();
}

// mb_strtolower gère les accents (É -> é) ; repli si l'extension mbstring est désactivée
function minuscules(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function validerNom(PDO $pdo, $valeur, ?int $idExclu = null): string
{
    $nom = texte($valeur);
    if ($nom === '') {
        erreur('Le nom de la salle est obligatoire.');
    }
    if (!preg_match('/^.{1,60}$/us', $nom)) {
        erreur('Le nom ne doit pas dépasser 60 caractères.');
    }

    // Comparaison sans tenir compte des majuscules, accents compris (É = é),
    // ce que COLLATE NOCASE de SQLite ne fait que pour les lettres ASCII
    $stmt = $pdo->prepare('SELECT nom FROM salle WHERE id <> :id');
    $stmt->execute(['id' => $idExclu ?? 0]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $existant) {
        if (minuscules($existant) === minuscules($nom)) {
            erreur('Une salle nommée « ' . $existant . ' » existe déjà.', 409);
        }
    }
    return $nom;
}

function validerCapacite($valeur): int
{
    $capacite = texte($valeur);
    if ($capacite === '') {
        erreur('La capacité est obligatoire.');
    }
    if (!preg_match('/^-?\d+$/', $capacite)) {
        erreur('La capacité doit être un nombre entier (ex : 10).');
    }
    $n = (int) $capacite;
    if ($n < 0) {
        erreur('La capacité ne peut pas être négative.');
    }
    if ($n === 0) {
        erreur('La capacité doit être supérieure à 0.');
    }
    if ($n > CAPACITE_MAX) {
        erreur('La capacité ne peut pas dépasser ' . CAPACITE_MAX . ' personnes.');
    }
    return $n;
}

function validerId(PDO $pdo, $valeur): int
{
    $id = filter_var($valeur, FILTER_VALIDATE_INT);
    if ($id === false) {
        erreur('Salle invalide.');
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM salle WHERE id = :id');
    $stmt->execute(['id' => $id]);
    if ($stmt->fetchColumn() == 0) {
        erreur('Cette salle n\'existe plus.', 404);
    }
    return $id;
}

try {
    $pdo = getPdo();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Recherche par capacité : api.php?capaciteMin=10 -> salles d'au moins 10 places
        if (isset($_GET['capaciteMin']) && texte($_GET['capaciteMin']) !== '') {
            $min = validerCapacite($_GET['capaciteMin']);
            $stmt = $pdo->prepare('SELECT id, nom, capacite FROM salle WHERE capacite >= :min ORDER BY capacite, nom');
            $stmt->execute(['min' => $min]);
            repondre(200, ['ok' => true, 'salles' => $stmt->fetchAll()]);
        }
        repondre(200, ['ok' => true, 'salles' => listeSalles($pdo)]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur('Méthode non autorisée.', 405);
    }

    $donnees = json_decode(file_get_contents('php://input'), true);
    if (!is_array($donnees)) {
        erreur('Requête invalide.');
    }

    switch ($donnees['action'] ?? '') {
        case 'add':
            $nom      = validerNom($pdo, $donnees['nom'] ?? '');
            $capacite = validerCapacite($donnees['capacite'] ?? '');
            $stmt = $pdo->prepare('INSERT INTO salle (nom, capacite) VALUES (:nom, :capacite)');
            $stmt->execute(['nom' => $nom, 'capacite' => $capacite]);
            repondre(201, [
                'ok' => true,
                'message' => 'Salle ajoutée.',
                'id' => (int) $pdo->lastInsertId(),
                'salles' => listeSalles($pdo),
            ]);

        case 'edit':
            $id       = validerId($pdo, $donnees['id'] ?? null);
            $nom      = validerNom($pdo, $donnees['nom'] ?? '', $id);
            $capacite = validerCapacite($donnees['capacite'] ?? '');
            $stmt = $pdo->prepare('UPDATE salle SET nom = :nom, capacite = :capacite WHERE id = :id');
            $stmt->execute(['nom' => $nom, 'capacite' => $capacite, 'id' => $id]);
            repondre(200, ['ok' => true, 'message' => 'Salle modifiée.', 'id' => $id, 'salles' => listeSalles($pdo)]);

        case 'delete':
            $id = validerId($pdo, $donnees['id'] ?? null);
            if ($pdo->query('SELECT COUNT(*) FROM salle')->fetchColumn() <= 1) {
                erreur('Il faut conserver au moins une salle.');
            }
            $stmt = $pdo->prepare('DELETE FROM salle WHERE id = :id');
            $stmt->execute(['id' => $id]);
            repondre(200, ['ok' => true, 'message' => 'Salle supprimée.', 'salles' => listeSalles($pdo)]);

        default:
            erreur('Action inconnue.');
    }
} catch (PDOException $e) {
    // Filet de sécurité : contraintes UNIQUE / CHECK de la base, base inaccessible…
    if (str_contains($e->getMessage(), 'UNIQUE')) {
        erreur('Une salle porte déjà ce nom.', 409);
    }
    if (str_contains($e->getMessage(), 'CHECK')) {
        erreur('Les données saisies sont invalides.');
    }
    erreur('Impossible d\'accéder à la base de données.', 500);
}
