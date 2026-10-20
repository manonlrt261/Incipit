# Journal d’activité MongoDB

Le journal privé est accessible depuis le profil aux administrateurs. Il enregistre les futures actions ; il ne reconstitue pas un historique des actions antérieures à son installation.

## Structure et choix des bases

MySQL conserve les livres, lectures, souhaits et prêts. Chaque action enregistrée par Doctrine ajoute, dans la même transaction, un événement à `evenement_outbox`. Une annulation de transaction annule donc aussi cet événement.

MongoDB conserve les documents dans `journal_activite` : identifiant stable `_id`, version, action, date BSON UTC, administrateur (identifiant et adresse de connexion), copie du livre (identifiant et titre), détails variables selon l’action (statut, note, dates de lecture). Les mots de passe, jetons et coordonnées des contacts sont exclus.

Ce modèle de documents permet de conserver des événements de formes différentes et les titres historiques indépendamment des modifications des tables métier. La consultation utilise une recherche filtrée et triée, limitée à trente événements par page. Les index portent sur date, action/date et livre/date.

## Transmission et panne

Après une réponse réussie à une action, un lot de vingt événements est transmis. La commande `php bin/console app:journal:transmettre` reprend jusqu’à cent événements ; elle peut être planifiée régulièrement sur le serveur. Un événement n’est marqué transmis qu’après acquittement MongoDB. Une reprise utilise un upsert sur le même identifiant : elle ne crée pas de doublon. Une panne MongoDB laisse les événements en MySQL sans empêcher l’action métier. La page signale l’indisponibilité et le nombre d’événements en attente.

## Configuration serveur à prévoir

L’extension PHP `mongodb` est nécessaire. Définir `MONGODB_URI` et `MONGODB_DB` dans la configuration privée du serveur, jamais dans Git ou le navigateur. Utiliser un utilisateur MongoDB limité à la base du journal, avec connexion chiffrée pour une base distante. Une installation sans ces variables reste utilisable ; le journal attend sa connexion.

La table `evenement_outbox` existe déjà dans le projet. Aucune nouvelle migration n’est requise. Il faut inclure cette table et la base MongoDB dans les sauvegardes. Les événements transmis restent actuellement conservés dans la file MySQL ; aucune purge automatique n’est activée.

## Vérifications reproductibles

`php tests/verifier-journal.php --local` nécessite une instance MongoDB locale de test sur le port 28719. Le test crée puis supprime une base MongoDB au nom aléatoire, utilise une file SQLite en mémoire pour simuler une panne et annule sa transaction MySQL à la fin. Il contrôle l’ensemble des types d’événements, les deux administrateurs, les filtres, la pagination, l’absence de doublon et la reprise. Il refuse une base métier distante. Ne pas utiliser de serveur MongoDB réel sur ce port de test.

Les contrôles complémentaires se trouvent dans `tests/verifier-acces-prives.php` et `tests/verifier-confidentialite-visiteurs.php` (option `--local`).
