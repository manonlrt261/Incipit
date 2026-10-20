<?php
ob_start();
// Test transactionnel local : php tests/verifier-acces-prives.php --local
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
 $marque='Recherche'.bin2hex(random_bytes(5));
 $livre=(new App\Entity\Livre())->setTitre($marque.' titre')->setSaga($marque.' saga')->setIsbn13('9780000000008')->setIsbn10('123456789X')->setDateCreation(new DateTime())->setDateModification(new DateTime());
 $auteur=(new App\Entity\Auteur())->setPrenom($marque)->setNom('Auteur')->setNomAffichage($marque.' Auteur');
 $em->persist($auteur);$livre->addAuteur($auteur);$em->persist($livre);
 $exemplaire=(new App\Entity\Exemplaire())->setLivre($livre)->setDateCreation(new DateTime());$em->persist($exemplaire);
 $horsCollection=(new App\Entity\Livre())->setTitre($marque.' wishlist')->setDateCreation(new DateTime())->setDateModification(new DateTime());$em->persist($horsCollection);
 $retire=(new App\Entity\Livre())->setTitre($marque.' retiré')->setDateCreation(new DateTime())->setDateModification(new DateTime());$em->persist($retire);
 $ancien=(new App\Entity\Exemplaire())->setLivre($retire)->setDateCreation(new DateTime())->setDateRetrait(new DateTime());$em->persist($ancien);$em->flush();
 foreach([$marque.' titre',$marque.' Auteur',$marque.' saga',strtolower($marque).' auteur','978-0-0000-0000-8','1-234-56789-X'] as $terme){
  $c=$b->request('GET','/tableau-de-bord',['recherche'=>$terme]);
  if($b->getResponse()->getStatusCode()!==200 || !$c->filter('.dashboard-resultats .dashboard-livre h3')->count() || !in_array($livre->getTitre(),$c->filter('.dashboard-resultats .dashboard-livre h3')->each(fn($n)=>$n->text()),true))throw new RuntimeException('Recherche non trouvée : '.$terme);
  if(!str_contains($c->filter('.dashboard-recherche')->attr('action'),'/tableau-de-bord'))throw new RuntimeException('Mauvaise cible');
 }
 foreach([$marque.' wishlist',$marque.' retiré',"' OR 1=1 --",$marque.'%'] as $terme){
  $c=$b->request('GET','/tableau-de-bord',['recherche'=>$terme]);
  if($b->getResponse()->getStatusCode()!==200 || $c->filter('.dashboard-resultats .dashboard-livre')->count()!==0)throw new RuntimeException('Périmètre ou saisie littérale incorrects');
 }
 $c=$b->request('GET','/tableau-de-bord',['recherche'=>'   ']);if($c->filter('.dashboard-resultats')->count()!==0)throw new RuntimeException('Recherche vide');
 // Vérifie qu’une recherche comportant plus de trente livres reste consultable jusqu’à la dernière page.
 for($i=0;$i<31;$i++){
  $volume=(new App\Entity\Livre())->setTitre($marque.' Pagination '.str_pad((string)$i,2,'0',STR_PAD_LEFT))->setDateCreation(new DateTime())->setDateModification(new DateTime());$em->persist($volume);
  $copie=(new App\Entity\Exemplaire())->setLivre($volume)->setDateCreation(new DateTime());$em->persist($copie);
 }
 $em->flush();
 $c=$b->request('GET','/tableau-de-bord',['recherche'=>$marque.' Pagination']);
 if($c->filter('.dashboard-resultats .dashboard-livre')->count()!==30)throw new RuntimeException('Première page des résultats');
 $c=$b->request('GET','/tableau-de-bord',['recherche'=>$marque.' Pagination','page'=>2]);
 if($c->filter('.dashboard-resultats .dashboard-livre')->count()!==1 || !str_contains($c->filter('.dashboard-resultats .dashboard-livre h3')->text(),'Pagination 30'))throw new RuntimeException('Dernière page des résultats');
 echo "Recherche par titre, auteur, saga et ISBN formaté : OK ; maintien sur le tableau de bord, absence de recherche vide, exclusion des souhaits et retraits et paramètres liés vérifiés.\n";
} finally {if($db->isTransactionActive())$db->rollBack();$k->shutdown();}
