<?php

namespace App\EventListener;

use App\Entity\{Exemplaire, Livre, Pret, SessionLecture, Souhait, Utilisateur};
use App\Service\JournalActivite;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsDoctrineListener(event: 'postPersist')]
#[AsDoctrineListener(event: 'postUpdate')]
#[AsDoctrineListener(event: 'postRemove')]
final class JournalDoctrine
{
    public function __construct(private JournalActivite $journal, private TokenStorageInterface $jeton) {}

    public function postPersist(LifecycleEventArgs $evenement): void { $this->enregistrer($evenement, 'ajout'); }
    public function postUpdate(LifecycleEventArgs $evenement): void { $this->enregistrer($evenement, 'modification'); }
    public function postRemove(LifecycleEventArgs $evenement): void { $this->enregistrer($evenement, 'suppression'); }

    // Les événements Doctrine sont émis pendant la transaction : le journal suit aussi ses annulations.
    private function enregistrer(LifecycleEventArgs $evenement, string $operation): void
    {
        $administrateur = $this->jeton->getToken()?->getUser();
        if (!$administrateur instanceof Utilisateur || !in_array('ROLE_ADMIN', $administrateur->getRoles(), true)) return;
        $objet = $evenement->getObject();
        $action = null;
        $details = [];
        $livre = null;
        if ($objet instanceof Livre && $operation !== 'suppression') {
            $livre = $objet;
            $action = $operation === 'ajout' ? 'livre_ajoute' : 'livre_modifie';
        } elseif ($objet instanceof SessionLecture) {
            $livre = $objet->getLivre();
            $action = ['ajout' => 'lecture_ajoutee', 'modification' => 'lecture_modifiee', 'suppression' => 'lecture_supprimee'][$operation];
            $details = ['statut' => $objet->getStatut(), 'note' => $objet->getNote(), 'debut' => $objet->getDateDebut()?->format('Y-m-d'), 'fin' => $objet->getDateFin()?->format('Y-m-d')];
        } elseif ($objet instanceof Souhait) {
            $livre = $objet->getLivre();
            $changements = $evenement->getObjectManager()->getUnitOfWork()->getEntityChangeSet($objet);
            $achete = isset($changements['statut']) && $changements['statut'][1] === 'acquis';
            $action = ['ajout' => 'souhait_ajoute', 'modification' => $achete ? 'souhait_achete' : 'souhait_modifie', 'suppression' => 'souhait_supprime'][$operation];
        } elseif ($objet instanceof Pret && $operation !== 'modification') {
            $livre = $objet->getExemplaire()?->getLivre();
            $action = $operation === 'ajout' ? 'pret_ajoute' : 'pret_supprime';
        } elseif ($objet instanceof Exemplaire && $operation === 'modification') {
            $changements = $evenement->getObjectManager()->getUnitOfWork()->getEntityChangeSet($objet);
            if (isset($changements['dateRetrait']) && $changements['dateRetrait'][1] !== null) {
                $livre = $objet->getLivre();
                $action = 'livre_retire';
            }
        }
        // Ne sérialise jamais les entités : seuls les champs utiles, sans mots de passe ni coordonnées de contacts, sont copiés.
        if ($action && $livre) {
            $this->journal->conserver($action, $administrateur->getId(), $administrateur->getUserIdentifier(),
                ['id' => $livre->getId(), 'titre' => $livre->getTitre()], $details);
        }
    }
}
