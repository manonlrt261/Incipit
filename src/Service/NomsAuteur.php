<?php

namespace App\Service;

final class NomsAuteur
{
    // Les anciens auteurs ont un nom complet unique : le premier mot devient le prénom, le reste le nom.
    public static function separer(string $nomComplet): array
    {
        $mots = preg_split('/\s+/u', trim($nomComplet), 2);
        return count($mots) === 2 ? ['prenom' => $mots[0], 'nom' => $mots[1]] : ['prenom' => '', 'nom' => $mots[0]];
    }

    // Préserve les noms composés dès qu’un prénom et un nom ont été enregistrés séparément.
    public static function presenter(array $auteur): array
    {
        $affichage = trim($auteur['nom_affichage'] ?? trim(($auteur['prenom'] ?? '').' '.($auteur['nom'] ?? '')));
        $noms = empty($auteur['prenom']) ? self::separer($affichage) : ['prenom' => $auteur['prenom'], 'nom' => $auteur['nom']];
        $initiales = mb_strtoupper(mb_substr($noms['prenom'], 0, 1).mb_substr($noms['nom'], 0, 1));
        return [...$auteur, ...$noms, 'affichage' => $affichage, 'initiales' => $initiales];
    }
}
