=== LEVELUP - Installation ===

1. COPIER les fichiers dans votre dossier web :
   - WAMP/XAMPP Windows : C:\wamp64\www\levelup\ ou C:\xampp\htdocs\levelup\
   - LAMP Linux         : /var/www/html/levelup/

2. CREER les dossiers uploads (Linux uniquement) :
   mkdir -p uploads/avatars uploads/preuves
   chmod 777 uploads/avatars uploads/preuves

   Windows : les dossiers se creent automatiquement au premier upload.

3. IMPORTER la base de donnees :
   - Ouvrir phpMyAdmin : http://localhost/phpmyadmin
   - Cliquer "Importer"
   - Selectionner le fichier : install.sql
   - Cliquer "Executer"
   Le fichier cree la base "levelup" avec toutes les tables et les 44 missions.

4. CONFIGURER config.php :
   Ouvrir config.php et modifier si besoin :
     DB_HOST = 'localhost'   (laisser tel quel sur WAMP/XAMPP/LAMP)
     DB_NAME = 'levelup'     (nom de la base cree a l'etape 3)
     DB_USER = 'root'        (utilisateur par defaut sur WAMP/XAMPP)
     DB_PASS = ''            (vide par defaut sur WAMP/XAMPP)

5. PLACER les .htaccess :
   - .htaccess          -> a la racine du projet (limite upload 100Mo)
   - avatars.htaccess   -> dans uploads/avatars/  (renommer en .htaccess)
   - preuves.htaccess   -> dans uploads/preuves/  (renommer en .htaccess)

6. ACCEDER a l'application :
   http://localhost/levelup/login-page.php

7. CREER un compte :
   - Email etudiant    : prenom.nom@etu.umontpellier.fr
   - Email enseignant  : prenom.nom@umontpellier.fr
   Le role est detecte automatiquement selon le domaine email.

=== IDENTIFIANTS PAR DEFAUT ===
Aucun compte pre-cree. Creer le premier compte via register.php.
Le role enseignant est attribue automatiquement aux emails @umontpellier.fr

=== STRUCTURE DES FICHIERS ===
config.php           - Configuration BDD (a modifier)
install.sql          - Base de donnees complete (tables + 44 missions)
login-page.php       - Page de connexion
register.php         - Inscription
login.php            - Navbar + session (inclus dans chaque page)
logout.php           - Deconnexion
index-etudiants.php  - Dashboard etudiant
index-prof.php       - Dashboard professeur
missions.php         - Catalogue des missions
missions-crud.php    - Gestion missions (prof)
soumettre.php        - Soumettre une preuve (etudiant)
valider.php          - Valider les preuves (prof)
leaderboard.php      - Classement
profile.php          - Profil etudiant
profile-prof.php     - Profil professeur
bug-bounty-submit.php  - Soumission bug (etudiant)
bug-bounty-valider.php - Validation bug (prof)
styles.css           - CSS global
.htaccess            - Config Apache (upload 100Mo)
avatars.htaccess     - Securite dossier avatars (renommer en .htaccess)
preuves.htaccess     - Securite dossier preuves (renommer en .htaccess)
