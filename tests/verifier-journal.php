<?php

// Exécuter avec --local ; MongoDB de test doit écouter exclusivement en local sur le port 28719.
ob_start();
if (!in_array('--local', $argv, true)) throw new RuntimeException('Test réservé à --local.');
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$params = (new Doctrine\DBAL\Tools\DsnParser(['mysql' => 'pdo_mysql']))->parse($_ENV['DATABASE_URL']);
if (!in_array($params['host'] ?? 'localhost', ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Une base locale est requise.');
$_ENV['MONGODB_URI'] = $_SERVER['MONGODB_URI'] = 'mongodb://127.0.0.1:28719';
$base = 'incipit_test_journal_'.bin2hex(random_bytes(6));
$_ENV['MONGODB_DB'] = $_SERVER['MONGODB_DB'] = $base;
$manager = new MongoDB\Driver\Manager($_ENV['MONGODB_URI'], ['serverSelectionTimeoutMS' => 500]);
$mongo = new App\Service\JournalMongo($_ENV['MONGODB_URI'], $base);
$tests = 0;
function verifier(bool $ok, string $message): void { global $tests; if (!$ok) throw new RuntimeException($message); ++$tests; }
class JournalTestKernel extends App\Kernel
{
    public function getProjectDir(): string { return dirname(__DIR__); }
    public function getCacheDir(): string { return sys_get_temp_dir().'/incipit-journal-cache'; }
    public function getLogDir(): string { return sys_get_temp_dir().'/incipit-journal-logs'; }
    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $c): void
    {
        parent::build($c);
        $c->addCompilerPass(new class implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(Symfony\Component\DependencyInjection\ContainerBuilder $c): void
            {
                foreach (['security.untracked_token_storage', 'session.factory'] as $id) {
                    if ($c->hasAlias($id)) $c->getAlias($id)->setPublic(true);
                    elseif ($c->hasDefinition($id)) $c->getDefinition($id)->setPublic(true);
                }
            }
        });
    }
}
$kernel = new JournalTestKernel('dev', true);
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$db = null;
try {
    // Une vraie base MongoDB isolée vérifie les requêtes BSON et l’idempotence, sans toucher au journal réel.
    $mongo->indexer();
    $filtres = ['action' => '', 'livre' => '', 'debut' => '', 'fin' => ''];
    $document = ['version' => 1, 'action' => 'livre_ajoute', 'date' => '2026-10-20T10:30:00+00:00', 'administrateur' => ['id' => 1, 'compte' => 'test@example.test'], 'livre' => ['id' => 123, 'titre' => 'Livre [test]'], 'details' => []];
    $mongo->enregistrer('stable', $document); $mongo->enregistrer('stable', $document);
    verifier(count($mongo->rechercher($filtres, 1)['evenements']) === 1, 'Reprise sans doublon');
    verifier(count($mongo->rechercher(array_replace($filtres, ['livre' => '[test]']), 1)['evenements']) === 1, 'Recherche littérale et non opérateur regex');
    verifier(count($mongo->rechercher(array_replace($filtres, ['action' => 'pret_ajoute']), 1)['evenements']) === 0, 'Filtre action');
    verifier(count($mongo->rechercher(array_replace($filtres, ['debut' => '2026-10-20', 'fin' => '2026-10-20']), 1)['evenements']) === 1, 'Bornes de dates inclusives');
    verifier(count($mongo->rechercher(array_replace($filtres, ['debut' => '2026-10-21']), 1)['evenements']) === 0, 'Date hors intervalle');
    for ($i = 0; $i < 31; ++$i) $mongo->enregistrer('pagination-'.$i, $document);
    verifier(count($mongo->rechercher($filtres, 1)['evenements']) === 30 && $mongo->rechercher($filtres, 1)['suite'], 'Premier lot borné');
    verifier(count($mongo->rechercher($filtres, 2)['evenements']) === 2 && !$mongo->rechercher($filtres, 2)['suite'], 'Dernier lot');

    // La file SQL de test est en mémoire : les tests de reprise ne transmettent aucun événement réel.
    $file = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $file->executeStatement('CREATE TABLE evenement_outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, identifiant_evenement TEXT, type_evenement TEXT, donnees TEXT, date_creation TEXT, date_transmission TEXT, statut TEXT, nombre_tentatives INTEGER)');
    $journal = new App\Service\JournalActivite($file, $mongo, new Psr\Log\NullLogger());
    $file->beginTransaction();
    $journal->conserver('livre_ajoute', 1, 'test@example.test', ['id' => 123, 'titre' => 'Transaction'], []);
    verifier($journal->transmettre()['transmis'] === 0, 'Aucune transmission avant validation SQL');
    $file->rollBack(); verifier($journal->enAttente() === 0, 'Annulation SQL du journal');
    $panne = new App\Service\JournalActivite($file, new App\Service\JournalMongo('mongodb://127.0.0.1:28720', $base), new Psr\Log\NullLogger());
    $panne->conserver('livre_ajoute', 1, 'test@example.test', ['id' => 123, 'titre' => 'Panne MongoDB'], []);
    verifier($panne->transmettre()['indisponible'] && $panne->enAttente() === 1, 'Panne sans perte des événements');
    verifier($journal->transmettre()['transmis'] === 1 && $journal->enAttente() === 0, 'Reprise après panne');
    verifier($journal->transmettre()['transmis'] === 0, 'Pas de nouvelle transmission du même événement');

    // Les vrais événements Doctrine sont vérifiés dans une transaction MySQL toujours annulée.
    $client->request('GET', '/espace/journal-activite');
    verifier($client->getResponse()->isRedirect(), 'Page privée sans connexion');
    $em = $kernel->getContainer()->get('doctrine')->getManager(); $db = $em->getConnection(); $db->beginTransaction();
    $avant = (int) $db->fetchOne('SELECT COUNT(*) FROM evenement_outbox');
    $seuil = (int) $db->fetchOne('SELECT COALESCE(MAX(id), 0) FROM evenement_outbox');
    $admins = $em->getRepository(App\Entity\Utilisateur::class)->findAll();
    foreach ($admins as $admin) {
        if (!in_array('ROLE_ADMIN', $admin->getRoles(), true)) continue;
        $client->loginUser($admin); $client->request('GET', '/espace/journal-activite');
        verifier($client->getResponse()->getStatusCode() === 200 && str_contains($client->getResponse()->getContent(), 'Livre [test]'), 'Consultation administrateur');
    }
    $livre = (new App\Entity\Livre())->setTitre('Journal intégration')->setDateCreation(new DateTime())->setDateModification(new DateTime());
    $em->persist($livre); $em->flush();
    $lecture = (new App\Entity\SessionLecture())->setLivre($livre)->setStatut('En cours')->setDateCreation(new DateTime())->setDateModification(new DateTime());
    $em->persist($lecture); $em->flush(); $lecture->setStatut('Terminé')->setNote('4.0'); $em->flush();
    $em->remove($lecture); $em->flush();
    $souhait = (new App\Entity\Souhait())->setLivre($livre)->setStatut('souhaité')->setPublique(false)->setDateAjout(new DateTime());
    $em->persist($souhait); $em->flush(); $souhait->setPublique(true); $em->flush(); $souhait->setStatut('acquis'); $em->flush(); $em->remove($souhait); $em->flush();
    $exemplaire = (new App\Entity\Exemplaire())->setLivre($livre)->setDateCreation(new DateTime()); $em->persist($exemplaire);
    $contact = (new App\Entity\Contact())->setPrenom('Fictif')->setDateCreation(new DateTime()); $em->persist($contact);
    $pret = (new App\Entity\Pret())->setExemplaire($exemplaire)->setContact($contact)->setStatut('En cours')->setDatePret(new DateTime())->setDateCreation(new DateTime());
    $em->persist($pret); $em->flush(); $em->remove($pret); $em->flush();
    $exemplaire->setDateRetrait(new DateTime()); $em->flush(); $livre->setTitre('Journal modifié'); $em->flush();
    $actions = $db->fetchFirstColumn('SELECT type_evenement FROM evenement_outbox WHERE id > ?', [$seuil]);
    foreach (array_keys(App\Service\JournalActivite::ACTIONS) as $action) {
        verifier(in_array($action, $actions, true), 'Événement Doctrine : '.$action);
    }
    $dernier = json_decode($db->fetchOne('SELECT donnees FROM evenement_outbox ORDER BY id DESC LIMIT 1'), true);
    verifier($dernier['livre']['titre'] === 'Journal modifié' && $dernier['administrateur']['id'] === $admin->getId(), 'Titre et acteur enregistrés');
    $client->request('GET', '/espace/journal-activite?debut=2026-02-31');
    verifier(str_contains($client->getResponse()->getContent(), 'Veuillez saisir des dates valides.'), 'Date invalide refusée');
    $db->rollBack(); verifier((int) $db->fetchOne('SELECT COUNT(*) FROM evenement_outbox') === $avant, 'Aucune donnée de test conservée');
    echo "$tests contrôles réussis : MongoDB, filtres, accès privés, Doctrine, panne, reprise et annulation.\n";
} finally {
    if ($db?->isTransactionActive()) $db->rollBack();
    $kernel->shutdown();
    $manager->executeCommand($base, new MongoDB\Driver\Command(['dropDatabase' => 1]));
}
