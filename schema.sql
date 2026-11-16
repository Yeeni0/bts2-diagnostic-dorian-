

DROP TABLE IF EXISTS salle;

CREATE TABLE salle (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    nom      TEXT    NOT NULL UNIQUE COLLATE NOCASE
                     CHECK (length(trim(nom)) BETWEEN 1 AND 60),
    capacite INTEGER NOT NULL
                     CHECK (typeof(capacite) = 'integer' AND capacite BETWEEN 1 AND 500)
);


INSERT INTO salle (nom, capacite) VALUES
    ('Lumière',      4),
    ('Confluence',   6),
    ('Westminster',  6),
    ('Guillotière', 12),
    ('Fourvière',   20);
