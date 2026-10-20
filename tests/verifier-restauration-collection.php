<?php
ob_start();
// Test transactionnel local : php tests/verifier-restauration-collection.php --local
if (!in_array('--local', $argv, true)) {
    throw new RuntimeException('Lancez ce test avec --local sur la base de développement.');
}
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$params=(new Doctrine\DBAL\Tools\DsnParser(['mysql'=>'pdo_mysql']))->parse($_ENV['DATABASE_URL']);
if (!in_array($params['host'] ?? 'localhost', ['localhost','127.0.0.1','::1'], true)) {
    throw new RuntimeException('Ce test est réservé à une base locale.');
}
class SecuriteAccesKernel extends App\Kernel {
 public function getProjectDir():string{return dirname(__DIR__);}
 public function getCacheDir():string{return sys_get_temp_dir().'/incipit-acces-cache';}
 public function getLogDir():string{return sys_get_temp_dir().'/incipit-acces-logs';}
 protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $c):void{
  parent::build($c);$c->addCompilerPass(new class implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface{public function process(Symfony\Component\DependencyInjection\ContainerBuilder $c):void{foreach(['security.untracked_token_storage','session.factory']as$id){if($c->hasAlias($id))$c->getAlias($id)->setPublic(true);elseif($c->hasDefinition($id))$c->getDefinition($id)->setPublic(true);}}});
 }
}

$k=new SecuriteAccesKernel('dev',true);
$b=new Symfony\Bundle\FrameworkBundle\KernelBrowser($k); $b->disableReboot();
$b->request('GET','/connexion');
$em=$k->getContainer()->get('doctrine')->getManager();$db=$em->getConnection();$db->beginTransaction();
try {
 $admin=$em->getRepository(App\Entity\Utilisateur::class)->findOneBy([],['id'=>'ASC']);$b->loginUser($admin);

 $book=(new App\Entity\Livre())->setTitre('Test restauration')->setDateCreation(new DateTime())->setDateModification(new DateTime());$em->persist($book);
 $old=(new App\Entity\Exemplaire())->setLivre($book)->setDateCreation(new DateTime('-1 year'))->setDateRetrait(new DateTime('-1 day'));$em->persist($old);
 $session=(new App\Entity\SessionLecture())->setLivre($book)->setStatut('Terminé')->setNote(1)->setDateCreation(new DateTime())->setDateModification(new DateTime());$em->persist($session);$em->flush();
 $id=$book->getId();$sid=$session->getId();$count=$db->fetchOne('SELECT COUNT(*) FROM livre');
 $c=$b->request('GET',"/livre/$id/details-retire");
 if($b->getResponse()->getStatusCode()!==200)throw new RuntimeException('Détails retirés');
 $token=$c->filter('[data-rajouter-livre] input[name="_token"]')->attr('value');
 $b->request('POST',"/livre/$id/rajouter",['_token'=>'incorrect']);
 if($b->getResponse()->getStatusCode()!==403)throw new RuntimeException('CSRF');
 for($i=0;$i<2;$i++){
  $b->request('POST',"/livre/$id/rajouter",['_token'=>$token],[],['HTTP_ORIGIN'=>'http://localhost']);
  if($b->getResponse()->getStatusCode()!==200)throw new RuntimeException('Restauration : '.$b->getResponse()->getContent());
 }
 if((int)$db->fetchOne('SELECT COUNT(*) FROM exemplaire WHERE livre_id=? AND date_retrait IS NULL',[$id])!==1)throw new RuntimeException('Doublon');
 if(!$db->fetchOne('SELECT date_retrait FROM exemplaire WHERE id=?',[$old->getId()]))throw new RuntimeException('Retrait perdu');
 if($db->fetchOne('SELECT COUNT(*) FROM livre')!==$count || (float)$db->fetchOne('SELECT note FROM session_lecture WHERE id=?',[$sid])!==1.0)throw new RuntimeException('Historique altéré');
 $c=$b->request('GET','/espace/mes-lectures');
 if(!$c->filter('#bibliotheque-livres [data-livre-id="'.$id.'"]')->count())throw new RuntimeException('Livre absent');
 $wish=(new App\Entity\Livre())->setTitre('Sans exemplaire')->setDateCreation(new DateTime())->setDateModification(new DateTime());$em->persist($wish);$em->flush();
 $b->request('POST','/livre/'.$wish->getId().'/rajouter',['_token'=>$token],[],['HTTP_ORIGIN'=>'http://localhost']);
 if($b->getResponse()->getStatusCode()!==409)throw new RuntimeException('Souhait accepté');
 echo "Restauration : CSRF, absence de doublon, conservation de la fiche et de la note, retour dans Mes lectures et refus hors retraits : OK\n";
} finally {if($db->isTransactionActive())$db->rollBack();$k->shutdown();}
