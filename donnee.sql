-- ============================================================
--  LevelUp – donnee.sql  (version complète avec avatar)
--  Importer dans phpMyAdmin sur la base db_PLACE_NEVEUX
-- ============================================================

DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id         INT(11) UNSIGNED AUTO_INCREMENT NOT NULL,
  user       VARCHAR(50)  NOT NULL UNIQUE,
  pass       VARCHAR(255) NOT NULL,
  role       VARCHAR(20)  NOT NULL DEFAULT 'etudiant',
  nom        VARCHAR(80)  DEFAULT NULL,
  prenom     VARCHAR(80)  DEFAULT NULL,
  email      VARCHAR(120) DEFAULT NULL,
  avatar     VARCHAR(255) DEFAULT NULL,
  niveau     INT(3) UNSIGNED NOT NULL DEFAULT 1,
  xp         INT(11) UNSIGNED NOT NULL DEFAULT 0,
  streak     INT(5) UNSIGNED NOT NULL DEFAULT 0,
  last_login DATETIME DEFAULT NULL,
  actif      TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
