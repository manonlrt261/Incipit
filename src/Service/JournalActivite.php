<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final class JournalActivite
{
    public const ACTIONS = [
        'livre_ajoute' => 'Livre ajouté',
        'livre_modifie' => 'Livre modifié',
        'livre_retire' => 'Livre retiré',

        'lecture_ajoutee' => 'Session ajoutée',
        'lecture_modifiee' => 'Session modifiée',
        'lecture_supprimee' => 'Session supprimée',

        'souhait_ajoute' => 'Souhait ajouté',
        'souhait_modifie' => 'Souhait modifié',
        'souhait_achete' => 'Souhait acheté',
        'souhait_supprime' => 'Souhait supprimé',

        'pret_ajoute' => 'Prêt ajouté',
        'pret_supprime' => 'Prêt supprimé',
    ];

    public function __construct(private Connection $connexion, private JournalMongo $mongo, private LoggerInterface $logger)
    {
    }

    // Écrit dans la transaction du traitement métier : une action annulée n’apparaîtra pas dans le journal.
    public function conserver(string $action, int $administrateur, string $compte, array $livre, array $details): void
    {
        $id = bin2hex(random_bytes(16));
        $date = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $document = [
            'version' => 1,
            'action' => $action,
            'date' => $date->format(DATE_ATOM),

            'administrateur' => ['id' => $administrateur, 'compte' => $compte],
            'livre' => $livre,
            'details' => $details
        ];
        $this->connexion->insert('evenement_outbox', [
            'identifiant_evenement' => $id,
            'type_evenement' => $action,

            'donnees' => json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),

            'date_creation' => $date->format('Y-m-d H:i:s'),
            'statut' => 'en attente',
            'nombre_tentatives' => 0,
        ]);
    }

    // Reprend un lot borné après une panne ; seuls les événements acquittés par MongoDB deviennent transmis.
    public function transmettre(int $limite = 100): array
    {
        if ($this->connexion->getTransactionNestingLevel() !== 0) {
            return ['transmis' => 0, 'indisponible' => false];
        }
        $lignes = $this->connexion->fetchAllAssociative("SELECT id, identifiant_evenement, donnees FROM evenement_outbox WHERE statut IN ('en attente', 'erreur') ORDER BY id LIMIT " . max(1, min(500, $limite)));
        $transmis = 0;

        if (!$lignes) {
            return ['transmis' => 0, 'indisponible' => false];
        }

        try {
            $this->mongo->indexer();

            foreach ($lignes as $ligne) {
                $this->mongo->enregistrer($ligne['identifiant_evenement'], json_decode($ligne['donnees'], true, 512, JSON_THROW_ON_ERROR));
                $this->connexion->executeStatement("UPDATE evenement_outbox SET statut = 'transmis', date_transmission = ?, nombre_tentatives = nombre_tentatives + 1 WHERE id = ?", [gmdate('Y-m-d H:i:s'), $ligne['id']]);
                ++$transmis;
            }
        } catch (\Throwable $erreur) {
            // Les journaux techniques ne reçoivent ni URI MongoDB, ni contenu d’événement, ni secret.
            $this->logger->warning('Transmission du journal différée.', ['type' => get_class($erreur)]);

            return ['transmis' => $transmis, 'indisponible' => true];
        }

        return ['transmis' => $transmis, 'indisponible' => false];
    }

    // Compte les événements qui restent à transmettre, y compris après une tentative en erreur.
    public function enAttente(): int
    {
        return (int) $this->connexion->fetchOne("SELECT COUNT(*) FROM evenement_outbox WHERE statut IN ('en attente', 'erreur')");
    }
}
