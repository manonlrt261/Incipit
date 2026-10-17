<?php
namespace App\Controller;

use App\Service\StatistiquesLecture;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class StatistiquesController extends AbstractController
{
    #[Route('/espace/mes-statistiques', name: 'app_espace_statistiques', methods: ['GET'])]
    // Calcule les statistiques de l’année choisie et renvoie soit la page complète, soit les graphiques actualisés.
    public function index(Request $request, StatistiquesLecture $statistiques): Response
    {
        // Encadre le choix de l’année autour de l’année courante dans le fuseau de Paris.
        $courante = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y');
        $valeur = $request->query->get('annee', (string) $courante);
        if (!is_string($valeur) || !preg_match('/^\d{4}$/', $valeur) || (int) $valeur < 1900 || (int) $valeur > $courante + 1) {
            return $this->json(['success' => false, 'message' => 'Année invalide.'], 400);
        }
        $donnees = $statistiques->calculer((int) $valeur, $this->getUser()->getId());
        // Renvoie seulement les graphiques pour permettre le changement d’année sans rechargement.
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'html' => $this->renderView('statistiques/_graphiques.html.twig', $donnees)]);
        }
        return $this->render('statistiques/index.html.twig', $donnees);
    }
    #[Route('/espace/mes-statistiques/objectif', name: 'app_statistiques_objectif', methods: ['POST'])]
    // Valide et enregistre l’objectif annuel, puis renvoie les graphiques avec la progression mise à jour.
    public function enregistrerObjectif(Request $request, \Doctrine\DBAL\Connection $connexion, StatistiquesLecture $statistiques): JsonResponse
    {
        // Vérifie que la modification de l’objectif provient du formulaire prévu.
        $jeton = $request->request->get('_token');
        if (!is_string($jeton) || !$this->isCsrfTokenValid('modifier_objectif', $jeton)) {
            return $this->json(['success' => false, 'message' => 'Session expirée. Actualisez la page.'], 403);
        }
        $annee = $request->request->all()['annee'] ?? '';
        $nombre = $request->request->all()['nombre'] ?? '';
        // Encadre le choix de l’année autour de l’année courante dans le fuseau de Paris.
        $courante = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y');
        if (!is_string($annee) || !preg_match('/^\d{4}$/', $annee) || (int) $annee < 1900 || (int) $annee > $courante + 1) {
            return $this->json(['success' => false, 'message' => 'Année invalide.'], 422);
        }
        if (!is_string($nombre) || !preg_match('/^[1-9]\d{0,4}$/', trim($nombre)) || (int) $nombre > 10000) {
            return $this->json(['success' => false, 'message' => 'Saisissez un nombre entier de livres entre 1 et 10 000.'], 422);
        }
        // Enregistre l’objectif et prépare les graphiques dans une même transaction.
        $connexion->beginTransaction();
        try {
            // La clé utilisateur/année garantit un seul objectif même si deux envois se croisent.
            $connexion->executeStatement(
                'INSERT INTO objectif_annuel (utilisateur_id, annee, nombre_livres) VALUES (:utilisateur, :annee, :nombre) ON DUPLICATE KEY UPDATE nombre_livres = :nouvelleValeur',
                ['utilisateur' => $this->getUser()->getId(), 'annee' => (int) $annee, 'nombre' => (int) $nombre, 'nouvelleValeur' => (int) $nombre]
            );
            $donnees = $statistiques->calculer((int) $annee, $this->getUser()->getId());
            $html = $this->renderView('statistiques/_graphiques.html.twig', $donnees);
            $connexion->commit();
            return $this->json(['success' => true, 'objectif' => (int) $nombre, 'annee' => (int) $annee, 'html' => $html]);
        } catch (\Throwable) {
            if ($connexion->isTransactionActive()) $connexion->rollBack();
            return $this->json(['success' => false, 'message' => 'Impossible d’enregistrer votre objectif. Réessayez.'], 500);
        }
    }

}
