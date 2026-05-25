SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `levelup` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `levelup`;

CREATE TABLE `users` (
  `id`         INT(11) UNSIGNED AUTO_INCREMENT NOT NULL,
  `user`       VARCHAR(50)  NOT NULL,
  `pass`       VARCHAR(255) NOT NULL,
  `role`       VARCHAR(20)  NOT NULL DEFAULT 'etudiant',
  `nom`        VARCHAR(80)  DEFAULT NULL,
  `prenom`     VARCHAR(80)  DEFAULT NULL,
  `email`      VARCHAR(120) DEFAULT NULL,
  `avatar`     VARCHAR(255) DEFAULT NULL,
  `niveau`     INT(3) UNSIGNED NOT NULL DEFAULT 1,
  `xp`         INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `streak`     INT(5) UNSIGNED NOT NULL DEFAULT 0,
  `last_login` DATETIME DEFAULT NULL,
  `actif`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `missions` (
  `id`          INT(11) UNSIGNED AUTO_INCREMENT NOT NULL,
  `titre`       VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `categorie`   ENUM('reseau','secu','dev') NOT NULL DEFAULT 'reseau',
  `ue`          VARCHAR(50) DEFAULT NULL,
  `difficulte`  ENUM('facile','moyen','difficile') NOT NULL DEFAULT 'moyen',
  `xp`          INT(5) UNSIGNED NOT NULL DEFAULT 50,
  `actif`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `soumissions` (
  `id`           INT(11) UNSIGNED AUTO_INCREMENT NOT NULL,
  `mission_id`   INT(11) UNSIGNED NOT NULL,
  `user_id`      INT(11) UNSIGNED NOT NULL,
  `commentaire`  TEXT DEFAULT NULL,
  `fichier`      VARCHAR(255) DEFAULT NULL,
  `statut`       ENUM('en_attente','valide','refuse') NOT NULL DEFAULT 'en_attente',
  `xp_attribue`  INT(5) UNSIGNED DEFAULT NULL,
  `valide_par`   INT(11) UNSIGNED DEFAULT NULL,
  `valide_le`    DATETIME DEFAULT NULL,
  `refuse_raison` TEXT DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_soum` (`user_id`,`mission_id`),
  FOREIGN KEY (`mission_id`) REFERENCES `missions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `connexions` (
  `id`      INT(11) UNSIGNED AUTO_INCREMENT NOT NULL,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `date_co` DATE NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_co` (`user_id`,`date_co`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `bug_bounty` (
  `id`               INT(11) UNSIGNED AUTO_INCREMENT NOT NULL,
  `user_id`          INT(11) UNSIGNED NOT NULL,
  `categorie`        VARCHAR(50) NOT NULL,
  `severite`         ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'low',
  `titre`            VARCHAR(200) NOT NULL,
  `description`      TEXT NOT NULL,
  `statut`           ENUM('ouvert','en_cours','valide','invalide') NOT NULL DEFAULT 'ouvert',
  `xp_attribue`      INT(5) UNSIGNED DEFAULT NULL,
  `commentaire_prof` TEXT DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_badges` (
  `id`          INT(11) UNSIGNED AUTO_INCREMENT NOT NULL,
  `user_id`     INT(11) UNSIGNED NOT NULL,
  `badge_id`    VARCHAR(50) NOT NULL,
  `unlocked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_badge` (`user_id`,`badge_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `missions` (`titre`,`description`,`categorie`,`ue`,`difficulte`,`xp`) VALUES
('Identifier les composants d''un reseau local','Schematise un reseau local simple. Identifie chaque composant et son role. Documente en PDF.','reseau','UE1 - Administrer','facile',30),
('Installer un OS Linux sur une VM','Installe Ubuntu Server sur VirtualBox. Configure le reseau en bridge. Prouve le fonctionnement.','reseau','UE1 - Administrer','facile',40),
('Configurer un poste client Windows en reseau','Configure une adresse IP fixe, masque, passerelle et DNS. Teste la connectivite.','reseau','UE1 - Administrer','facile',30),
('Identifier un dysfonctionnement reseau','Utilise ping, ipconfig et tracert pour diagnostiquer et corriger un probleme reseau.','reseau','UE1 - Administrer','facile',40),
('Configurer un switch manageable','Configure un switch Cisco (hostname, passwords, SSH). Documente chaque commande.','reseau','UE1 - Administrer','moyen',60),
('Mettre en place des VLANs','Configure 3 VLANs sur un switch Cisco. Assigne les ports. Verifie l''isolation.','reseau','UE1 - Administrer','moyen',70),
('Configurer le routage inter-VLAN','Mets en place le routage inter-VLAN via router-on-a-stick. Teste avec Wireshark.','reseau','UE1 - Administrer','moyen',80),
('Installer un serveur DHCP','Installe isc-dhcp-server sur Linux. Configure les plages. Teste l''attribution automatique.','reseau','UE1 - Administrer','moyen',60),
('Installer un serveur DNS','Installe BIND9. Configure une zone forward et reverse. Teste la resolution de noms.','reseau','UE1 - Administrer','moyen',70),
('Virtualiser deux serveurs en reseau','Cree 2 VMs Linux et Windows Server. Configure un reseau interne. Teste les communications.','reseau','UE1 - Administrer','moyen',70),
('Deployer un support de transmission','Realise un cable RJ45. Teste-le avec un testeur. Documente la norme utilisee.','reseau','UE1 - Administrer','moyen',50),
('Mettre en place un serveur Active Directory','Installe Windows Server AD. Cree des OUs, utilisateurs et groupes. Joint un client.','reseau','UE1 - Administrer','difficile',120),
('Deployer Zabbix ou Nagios','Installe un outil de supervision. Surveille 3 equipements. Configure une alerte e-mail.','reseau','UE1 - Administrer','difficile',130),
('Configurer STP avec redondance','Configure STP sur 2 switchs. Identifie le root bridge. Simule une panne.','reseau','UE1 - Administrer','difficile',110),
('Mesurer et analyser un signal reseau','Utilise Wireshark pour capturer du trafic. Identifie les protocoles. Fournis un rapport.','reseau','UE2 - Connecter','facile',35),
('Caracteriser un support de transmission','Compare cuivre et fibre optique. Tableau comparatif debit, attenuation, distance.','reseau','UE2 - Connecter','facile',30),
('Analyser une trame Ethernet','Capture et analyse une trame complete. Identifie les champs MAC, EtherType, payload.','reseau','UE2 - Connecter','facile',40),
('Configurer un point d''acces WiFi','Configure un AP WPA2-AES. Teste la connexion. Mesure le debit avec iperf.','reseau','UE2 - Connecter','moyen',65),
('Configurer WPA2 Enterprise','Configure 802.1X avec FreeRADIUS. Teste l''authentification. Capture le handshake EAP.','secu','UE2 - Connecter','moyen',90),
('Mettre en place la VoIP','Installe Asterisk. Configure deux softphones. Analyse le trafic RTP avec Wireshark.','reseau','UE2 - Connecter','moyen',85),
('Configurer un tunnel VPN','Configure un tunnel IPSec ou OpenVPN entre deux routeurs. Teste et capture le trafic.','secu','UE2 - Connecter','moyen',90),
('Deployer un reseau WiFi avec controleur','Configure plusieurs APs avec UniFi. Mets en place le roaming. Teste la continuite.','reseau','UE2 - Connecter','moyen',80),
('Configurer BGP entre deux AS','Utilise GNS3 pour simuler deux AS. Configure BGP. Verifie la table de routage.','reseau','UE2 - Connecter','difficile',150),
('Analyser les performances d''un lien','Utilise iperf3 TCP et UDP. Analyse gigue et perte. Compare filaire vs WiFi.','reseau','UE2 - Connecter','difficile',100),
('Creer une page HTML/CSS responsive','Page web responsive Flexbox/Grid avec navigation, hero et footer. Fournis le code.','dev','UE3 - Programmer','facile',30),
('Ecrire un script shell de sauvegarde','Script bash horodatage, compression tar.gz, conservation 7 derniers, log.','dev','UE3 - Programmer','facile',40),
('Manipuler des donnees JSON avec Python','Script Python : lit JSON, filtre, genere CSV. Bibliotheque standard uniquement.','dev','UE3 - Programmer','facile',35),
('Creer une base de donnees MySQL','BDD avec 3 tables liees. 5 requetes SELECT avec JOIN, WHERE, GROUP BY.','dev','UE3 - Programmer','facile',40),
('Creer une page PHP dynamique avec BDD','PHP + MySQL via PDO. Tableau HTML avec pagination.','dev','UE3 - Programmer','moyen',60),
('Developper un formulaire securise','Formulaire PHP : validation serveur, protection XSS et SQLi, mail de confirmation.','dev','UE3 - Programmer','moyen',70),
('Creer une API REST en PHP','API REST complete GET/POST/PUT/DELETE. Bons codes HTTP. Teste avec curl.','dev','UE3 - Programmer','moyen',80),
('Implémenter l''authentification PHP','Login/logout securise. password_hash. Sessions. Protection CSRF.','secu','UE3 - Programmer','moyen',75),
('Automatiser une tache reseau avec Python','Script Python SSH via paramiko. Recupere interfaces et routing table. Genere rapport.','dev','UE3 - Programmer','moyen',85),
('Adapter les formats de donnees','Convertisseur JSON/XML/CSV. Gestion erreurs. Tests unitaires.','dev','UE3 - Programmer','moyen',65),
('Scanner de ports en Python','Scanner sans nmap. Detecte ports ouverts. Identifie services via bannieres.','secu','UE3 - Programmer','moyen',80),
('Deployer une application LAMP','PHP/MySQL sur Linux. Virtual hosts Apache. Permissions. Pare-feu. Documentation.','dev','UE3 - Programmer','difficile',120),
('Outil de monitoring reseau en Python','Script ping, log temps reponse, detection panne, alerte, graphiques matplotlib.','dev','UE3 - Programmer','difficile',130),
('Securiser une app contre OWASP Top 10','DVWA : corriger 5 failles (XSS, SQLi, CSRF, broken auth). Rapport de pentest.','secu','UE3 - Programmer','difficile',180),
('Dashboard de supervision reseau','App web PHP affichant etat equipements via SNMP/ping. JS rafraichissement auto.','dev','UE3 - Programmer','difficile',160),
('Scanner des vulnerabilites avec nmap','Scan reseau autorise. Services exposes, versions, CVE. Rapport de recommandations.','secu','Transversal','moyen',80),
('Mettre en place un pare-feu iptables','iptables Linux : regles entrant/sortant. Bloquer ports, autoriser services, logger.','secu','Transversal','moyen',80),
('Analyser les logs d''un serveur','Logs Apache/SSH : intrusions, erreurs 404, IPs suspectes. Mettre en place fail2ban.','secu','Transversal','moyen',70),
('Chiffrer avec SSL/TLS','Certificat SSL sur Apache/Nginx. Force HTTPS. Analyse handshake TLS. SSL Labs.','secu','Transversal','difficile',110),
('Mettre en place une DMZ','Architecture 3 zones LAN/DMZ/WAN. Serveur web en DMZ. Regles pare-feu. Schema.','secu','Transversal','difficile',140);
