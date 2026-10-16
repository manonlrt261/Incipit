<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

final class StatistiquesLecture
{
    public function __construct(private readonly Connection $connexion) {}

    public function calculer(int $annee, int $utilisateurId): array
    {
        $lectures = $this->connexion->fetchAllAssociative('SELECT id, livre_id, statut, date_fin FROM session_lecture');
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
        $parAnnee = [];
        $livresLus = [];
        $auteurs = [];
        $sansDate = 0;
        foreach ($lectures as $lecture) {
            $statut = mb_strtolower(trim($lecture['statut']));
            if (array_key_exists($statut, $compteurs)) $compteurs[$statut]++;
            if ($statut !== 'terminé') continue;
            if (!$lecture['date_fin']) { $sansDate++; continue; }
            $date = new \DateTimeImmutable($lecture['date_fin']);
            $an = (int) $date->format('Y');
            $parAnnee[$an] = ($parAnnee[$an] ?? 0) + 1;
            if ($an !== $annee) continue;
            $mois[(int) $date->format('n')]++;
            $livreId = (int) $lecture['livre_id'];
            $livresLus[$livreId] = true;
            foreach ($auteursParLivre[$livreId] ?? [] as $id => $nom) {
                $auteurs[$id] ??= ['nom' => $nom, 'nombre' => 0];
                $auteurs[$id]['nombre']++;
            }
        }
        $genres = [];
        foreach (array_keys($livresLus) as $id) {
            foreach ($genresParLivre[$id] ?? [0 => 'Sans genre renseigné'] as $genreId => $nom) {
                $genres[$genreId] ??= ['nom' => $nom, 'nombre' => 0];
                $genres[$genreId]['nombre']++;
            }
        }
        $trier = static fn(array $a, array $b): int => $b['nombre'] <=> $a['nombre'] ?: strnatcasecmp($a['nom'], $b['nom']);
        usort($genres, $trier);
        usort($auteurs, $trier);
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
        // Inclure les années sans lecture entre la première année connue et aujourd’hui.
        $comparaison = [];
        for ($an = min($annees); $an <= max($annees); $an++) $comparaison[$an] = $parAnnee[$an] ?? 0;
        $objectif = $this->connexion->fetchOne('SELECT nombre_livres FROM objectif_annuel WHERE utilisateur_id = :id AND annee = :annee', ['id' => $utilisateurId, 'annee' => $annee]);
        $objectif = $objectif !== false && (int) $objectif > 0 ? (int) $objectif : null;
        $termineesAnnee = array_sum($mois);
        return [
            'annee' => $annee, 'annees' => $annees, 'compteurs' => $compteurs,
            'mois' => $mois, 'maximumMois' => max(1, ...array_values($mois)),
            'comparaison' => $comparaison, 'maximumAnnuel' => max(1, ...array_values($comparaison)),
            'genres' => $genres, 'livresLus' => count($livresLus), 'auteurs' => array_slice($auteurs, 0, 5),
            'termineesAnnee' => $termineesAnnee, 'sansDate' => $sansDate,
            'objectif' => $objectif, 'progression' => $objectif ? (int) round($termineesAnnee / $objectif * 100) : null,
        ];
    }
}
