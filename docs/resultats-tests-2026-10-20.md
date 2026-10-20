# Résultats de la campagne du 20 octobre 2026

## Identification

Campagne locale exécutée le 20 octobre 2026, code de sortie final **0**. Base du code : commit `0644a87979774769744c0ec547ed5c755dbc7d52`, complété par les trois fichiers de tests ajoutés dans cette étape (`executer.sh`, `verifier-donnees-lecture.php`, `verifier-filtres.cjs`). Ces ajouts n’étaient pas encore commités lors du lancement. Leur empreinte figure plus bas.

Environnement observé : PHP 8.5.10, Node.js v24.16.0, binaire MongoDB 8.0.34. PHP publié sur Alwaysdata est configuré en 8.4 d’après les échanges précédents ; cette campagne ne constitue pas une nouvelle exécution sur ce serveur. Les scripts SQL utilisent la base locale de développement et annulent leurs transactions de données d’essai. Le journal utilise MongoDB isolé sur 127.0.0.1:28719 ; la panne est simulée sur 28720 sans service ; le test d’archive utilise des bases temporaires sur 27017.

Commande réellement exécutée :

```bash
bash tests/executer.sh --local --mongodb
```

Les huit suites ont réussi. Les nombres de contrôles explicitement annoncés par certaines suites sont reproduits ci-dessous ; les deux suites ne comptant pas leurs assertions ne sont pas artificiellement ajoutées à un total. Un contrôle de code n’équivaut pas à un parcours manuel complet.

## Jeu d’essai et observations

| Référence | Données d’entrée / action | Résultat attendu | Résultat obtenu | Écart |
|---|---|---|---|---|
| A01 | Statut Inconnu | Exception de validation avec message du statut invalide | Refus et message vérifiés | Aucun constaté |
| A02 | Note 6 | Refus hors plage 0–5 | Refus et message vérifiés | Aucun constaté |
| A03 | Note abc | Refus d’une valeur non numérique | Refus et message vérifiés | Aucun constaté |
| A04 | Statut En cours ; début 2026-02-31 | Refus de la date inexistante | Refus et message vérifiés | Aucun constaté |
| A05 | Terminé ; début 2026-10-20 ; fin 2026-10-19 | Refus des dates inversées | Refus et message vérifiés | Aucun constaté |
| A06 | Note vide puis note 0 | null et zéro restent distincts | Valeurs distinctes vérifiées | Aucun constaté |
| A07 | Livre dans la pile ; Terminé ; note 4.5 ; dates 2026-10-01 et 2026-10-20 | Retrait de la pile, statut et note conservés en mémoire | Assertions réussies | Aucun constaté ; pas d’essai navigateur |
| A08 | Trois cartes simulées ; noms Zulu, Alpha, Milieu ; prénoms Alice, Zoé, Marc ; notes 1, 1.5, 5 ; dates 10, 20, 30 | Quatre ordres alphabétiques corrects ; tranche 1 associée aux dates ; critères préservés | Assertions du module réel réussies | Aucun constaté sur ce jeu construit |
| A09 | Visiteur puis compte sans rôle administrateur | Refus des parcours privés ; contrôles CSRF et méthodes | 171 contrôles réussis, dont 48 parcours sans connexion et 48 sans rôle administrateur | Aucun constaté dans ce périmètre |
| A10 | Visiteur, souhait privé et paramètres manipulés | Pas de lecture privée ni modification non autorisée | 64 contrôles réussis | Aucun constaté dans ce périmètre |
| A11 | Titre/auteur/saga avec marqueur aléatoire ; ISBN 9780000000008 et 123456789X, avec séparateurs ; 31 résultats de pagination | Résultats dans le tableau de bord ; 30 puis 1 ; exclusion souhaits/retraits | Assertions réussies | Aucun constaté ; ISBN synthétiques utilisés uniquement dans la base locale, sans fournisseur externe |
| A12 | Recherche absente ; texte ressemblant à une injection ; recherche vide | Aucun résultat indu ; saisie littérale ; pas de recherche vide affichée | Assertions réussies | Aucun constaté |
| A13 | Livre retiré noté 1 ; jeton invalide puis deux restaurations autorisées | Refus CSRF ; une seule copie active ; historique conservé | Assertions réussies ; note conservée ; retour dans Mes lectures | Aucun constaté |
| A14 | Livre sans exemplaire retiré soumis à restauration | Refus HTTP 409 | Refus vérifié | Aucun constaté |
| A15 | Même identifiant d’événement transmis deux fois ; filtres action/livre/date ; pagination | Un seul événement ; filtres et pages cohérents | Partie des 30 contrôles journal réussis | Aucun constaté |
| A16 | MongoDB inaccessible sur port local 28720 ; événement en attente | Événement conservé ; indisponibilité signalée ; reprise vers l’instance saine ; pas de retransmission | Assertions de panne, reprise et idempotence réussies | Aucun constaté ; pas d’arrêt du cluster Atlas réel |
| A17 | Document BSON avec ObjectId, UTCDateTime et Decimal128 ; export puis restauration dans une autre base temporaire | Types et index vérifiés, absence d’URI dans les métadonnées ; erreurs d’archive détectées | 10 contrôles d’archive réussis | Aucun constaté dans ce périmètre ; ne prouve pas une restauration globale SQL + couvertures |

## Journal exact de la campagne

Le texte suivant est la sortie capturée du lanceur. Aucun identifiant secret n’y figure ; les lignes ont été relues avant intégration.

```text
Suite : Règles de lecture et saisies invalides
OK : statut inconnu refusé
OK : note hors limites refusé
OK : note non numérique refusé
OK : date inexistante refusé
OK : dates inversées refusé
OK : note absente distincte de zéro
OK : fin de lecture, note et retrait automatique de la pile
7 contrôles métier réussis.
Résultat : SUCCÈS
Suite : Filtres combinables
4 tris auteur, note 1★ + ancienneté dans les deux ordres de sélection, réinitialisation : OK
Wishlist : genre + statut + auteur + date ; prêts : genre + contact + auteur + date : OK
Résultat : SUCCÈS
Suite : Accès privés
48 parcours privés refusés sans connexion.
48 parcours refusés sans rôle administrateur.
Tests CSRF, pages des deux administrateurs, souhait privé et méthodes HTTP vérifiés. Total : 171 contrôles.
Résultat : SUCCÈS
Suite : Confidentialité visiteurs
OK : 64 contrôles visiteurs, confidentialité, paramètres manipulés et données inchangées.
Résultat : SUCCÈS
Suite : Recherche tableau de bord
Recherche par titre, auteur, saga et ISBN formaté : OK ; maintien sur le tableau de bord, absence de recherche vide, exclusion des souhaits et retraits et paramètres liés vérifiés.
Résultat : SUCCÈS
Suite : Restauration collection
Restauration : CSRF, absence de doublon, conservation de la fiche et de la note, retour dans Mes lectures et refus hors retraits : OK
Résultat : SUCCÈS
Suite : Journal et panne MongoDB
30 contrôles réussis : MongoDB, filtres, accès privés, Doctrine, panne, reprise et annulation.
Résultat : SUCCÈS
Suite : Archive MongoDB
10 contrôles réussis : export, restauration isolée, types BSON, index, intégrité et indisponibilité.
Résultat : SUCCÈS
```

## Empreintes des scripts ajoutés

Ces empreintes SHA-256 identifient les scripts utilisés lors de cette campagne.

- `executer.sh` : `888195f8b089a5aedf6a567fdd4c68d2aab7d08a478570860163a6cf2225bf5c`
- `verifier-donnees-lecture.php` : `d288311d2555edd04a6fd9b35314bc5667654d405462e8016dd72bd8d3a16961`
- `verifier-filtres.cjs` : `e05207ce409766a1e2c1492f4f02b17c9f9754d19652cb9ed2730156a58788b5`

## Réserves et suites à donner

Aucune anomalie n’a été détectée par cette campagne dans les assertions exécutées. Cette conclusion ne signifie pas que tout le site a été validé. La réception des courriels, l’expiration et la réutilisation réelles des liens, le scan sur matériel, le zoom Safari, les manipulations de toutes les fenêtres et les données auteurs de production nécessitent encore leurs preuves manuelles.

La simulation de panne externe porte sur MongoDB. Les fournisseurs ISBN/couvertures et le transport des courriels ne sont pas simulés par cette campagne. Les tests de charge, la bascule d’un replica set Atlas et les temps de réponse ne sont pas mesurés ici. Le cahier manuel reste à compléter avec les résultats réellement observés.

Les scénarios utilisent des fixtures locales ou des structures en mémoire, puis des transactions SQL annulées et des bases MongoDB temporaires supprimées. Le fait qu’une suite se termine correctement ne constitue pas une preuve de restauration du site de production. Les caches et les séquences d’identifiants peuvent évoluer sur l’environnement local.
