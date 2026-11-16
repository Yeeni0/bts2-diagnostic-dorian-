-- Base des salles de réservation (SQLite)
-- Créée automatiquement par db.php au premier lancement,
-- ou à la main : sqlite3 salles.db < schema.sql

DROP TABLE IF EXISTS salle;

CREATE TABLE salle (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    nom      TEXT    NOT NULL UNIQUE COLLATE NOCASE
                     CHECK (length(trim(nom)) BETWEEN 1 AND 60),
    capacite INTEGER NOT NULL
                     CHECK (typeof(capacite) = 'integer' AND capacite BETWEEN 1 AND 500)
);

-- Jeu de test : 5 salles (capacité >= 8 = salle pour grands groupes)
INSERT INTO salle (nom, capacite) VALUES
    ('Lumière',      4),
    ('Confluence',   6),
    ('Westminster',  6),
    ('Guillotière', 12),
    ('Fourvière',   20);
