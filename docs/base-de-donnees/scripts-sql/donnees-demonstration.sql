-- INCIPIT - Jeu de demonstration pour une base locale dediee.
-- Prerequis : base incipit_demo creee et migrations Doctrine appliquees.
-- Ne pas importer dans la bibliotheque publiee ou dans une base personnelle.
-- Executer UNE SEULE FOIS sur un socle sans donnees metier.
-- Les genres installes par les migrations peuvent etre presents.
-- Les titres/auteurs de reference sont distingues des donnees fictives.
-- Ne contient aucun compte, mot de passe ou secret.

USE incipit_demo;
SET NAMES utf8mb4;
START TRANSACTION;

-- 1. Auteur du livre reserve a la demonstration.
INSERT INTO auteur (nom, prenom, nom_affichage)
VALUES ('Dumas', 'Alexandre', 'Alexandre Dumas');
SET @demo_auteur_dumas = LAST_INSERT_ID();

-- 2. Fiche bibliographique : titre et auteur connus du scenario.
-- ISBN, edition, couverture et pagination non fournis : pas de valeur inventee.
-- Le livre n'est pas encore possede et n'appartient pas a la pile a lire.
INSERT INTO livre (
    titre, langue, saga, tome, resume, pile_a_lire,
    date_creation, date_modification
) VALUES (
    'Vingt ans après', 'fr', 'Les Trois Mousquetaires', 2,
    '[DEMO] Livre reserve au parcours wishlist, acquisition et lecture.', 0,
    '2026-10-01 09:00:00', '2026-10-01 09:00:00'
);
SET @demo_livre_dumas = LAST_INSERT_ID();

-- 3. Association : les identifiants generes sont reutilises sans les supposer.
INSERT INTO livre_auteur (livre_id, auteur_id)
VALUES (@demo_livre_dumas, @demo_auteur_dumas);

-- 4. Etat de depart : souhait public non acquis.
-- Aucun exemplaire et aucune session pour ce livre : l'achat sera fait dans l'UI.
-- Le prix et les dates sont des valeurs fictives de demonstration.
INSERT INTO souhait (
    livre_id, priorite, prix_estime, commentaire,
    date_ajout, date_acquisition, statut, publique
) VALUES (
    @demo_livre_dumas, NULL, 12.50,
    '[DEMO] Souhait public a marquer comme achete pendant la demonstration.',
    '2026-10-01 09:00:00', NULL, 'souhaité', 1
);

-- 5. Second auteur et second titre entierement fictifs.
INSERT INTO auteur (nom, prenom, nom_affichage)
VALUES ('Exemple', 'Camille', 'Camille Exemple');
SET @demo_auteur_fictif = LAST_INSERT_ID();

INSERT INTO livre (
    titre, langue, resume, pile_a_lire, date_creation, date_modification
) VALUES (
    'Les jardins de papier [DEMO]', 'fr',
    '[DEMO] Ouvrage fictif pour illustrer exemplaire, lecture et pret.', 0,
    '2026-10-02 09:00:00', '2026-10-10 18:00:00'
);
SET @demo_livre_fictif = LAST_INSERT_ID();

INSERT INTO livre_auteur (livre_id, auteur_id)
VALUES (@demo_livre_fictif, @demo_auteur_fictif);

-- 6. Exemplaire possede : il permet l'affichage du livre dans la collection.
INSERT INTO exemplaire (
    livre_id, date_acquisition, prix_achat, provenance, etat,
    emplacement, commentaire, date_creation, date_retrait
) VALUES (
    @demo_livre_fictif, '2026-10-02', 9.90, 'Don fictif', 'Bon etat',
    'Etagere de demonstration', '[DEMO] Exemplaire de test.',
    '2026-10-02 09:00:00', NULL
);
SET @demo_exemplaire = LAST_INSERT_ID();

-- 7. Lecture terminee : note de 5/5, dates coherentes, hors pile a lire.
-- Ce choix illustre les contraintes SQL sans preterminer la lecture de Dumas.
INSERT INTO session_lecture (
    livre_id, date_debut, date_fin, statut, note, avis,
    date_creation, date_modification
) VALUES (
    @demo_livre_fictif, '2026-10-03', '2026-10-10', 'Terminé', 5.0,
    '[DEMO] Avis fictif pour le jeu de donnees.',
    '2026-10-03 09:00:00', '2026-10-10 18:00:00'
);

-- 8. Contact fictif : aucune coordonnee personnelle.
INSERT INTO contact (prenom, nom, email, telephone, commentaire, date_creation)
VALUES (
    'Alex', 'Demo', 'alex.demo@example.invalid', NULL,
    '[DEMO] Contact fictif.', '2026-10-11 09:00:00'
);
SET @demo_contact = LAST_INSERT_ID();

-- 9. Pret rattache a l'exemplaire et au contact existants.
-- Retour effectif absent : le pret est en cours.
INSERT INTO pret (
    exemplaire_id, contact_id, date_pret, date_retour_prevue,
    date_retour_effective, statut, commentaire, date_creation
) VALUES (
    @demo_exemplaire, @demo_contact, '2026-10-12', '2026-11-15',
    NULL, 'En cours', '[DEMO] Pret fictif pour montrer les relations.',
    '2026-10-12 09:00:00'
);

COMMIT;

-- L'import SQL ne declenche pas l'ecouteur Doctrine du journal d'activite.
-- Les evenements seront produits par les actions effectuees dans l'application.
