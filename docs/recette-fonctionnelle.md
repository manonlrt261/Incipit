# Incipit — Cahier de recette fonctionnelle et suivi des anomalies

Document de travail pour le dossier projet et la préparation de la démonstration au jury.

Date de préparation : 20 octobre 2026. Dernière version envoyée sur le dépôt : `0644a87`, après `958c475`. Le déploiement effectif de cette version doit être confirmé par le résultat des opérations sur Alwaysdata.

## 1. Périmètre et valeur des résultats

J’ai organisé la vérification d’Incipit autour des parcours réellement proposés aux utilisateurs : accéder à l’espace privé, gérer une bibliothèque, suivre des lectures, préparer une pile à lire, gérer des souhaits et des prêts, puis consulter les statistiques et les informations publiques. L’objectif est de vérifier à la fois le résultat visible, la conservation des données et le refus des opérations non autorisées.

L’utilisatrice a indiqué avoir effectué manuellement la vérification des fonctionnalités. Toutefois, les résultats individuels, les dates d’exécution et les captures de chaque essai n’ont pas tous été transmis. Ce document consigne donc les scénarios détaillés et les anomalies effectivement signalées ; il ne transforme pas la déclaration générale d’exécution en validation de chaque cas.

Les rubriques « Résultat manuel » doivent être complétées à partir des observations réelles. Un comportement attendu décrit une exigence ; il ne prouve pas que cette exigence a été satisfaite. De même, une correction enregistrée dans le dépôt ne prouve pas son déploiement ni sa validation sur téléphone.

### Convention de suivi

**À renseigner** : essai annoncé ou prévu, sans résultat individuel transmis. **Conforme** : étapes exécutées et résultat attendu effectivement observé. **Non conforme** : écart observé et reproductible. **À recontrôler** : correction réalisée, mais nouvel essai manuel nécessaire. **Bloqué** : essai impossible, avec sa cause explicitement notée.

Pour chaque exécution, relever la date, la version du site, l’appareil, le navigateur, les critères sélectionnés, le résultat obtenu et la référence de la preuve. Une capture peut montrer le résultat visible ; les scripts automatisés apportent une preuve complémentaire sur des contrôles serveur précis.

## 2. Conditions de réalisation et données d’essai

Les opérations de modification et de suppression doivent porter sur des données de démonstration identifiables. La sauvegarde de la base et des couvertures permet de retrouver l’état préparé. Une sauvegarde récente incluant MongoDB a été produite sur le serveur : `incipit-2026-10-20-150827-0c39a1`. Le retour de cette opération annonce le contrôle du SQL, des couvertures, la relecture du journal MongoDB et la création des empreintes SHA-256. Ce retour ne vaut pas preuve d’une restauration complète : cette dernière reste à documenter séparément.

Préparer deux contextes de navigation : un navigateur connecté avec le compte de démonstration autorisé, et une fenêtre privée sans connexion. Les essais de visibilité publique se contrôlent dans la seconde fenêtre pour éviter de confondre les droits d’administration avec ceux d’un visiteur.

Sur ordinateur et téléphone, noter le système et la version du navigateur réellement utilisés : [À COMPLÉTER]. Ne pas inclure de mot de passe, de jeton de réinitialisation complet, de chaîne MongoDB ni de coordonnées personnelles dans les captures destinées au jury.

### Jeu de données proposé — à créer ou à identifier avant les essais

| Référence | Données à utiliser | Rôle dans la recette |
|---|---|---|
| L-A | Titre « Livre de démonstration A », auteur « Alice Bernard », genre « Roman », saga « Cycle de démonstration », tome 1 | Parcours principal ; valeur fictive proposée, pas un livre annoncé comme créé |
| L-B | Titre « Livre de démonstration B », auteur « Zoé Albert », genre « Polar », tome 2 d’une saga de démonstration | Différencier le tri du nom et du prénom |
| L-C | Livre de test déjà présent avec le même ISBN que celui recherché | Vérifier l’avertissement de doublon |
| L-D | Livre possédé associé à une ancienne session terminée | Vérifier une relecture sans effacement de l’historique |
| L-R | Livre retiré de la collection avec une session notée | Vérifier le retour dans la collection |
| S-A | Souhait privé de démonstration | Contrôler la confidentialité et l’achat |
| C-A | Contact fictif « Camille Démonstration » ; adresse de test uniquement si nécessaire | Vérifier les prêts sans utiliser de données personnelles |
| A-1 / A-2 | Marceau Miller / Antoine de Saint-Exupéry, présents dans la version utilisée par l’utilisatrice | Rejouer l’anomalie de tri signalée ; non trouvés dans la base locale examinée |

Pour les ISBN, choisir ceux de livres réellement utilisés pendant la démonstration : [À COMPLÉTER : ISBN-10 et ISBN-13]. Ne pas inventer un ISBN pour prétendre qu’une recherche externe a réussi. Les titres et contacts fictifs ci-dessus servent à préparer les essais, et ne constituent pas des résultats observés.

## 3. Scénarios détaillés d’essais manuels

Chaque cas ci-dessous est indépendant sauf mention contraire. Après une modification, recharger la page afin de vérifier la persistance du résultat. Les intitulés exacts d’un bouton non reproduit ici doivent être repris de l’interface réellement affichée.

### Accès et authentification

#### T01 — Connexion valide

**Préconditions et données.** Compte de démonstration actif ; fenêtre déconnectée.

**Procédure.** Ouvrir la connexion ; saisir les identifiants autorisés ; valider ; accéder à Mes lectures.

**Résultat attendu.** L’espace privé est accessible et les données autorisées sont visibles.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Capture après connexion sans identifiants sensibles.

#### T02 — Connexion refusée

**Préconditions et données.** Fenêtre déconnectée.

**Procédure.** Saisir un identifiant ou un mot de passe incorrect ; valider ; essayer ensuite une URL privée.

**Résultat attendu.** Le message « Identifiant ou mot de passe invalide. » apparaît ; aucune session privée n’est ouverte.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Message d’erreur et refus d’accès.

#### T03 — Affichage du mot de passe

**Préconditions et données.** Champ de connexion contenant une valeur de test.

**Procédure.** Cliquer sur l’œil ; vérifier le texte affiché ; cliquer de nouveau ; répéter sur la page de nouveau mot de passe.

**Résultat attendu.** La valeur alterne entre masquée et visible ; les images afficher2 et masquer2 correspondent à l’action disponible.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Captures utilisant exclusivement une valeur fictive.

#### T04 — Déconnexion et accès direct

**Préconditions et données.** Session ouverte.

**Procédure.** Se déconnecter ; saisir directement les URL des lectures, prêts, profil et journal ; actualiser une ancienne page privée.

**Résultat attendu.** Les nouvelles requêtes privées sont refusées ou dirigées vers la connexion ; aucune modification n’est possible.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** URL demandée et résultat ; ne pas interpréter une ancienne capture en cache comme une requête autorisée.

#### T05 — Visiteur et modification

**Préconditions et données.** Fenêtre privée sans connexion.

**Procédure.** Ouvrir les pages publiques ; tenter l’accès aux formulaires privés par leurs URL ; contrôler l’absence d’actions de modification accessibles.

**Résultat attendu.** Le visiteur peut consulter le contenu public autorisé, sans modifier les livres, sessions, prêts ni paramètres.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Capture publique et refus d’une page privée.

### Réinitialisation du mot de passe

#### T06 — Demande et réception

**Préconditions et données.** Boîte de démonstration accessible.

**Procédure.** Demander un lien ; vérifier la boîte et les indésirables ; relever heure de demande et de réception.

**Résultat attendu.** Un message est reçu et contient un lien exploitable ; le délai réel est consigné sans estimation inventée.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Capture du courriel avec lien et données sensibles masqués.

#### T07 — Lien utilisé avant expiration

**Préconditions et données.** Lien récent non utilisé.

**Procédure.** Ouvrir le lien avant quinze minutes ; saisir un mot de passe conforme et sa confirmation ; enregistrer ; se reconnecter.

**Résultat attendu.** La réinitialisation aboutit ; le nouveau mot de passe permet la connexion.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Heures de demande et d’utilisation ; confirmation visible.

#### T08 — Lien expiré

**Préconditions et données.** Lien non utilisé ayant dépassé quinze minutes.

**Procédure.** Attendre plus de quinze minutes depuis sa génération ; ouvrir le lien ; tenter d’accéder à la réinitialisation.

**Résultat attendu.** La demande n’est plus acceptée ; un nouveau lien est nécessaire. La configuration vérifiée fixe la durée à 900 secondes.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Heures réelles et message obtenu.

#### T09 — Lien déjà utilisé

**Préconditions et données.** Réinitialisation précédente réussie.

**Procédure.** Rouvrir le même lien après son utilisation ; tenter un second changement.

**Résultat attendu.** Le lien utilisé ne permet pas une nouvelle réinitialisation.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Résultat après réouverture ; URL masquée.

#### T10 — Validation des mots de passe

**Préconditions et données.** Formulaire de nouveau mot de passe ouvert.

**Procédure.** Tester une confirmation différente ; tester une valeur trop courte ; corriger avec une valeur conforme.

**Résultat attendu.** Les valeurs invalides sont refusées ; le compte reste utilisable ; aucune valeur sensible n’est affichée dans la preuve.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Messages réellement obtenus.

### Livres et métadonnées

#### T11 — Ajout manuel complet

**Préconditions et données.** Jeu L-A disponible ; connexion ouverte.

**Procédure.** Ouvrir Ajouter un livre ; saisir titre, auteur, genres, saga et tome ; ajouter une couverture si nécessaire ; enregistrer ; recharger.

**Résultat attendu.** Une seule fiche apparaît avec les valeurs saisies et un exemplaire actif ; les informations persistent.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Fiche avant validation puis après rechargement.

#### T12 — Modification d’une fiche

**Préconditions et données.** L-A enregistré.

**Procédure.** Modifier le titre de test, un auteur, les genres, la saga et le tome ; enregistrer ; rouvrir la fiche.

**Résultat attendu.** La même fiche contient les nouvelles valeurs ; aucune duplication involontaire n’apparaît.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Comparaison avant/après avec identifiant ou titre repérable.

#### T13 — Auteurs et genres multiples

**Préconditions et données.** Livre de test modifiable.

**Procédure.** Associer plusieurs auteurs et genres ; retirer une association ; enregistrer ; vérifier les filtres et les détails.

**Résultat attendu.** Les associations correspondent au choix final ; le retrait d’une association ne supprime pas les autres livres.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Détails et recherche correspondante.

#### T14 — Avertissement de doublon

**Préconditions et données.** L-C déjà présent.

**Procédure.** Rechercher ou saisir le même ISBN dans l’ajout ; tenter de poursuivre ; lire l’avertissement avant toute validation.

**Résultat attendu.** Le doublon potentiel est signalé ; consigner précisément les choix proposés et la conséquence de chaque choix testé.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Avertissement et décision ; ne pas affirmer un blocage absolu si l’interface permet de confirmer.

#### T15 — Retrait et retour dans la collection

**Préconditions et données.** L-R possède une session et un exemplaire actif avant le retrait.

**Procédure.** Retirer le livre selon le parcours proposé ; ouvrir ses détails parmi les retirés ; cliquer Rajouter à ma collection ; recharger.

**Résultat attendu.** Le livre revient dans Mes lectures ; sa fiche et ses sessions sont conservées ; il disparaît de la liste des livres actuellement retirés.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Session avant/après et apparition dans la collection.

#### T16 — Résistance au double clic de restauration

**Préconditions et données.** L-R retiré ; détails ouverts.

**Procédure.** Déclencher le retour ; vérifier que le bouton se désactive ; répéter uniquement si le parcours reste disponible.

**Résultat attendu.** Un seul exemplaire actif est créé ; la fiche n’est pas dupliquée. Ce point dispose aussi d’un contrôle serveur automatisé.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Résultat visible ; référence du contrôle automatisé en complément.

### Sessions, dates et notes

#### T17 — Ajout d’une session

**Préconditions et données.** Livre de test existant.

**Procédure.** Créer une session avec statut, dates cohérentes, note et avis de démonstration ; enregistrer ; recharger.

**Résultat attendu.** La session appartient au bon livre et les valeurs sont conservées.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Détails de la session après rechargement.

#### T18 — Modification d’une session

**Préconditions et données.** Session de test enregistrée.

**Procédure.** Changer statut, note, dates et avis ; enregistrer ; consulter de nouveau la fiche.

**Résultat attendu.** La session sélectionnée est modifiée ; les autres sessions restent intactes.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Avant/après de la session et nombre de sessions.

#### T19 — Suppression ciblée

**Préconditions et données.** Livre contenant au moins deux sessions de test.

**Procédure.** Supprimer une session ; vérifier le livre et l’autre session ; recharger.

**Résultat attendu.** Seule la session choisie disparaît ; le livre et l’autre historique sont conservés.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Nombre de sessions et session restante.

#### T20 — Dates incohérentes et notes limites

**Préconditions et données.** Formulaire de session ouvert.

**Procédure.** Tester une fin antérieure au début ; tester une note hors limites avec les moyens autorisés par le formulaire ; tester ensuite 0 et 5 si ces valeurs sont proposées.

**Résultat attendu.** Les données incohérentes sont refusées ; les valeurs limites valides sont traitées comme prévu ; relever les messages exacts.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Valeurs soumises et messages ; ne pas fabriquer de résultat serveur.

#### T21 — Note absente et zéro

**Préconditions et données.** Deux livres ou sessions de test.

**Procédure.** Laisser une note vide sur le premier ; enregistrer 0 sur le second ; comparer l’affichage et le filtre.

**Résultat attendu.** Une note absente reste distincte de 0 ; le filtre 0 ne doit pas inclure les livres sans note.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Détails et résultat du filtre 0.

#### T22 — Tranche de note et date d’ajout

**Préconditions et données.** Plusieurs livres avec dates distinctes, dont des notes 1 et 1,5.

**Procédure.** Choisir la tranche 1★ ; choisir les ajouts les plus anciens ; valider ; inverser l’ordre de sélection des critères.

**Résultat attendu.** La sélection conserve la tranche de note et le tri par ancienneté. La règle actuelle regroupe 1 et 1,5 dans la tranche 1★.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Critères visibles et ordre des résultats.

### Pile à lire

#### T23 — Ajout et retrait simple

**Préconditions et données.** Livre possédé actif.

**Procédure.** Ajouter le livre à la pile ; vérifier sa présence ; le retirer de la pile ; revenir dans Mes lectures.

**Résultat attendu.** La sélection de pile change ; le livre reste dans la bibliothèque et son historique reste conservé.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Pile avant/après et fiche toujours présente.

#### T24 — Commencement d’une lecture

**Préconditions et données.** Livre placé dans la pile.

**Procédure.** Utiliser l’action de commencement ; ouvrir ses sessions ; consulter le statut et la date de début.

**Résultat attendu.** Une session non terminée est réutilisée, ou une nouvelle session est créée si nécessaire ; elle passe En cours. Le code conserve la pile jusqu’à l’action prévue de retrait.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Session après commencement ; ne pas confondre commencer et terminer.

#### T25 — Fin de lecture et retrait automatique

**Préconditions et données.** Livre de la pile avec session en cours.

**Procédure.** Passer la session à Terminé ; enregistrer ; contrôler la pile, Mes lectures et l’historique après rechargement.

**Résultat attendu.** Le livre sort automatiquement de la pile selon la règle métier attendue et conserve sa lecture terminée.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** État des trois vues ; résultat manuel à confirmer.

#### T26 — Relecture

**Préconditions et données.** L-D avec ancienne session terminée.

**Procédure.** Remettre le livre dans la pile ; commencer une nouvelle lecture ; comparer les sessions.

**Résultat attendu.** Une nouvelle lecture peut commencer sans remplacer l’ancienne session terminée.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Ancienne et nouvelle sessions visibles ensemble.

### Wishlist et confidentialité

#### T27 — Ajout et modification d’un souhait

**Préconditions et données.** Livre de démonstration non possédé.

**Procédure.** Ajouter un souhait ; renseigner les informations proposées ; modifier ces informations ; recharger.

**Résultat attendu.** Le souhait est conservé avec ses valeurs finales et n’est pas présenté comme un exemplaire déjà possédé.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Souhait après ajout et modification.

#### T28 — Achat d’un souhait

**Préconditions et données.** S-A présent.

**Procédure.** Déclencher l’achat ; vérifier la wishlist et la bibliothèque ; recharger.

**Résultat attendu.** Le livre est traité comme acquis selon le parcours prévu ; consigner le devenir exact du souhait et l’existence d’un exemplaire actif.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Avant/après dans les deux pages.

#### T29 — Suppression d’un souhait

**Préconditions et données.** Souhait de test identifié.

**Procédure.** Supprimer le souhait ; confirmer si demandé ; recharger et rechercher le même titre.

**Résultat attendu.** Le souhait choisi est retiré ; les autres éléments restent inchangés.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Résultat et compteur.

#### T30 — Souhait privé

**Préconditions et données.** S-A configuré privé.

**Procédure.** Se déconnecter dans une autre fenêtre ; consulter la page publique et les détails accessibles par URL.

**Résultat attendu.** Le souhait privé et ses détails ne sont pas exposés au visiteur.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Page publique et refus d’accès direct.

#### T31 — Souhait public et retour au privé

**Préconditions et données.** Souhait de test initialement privé.

**Procédure.** Activer sa publication ; contrôler en fenêtre privée ; désactiver ; recharger la fenêtre privée.

**Résultat attendu.** La visibilité suit le paramètre réellement enregistré ; le retour au privé retire l’information du parcours public.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Deux états publics sans session administrateur.

### Prêts et contacts

#### T32 — Création et modification d’un contact

**Préconditions et données.** Contact fictif C-A.

**Procédure.** Créer le contact avec les données requises ; modifier son nom ou une information de test ; vérifier sa fiche.

**Résultat attendu.** Le contact est enregistré et modifié sans doublon accidentel.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Fiche du contact avec données fictives.

#### T33 — Création et retour d’un prêt

**Préconditions et données.** Livre possédé disponible et C-A.

**Procédure.** Créer un prêt ; vérifier livre, contact et dates ; utiliser le parcours de retour ; recharger.

**Résultat attendu.** Le prêt est rattaché au bon exemplaire et au bon contact ; le retour modifie son état sans effacer les autres prêts.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Prêt avant/après et état affiché.

#### T34 — Suppression et contraintes du contact

**Préconditions et données.** Contact fictif ; éventuel prêt associé.

**Procédure.** Tester la suppression sur un contact sans historique puis, si pertinent, sur un contact associé à un prêt ; noter les confirmations ou refus.

**Résultat attendu.** Aucune référence incohérente n’est créée ; consigner la règle réellement appliquée plutôt que supposer une suppression en cascade.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Message de confirmation ou de refus.

#### T35 — Filtres combinés des prêts

**Préconditions et données.** Plusieurs prêts avec genres, contacts, auteurs et dates distincts.

**Procédure.** Choisir un genre ; un tri auteur ; un tri contact ; une date ; valider ; réinitialiser.

**Résultat attendu.** Les sélections ne s’effacent pas entre elles ; le dernier tri choisi est prioritaire, les précédents départagent les égalités ; réinitialiser rétablit la liste.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Sélections et liste obtenue.

### Statistiques, objectifs et accueil public

#### T36 — Cohérence des statistiques

**Préconditions et données.** Petit jeu de lectures dont le total est connu.

**Procédure.** Relever le total attendu ; consulter la même période dans les statistiques ; modifier une session de test puis vérifier de nouveau.

**Résultat attendu.** Les agrégats correspondent aux données et à la période retenue ; préciser si le calcul compte des livres distincts ou des sessions selon l’indicateur.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Calcul manuel de référence et capture.

#### T37 — Objectif de lecture

**Préconditions et données.** Objectif et année identifiés.

**Procédure.** Créer ou modifier l’objectif ; vérifier la progression ; recharger ; choisir une autre année si proposé.

**Résultat attendu.** La valeur est conservée et la progression utilise le bon périmètre temporel.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Objectif avant/après ; calcul indépendant.

#### T38 — Ordre des éléments publics

**Préconditions et données.** Compte autorisé et éléments de démonstration.

**Procédure.** Modifier l’ordre dans l’éditeur ; enregistrer ; ouvrir la page publique sans connexion ; recharger.

**Résultat attendu.** L’ordre public suit celui enregistré ; les éléments masqués restent absents.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Éditeur et page publique pour le même état.

### Recherches, tris et appareils

#### T39 — Recherche ISBN

**Préconditions et données.** ISBN réel relevé dans le jeu d’essai.

**Procédure.** Rechercher l’ISBN-13 puis l’ISBN-10 ; tester une valeur avec séparateurs si acceptée ; contrôler les champs préremplis.

**Résultat attendu.** Le livre proposé correspond à l’édition recherchée ; aucune équivalence d’édition n’est supposée sans vérification.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** ISBN utilisé et résultat ; réseau ou absence de résultat à consigner.

#### T40 — Recherche de couverture en ligne

**Préconditions et données.** Livre de test avec titre et auteur.

**Procédure.** Ouvrir la recherche de couverture ; saisir le titre ; consulter les résultats ; choisir une image ; enregistrer ; recharger.

**Résultat attendu.** La barre reste compacte ; l’image sélectionnée apparaît dans la fiche et persiste.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Recherche ouverte et couverture finale.

#### T41 — Webcam sur ordinateur

**Préconditions et données.** Ordinateur équipé ; autorisation navigateur disponible.

**Procédure.** Ouvrir le scan ISBN ; accepter la webcam ; présenter un code ; fermer le scan ; vérifier l’arrêt de la caméra.

**Résultat attendu.** La webcam s’ouvre sans sélecteur de caméra destiné au mobile ; la fermeture libère le flux. La reconnaissance du code doit être observée sur le matériel réel.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Appareil, navigateur et résultat de reconnaissance.

#### T42 — Appareil photo sur téléphone

**Préconditions et données.** Téléphone équipé et ISBN réel.

**Procédure.** Ouvrir le scan ; photographier le code entier net ; valider ; vérifier le résultat ; recommencer en annulant une capture.

**Résultat attendu.** Le parcours mobile ouvre la capture prévue ; l’annulation ne crée pas de livre ni de recherche erronée.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Appareil, orientation et résultat réel.

#### T43 — Recherche du tableau de bord

**Préconditions et données.** Livre possédé avec titre, auteur, saga et ISBN connus.

**Procédure.** Rechercher chacun de ces champs ; vérifier la page obtenue ; essayer une recherche sans résultat et effacer la recherche.

**Résultat attendu.** Les résultats restent sur Mon tableau de bord ; les souhaits seuls et les livres retirés ne sont pas ajoutés aux résultats de collection.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** URL, terme et liste obtenue.

#### T44 — Tri alphabétique des lectures

**Préconditions et données.** Auteurs aux noms et prénoms distincts ; exemple signalé disponible en production.

**Procédure.** Choisir Prénom : Croissant puis Nom : Croissant ; contrôler les premiers résultats ; tester les ordres décroissants ; vérifier aussi le menu Genre.

**Résultat attendu.** Antoine doit précéder Marceau par prénom croissant. Le tri par nom utilise le nom de famille. Les genres du menu suivent l’ordre alphabétique.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Critères complets et deux cartes mal ou bien placées ; après correction, statut À recontrôler.

#### T45 — Combinaison et conservation des critères

**Préconditions et données.** Plusieurs livres et souhaits de démonstration.

**Procédure.** Choisir recherche, genre, statut et tri ; valider ; modifier une carte ou ajouter un livre lorsque le parcours le permet ; contrôler les critères ; réinitialiser.

**Résultat attendu.** Les critères restent combinés pendant l’utilisation de la page et son actualisation dynamique. La conservation après navigation ou fermeture du navigateur n’est pas présumée.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Valeurs des menus avant/après et résultat de réinitialisation.

#### T46 — Zoom mobile et mise en page

**Préconditions et données.** Téléphone réel ; page rechargée après déploiement.

**Procédure.** Toucher les champs de recherche des lectures, du tableau de bord et des fenêtres modales ; saisir ; valider ; refermer le clavier ; faire défiler.

**Résultat attendu.** La saisie ne provoque pas le zoom automatique lié aux polices trop petites ; les contrôles restent accessibles ; le zoom manuel demeure possible.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Vidéo courte ou captures avant/après, avec navigateur et version.

#### T47 — Navigation au clavier et fermeture des fenêtres

**Préconditions et données.** Ordinateur ; fenêtre d’ajout ou de détail ouverte.

**Procédure.** Parcourir les champs avec Tab ; activer les commandes avec le clavier ; fermer avec le bouton prévu ; rouvrir la fenêtre.

**Résultat attendu.** Le focus reste visible, les actions sont accessibles et la réouverture ne cumule pas les traitements.

**Résultat manuel.** [À RENSEIGNER : observation réelle, date, version, appareil, conforme / non conforme / bloqué.]

**Preuve à conserver.** Observation du focus et résultat après réouverture.

## 4. Registre des anomalies signalées et des corrections

Les versions indiquées ci-dessous sont celles du dépôt. Leur présence dans une version publiée doit être confirmée sur Alwaysdata. La priorité traduit l’effet sur la démonstration : P1 pour un parcours essentiel perturbé, P2 pour un résultat ou une présentation dégradés. Elle ne constitue pas une classification de vulnérabilité.

| Réf. | Anomalie et effet observé | Correction réalisée | Validation disponible et limite | Priorité / suite |
|---|---|---|---|---|
| AN-01 | À la connexion, le message « Identifiants invalides » ne correspondait pas au texte demandé. | Traduction « Identifiant ou mot de passe invalide. », version 958c475. | Catalogue de traduction et syntaxe vérifiés ; refaire T02 sur le site publié. | P2 — À recontrôler |
| AN-02 | L’œil de la connexion ne révélait pas le mot de passe. | Réutilisation du mécanisme de la page de réinitialisation ; correction du branchement du bouton et chargement du module, 958c475. | Contrôles locaux de bascule, images, attributs d’accessibilité et navigation Turbo signalés réussis ; essai mobile réel à conserver. | P1 — T03 |
| AN-03 | La recherche du tableau de bord renvoyait vers Ma pile à lire. | Recherche traitée sur la route du tableau de bord ; titre, auteur, saga et ISBN ; résultats paginés, 958c475. | Script local réussi : champs recherchés, ISBN formatés, pagination, exclusion des souhaits seuls et des retraits, saisies traitées littéralement. | P1 — T43 |
| AN-04 | Choisir un tri effaçait les autres critères, empêchant notamment 1★ + ajouts anciens. | Gestion partagée conservant les valeurs des filtres ; dernier tri choisi prioritaire, puis critères secondaires, 958c475. | Vérifications de logique JavaScript réussies ; intégration réelle à confirmer dans les trois pages. | P1 — T22, T35, T45 |
| AN-05 | Les quatre variantes nom/prénom demandées n’étaient pas disponibles dans Mes lectures. | Ajout des quatre choix et des données de nom/prénom dans les cartes, 958c475. | Tests locaux des quatre sens réussis ; l’anomalie AN-11 montre qu’une validation sur données réelles reste nécessaire. | P2 — T44 |
| AN-06 | Un livre retiré ne pouvait pas être remis simplement dans la collection. | Ajout du bouton centré « Rajouter à ma collection » et d’une action serveur protégée ; nouvelle copie active, ancienne fiche et historique conservés, 958c475. | Script transactionnel local réussi : CSRF, absence de doublon après répétition, note conservée, retour dans Mes lectures, refus pour un livre sans retrait. | P1 — T15 et T16 |
| AN-07 | Sur ordinateur, le scanner proposait un choix de caméra non souhaité. | Ouverture directe de la webcam et masquage du sélecteur sur ordinateur ; parcours mobile distinct, 958c475. | Syntaxe JavaScript vérifiée ; ouverture et reconnaissance sur webcam réelle à documenter. | P2 — T41 et T42 |
| AN-08 | La recherche de couverture produisait un très grand encadré. | Correction d’une base flexible de 640 px appliquée verticalement ; barre en ligne à hauteur automatique, 958c475. | Test de rendu local : barre de 280 × 47 px dans une zone de 280 px, pour des fenêtres de 1100 et 390 px. Ce test ne vaut pas essai complet sur Safari. | P2 — T40 |
| AN-09 | Les boutons du journal ne correspondaient pas aux autres pages. | Réutilisation de bibliotheque-valider et bibliotheque-reinitialiser ; libellé Valider et symbole de réinitialisation, 958c475. | Modèle Twig valide ; vérification visuelle publiée à conserver. | P2 — Contrôle visuel du journal |
| AN-10 | Les genres du filtre de Mes lectures n’étaient pas classés alphabétiquement. | Ordre par nom, puis identifiant pour départager les égalités, 0644a87. | Syntaxe PHP et contrôles locaux associés réussis ; liste publiée à recontrôler. | P2 — T44 |
| AN-11 | Par prénom croissant, Marceau Miller apparaissait avant Antoine de Saint-Exupéry. | Alignement des données de tri sur le service de présentation des auteurs, avec traitement des anciennes fiches sans prénom séparé, 0644a87. | Un test local simulant une ancienne fiche place Antoine avant Marceau. Les deux fiches réelles n’étaient pas présentes dans la base locale ; la cause en production n’est donc pas confirmée. | P1 — Rejouer exactement T44 avec ces deux auteurs avant de clôturer |
| AN-12 | Sur téléphone, la saisie d’une recherche zoomait la page et le zoom persistait. | Taille des champs à 16 px sous 700 px, y compris dans les fenêtres modales, sans désactiver le zoom manuel, 0644a87. | Règle et différences de code contrôlées ; pas de preuve d’essai sur l’iPhone réel après déploiement. | P2 — T46 indispensable |

### Incident technique complémentaire : journal MongoDB

La connexion du journal a rencontré plusieurs difficultés distinctes : extension PHP absente, extension compilée pour une autre version de PHP, configuration différente entre le terminal et le serveur web, erreur d’authentification, puis délai de connexion lié aux accès réseau. Les retours transmis ont permis de distinguer ces causes au lieu de conclure à une panne unique.

Après compilation et configuration de l’extension pour PHP 8.4, la connexion du terminal a réussi. Le journal web est resté indisponible jusqu’à l’activation de l’extension dans sa propre configuration et à l’autorisation de l’adresse du serveur web dans Atlas. Les captures suivantes ont montré des événements effectivement affichés dans le journal. Les tâches périodiques ont ensuite été configurées pour la reprise des événements et les sauvegardes.

Une exécution de transmission a retourné zéro événement, un code de sortie 0 et une durée de 0,604 seconde. Ce résultat atteste que cette exécution s’est terminée sans erreur ; en l’absence d’événement à transmettre, il ne démontre pas à lui seul la reprise d’une file non vide. La sauvegarde manuelle du 20 octobre à 15:08:27 a annoncé une relecture et une validation du journal MongoDB. La première sauvegarde automatique quotidienne et une restauration complète doivent faire l’objet de preuves distinctes.

## 5. Parcours représentatif : ajout d’un livre et suivi de sa lecture

### 5.1 Objectif et justification

J’ai retenu le parcours allant de l’ajout d’un livre à la fin de sa lecture, car il mobilise l’interface, les contrôles de saisie, les données relationnelles et leur restitution dans plusieurs pages. Ce parcours montre aussi l’intérêt de conserver l’historique : terminer une lecture ne supprime ni le livre, ni ses métadonnées, ni la possibilité de le relire.

Il est pertinent pour expliquer au jury la séparation entre un livre, ses exemplaires et ses sessions. La fiche décrit l’ouvrage ; l’exemplaire traduit sa présence dans la collection ; les sessions enregistrent les lectures successives. Le journal MongoDB peut compléter la démonstration en conservant la trace des actions prises en charge, sans remplacer la base relationnelle qui porte les données métier.

Le déroulé ci-dessous constitue un jeu d’essai reproductible. Les résultats obtenus restent à renseigner après une exécution suivie de bout en bout sur une même version. Les résultats d’essais séparés ne doivent pas être présentés comme ceux d’un parcours complet s’il n’a pas été exécuté ainsi.

### 5.2 État initial à relever

Utiliser L-A, ou un autre livre clairement identifié dans la recette. Noter son titre, son auteur, les genres, la saga et le tome choisis. Avant sa création, rechercher son titre et son ISBN pour établir qu’il n’est pas déjà présent. Relever le compteur des livres possédés, celui de la pile et celui des lectures terminées si ces compteurs sont utilisés comme preuves.

Noter les valeurs initiales : livres possédés [À COMPLÉTER], livres dans la pile [À COMPLÉTER], lectures terminées sur la période choisie [À COMPLÉTER]. Préparer deux dates cohérentes, dans la même période d’analyse : début [À COMPLÉTER] et fin [À COMPLÉTER]. La note proposée pour l’essai est 4,5/5, uniquement si l’interface accepte cette granularité ; sinon utiliser une valeur proposée et la consigner.

### 5.3 Étape A — Ouvrir une session autorisée

Je me connecte avec le compte de démonstration et j’ouvre Mes lectures. J’observe que les commandes de gestion sont accessibles. Dans une fenêtre privée séparée, je vérifie que le même parcours privé demande une connexion. Cette double observation montre que l’accès ne repose pas seulement sur la présence ou l’absence visuelle d’un bouton.

**Attendu :** accès de l’administrateur et refus du visiteur. **Obtenu :** [À COMPLÉTER]. **Preuves :** P-A01 pour l’espace connecté ; P-A02 pour le refus d’accès anonyme.

### 5.4 Étape B — Créer le livre et ses associations

J’ouvre l’ajout d’un livre et je renseigne les données préparées. Pour ce parcours principal, l’ajout manuel permet de maîtriser les valeurs ; les recherches ISBN et couvertures restent des essais complémentaires afin qu’un aléa de service externe ne masque pas la validation de l’enregistrement métier.

Je renseigne le titre, un auteur, un ou plusieurs genres, la saga et le tome. Je vérifie les valeurs avant validation. Après enregistrement, je retrouve la carte et j’ouvre les détails. Je recharge ensuite la page pour vérifier que le résultat n’est pas limité à un affichage temporaire du navigateur.

**Attendu :** une fiche unique, les associations correctes et un exemplaire actif. Pour une seule nouvelle fiche possédée, le compteur concerné augmente d’une unité ; il faut comparer des compteurs ayant le même périmètre. **Obtenu :** [À COMPLÉTER]. **Preuves :** P-B01 avant validation, P-B02 après rechargement. **Écart éventuel :** [À COMPLÉTER].

### 5.5 Étape C — Modifier une information contrôlable

Je corrige une information de démonstration, par exemple le tome ou le genre, puis j’enregistre. Je rouvre la même fiche pour comparer la valeur finale à la saisie. Je vérifie que les sessions et les autres livres ne sont pas affectés.

Cette étape distingue la modification d’une fiche de la création d’un second livre. **Attendu :** les données finales sont présentes sur la même fiche ; le nombre de livres ne change pas à cause de cette seule modification. **Obtenu :** [À COMPLÉTER]. **Preuve :** P-C01.

### 5.6 Étape D — Ajouter à la pile et commencer la lecture

J’ajoute le livre à Ma pile à lire, puis je consulte cette page. Je déclenche ensuite l’action de commencement. J’ouvre les sessions du livre pour contrôler le statut En cours et la date de début. Le contrôleur examine la dernière session : il peut réutiliser une session non terminée ou en créer une nouvelle si aucune session ne convient. Il ne faut donc pas annoncer systématiquement une session supplémentaire sans vérifier l’état initial.

**Attendu :** le livre est sélectionné dans la pile ; le commencement produit une session en cours et conserve l’historique existant. **Obtenu :** [À COMPLÉTER : session créée ou réutilisée, date et statut]. **Preuves :** P-D01 pour la pile et P-D02 pour la session.

### 5.7 Étape E — Vérifier un refus de données incohérentes

Avant la clôture, je tente une date de fin antérieure à la date de début. Je relève la réaction exacte du formulaire ou du serveur. Je vérifie ensuite que la session précédente n’a pas été remplacée par les données invalides. Je corrige les dates et poursuis le parcours.

Cette étape démontre un contrôle négatif : vérifier une application ne consiste pas uniquement à lui fournir des données correctes. **Attendu :** refus des données incohérentes et conservation de l’état valide précédent. **Obtenu :** [À COMPLÉTER]. **Preuve :** P-E01 avec valeurs de test et message.

### 5.8 Étape F — Terminer, noter et commenter la lecture

Je passe la session à Terminé, je renseigne la date de fin cohérente, la note retenue et un avis fictif reconnaissable. Après enregistrement et rechargement, je vérifie le statut, les deux dates, la note et l’avis. Je consulte Ma pile à lire pour contrôler le retrait automatique attendu, puis les statistiques sur la même période.

**Attendu :** la session terminée est conservée, le livre reste possédé et la pile reflète la règle de fin de lecture. Les statistiques évoluent selon leur définition, sans supposer que tous les compteurs comptent des sessions plutôt que des livres distincts. **Obtenu :** [À COMPLÉTER : valeurs avant/après et période]. **Preuves :** P-F01 pour la session, P-F02 pour la pile et P-F03 pour les statistiques.

### 5.9 Étape G — Retrouver le livre avec des critères combinés

Je recherche le titre ou l’auteur dans Mes lectures. J’ajoute le genre et le statut Terminé, puis le tri par prénom. Je vérifie que les sélections restent visibles et que le livre attendu fait partie des résultats. Pour un contrôle d’ordre, il faut conserver plusieurs résultats : un seul livre trouvé ne prouve pas un tri correct.

Je rejoue séparément l’exemple Antoine de Saint-Exupéry et Marceau Miller par prénom croissant. Je note tous les autres tris actifs : un changement ultérieur de tri devient prioritaire et peut expliquer un classement différent. Pour vérifier uniquement le tri alphabétique, je réinitialise d’abord les critères, puis je sélectionne le tri à contrôler.

**Attendu :** critères cumulés, ordre correspondant au tri prioritaire et remise à zéro complète par Réinitialiser. **Obtenu :** [À COMPLÉTER]. **Preuve :** P-G01 montrant les menus et plusieurs résultats.

### 5.10 Étape H — Consulter la traçabilité et vérifier une relecture

Je consulte le journal d’activité pour chercher les événements correspondant aux actions prises en charge, en utilisant le titre et les dates. Une absence doit être étudiée : filtre trop restrictif, événement en attente ou indisponibilité ne doivent pas être confondus. Je vérifie ensuite qu’une nouvelle lecture peut être commencée en conservant la précédente terminée.

**Attendu :** la traçabilité disponible correspond aux actions enregistrées ; une relecture ne réécrit pas l’ancienne session terminée. **Obtenu :** [À COMPLÉTER]. **Preuves :** P-H01 pour le journal et P-H02 pour les deux sessions.

### 5.11 Extension — Retirer puis racheter le livre

En extension du parcours, je retire le livre de la collection, j’ouvre ses détails dans les retirés et je sélectionne Rajouter à ma collection. Le traitement crée un exemplaire actif tout en conservant l’ancien retrait. Il ne demande pas de ressaisir la fiche et ne réinitialise pas les lectures.

**Attendu :** retour du livre parmi les possédés, conservation de ses informations et de son historique ; aucune seconde copie active créée par la répétition de la même action de restauration. **Obtenu :** [À COMPLÉTER]. **Preuve :** P-X01 et référence au script de restauration.

## 6. Vérifications automatisées complémentaires déjà disponibles

Ces contrôles complètent les observations manuelles ; ils ne remplacent pas la vérification de la réception d’un courriel, d’une webcam réelle ou du comportement de Safari sur téléphone.

| Vérification | Résultat effectivement rapporté pendant le travail | Limite à présenter au jury |
|---|---|---|
| Protection des parcours privés | 171 contrôles locaux réussis après l’ajout de la restauration : refus sans connexion, refus sans rôle administrateur, CSRF et autres assertions du script. | 171 désigne des contrôles, pas 171 parcours utilisateur manuels. L’ensemble des autorisations possibles n’est pas prouvé par ce seul chiffre. |
| Recherche du tableau de bord | Script local réussi pour titre, auteur, saga, ISBN formaté, pagination, recherches vides, exclusions et paramètres liés. | Ne mesure pas la pertinence d’un service externe et ne constitue pas une mesure de charge. |
| Restauration de collection | Script local réussi pour CSRF, conservation de la fiche et de la note, absence de doublon, retour dans Mes lectures et refus hors retraits. | Les données de test sont temporaires dans une transaction annulée ; vérifier aussi l’interface publiée. |
| Tris et critères combinés | Tests de logique JavaScript réussis pour les quatre tris d’auteur, note et ancienneté, wishlist et prêts. | Un jeu de données construit ne reproduit pas nécessairement toutes les anciennes fiches de production. |
| Ancienne fiche auteur | Simulation locale réussie : Antoine avant Marceau lorsque le prénom de l’ancienne fiche est reconstitué. | La cause exacte de l’anomalie réelle n’a pas été démontrée sur les deux fiches publiées. |
| Recherche de couverture | Mesure de rendu locale 280 × 47 px dans les deux largeurs de fenêtre testées. | Pas une validation de reconnaissance ISBN ni un essai sur Safari réel. |
| Syntaxe et configuration | 38 modèles Twig, un fichier YAML de traduction et le conteneur de production ont passé les contrôles avant la publication 958c475 ; les modifications PHP suivantes ont passé la vérification de syntaxe. | Une syntaxe valide ne garantit pas la conformité fonctionnelle. |

Les scripts versionnés se trouvent dans le dossier `tests` du projet : `verifier-acces-prives.php`, `verifier-recherche-dashboard.php`, `verifier-restauration-collection.php`, ainsi que des scripts de confidentialité et de journal. Leur existence ne signifie pas qu’ils ont tous été réexécutés sur la dernière version. Présenter le résultat de chaque lancement avec sa date et sa version, sans attribuer un succès récent aux tests simplement présents dans le dépôt.

## 7. Exploitation des résultats et clôture de la recette

### Ordre conseillé pour compléter les preuves

1. Confirmer la version réellement publiée et identifier l’appareil et le navigateur de chaque campagne.
2. Rejouer les anomalies AN-02, AN-03, AN-04, AN-06 et AN-11, qui touchent directement les parcours de démonstration.
3. Rejouer sur téléphone AN-07, AN-08 et AN-12 ; un écran réduit sur ordinateur ne remplace pas cet essai.
4. Compléter les 47 résultats manuels à partir des observations réelles. Si un scénario n’a pas été exécuté, le laisser explicitement non exécuté.
5. Exécuter le parcours représentatif de bout en bout et enregistrer les références P-A01 à P-X01 utiles.
6. Corriger chaque écart, rejouer le scénario qui échouait, puis vérifier les fonctionnalités voisines affectées.
7. Conserver un état de démonstration cohérent et une sauvegarde identifiable ; documenter le nettoyage des données de test si nécessaire.

### Fiche de résultat à recopier pour chaque exécution

- Référence du scénario : [À COMPLÉTER].
- Date et heure : [À COMPLÉTER].
- Version / environnement : [À COMPLÉTER].
- Appareil, système et navigateur : [À COMPLÉTER].
- État initial et données exactes : [À COMPLÉTER].
- Étapes réellement exécutées : [À COMPLÉTER].
- Résultat attendu : reprendre le scénario et préciser les valeurs de référence.
- Résultat obtenu : [À COMPLÉTER : texte du message, état des données, compteurs et comportement observé].
- Conclusion : conforme / non conforme / bloqué / non exécuté.
- Preuve : [À COMPLÉTER : capture, vidéo ou retour de script].
- Anomalie associée et correction : [À COMPLÉTER ou sans objet].
- Résultat du nouvel essai après correction : [À COMPLÉTER ; ne pas le déduire de la correction seule].

### Synthèse à finaliser pour le dossier

La campagne couvre 47 scénarios préparés. Le nombre effectivement exécuté est [À COMPLÉTER], dont [À COMPLÉTER] conformes, [À COMPLÉTER] non conformes et [À COMPLÉTER] bloqués. Les anomalies encore ouvertes sont [À COMPLÉTER]. Ces nombres doivent être calculés à partir des fiches complétées, pas à partir du nombre de scénarios préparés.

Pour l’intégration au dossier, présenter la méthode, le jeu de données, quelques résultats significatifs et l’analyse des écarts dans le corps du rapport. Le cahier complet et les preuves peuvent alimenter les annexes dans la limite de pagination prévue par les consignes. Le parcours représentatif permet d’expliquer la continuité entre besoin, réalisation, résultat attendu et résultat observé.
