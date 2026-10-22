<?php

ob_start();

// Test transactionnel local : php tests/verifier-confidentialite-visiteurs.php --local
if (!in_array('--local', $argv, true)) {
    throw new RuntimeException('Lancez ce test avec --local sur la base de développement.');
}
require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$params = (new Doctrine\DBAL\Tools\DsnParser(['mysql' => 'pdo_mysql']))->parse($_ENV['DATABASE_URL']);

if (!in_array($params['host'] ?? 'localhost', ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Ce test est réservé à une base locale.');
}

class ConfidentialiteVisiteurKernel extends App\Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/incipit-visiteurs-cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/incipit-visiteurs-logs';
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

$kernel = new ConfidentialiteVisiteurKernel('dev', true);
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$client->request('GET', '/connexion');
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$controles = 0;
$verifier = static function (bool $valide, string $message) use (&$controles): void {
    if (!$valide) {
        throw new RuntimeException($message);
    }
    ++$controles;
};
$bloque = static fn ($reponse): bool => $reponse->isRedirect()
    && parse_url($reponse->headers->get('Location'), PHP_URL_PATH) === '/connexion';

// Compare toutes les lignes avant et après les tentatives, sans afficher les données privées.
$empreinte = static function () use ($db): string {
    $contenu = [];

    foreach ($db->fetchFirstColumn('SHOW TABLES') as $table) {
        $lignes = array_map('serialize', $db->fetchAllAssociative('SELECT * FROM `' . str_replace('`', '``', $table) . '`'));
        sort($lignes, SORT_STRING);
        $contenu[$table] = $lignes;
    }
    ksort($contenu);

    return hash('sha256', serialize($contenu));
};
$db->beginTransaction();

try {
    $owner = $em->getRepository(App\Entity\Utilisateur::class)->findOneBy([], ['id' => 'ASC']);
    $marque = 'Confidentiel-' . bin2hex(random_bytes(5));
    $creerSouhait = static function (string $titre, bool $public, string $statut = 'souhaité') use ($em) {
        $livre = (new App\Entity\Livre())->setTitre($titre)->setResume('Résumé ' . $titre)
            ->setDateCreation(new DateTime())->setDateModification(new DateTime());
        $souhait = (new App\Entity\Souhait())->setLivre($livre)->setPublique($public)
            ->setStatut($statut)->setDateAjout(new DateTime())->setPrixEstime('17.43')
            ->setLienAchat('https://example.test/' . $titre);
        $em->persist($livre);
        $em->persist($souhait);

        return $souhait;
    };
    $prive = $creerSouhait($marque, false);
    $public = $creerSouhait('Public-' . $marque, true);
    $achat = $creerSouhait('Acquis-' . $marque, true, 'acquis');
    $em->flush();
    $db->executeStatement('INSERT INTO parametre_confidentialite (utilisateur_id, afficher_wishlist, afficher_auteurs_favoris, afficher_livres_favoris, date_modification) VALUES (?, 1, 1, 1, NOW()) ON DUPLICATE KEY UPDATE afficher_wishlist = 1', [$owner->getId()]);
    $titrePrive = $prive->getLivre()->getTitre();
    $id = $prive->getLivre()->getId();
    $wishId = $prive->getId();

    // Même un vrai jeton obtenu par un administrateur ne remplace pas une connexion autorisée.
    $client->loginUser($owner);
    $page = $client->request('GET', '/espace/ma-wishlist');
    $jeton = $page->filter('[data-id="' . $wishId . '"]')->attr('data-token');
    $client->getCookieJar()->clear();
    $kernel->getContainer()->get('security.untracked_token_storage')->setToken(null);
    $avant = $empreinte();
    $actions = [
        '/livre/modal/ajouter', '/livre/' . $id . '/modifier', '/livre/' . $id . '/retirer',
        '/livre/' . $id . '/pile/ajouter', '/livre/' . $id . '/pile/retirer', '/livre/' . $id . '/pile/commencer',
        '/livre/' . $id . '/lecture/ajouter', '/wishlist/ajouter', '/wishlist/' . $wishId . '/modifier',
        '/wishlist/' . $wishId . '/acheter', '/wishlist/' . $wishId . '/supprimer',
        '/mes-prets/ajouter', '/mes-prets/contacts/ajouter', '/espace/accueil/ordre',
        '/espace/mon-profil/visibilite', '/espace/mon-profil/mot-de-passe',
        '/espace/mes-statistiques/objectif',
    ];

    foreach ($actions as $url) {
        foreach ([[], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'application/json']] as $entetes) {
            $client->request('POST', $url, ['_token' => $jeton, 'titre' => 'MODIFICATION NON AUTORISÉE', 'publique' => '1', 'statut' => 'Terminé'], [], $entetes);
            $verifier($bloque($client->getResponse()), 'Action anonyme bloquée : ' . $url);
        }
    }
    $verifier($empreinte() === $avant, 'Aucune ligne modifiée par les tentatives anonymes');

    // Vérifie le HTML complet, y compris les fiches masquées, plutôt que seulement l’affichage visuel.
    foreach (['/', '/ma-wishlist', '/ma-wishlist?publique=0', '/ma-wishlist?id=' . $wishId] as $url) {
        $page = $client->request('GET', $url);
        $verifier($client->getResponse()->getStatusCode() === 200, 'Page publique accessible');
        $titres = $page->filter('.wishlist-carte h2, .accueil-carte-livre h3')->each(static fn ($noeud) => $noeud->text());
        $verifier(!in_array($titrePrive, $titres, true), 'Titre privé absent de ' . $url);
        $html = $client->getResponse()->getContent();
        $verifier(!str_contains($html, 'Résumé ' . $titrePrive), 'Résumé privé absent');
        $verifier(!str_contains($html, 'https://example.test/' . $titrePrive), 'Lien privé absent');
        $verifier(!in_array($achat->getLivre()->getTitre(), $titres, true), 'Achat exclu du public');
    }
    $page = $client->request('GET', '/ma-wishlist');
    $verifier($page->filter('[data-livre-id="' . $public->getLivre()->getId() . '"]')->count() === 1, 'Souhait public affiché');
    $verifier($page->filter('.wishlist-details button[data-action]')->count() === 0, 'Aucune commande de gestion dans les fiches publiques');

    foreach (['/wishlist/' . $wishId . '/details', '/espace/ma-wishlist', '/livre/' . $id . '/details', '/ma-wishlist?edition=1', '/espace/accueil/edition'] as $url) {
        $client->request('GET', $url);
        $verifier($bloque($client->getResponse()), 'Adresse directe protégée : ' . $url);
    }
    // Masquer la section entière doit également fermer l’accès à sa page publique complète.
    $db->executeStatement('UPDATE parametre_confidentialite SET afficher_wishlist = 0 WHERE utilisateur_id = ?', [$owner->getId()]);
    $page = $client->request('GET', '/');
    $verifier($page->filter('a[href="/ma-wishlist"]')->count() === 0, 'Section wishlist masquée sur l’accueil');
    $client->request('GET', '/ma-wishlist');
    $verifier($client->getResponse()->getStatusCode() === 404, 'Page publique fermée lorsque la section est masquée');
    echo "OK : $controles contrôles visiteurs, confidentialité, paramètres manipulés et données inchangées.\n";
} finally {
    if ($db->isTransactionActive()) {
        $db->rollBack();
    }
    $kernel->shutdown();
}
