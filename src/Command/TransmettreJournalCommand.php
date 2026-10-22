<?php

namespace App\Command;

use App\Service\JournalActivite;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:journal:transmettre',
    description: 'Transmet les événements en attente vers MongoDB.',
)]
final class TransmettreJournalCommand extends Command
{
    public function __construct(private JournalActivite $journal)
    {
        parent::__construct();
    }

    // Permet une reprise périodique même lorsqu’aucun administrateur n’effectue de nouvelle action.
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $resultat = $this->journal->transmettre();

        $io->text($resultat['transmis'].' événement(s) transmis.');

        if ($resultat['indisponible']) {
            $io->warning(
                'MongoDB est indisponible ou non configuré. Les événements restent conservés en attente.'
            );

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
