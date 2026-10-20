<?php

namespace App\EventListener;

use App\Service\JournalActivite;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class TransmettreJournal
{
    public function __construct(private JournalActivite $journal, private LoggerInterface $logger) {}

    // Transmet après la réponse ; une panne MongoDB ne transforme jamais une action réussie en erreur utilisateur.
    #[AsEventListener(event: 'kernel.terminate')]
    public function terminer(TerminateEvent $evenement): void
    {
        if (!$evenement->isMainRequest() || $evenement->getRequest()->isMethodSafe() || !$evenement->getResponse()->isSuccessful()) return;
        try { $this->journal->transmettre(20); }
        catch (\Throwable $erreur) { $this->logger->warning('Transmission du journal différée.', ['type' => get_class($erreur)]); }
    }
}
