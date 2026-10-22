<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Centralise les écritures et les recherches MongoDB sans exposer la connexion au navigateur.
final class JournalMongo
{
    private ?\MongoDB\Driver\Manager $manager = null;

    public function __construct(
        #[Autowire('%env(default::MONGODB_URI)%')]
        private ?string $uri = null,
        #[Autowire('%env(default::MONGODB_DB)%')]
        private ?string $base = null,
    ) {
    }

    // Décrit uniquement la présence de la configuration pour comparer PHP web et CLI sans révéler les identifiants.
    public function diagnostic(): array
    {
        return [
            'php' => PHP_VERSION,

            'execution' => PHP_SAPI,

            'extension_mongodb' => extension_loaded('mongodb'),

            'version_mongodb' => phpversion('mongodb') ?: null,

            'uri_presente' => (bool) $this->uri,

            'base_renseignee' => (bool) $this->base,

            'php_ini' => php_ini_loaded_file() ?: null,
        ];
    }

    // Réutilise la connexion et borne les délais pour limiter l’attente lorsque MongoDB est indisponible.
    private function manager(): \MongoDB\Driver\Manager
    {
        if (!$this->uri || !extension_loaded('mongodb')) {
            throw new \RuntimeException('Le journal d’activité n’est pas encore connecté à MongoDB.');
        }

        return $this->manager ??= new \MongoDB\Driver\Manager($this->uri, [
            'serverSelectionTimeoutMS' => 500,
            'connectTimeoutMS' => 500,
            'socketTimeoutMS' => 1500,
        ]);
    }

    // Vérifie le nom de la base configurée avant de construire les noms de collections.
    private function base(): string
    {
        $base = $this->base ?: 'incipit';

        if (!preg_match('/^[a-zA-Z0-9_-]{1,63}$/', $base)) {
            throw new \RuntimeException('Nom de base du journal invalide.');
        }

        return $base;
    }

    // L’identifiant stable rend une reprise après panne inoffensive : un événement n’est inséré qu’une fois.
    public function enregistrer(string $id, array $document): void
    {
        $document['date'] = new \MongoDB\BSON\UTCDateTime(new \DateTimeImmutable($document['date']));
        $ecriture = new \MongoDB\Driver\BulkWrite();
        $ecriture->update(['_id' => $id], ['$setOnInsert' => $document], ['upsert' => true]);
        $this->manager()->executeBulkWrite($this->base() . '.journal_activite', $ecriture, ['writeConcern' => new \MongoDB\Driver\WriteConcern(1)]);
    }

    // Les filtres sont construits côté serveur ; aucun opérateur MongoDB envoyé par le client n’est utilisé.
    public function rechercher(array $filtres, int $page): array
    {
        $filtre = [];

        if ($filtres['action'] !== '') {
            $filtre['action'] = $filtres['action'];
        }

        if ($filtres['livre'] !== '') {
            $filtre['livre.titre'] = new \MongoDB\BSON\Regex(preg_quote($filtres['livre']), 'i');
        }

        foreach (['debut' => '$gte', 'fin' => '$lt'] as $champ => $operateur) {
            if ($filtres[$champ] === '') {
                continue;
            }
            $date = new \DateTimeImmutable($filtres[$champ], new \DateTimeZone('Europe/Paris'));

            if ($champ === 'fin') {
                $date = $date->modify('+1 day');
            }
            $filtre['date'][$operateur] = new \MongoDB\BSON\UTCDateTime($date);
        }
        $requete = new \MongoDB\Driver\Query($filtre, [
            'sort' => ['date' => -1, '_id' => -1],
            'skip' => ($page - 1) * 30,
            'limit' => 31,
            'maxTimeMS' => 1500,
        ]);
        $curseur = $this->manager()->executeQuery($this->base() . '.journal_activite', $requete);
        $curseur->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        $documents = $curseur->toArray();
        $suite = count($documents) > 30;
        $documents = array_slice($documents, 0, 30);

        foreach ($documents as &$document) {
            $document['date'] = $document['date']->toDateTime()->setTimezone(new \DateTimeZone('Europe/Paris'));
        }

        return ['evenements' => $documents, 'suite' => $suite];
    }

    // Prépare les index de consultation lors de la transmission, jamais depuis une saisie utilisateur.
    public function indexer(): void
    {
        $this->manager()->executeCommand($this->base(), new \MongoDB\Driver\Command([
            'createIndexes' => 'journal_activite',
            'indexes' => [
                ['key' => ['date' => -1, '_id' => -1], 'name' => 'date_recente'],

                ['key' => ['action' => 1, 'date' => -1], 'name' => 'action_date'],

                ['key' => ['livre.id' => 1, 'date' => -1], 'name' => 'livre_date'],
            ],
        ]));
    }
}
