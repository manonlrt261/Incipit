<?php

namespace App\Command;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un compte administrateur Incipit.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $io->title('Création du compte administrateur Incipit');

        $questionEmail = new Question('Adresse e-mail : ');
        $questionEmail->setValidator(function ($value): string {
            $email = trim((string) $value);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Veuillez saisir une adresse e-mail valide.');
            }

            return $email;
        });

        $email = $helper->ask($input, $output, $questionEmail);

        $utilisateurExistant = $this->entityManager
            ->getRepository(Utilisateur::class)
            ->findOneBy(['email' => $email]);

        if ($utilisateurExistant !== null) {
            $io->error('Un compte existe déjà avec cette adresse e-mail.');

            return Command::FAILURE;
        }

        $questionMotDePasse = new Question('Mot de passe : ');
        $questionMotDePasse->setHidden(true);
        $questionMotDePasse->setHiddenFallback(false);
        $questionMotDePasse->setValidator(function ($value): string {
            $motDePasse = (string) $value;

            if (strlen($motDePasse) < 12) {
                throw new \RuntimeException(
                    'Le mot de passe doit contenir au moins 12 caractères.'
                );
            }

            return $motDePasse;
        });

        $motDePasse = $helper->ask($input, $output, $questionMotDePasse);

        $questionConfirmation = new Question('Confirmez le mot de passe : ');
        $questionConfirmation->setHidden(true);
        $questionConfirmation->setHiddenFallback(false);

        $confirmation = $helper->ask($input, $output, $questionConfirmation);

        if ($motDePasse !== $confirmation) {
            $io->error('Les deux mots de passe ne correspondent pas.');

            return Command::FAILURE;
        }

        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($email);
        $utilisateur->setRoles(['ROLE_ADMIN']);
        $utilisateur->setPassword(
            $this->passwordHasher->hashPassword($utilisateur, $motDePasse)
        );

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        $io->success('Ton compte administrateur Incipit a été créé avec succès.');

        return Command::SUCCESS;
    }
}
