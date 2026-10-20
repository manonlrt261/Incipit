# Vérification reproductible d’Incipit

## Documents

- [Cahier de recette fonctionnelle](recette-fonctionnelle.md) : 47 essais manuels, anomalies et parcours représentatif. Les observations non transmises restent à renseigner.
- [Résultats du 20 octobre 2026](resultats-tests-2026-10-20.md) : campagne réellement exécutée et limites.

## Préconditions

Travailler depuis la racine d’un clone de développement. Installer les dépendances avec `composer install` (avec les dépendances de développement). Utiliser une version de PHP compatible avec `composer.lock`, les extensions demandées par Composer, MySQL/MariaDB local et Node.js pour le contrôle JavaScript. Les tests MongoDB demandent l’extension PHP `mongodb`, MongoDB local et `pdo_sqlite` pour la file temporaire en mémoire.

Configurer `.env.local` avec une base SQL **de développement** contenant le schéma à jour et les comptes attendus par les tests : les scripts HTTP réutilisent le premier compte administrateur existant et certaines suites attendent les comptes de démonstration. Ne pas importer des données personnelles pour les publier avec les tests. Ne jamais versionner ce fichier ni les identifiants. Vérifier les migrations avant exécution sur une base jetable.

Les scripts `--local` refusent un hôte SQL distant. Ce garde-fou n’identifie pas une base de production accessible par tunnel ou installée sur la même machine : il appartient à l’opérateur de sélectionner réellement une base de développement. Les fixtures SQL sont créées dans une transaction annulée en fin d’essai ; les tests ne se lancent pas en parallèle contre cette même base. Les séquences d’identifiants peuvent néanmoins avancer et des caches ou sessions techniques peuvent être produits.

## Lancer les suites principales

```bash
bash tests/executer.sh --local
```

Chaque suite tourne dans son propre processus. Le lanceur poursuit les autres suites après un échec et rend un code final non nul si l’une échoue. Sans `--mongodb`, il indique explicitement que les deux suites MongoDB ne sont pas exécutées. Un succès de cette commande ne signifie donc pas que MongoDB a été testé.

Commandes individuelles :

```bash
php tests/verifier-donnees-lecture.php
node tests/verifier-filtres.cjs
php tests/verifier-acces-prives.php --local
php tests/verifier-confidentialite-visiteurs.php --local
php tests/verifier-recherche-dashboard.php --local
php tests/verifier-restauration-collection.php --local
```

Le contrôle de filtres emploie un DOM minimal simulé et le module JavaScript réel. Il teste les valeurs conservées, l’ordre et les combinaisons ; il ne teste ni le rendu CSS, ni les gestes sur téléphone, ni toute la navigation du navigateur.

## Préparer MongoDB pour les suites complémentaires

Le journal utilise une instance de test sur `127.0.0.1:28719` et des bases à nom aléatoire `incipit_test_journal_*`, supprimées en fin d’essai. Le port `28720` doit rester libre : il sert à simuler le service indisponible. Ne pas placer un service sur ce port pendant le test.

Exemple de démarrage de l’instance isolée dans un autre terminal (nécessite `mongod`) :

```bash
mkdir -p var/mongodb-test
mongod --bind_ip 127.0.0.1 --port 28719 --dbpath "$PWD/var/mongodb-test"
```

Laisser ce terminal ouvert. Le test d’archive utilise séparément `127.0.0.1:27017`, avec des bases temporaires `incipit_test_archive_*`, puis leur suppression. Une instance de développement doit donc aussi écouter sur ce port. Il n’utilise jamais l’URI Atlas de production. Les exports temporaires vont dans le répertoire temporaire du système.

```bash
bash tests/executer.sh --local --mongodb
```

Ou séparément :

```bash
php tests/verifier-journal.php --local
php tests/verifier-archive-journal.php
```

Arrêter uniquement l’instance isolée que l’on vient de lancer, avec Ctrl+C dans son terminal. Ne pas arrêter un service MongoDB utilisé par d’autres travaux. Après un arrêt brutal des tests, vérifier les bases de test résiduelles avant de supprimer exclusivement les bases de cette campagne.

## Ce que vérifient les essais négatifs

Le test métier transmet notamment un statut inconnu, une note égale à 6, une note non numérique, une date inexistante et une fin avant le début. Il vérifie le refus et le message attendu. Il distingue aussi une note absente d’un zéro et contrôle le retrait de la pile à la fin de lecture.

La recherche du tableau de bord vérifie les résultats absents, les valeurs littérales ressemblant à une injection et les livres exclus du périmètre. Les contrôles d’accès vérifient les visiteurs, les droits insuffisants et les jetons CSRF invalides. La restauration refuse les fiches qui ne correspondent pas à un livre retiré.

Le journal simule une connexion MongoDB indisponible et vérifie la conservation d’un événement, sa transmission après reprise et l’absence de retransmission. Les données de cette file sont en SQLite mémoire ; le serveur Atlas réel n’est pas interrompu.

Les pannes des fournisseurs ISBN, images et courriels ne sont pas couvertes par cette simulation MongoDB : elles restent à tester séparément. Ne pas annoncer une couverture générale de tous les services externes.

## Conserver une preuve

Noter la date, le commit de départ et les modifications locales, les versions PHP/Node/MongoDB, les commandes, les sorties et les codes de sortie. Une campagne lancée avec des tests non encore commités doit être décrite comme telle. Ne pas remplacer silencieusement un ancien résultat par celui d’une autre version.

Pour capturer la sortie dans un terminal Bash :

```bash
set -o pipefail
bash tests/executer.sh --local --mongodb 2>&1 | tee /tmp/incipit-resultats-tests.log
```

Examiner le journal avant de le conserver dans le dépôt : une erreur inattendue peut révéler des données de configuration. Le rapport versionné doit garder les informations utiles à la reproduction en supprimant uniquement les secrets, avec mention de cette suppression si elle a eu lieu. Conserver les essais échoués et leur correction lorsqu’ils éclairent une anomalie ; ne pas présenter un contrôle non exécuté comme réussi.

## Place de la recette manuelle

La réception d’un courriel, le lien expiré, l’accès à une webcam, les permissions, la mise au point du téléphone, le zoom Safari et la présentation des fenêtres demandent des observations sur les appareils réels. Utiliser les fiches du cahier de recette et identifier les captures. Le dépôt distingue ainsi les scénarios préparés, les contrôles exécutés et les résultats encore manquants.
