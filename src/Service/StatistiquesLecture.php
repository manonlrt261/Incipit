<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class StatistiquesLecture
{
    public function __construct(private readonly Connection $connexion)
    {
    }

    // Prépare les graphiques de l’année choisie, l’historique annuel et la progression par genre.
    public function calculer(int $annee, int $utilisateurId): array
    {
        $lectures = $this->connexion->fetchAllAssociative('SELECT sl.id, sl.livre_id, sl.statut, sl.date_fin, l.titre FROM session_lecture sl JOIN livre l ON l.id = sl.livre_id');
        // Indexe genres et auteurs par livre pour les réutiliser sans requête dans chaque boucle.
        $genresParLivre = [];

        foreach ($this->connexion->fetchAllAssociative('SELECT lg.livre_id, g.id, g.nom FROM livre_genre lg JOIN genre g ON g.id = lg.genre_id') as $ligne) {
            $genresParLivre[(int) $ligne['livre_id']][(int) $ligne['id']] = $ligne['nom'];
        }
        $auteursParLivre = [];

        foreach ($this->connexion->fetchAllAssociative("SELECT la.livre_id, a.id, COALESCE(NULLIF(a.nom_affichage, ''), CONCAT_WS(' ', a.prenom, a.nom)) AS nom FROM livre_auteur la JOIN auteur a ON a.id = la.auteur_id") as $ligne) {
            $auteursParLivre[(int) $ligne['livre_id']][(int) $ligne['id']] = $ligne['nom'];
        }
        $compteurs = ['terminé' => 0, 'en cours' => 0, 'pas commencé' => 0, 'abandonné' => 0];
        $mois = array_fill(1, 12, 0);
        $detailsMois = array_fill(1, 12, []);
        $detailsAnnees = [];
        $tousLivresLus = [];
        $parAnnee = [];
        $livresLus = [];
        $auteurs = [];
        $sansDate = 0;

        // Les compteurs portent sur toutes les sessions ; les graphiques datés retiennent les lectures terminées.
        foreach ($lectures as $lecture) {
            $statut = mb_strtolower(trim($lecture['statut']));

            if (array_key_exists($statut, $compteurs)) {
                $compteurs[$statut]++;
            }

            if ($statut !== 'terminé') {
                continue;
            }
            $tousLivresLus[(int) $lecture['livre_id']] = true;

            if (!$lecture['date_fin']) {
                $sansDate++;
                continue;
            }
            $date = new \DateTimeImmutable($lecture['date_fin']);
            $an = (int) $date->format('Y');
            $parAnnee[$an] = ($parAnnee[$an] ?? 0) + 1;
            $numeroMois = (int) $date->format('n');
            // Conserve le détail de toutes les années, indépendamment de l’année du graphique mensuel.
            $detail = [
                'titre' => $lecture['titre'],

                'auteurs' => implode(', ', $auteursParLivre[(int) $lecture['livre_id']] ?? []),

                'date' => $lecture['date_fin'],
            ];
            $detailsAnnees[$an][$numeroMois][] = $detail;

            if ($an !== $annee) {
                continue;
            }
            $mois[$numeroMois]++;
            $detailsMois[$numeroMois][] = $detail;
            $livreId = (int) $lecture['livre_id'];
            $livresLus[$livreId] = true;

            foreach ($auteursParLivre[$livreId] ?? [] as $id => $nom) {
                $auteurs[$id] ??= ['nom' => $nom, 'nombre' => 0];
                $auteurs[$id]['nombre']++;
            }
        }
        // Compte chaque livre une fois par genre, même s’il a été relu dans l’année.
        $genres = [];

        foreach (array_keys($livresLus) as $id) {
            foreach ($genresParLivre[$id] ?? [0 => 'Sans genre renseigné'] as $genreId => $nom) {
                $genres[$genreId] ??= ['nom' => $nom, 'nombre' => 0];
                $genres[$genreId]['nombre']++;
            }
        }
        $trier = static fn (array $a, array $b): int => $b['nombre'] <=> $a['nombre'] ?: strnatcasecmp($a['nom'], $b['nom']);
        usort($genres, $trier);
        usort($auteurs, $trier);
        // Calcule les portions et les angles cumulés nécessaires au graphique circulaire.
        $totalGenres = array_sum(array_column($genres, 'nombre'));
        $couleurs = ['#925537', '#e39767', '#e9bb66', '#8f9b8c', '#ad96b4', '#c7b9aa', '#a97660', '#8c93aa'];
        $position = 0;

        foreach ($genres as $i => &$genre) {
            $genre['part'] = $totalGenres > 0 ? $genre['nombre'] / $totalGenres * 100 : 0;
            $genre['debut'] = $position;
            $position += $genre['part'];
            $genre['fin'] = $position;
            $genre['couleur'] = $couleurs[$i % count($couleurs)];
        }
        unset($genre);
        $courante = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y');
        $annees = array_unique([...array_keys($parAnnee), $courante, $annee]);
        rsort($annees, SORT_NUMERIC);
        // Inclut les années sans lecture jusqu’à la plus récente année connue, courante ou sélectionnée.
        $comparaison = [];
        for ($an = min($annees); $an <= max($annees); $an++) {
            $comparaison[$an] = $parAnnee[$an] ?? 0;
        }
        // Les périodes courtes incluent les années sans lecture ; toutes les années conserve l’historique complet.
        $comparaisons = ['toutes' => $comparaison];
        $derniereAnnee = max($annees);

        foreach ([3, 5, 10] as $duree) {
            $comparaisons[(string) $duree] = [];
            for ($an = $derniereAnnee - $duree + 1; $an <= $derniereAnnee; $an++) {
                $comparaisons[(string) $duree][$an] = $parAnnee[$an] ?? 0;
            }
        }

        // Compte chaque livre encore possédé une seule fois par genre et une seule fois parmi les livres lus.
        $genresCollection = [];
        $possedes = $this->connexion->fetchFirstColumn('SELECT l.id FROM livre l WHERE EXISTS (SELECT 1 FROM exemplaire e WHERE e.livre_id = l.id AND e.date_retrait IS NULL)');

        foreach ($possedes as $id) {
            foreach ($genresParLivre[(int) $id] ?? [0 => 'Sans genre'] as $genreId => $nom) {
                $genresCollection[$genreId] ??= ['nom' => $nom, 'possedes' => 0, 'lus' => 0];
                $genresCollection[$genreId]['possedes']++;

                if (isset($tousLivresLus[(int) $id])) {
                    $genresCollection[$genreId]['lus']++;
                }
            }
        }

        foreach ($genresCollection as &$genre) {
            $genre['pourcentage'] = (int) round($genre['lus'] / $genre['possedes'] * 100);
        }
        unset($genre);
        usort($genresCollection, static fn (array $a, array $b): int => strnatcasecmp($a['nom'], $b['nom']));

        // Dans une liste mensuelle, les lectures les plus récentes apparaissent en premier.
        foreach ($detailsMois as &$details) {
            usort($details, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']) ?: strnatcasecmp($a['titre'], $b['titre']));
        }
        unset($details);

        // Présente les mois de janvier à décembre et les lectures par date croissante dans chaque mois.
        foreach ($detailsAnnees as &$moisAnnee) {
            ksort($moisAnnee, SORT_NUMERIC);

            foreach ($moisAnnee as &$lecturesMois) {
                usort($lecturesMois, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']) ?: strnatcasecmp($a['titre'], $b['titre']));
            }
            unset($lecturesMois);
        }
        unset($moisAnnee);
        // Un objectif absent reste distinct d’un objectif chiffré pour les messages et la progression.
        $objectif = $this->connexion->fetchOne('SELECT nombre_livres FROM objectif_annuel WHERE utilisateur_id = :id AND annee = :annee', ['id' => $utilisateurId, 'annee' => $annee]);
        $objectif = $objectif !== false && (int) $objectif > 0 ? (int) $objectif : null;
        $termineesAnnee = array_sum($mois);

        return [
            'detailsAnnees' => $detailsAnnees,
            'detailsMois' => $detailsMois,
            'comparaisons' => $comparaisons,
            'genresCollection' => $genresCollection,

            'annee' => $annee,
            'annees' => $annees,
            'compteurs' => $compteurs,

            'mois' => $mois,
            'maximumMois' => max(1, ...array_values($mois)),

            'comparaison' => $comparaison,
            'maximumAnnuel' => max(1, ...array_values($comparaison)),

            'genres' => $genres,
            'livresLus' => count($livresLus),
            'auteurs' => array_slice($auteurs, 0, 5),

            'termineesAnnee' => $termineesAnnee,
            'sansDate' => $sansDate,

            'objectif' => $objectif,
            'progression' => $objectif ? (int) round($termineesAnnee / $objectif * 100) : null,
        ];
    }
}
