<?php
// Vérifie les règles métier sans base de données ni service externe.
require dirname(__DIR__).'/vendor/autoload.php';
use App\Service\DonneesLecture;
use Symfony\Component\HttpFoundation\Request;
$service = new DonneesLecture();
$cas = [
 ['statut inconnu', ['statut'=>'Inconnu'], 'Statut de lecture invalide.'],
 ['note hors limites', ['statut'=>'Terminé','note'=>'6'], 'La note doit être comprise entre 0 et 5, par demi-étoile.'],
 ['note non numérique', ['note'=>'abc'], 'La note doit être comprise entre 0 et 5, par demi-étoile.'],
 ['date inexistante', ['statut'=>'En cours','dateDebut'=>'2026-02-31'], 'La date de lecture est invalide.'],
 ['dates inversées', ['statut'=>'Terminé','dateDebut'=>'2026-10-20','dateFin'=>'2026-10-19'], 'La date de fin ne peut pas précéder la date de début.'],
];
foreach ($cas as [$nom,$entree,$attendu]) {
 try {$service->lire(new Request([], $entree)); throw new RuntimeException('Valeur invalide acceptée : '.$nom);}
 catch (InvalidArgumentException $e) {if($e->getMessage()!==$attendu)throw new RuntimeException('Message inattendu : '.$nom);}
 echo "OK : $nom refusé\n";
}
$vide=$service->lire(new Request([],['note'=>'']));
$zero=$service->lire(new Request([],['note'=>'0']));
if($vide['note']!==null || $zero['note']!=='0')throw new RuntimeException('Note vide confondue avec zéro');
echo "OK : note absente distincte de zéro\n";
$livre=(new App\Entity\Livre())->setPileALire(true);
$session=(new App\Entity\SessionLecture())->setLivre($livre);
$donnees=$service->lire(new Request([],['statut'=>'Terminé','note'=>'4.5','dateDebut'=>'2026-10-01','dateFin'=>'2026-10-20']));
$service->appliquer($session,$donnees);
if($livre->isPileALire() || $session->getStatut()!=='Terminé' || (float)$session->getNote()!==4.5)throw new RuntimeException('Fin de lecture incorrecte');
echo "OK : fin de lecture, note et retrait automatique de la pile\n7 contrôles métier réussis.\n";
