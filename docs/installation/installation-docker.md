# Installation locale avec Docker Compose

## Rôle et périmètre

Cette configuration complète l’installation historique avec Homebrew. Elle a été
ajoutée après le développement initial pour fournir un environnement local décrit
par des fichiers versionnables. Elle n’est pas utilisée pour publier Incipit sur
Alwaysdata et ne reprend ni sa configuration privée ni ses données.

Le `Dockerfile` construit l’image de l’application. `compose.yaml` décrit les quatre
services et leurs connexions. `compose.override.yaml` est volontairement vide :
l’ancien exemple PostgreSQL ne correspondait pas au moteur SQL utilisé par Incipit.

## Prérequis

- Docker Desktop démarré, avec Docker Compose disponible.
- Les ports locaux 8080 et 8025 libres.
- Un accès Internet pour télécharger les images, dépendances Composer et ressources
  déclarées dans l’importmap pendant la construction.
- Une copie du dépôt comprenant toutes les migrations, notamment
  `migrations/Version20261014000000.php` pour créer le socle sur une base vide.

## Services et ports

- `app` : PHP 8.4 et Apache, avec Composer, PDO MySQL, Intl, ZIP, Mbstring, XML et
  l’extension MongoDB. Apache sert uniquement `/var/www/html/public`. Le port
  **8080 du Mac** est relié au **port 80 du conteneur**, sur `127.0.0.1`.
- `database` : MySQL 8.4, base locale `incipit_demo`. Son port **3306** est utilisé
  uniquement sur le réseau Compose ; aucun port MySQL n’est publié sur le Mac.
- `mongodb` : MongoDB 8.0, base du journal `incipit_demo_logs`. Le port **27017**
  reste interne. Cette instance locale est indépendante de MongoDB Atlas.
- `mailer` : Mailpit. L’application utilise son serveur SMTP interne **1025**.
  Les messages sont consultables sur **http://localhost:8025** et ne sont pas envoyés
  à leurs destinataires réels.

Les noms `database`, `mongodb` et `mailer` sont résolus sur le réseau privé créé par
Compose. Depuis `app`, `localhost` désignerait le conteneur de l’application, et non
les autres services. Les contrôles de santé MySQL et MongoDB conditionnent le
démarrage d’`app` ; ils ne remplacent pas la création des tables.

Les identifiants publics de cette configuration sont réservés au développement.
MySQL et MongoDB n’exposent pas leurs ports à l’hôte. Ne pas utiliser ces paramètres
pour un hébergement public. Les clés de catalogues externes sont vides par défaut.

## Volumes

- `mysql_data` conserve les fichiers de MySQL dans `/var/lib/mysql`.
- `mongo_data` conserve les documents et index MongoDB dans `/data/db`.
- `couvertures` conserve les images importées dans `/var/www/html/public/uploads`.
- `app_var` conserve le cache, les journaux et les sessions locales dans
  `/var/www/html/var`.

Ces volumes sont propres au projet Compose `incipit-local`. Ils ne montent pas les
répertoires des services Homebrew. Les sources et les dépendances PHP sont copiées
dans l’image, sans montage du dossier de travail : une modification du code exige
une reconstruction. Les volumes ne sont pas des sauvegardes.

Le `.dockerignore` exclut notamment les fichiers `.env` du Mac, les secrets, Git,
`vendor`, les caches, les ressources téléchargées ou compilées et les couvertures
personnelles. Le fichier public `docker/app.env` fournit les paramètres locaux de
construction ; les paramètres de fonctionnement sont aussi explicites dans Compose.

## Première installation

À la racine du projet :

```bash
docker compose config --quiet
docker compose up -d --build --wait
docker compose exec --user www-data app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec --user www-data app php bin/console app:create-admin
```

Le compte de gestion est créé interactivement ; aucun mot de passe de compte web
n’est fourni dans l’image ou dans les scripts de démonstration.

Ouvrir ensuite :

- Application : http://localhost:8080
- Courriels de test : http://localhost:8025

Pour préparer le jeu de démonstration, une seule fois sur cette base métier vide :

```bash
docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE"' < docs/base-de-donnees/scripts-sql/donnees-demonstration.sql
docker compose exec -T database sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE"' < docs/base-de-donnees/scripts-sql/verification-demonstration.sql
```

Ne pas répéter l’import sur une base déjà préparée : ce script n’est pas idempotent.
Les INSERT directs ne produisent pas d’événements Doctrine ; les actions suivantes
réalisées dans l’interface alimentent le journal.

## Vérifications

```bash
docker compose ps
docker compose exec --user www-data app php bin/console about
docker compose exec app php -r 'echo "PDO MySQL : ", extension_loaded("pdo_mysql") ? "oui" : "non", PHP_EOL, "MongoDB : ", extension_loaded("mongodb") ? "oui" : "non", PHP_EOL;'
docker compose exec --user www-data app php bin/console doctrine:schema:validate
docker compose exec mongodb mongosh --quiet --eval 'db.adminCommand({ping:1})'
docker compose exec --user www-data app php bin/console app:journal:transmettre
```

La validation du schéma peut révéler des écarts entre les entités et les contraintes
ajoutées par migration : analyser la sortie avant d’envisager une modification.
Ne pas exécuter automatiquement `doctrine:schema:update --force`.

Dans le navigateur, contrôler l’accueil, la connexion, le parcours du souhait,
l’achat, les lectures, le journal et les courriels de réinitialisation dans Mailpit.
L’environnement local utilise HTTP ; il ne reproduit pas la configuration HTTPS
de l’hébergement. Aucune tâche planifiée n’est ajoutée à cette configuration :
la commande du journal est disponible pour une reprise manuelle.

## Modifier les sources et arrêter

Après une modification du code :

```bash
docker compose up -d --build --wait
docker compose exec --user www-data app php bin/console cache:clear
```

Arrêter les services en conservant les données :

```bash
docker compose down
```

`docker compose down --volumes` supprime les volumes, donc les données locales,
couvertures et sessions de cet environnement. Ce n’est pas la commande d’arrêt courant.

## Sources techniques

- Image PHP officielle : https://hub.docker.com/_/php
- Services Compose : https://docs.docker.com/reference/compose-file/services/
- Ordre de démarrage : https://docs.docker.com/compose/how-tos/startup-order/


## Vérification réalisée le 2 novembre 2026

Les contrôles suivants ont été exécutés sur le Mac, avec Docker Desktop 29.5.2 :

- Configuration fusionnée : `docker compose config --quiet` réussit.
- Construction de l’image : réussie ; PHP 8.4.27, extensions PDO MySQL, Intl,
  MongoDB, Mbstring, DOM et XMLWriter disponibles.
- Démarrage : quatre services actifs ; MySQL, MongoDB et Mailpit déclarés sains.
- Migrations : neuf migrations et 79 requêtes exécutées jusqu’à
  `DoctrineMigrations\Version20261018160000` dans la nouvelle base `incipit_demo`.
- `doctrine:schema:validate` : mapping correct et schéma synchronisé.
- HTTP : accueil, connexion et interface Mailpit répondent avec le statut 200.
- Jeu de démonstration : importé une seule fois ; les consultations retrouvent
  deux auteurs, deux livres, deux associations et une ligne dans chacune des tables
  souhait, exemplaire, session de lecture, contact et prêt.
- Journal : un événement fictif a été conservé avec le service `JournalActivite`,
  transmis depuis SQL à MongoDB, puis relancé avec le même identifiant. Un seul
  document a été trouvé. La ligne SQL et le document de contrôle ont ensuite été
  supprimés par leur identifiant, sans toucher au jeu de démonstration.
- Courriel : un message fictif a été envoyé avec Symfony Mailer au SMTP Mailpit.

L’accès initial au registre était bloqué par le gestionnaire d’identifiants Docker
Desktop. Les images publiques ont finalement été téléchargées avec une configuration
Docker temporaire sans identifiants, sans modifier les réglages personnels.

Le parcours complet après authentification n’a pas été exécuté dans le navigateur
pour cette vérification. Aucun compte web n’a été créé automatiquement : utiliser
la commande interactive `app:create-admin` pour préparer son accès local.
Les services sont laissés démarrés et le jeu de démonstration est déjà chargé dans
ces volumes ; ne pas réimporter le script sur cette instance.

Aucune intervention n’a été réalisée sur Alwaysdata et aucun commit Docker n’a
encore été créé. Avant de fournir un clonage neuf, inclure la migration de socle
encore non suivie par Git avec les nouveaux fichiers Docker.
