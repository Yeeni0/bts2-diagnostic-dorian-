Pour lancer l'app utiliser un local host
Pour créer la base de données
Via un script SQL  La méthode la plus directe consiste à exécuter une requête SQL. Pour éviter les erreurs si la base existe déjà, utilisez IF NOT EXISTS.

CREATE DATABASE IF NOT EXISTS salle.db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci

Une fois la base créée, structurez vos données avec la commande CREATE TABLE :

CREATE TABLE clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE
);