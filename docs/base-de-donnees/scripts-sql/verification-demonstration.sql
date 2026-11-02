-- Consultations uniquement, apres l'import et AVANT le parcours manuel.
USE incipit_demo;

-- Attendu : 2 auteurs, 2 livres, 2 associations et une ligne dans chaque autre table.
SELECT 'auteur' AS table_verifiee, COUNT(*) AS nombre FROM auteur
UNION ALL SELECT 'livre', COUNT(*) FROM livre
UNION ALL SELECT 'livre_auteur', COUNT(*) FROM livre_auteur
UNION ALL SELECT 'souhait', COUNT(*) FROM souhait
UNION ALL SELECT 'exemplaire', COUNT(*) FROM exemplaire
UNION ALL SELECT 'session_lecture', COUNT(*) FROM session_lecture
UNION ALL SELECT 'contact', COUNT(*) FROM contact
UNION ALL SELECT 'pret', COUNT(*) FROM pret;

-- Attendu : Dumas, souhaite public, 0 exemplaire, 0 lecture, pile = 0.
SELECT l.titre, a.nom_affichage AS auteur, s.statut, s.publique, l.pile_a_lire,
       (SELECT COUNT(*) FROM exemplaire e WHERE e.livre_id = l.id) AS exemplaires,
       (SELECT COUNT(*) FROM session_lecture sl WHERE sl.livre_id = l.id) AS lectures
FROM livre l
JOIN livre_auteur la ON la.livre_id = l.id
JOIN auteur a ON a.id = la.auteur_id
JOIN souhait s ON s.livre_id = l.id
WHERE l.titre = 'Vingt ans après';

-- Attendu : titre fictif, lecture Terminé, note 5, contact Alex Demo, pret En cours.
SELECT l.titre, sl.statut AS lecture, sl.note, p.statut AS pret,
       CONCAT(c.prenom, ' ', c.nom) AS contact, p.date_retour_effective
FROM pret p
JOIN exemplaire e ON e.id = p.exemplaire_id
JOIN livre l ON l.id = e.livre_id
JOIN contact c ON c.id = p.contact_id
JOIN session_lecture sl ON sl.livre_id = l.id
WHERE l.titre = 'Les jardins de papier [DEMO]';
