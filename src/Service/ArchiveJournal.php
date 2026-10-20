<?php

namespace App\Service;

use MongoDB\BSON\Document;
use MongoDB\Driver\{Command, Manager, Query};

final class ArchiveJournal
{
    // Le format Extended JSON canonique conserve les identifiants, dates et types BSON lors d’une restauration.
    public function exporter(string $uri, string $base, string $dossier): array
    {
        if (!extension_loaded('mongodb') || !$uri || !preg_match('/^[a-zA-Z0-9_-]{1,63}$/', $base)) {
            throw new \RuntimeException('Configuration MongoDB de sauvegarde absente ou invalide.');
        }
        $manager = new Manager($uri, ['serverSelectionTimeoutMS' => 10000, 'connectTimeoutMS' => 10000, 'socketTimeoutMS' => 30000]);
        // Vérifie également la présence de la collection pour ne pas annoncer une sauvegarde vide d’une mauvaise base.
        $collections = $manager->executeCommand($base, new Command(['listCollections' => 1, 'filter' => ['name' => 'journal_activite'], 'nameOnly' => true]))->toArray();
        if (!$collections) throw new \RuntimeException('Collection du journal introuvable.');
        $archive = $dossier.'/journal.mongodb.jsonl.gz';
        $flux = gzopen($archive, 'wb9');
        if (!$flux) throw new \RuntimeException('Création de l’archive MongoDB impossible.');
        $nombre = 0;
        $empreinte = hash_init('sha256');
        try {
            $curseur = $manager->executeQuery($base.'.journal_activite', new Query([], ['batchSize' => 500]));
            foreach ($curseur as $document) {
                $ligne = Document::fromPHP($document)->toCanonicalExtendedJSON()."\n";
                if (gzwrite($flux, $ligne) !== strlen($ligne)) throw new \RuntimeException('Écriture MongoDB incomplète.');
                hash_update($empreinte, $ligne);
                ++$nombre;
            }
        } finally { gzclose($flux); }
        $hash = hash_final($empreinte);
        $this->verifier($archive, $nombre, $hash);
        $indexes = [];
        foreach ($manager->executeCommand($base, new Command(['listIndexes' => 'journal_activite'])) as $index) {
            $indexes[] = json_decode(Document::fromPHP($index)->toCanonicalExtendedJSON(), true, 512, JSON_THROW_ON_ERROR);
        }
        $informations = ['format' => 'mongodb-canonical-extended-json-lines-gzip-v1', 'base' => $base,
            'collection' => 'journal_activite', 'documents' => $nombre, 'sha256_decompresse' => $hash,
            'date_utc' => gmdate(DATE_ATOM), 'indexes' => $indexes];
        if (file_put_contents($dossier.'/journal.mongodb.metadata.json', json_encode($informations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n") === false) {
            throw new \RuntimeException('Métadonnées MongoDB non enregistrées.');
        }
        return $informations;
    }

    // Relit tout le fichier compressé et valide chaque document avant de déclarer la copie complète.
    public function verifier(string $archive, int $nombreAttendu, string $hashAttendu): void
    {
        $flux = gzopen($archive, 'rb');
        if (!$flux) throw new \RuntimeException('Archive MongoDB illisible.');
        $nombre = 0;
        $empreinte = hash_init('sha256');
        try {
            while (($ligne = gzgets($flux)) !== false) {
                Document::fromJSON($ligne);
                hash_update($empreinte, $ligne);
                ++$nombre;
            }
            if (!gzeof($flux)) throw new \RuntimeException('Archive MongoDB tronquée.');
        } finally { gzclose($flux); }
        if ($nombre !== $nombreAttendu || !hash_equals($hashAttendu, hash_final($empreinte))) {
            throw new \RuntimeException('Le contrôle de l’archive MongoDB a échoué.');
        }
    }
}
