<?php

namespace App\Controller;

use App\Service\{JournalActivite, JournalMongo};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class JournalController extends AbstractController
{
    #[Route('/espace/journal-activite', name: 'app_journal_activite', methods: ['GET'])]
    public function index(Request $requete, JournalMongo $mongo, JournalActivite $journal): Response
    {
        $filtres = [];
        foreach (['action', 'livre', 'debut', 'fin'] as $cle) $filtres[$cle] = trim($requete->query->getString($cle));
        $filtres['livre'] = mb_substr($filtres['livre'], 0, 150);
        $page = max(1, min(1000, $requete->query->getInt('page', 1)));
        $erreur = null;
        $resultat = ['evenements' => [], 'suite' => false];
        // Vérifie les filtres avant de construire la requête MongoDB, sans accepter d’opérateurs venant du navigateur.
        foreach (['debut', 'fin'] as $cle) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $filtres[$cle]);
            if ($filtres[$cle] !== '' && (!$date || $date->format('Y-m-d') !== $filtres[$cle])) $erreur = 'Veuillez saisir des dates valides.';
        }
        if ($filtres['action'] !== '' && !isset(JournalActivite::ACTIONS[$filtres['action']])) $erreur = 'Veuillez choisir une action de la liste.';
        if ($filtres['debut'] && $filtres['fin'] && $filtres['debut'] > $filtres['fin']) $erreur = 'La date de début doit précéder la date de fin.';
        if (!$erreur) {
            try { $resultat = $mongo->rechercher($filtres, $page); }
            catch (\Throwable $exception) {
                // Le détail reste dans les logs serveur : aucun message du pilote susceptible de contenir une URI n’est copié.
                error_log('[INCIPIT - JOURNAL] '.json_encode($mongo->diagnostic() + [
                    'type' => get_class($exception),
                    'code' => $exception->getCode(),
                ], JSON_UNESCAPED_SLASHES).PHP_EOL, 3, $this->getParameter('kernel.logs_dir').'/journal-mongodb.log');
                $erreur = 'Le journal est momentanément indisponible. Les nouvelles actions restent conservées en attente de transmission.';
            }
        }
        return $this->render('journal/index.html.twig', $resultat + ['filtres' => $filtres, 'actions' => JournalActivite::ACTIONS, 'page' => $page, 'erreur' => $erreur, 'enAttente' => $journal->enAttente()]);
    }
}
