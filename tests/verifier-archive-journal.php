<?php

// Ce test utilise exclusivement des bases temporaires sur MongoDB local et n’importe jamais la configuration de production.
require dirname(__DIR__).'/vendor/autoload.php';
$manager = new MongoDB\Driver\Manager('mongodb://127.0.0.1:27017', ['serverSelectionTimeoutMS' => 1000]);
$nom = 'incipit_test_archive_'.bin2hex(random_bytes(6));
$restauration = $nom.'_restauration';
$dossier = sys_get_temp_dir().'/'.$nom;
mkdir($dossier, 0700);
$tests = 0;
function controle(bool $ok, string $message): void { global $tests; if (!$ok) throw new RuntimeException($message); ++$tests; }
try {
    $ecriture = new MongoDB\Driver\BulkWrite();
    $id = new MongoDB\BSON\ObjectId();
    $ecriture->insert(['_id' => $id, 'date' => new MongoDB\BSON\UTCDateTime(1729440000123), 'titre' => 'Événement de contrôle', 'nombre' => new MongoDB\BSON\Decimal128('4.50')]);
    $manager->executeBulkWrite($nom.'.journal_activite', $ecriture);
    $manager->executeCommand($nom, new MongoDB\Driver\Command(['createIndexes' => 'journal_activite', 'indexes' => [['key' => ['date' => -1], 'name' => 'date_test']]]));
    $archive = new App\Service\ArchiveJournal();
    $meta = $archive->exporter('mongodb://127.0.0.1:27017', $nom, $dossier);
    controle($meta['documents'] === 1, 'Nombre de documents');
    controle(count($meta['indexes']) === 2, 'Index sauvegardés');
    controle(!str_contains(file_get_contents($dossier.'/journal.mongodb.metadata.json'), 'mongodb://'), 'Pas d’URI dans les métadonnées');
    $flux = gzopen($dossier.'/journal.mongodb.jsonl.gz', 'rb');
    $copie = new MongoDB\Driver\BulkWrite();
    while (($ligne = gzgets($flux)) !== false) $copie->insert(MongoDB\BSON\Document::fromJSON($ligne));
    gzclose($flux);
    $manager->executeBulkWrite($restauration.'.journal_activite', $copie);
    $lu = $manager->executeQuery($restauration.'.journal_activite', new MongoDB\Driver\Query(['_id' => $id]))->toArray();
    controle(count($lu) === 1, 'Restauration dans une base séparée');
    controle((string)$lu[0]->date === '1729440000123', 'Date BSON conservée');
    controle($lu[0]->nombre instanceof MongoDB\BSON\Decimal128 && (string)$lu[0]->nombre === '4.50', 'Type décimal conservé');
    foreach ($meta['indexes'] as $index) {
        if ($index['name'] === '_id_') continue;
        $index = MongoDB\BSON\Document::fromJSON(json_encode($index, JSON_THROW_ON_ERROR))->toPHP();
        unset($index->v, $index->ns);
        $manager->executeCommand($restauration, new MongoDB\Driver\Command(['createIndexes' => 'journal_activite', 'indexes' => [$index]]));
    }
    controle(count($manager->executeCommand($restauration, new MongoDB\Driver\Command(['listIndexes' => 'journal_activite']))->toArray()) === 2, 'Index restaurés');
    try { $archive->verifier($dossier.'/journal.mongodb.jsonl.gz', 99, $meta['sha256_decompresse']); throw new LogicException('Contrôle attendu'); }
    catch (RuntimeException) { ++$tests; }
    try { $archive->verifier($dossier.'/journal.mongodb.jsonl.gz', 1, str_repeat('0', 64)); throw new LogicException('Contrôle attendu'); }
    catch (RuntimeException) { ++$tests; }
    try { $archive->exporter('mongodb://127.0.0.1:28720', $nom, $dossier); throw new LogicException('Panne attendue'); }
    catch (MongoDB\Driver\Exception\ConnectionTimeoutException) { ++$tests; }
    echo "$tests contrôles réussis : export, restauration isolée, types BSON, index, intégrité et indisponibilité.\n";
} finally {
    $manager->executeCommand($nom, new MongoDB\Driver\Command(['dropDatabase' => 1]));
    $manager->executeCommand($restauration, new MongoDB\Driver\Command(['dropDatabase' => 1]));
    foreach (glob($dossier.'/*') as $fichier) unlink($fichier);
    rmdir($dossier);
}
