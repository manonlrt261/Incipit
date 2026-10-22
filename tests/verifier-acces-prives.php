<?php

ob_start();

// Test transactionnel local : php tests/verifier-acces-prives.php --local
if (!in_array('--local', $argv, true)) {
    throw new RuntimeException('Lancez ce test avec --local sur la base de développement.');
}
require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$params = (new Doctrine\DBAL\Tools\DsnParser(['mysql' => 'pdo_mysql']))->parse($_ENV['DATABASE_URL']);

if (!in_array($params['host'] ?? 'localhost', ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Ce test est réservé à une base locale.');
}

class SecuriteAccesKernel extends App\Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/incipit-acces-cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/incipit-acces-logs';
    }

    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $c): void
    {
        parent::build($c);
        // Rend accessibles les services nécessaires à la simulation des sessions de connexion.
        $c->addCompilerPass(new class () implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(Symfony\Component\DependencyInjection\ContainerBuilder $c): void
            {
                foreach (['security.untracked_token_storage', 'session.factory'] as $id) {
                    if ($c->hasAlias($id)) {
                        $c->getAlias($id)->setPublic(true);
                    } elseif ($c->hasDefinition($id)) {
                        $c->getDefinition($id)->setPublic(true);
                    }
                }
            }
        });
    }
}

$k = new SecuriteAccesKernel('dev', true);
$b = new Symfony\Bundle\FrameworkBundle\KernelBrowser($k);
$b->disableReboot();
$b->request('GET', '/connexion');
$em = $k->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$tests = 0;

function check($ok, $m)
{
    global $tests;

    if (!$ok) {
        throw new RuntimeException($m);
    }
    $tests++;
}
// Parcourt les routes réelles afin de repérer aussi les nouveaux parcours privés.
$routes = [];

foreach ($k->getContainer()->get('router')->getRouteCollection() as $name => $r) {
    $path = $r->getPath();

    if (!preg_match('~^/(espace|livre|wishlist|mes-prets|tableau-de-bord)(/|$)~', $path)) {
        continue;
    }
    $path = preg_replace_callback('/\{([^}]+)\}/', fn ($m) => $m[1] === 'action' ? (str_starts_with($path, '/wishlist') ? 'acheter' : 'ajouter') : '1', $path);

    foreach ($r->getMethods() ?: ['GET'] as $method) {
        if (in_array($method, ['GET', 'POST'])) {
            $routes[] = [$method, $path];
        }
    }
}

foreach ($routes as [$method, $url]) {
    $b->request($method, $url);
    check(($b->getResponse()->isRedirect() && parse_url($b->getResponse()->headers->get('Location'), PHP_URL_PATH) === '/connexion'), 'Anonyme bloqué ' . $method . ' ' . $url);
}
echo count($routes) . " parcours privés refusés sans connexion.\n";
// Les comptes et livres de contrôle ainsi que les modifications sont annulés à la fin.
$db->beginTransaction();

try {
    $regular = (new App\Entity\Utilisateur())->setEmail('acces-test@example.test')->setRoles([])->setPassword('test-non-utilisable');
    $em->persist($regular);
    $em->flush();
    $b->loginUser($regular);

    foreach ($routes as [$method, $url]) {
        $b->request($method, $url);
        check($b->getResponse()->getStatusCode() === 403, 'Utilisateur non admin bloqué ' . $url);
    }
    echo count($routes) . " parcours refusés sans rôle administrateur.\n";
    $owner = $em->getRepository(App\Entity\Utilisateur::class)->findOneBy([], ['id' => 'ASC']);
    $jury = $em->getRepository(App\Entity\Utilisateur::class)->findOneBy(['email' => 'incipitbibliothequevirtuelle@gmail.com']);

    foreach ([$owner, $jury] as $user) {
        check($user !== null, 'Compte existant');
        $b->loginUser($user);

        foreach (['/espace/mes-lectures', '/livre', '/espace/ma-wishlist', '/espace/mes-auteurs', '/mes-prets', '/espace/mon-profil', '/espace/mes-statistiques', '/tableau-de-bord', '/espace/accueil/edition'] as $url) {
            $b->request('GET', $url);
            check($b->getResponse()->getStatusCode() === 200, 'Page admin ' . $url);
        }
    }
    $book = (new App\Entity\Livre())->setTitre('TEST PRIVÉ ACCÈS')->setSaga('Saga accès')->setDateCreation(new DateTime())->setDateModification(new DateTime());
    $em->persist($book);
    $author = (new App\Entity\Auteur())->setNom('Test')->setPrenom('Accès')->setNomAffichage('Accès Test');
    $em->persist($author);
    $book->addAuteur($author);
    $copy = (new App\Entity\Exemplaire())->setLivre($book)->setDateCreation(new DateTime());
    $em->persist($copy);
    $contact = (new App\Entity\Contact())->setPrenom('Contact fictif')->setDateCreation(new DateTime());
    $em->persist($contact);
    $loan = (new App\Entity\Pret())->setExemplaire($copy)->setContact($contact)->setStatut('En cours')->setDatePret(new DateTime())->setDateCreation(new DateTime());
    $em->persist($loan);
    $wish = (new App\Entity\Souhait())->setLivre($book)->setStatut('souhaité')->setPublique(false)->setDateAjout(new DateTime());
    $em->persist($wish);
    $session = (new App\Entity\SessionLecture())->setLivre($book)->setStatut('En cours')->setDateCreation(new DateTime())->setDateModification(new DateTime());
    $em->persist($session);
    $em->flush();
    $id = $book->getId();
    $a = $author->getId();
    $w = $wish->getId();
    $p = $loan->getId();
    $c = $contact->getId();
    $l = $session->getId();
    $actions = ["/livre/modal/ajouter", "/livre/$id/modifier", "/livre/$id/retirer", "/livre/$id/pile/ajouter", "/livre/$id/pile/retirer", "/livre/$id/pile/commencer", "/livre/$id/lecture/ajouter", "/livre/$id/lecture/$l/modifier", "/livre/$id/lecture/$l/supprimer", '/wishlist/ajouter', "/wishlist/$w/modifier", "/wishlist/$w/acheter", "/wishlist/$w/supprimer", '/mes-prets/ajouter', "/mes-prets/$p/supprimer", '/mes-prets/contacts/ajouter', "/mes-prets/contacts/$c/modifier", "/mes-prets/contacts/$c/supprimer", '/espace/mon-profil/mot-de-passe', '/espace/mon-profil/visibilite', '/espace/accueil/ordre', '/espace/mes-statistiques/objectif'];

    foreach ($actions as $url) {
        foreach ([[], ['_token' => 'invalide']] as $data) {
            $b->request('POST', $url, $data);
            check($b->getResponse()->getStatusCode() === 403, 'CSRF ' . $url);
        }
    }

    foreach (["/espace/mes-auteurs/$a/modifier", "/espace/mes-auteurs/$a/saga/modifier?saga=saga%20accès", '/livre/ajouter'] as $url) {
        $b->request('POST', $url, ['_token' => 'invalide']);
        check($b->getResponse()->getStatusCode() === 200, 'Formulaire affiche le refus ' . $url);
    }
    $em->clear();
    check($em->find(App\Entity\Livre::class, $id)->getTitre() === 'TEST PRIVÉ ACCÈS', 'Livre préservé');
    check($em->find(App\Entity\Souhait::class, $w)->getStatut() === 'souhaité', 'Souhait préservé');
    $b->request('GET', "/livre/$id/retirer");
    check($b->getResponse()->getStatusCode() === 405, 'Retrait interdit en GET');
    // Quitte la session pour vérifier le contenu public et les détails privés du souhait.
    $b->getCookieJar()->clear();
    $k->getContainer()->get('security.untracked_token_storage')->setToken(null);
    $b->request('GET', '/ma-wishlist');
    check(!str_contains($b->getResponse()->getContent(), 'TEST PRIVÉ ACCÈS'), 'Souhait privé absent du public');
    $b->request('GET', "/wishlist/$w/details");
    check(($b->getResponse()->isRedirect() && parse_url($b->getResponse()->headers->get('Location'), PHP_URL_PATH) === '/connexion'), 'Détails souhait privés');
    // Une déconnexion sans jeton ne doit pas fermer la session ; une soumission depuis le site la ferme.
    $b->loginUser($owner);
    $b->request('POST', '/logout', ['_csrf_token' => 'invalide']);
    $b->request('GET', '/espace/mon-profil');
    check($b->getResponse()->getStatusCode() === 200, 'Déconnexion invalide ne ferme pas la session');
    $c = $b->request('GET', '/espace/mon-profil');
    $token = $c->filter('form[action="/logout"] input[name="_csrf_token"]')->attr('value');
    $b->request('POST', '/logout', ['_csrf_token' => $token], [], ['HTTP_ORIGIN' => 'http://localhost']);
    check($b->getResponse()->isRedirect(), 'Déconnexion valide');
    $b->request('GET', '/espace/mon-profil');
    check($b->getResponse()->isRedirect(), 'Session terminée après déconnexion');
    echo "Tests CSRF, pages des deux administrateurs, souhait privé et méthodes HTTP vérifiés. Total : $tests contrôles.\n";
} finally {
    if ($db->isTransactionActive()) {
        $db->rollBack();
    }
    $k->shutdown();
}
