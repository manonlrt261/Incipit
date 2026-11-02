# Jeu de données de démonstration d’Incipit

## Objectif

Préparer un état initial reproductible pour une démonstration sur une base locale
dédiée nommée `incipit_demo`, après application des migrations Doctrine.
Ce jeu ne doit pas être importé dans la bibliothèque publiée ou une base personnelle.

Fichiers fournis :

- `scripts-sql/donnees-demonstration.sql` : INSERT commentés, dans une transaction.
- `scripts-sql/verification-demonstration.sql` : consultations de contrôle de l’état initial.

Le script d’insertion doit être exécuté une seule fois sur une base ne contenant
pas encore de données métier. Les genres préinstallés par les migrations sont admis.
Il n’est pas idempotent : un nouvel import créerait d’autres lignes. Pour rejouer le
scénario depuis son état initial, préparer une autre base de démonstration vierge
avec le même nom sur une instance isolée, ou restaurer une sauvegarde de cet état.

## Données et scénario

### Livre réservé au parcours oral

Le titre « Vingt ans après », son auteur Alexandre Dumas et sa place dans la saga
« Les Trois Mousquetaires » correspondent au scénario retenu. Les valeurs de gestion
(dates, prix et commentaire) sont fictives. Aucun ISBN, pagination, éditeur ou couverture
n’est inventé : ces champs restent absents.

État initial : un souhait au statut `souhaité`, public, sans exemplaire ni session
de lecture, et avec `pile_a_lire = 0`.

Parcours manuel prévu :

1. Retrouver le souhait et constater son caractère public.
2. Le marquer comme acheté : l’application crée l’exemplaire et la première lecture.
3. Ouvrir sa fiche et l’ajouter à la pile à lire.
4. Le retrouver par recherche et filtres, puis commencer la lecture.
5. Terminer la lecture avec une note de 5/5 et des dates cohérentes.
6. Contrôler les sélections publiques si leur visibilité globale est activée,
   puis consulter l’auteur et enregistrer un prêt au contact fictif.

Le script prépare uniquement le départ du parcours. Il ne crée pas à l’avance
l’achat ou la lecture de ce livre.

### Exemple complémentaire entièrement fictif

« Les jardins de papier [DEMO] », de Camille Exemple, permet d’illustrer des
relations déjà remplies : un exemplaire possédé, une lecture terminée notée 5/5
et un prêt en cours au contact fictif Alex Demo. L’adresse utilise le domaine
réservé `example.invalid` ; aucun numéro de téléphone n’est renseigné.

### Identifiants et accès

Les identifiants sont générés par la base et récupérés avec `LAST_INSERT_ID()`
dans la même connexion. Les INSERT parents précèdent les associations et les
enregistrements dépendants. Les clés étrangères restent actives.

Aucun compte ou mot de passe n’est fourni. Pour une démonstration dans le navigateur,
configurer une copie locale de l’application sur cette base et créer un compte de
gestion réservé aux essais avec la commande du projet `app:create-admin`.
Vérifier les réglages de visibilité publique depuis ce compte.

## Utilisation

Sur une instance de développement isolée :

1. Créer `incipit_demo` avec `utf8mb4` et `utf8mb4_unicode_ci`.
2. Configurer explicitement la copie locale pour cette base et appliquer les migrations.
3. Importer `donnees-demonstration.sql` avec le client MySQL ou l’onglet d’import SQL.
4. Arrêter l’import en cas d’erreur ; ne pas poursuivre les instructions manuellement.
5. Exécuter `verification-demonstration.sql` avant toute manipulation dans l’interface.

Exemple avec un compte local autorisé, en adaptant les paramètres de connexion :

```bash
mysql --default-character-set=utf8mb4 -u compte_local -p incipit_demo < docs/base-de-donnees/scripts-sql/donnees-demonstration.sql
mysql --default-character-set=utf8mb4 -u compte_local -p incipit_demo < docs/base-de-donnees/scripts-sql/verification-demonstration.sql
```

Les fichiers sélectionnent explicitement `incipit_demo` avec `USE`.
Ne pas employer une connexion au serveur publié.

## Vérification réellement effectuée le 2 novembre 2026

Le contrôle a utilisé une instance temporaire MySQL **8.4.11**, avec son propre
répertoire de données et son propre socket, sans écoute réseau. Les bases existantes
et l’hébergement Alwaysdata n’ont pas été utilisés.

Les neuf migrations du projet ont été exécutées jusqu’à
`DoctrineMigrations\\Version20261018160000`, puis les deux fichiers SQL ont été lancés
sans erreur. Résultats observés :

- `auteur` : 2 lignes ; `livre` : 2 ; `livre_auteur` : 2.
- `souhait`, `exemplaire`, `session_lecture`, `contact`, `pret` : 1 ligne chacun.
- « Vingt ans après » : auteur Alexandre Dumas, statut `souhaité`, `publique = 1`,
  `pile_a_lire = 0`, zéro exemplaire et zéro session.
- Livre fictif : lecture `Terminé`, note `5.0`, prêt `En cours`, contact Alex Demo,
  retour effectif `NULL`.

Les résultats correspondent aux attendus de ces consultations ; aucun écart n’a
été constaté pour l’import et ces contrôles SQL. Ce contrôle ne constitue pas une
exécution du parcours complet dans le navigateur.

## Journal d’activité

Les INSERT SQL directs ne passent pas par Doctrine et ne déclenchent pas son
écouteur métier. Ils ne créent donc pas les événements du journal d’Incipit.
Pour démontrer le journal, effectuer ensuite les actions dans l’application,
avec une configuration MongoDB de développement et des services isolés de la production.

## Textes proposés pour le dossier

### Dans « Conception et mise en place des données »

Pour préparer une démonstration reproductible, j’ai constitué un jeu de données
SQL réservé à une base de développement dédiée. Il associe des auteurs, des livres,
un souhait, un exemplaire, une session de lecture, un contact et un prêt. Les INSERT
sont ordonnés pour respecter les clés étrangères. Je récupère les identifiants
générés avec LAST_INSERT_ID() afin de créer les associations sans supposer leurs valeurs.

Le livre réservé au parcours, Vingt ans après, est initialement présent dans la
wishlist publique, sans exemplaire ni lecture. Son acquisition et le suivi de sa
lecture sont ensuite réalisés dans l’interface. Un second ouvrage entièrement fictif
illustre les relations déjà renseignées. Le script complet est fourni dans
docs/base-de-donnees/scripts-sql/donnees-demonstration.sql.

### Dans « Tests et validation »

Le 2 novembre 2026, le jeu de démonstration a été importé dans une instance MySQL
8.4.11 isolée, après application des neuf migrations du projet. Les requêtes de
vérification ont confirmé les effectifs attendus et les liens entre les données.
Vingt ans après était bien un souhait public sans exemplaire ni lecture ; le second
ouvrage possédait un exemplaire, une lecture notée 5/5 et un prêt lié au contact fictif.
Aucun écart n’a été constaté pour ces contrôles SQL. Ils valident la préparation des
données et restent distincts de la recette du parcours dans le navigateur.
