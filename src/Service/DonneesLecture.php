<?php

namespace App\Service;

use App\Entity\SessionLecture;
use Symfony\Component\HttpFoundation\Request;

final class DonneesLecture
{
    // Valide ensemble le statut, la note et les dates avant toute écriture.
    public function lire(Request $request, string $statutParDefaut = 'Pas commencé'): array
    {
        $statut = trim($request->request->getString('statut', $statutParDefaut));

        if (!in_array($statut, ['Pas commencé', 'En cours', 'En pause', 'Abandonné', 'Terminé', 'À lire'], true)) {
            throw new \InvalidArgumentException('Statut de lecture invalide.');
        }
        // Une lecture abandonnée reçoit toujours zéro, même si une ancienne note est envoyée.
        $note = $statut === 'Abandonné' ? '0' : trim($request->request->getString('note'));

        if ($note !== '' && !preg_match('/^(?:[0-4](?:\.[05])?|5(?:\.0)?)$/', $note)) {
            throw new \InvalidArgumentException('La note doit être comprise entre 0 et 5, par demi-étoile.');
        }
        $dates = ['dateDebut' => null, 'dateFin' => null];

        foreach (array_keys($dates) as $champ) {
            $texte = trim($request->request->getString($champ));

            // Ignore les dates devenues sans objet après un changement de statut.
            if ($texte === '' || in_array($statut, ['Pas commencé', 'À lire'], true)
                || ($champ === 'dateFin' && $statut !== 'Terminé')) {
                continue;
            }
            $date = \DateTime::createFromFormat('!Y-m-d', $texte);

            if (!$date || $date->format('Y-m-d') !== $texte) {
                throw new \InvalidArgumentException('La date de lecture est invalide.');
            }
            $dates[$champ] = $date;
        }

        // Une lecture terminée sans date explicite prend la date du jour en France.
        if ($statut === 'Terminé' && !$dates['dateFin']) {
            $dates['dateFin'] = new \DateTime('today', new \DateTimeZone('Europe/Paris'));
        }

        if ($dates['dateDebut'] && $dates['dateFin'] && $dates['dateFin'] < $dates['dateDebut']) {
            throw new \InvalidArgumentException('La date de fin ne peut pas précéder la date de début.');
        }

        return ['statut' => $statut, 'note' => $note === '' ? null : $note] + $dates;
    }

    // Applique les mêmes informations à la session créée ou modifiée depuis le livre.
    public function appliquer(SessionLecture $session, array $donnees): void
    {
        // Retire la sélection dès qu’une lecture est terminée, tout en conservant le livre.
        if ($donnees['statut'] === 'Terminé') {
            $session->getLivre()?->setPileALire(false);
        }
        $session->setStatut($donnees['statut']);
        $session->setNote($donnees['note']);
        $session->setDateDebut($donnees['dateDebut']);
        $session->setDateFin($donnees['dateFin']);
        $session->setDateModification(new \DateTime());
    }
}
