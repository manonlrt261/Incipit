<?php
// Sauvegarde les données et les couvertures hors du projet, sans afficher les identifiants MySQL.
$projet = $argv[1] ?? dirname(__DIR__);
$destination = $argv[2] ?? (getenv('HOME').'/sauvegardes');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require $projet.'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($projet.'/.env');
$params = (new Doctrine\DBAL\Tools\DsnParser(['mysql' => 'pdo_mysql']))->parse($_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? '');
if (($params['driver'] ?? '') !== 'pdo_mysql') throw new RuntimeException('Une base MySQL est attendue.');
umask(0077);
// Un verrou empêche deux sauvegardes simultanées d’alourdir le serveur.
if (!is_dir($destination) && !mkdir($destination, 0700, true)) throw new RuntimeException('Destination inaccessible.');
$destination = realpath($destination);
$public = realpath($projet.'/public');
if ($public && ($destination === $public || str_starts_with($destination, $public.DIRECTORY_SEPARATOR))) throw new RuntimeException('La sauvegarde doit rester hors du dossier public.');
$verrou = fopen($destination.'/.sauvegarde.lock', 'c');
if (!$verrou || !flock($verrou, LOCK_EX | LOCK_NB)) { fwrite(STDERR, "Une sauvegarde est déjà en cours.\n"); exit(1); }
$statut = 0;
$dossier = $destination.'/incipit-'.date('Y-m-d-His').'-'.bin2hex(random_bytes(3));
if (!mkdir($dossier, 0700, true)) throw new RuntimeException('Création du dossier impossible.');
$config = tempnam(sys_get_temp_dir(), 'incipit-db-');
function executer(array $commande, string $sortie): void {
    $processus = proc_open($commande, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $sortie, 'w'], 2 => ['pipe', 'w']], $flux);
    if (!is_resource($processus)) throw new RuntimeException('Démarrage de la sauvegarde impossible.');
    $erreur = stream_get_contents($flux[2]);
    fclose($flux[2]);
    if (proc_close($processus) !== 0) throw new RuntimeException('Échec de la commande de sauvegarde. Vérifiez les outils et les droits MySQL.');
}
try {
    // Le mot de passe passe dans un fichier temporaire privé, jamais dans la ligne de commande.
    $options = "[client]\n";
    foreach (['host' => 'host', 'port' => 'port', 'user' => 'user', 'password' => 'password', 'unix_socket' => 'socket'] as $champ => $option) {
        if (isset($params[$champ])) $options .= $option.'="'.str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], (string) $params[$champ]).'"'."\n";
    }
    file_put_contents($config, $options);
    chmod($config, 0600);
    executer(['mysqldump', '--defaults-extra-file='.$config, '--single-transaction', '--quick', '--no-tablespaces', '--hex-blob', '--default-character-set=utf8mb4', '--databases', $params['dbname']], $dossier.'/base.sql');
    if (filesize($dossier.'/base.sql') === 0) throw new RuntimeException('Le fichier SQL est vide.');
    $couvertures = $projet.'/public/uploads/couvertures';
    if (is_dir($couvertures)) {
        executer(['tar', '-czf', $dossier.'/couvertures.tar.gz', '-C', $projet.'/public', 'uploads/couvertures'], $dossier.'/archive.log');
        executer(['tar', '-tzf', $dossier.'/couvertures.tar.gz'], $dossier.'/liste-couvertures.txt');
    } else {
        file_put_contents($dossier.'/couvertures-absentes.txt', "Aucun dossier de couvertures importées au moment de la sauvegarde.\n");
    }
    // MongoDB utilise l’extension PHP déjà installée : aucun identifiant n’est transmis à une commande externe.
    $archiveJournal = new App\Service\ArchiveJournal();
    $informations = $archiveJournal->exporter(
        $_ENV['MONGODB_URI'] ?? $_SERVER['MONGODB_URI'] ?? '',
        $_ENV['MONGODB_DB'] ?? $_SERVER['MONGODB_DB'] ?? '',
        $dossier
    );
    file_put_contents($dossier.'/LISEZ-MOI.txt', "Copie MySQL, couvertures importées et journal MongoDB (Extended JSON canonique compressé, avec index). Les images distantes ne sont pas téléchargées. Copie privée : ne pas publier ni versionner. Les bases sont exportées successivement, sans instantané commun : éviter les modifications pendant la sauvegarde. La table evenement_outbox est incluse dans MySQL. Une restauration réelle doit être faite dans des bases séparées avant tout remplacement.\n");
    // Les empreintes permettent de vérifier les fichiers après leur téléchargement.
    $empreintes = [];
    foreach (glob($dossier.'/*') as $fichier) $empreintes[] = hash_file('sha256', $fichier).'  '.basename($fichier);
    file_put_contents($dossier.'/SHA256SUMS', implode("\n", $empreintes)."\n");

    echo "Sauvegarde créée : ".$dossier.PHP_EOL;
    echo "SQL non vide ; couvertures contrôlées ; journal MongoDB relu et validé ; empreintes SHA-256 enregistrées.".PHP_EOL;
} catch (Throwable $e) {
    file_put_contents($dossier.'/ECHEC.txt', "Sauvegarde incomplète : ne pas utiliser pour restaurer.\n");
    fwrite(STDERR, 'Sauvegarde incomplète : '.get_class($e).'. Vérifier MySQL, MongoDB, les droits et l’espace disponible.'.PHP_EOL);
    $statut = 1;
} finally {
    if (is_file($config)) unlink($config);
    flock($verrou, LOCK_UN);
    fclose($verrou);
}

exit($statut);
