<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:promote', description: 'Donne le rôle ROLE_ADMIN à un utilisateur')]
class PromoteUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("Utilisateur '$email' introuvable.");
            return Command::FAILURE;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $io->warning("'$email' a déjà ROLE_ADMIN.");
            return Command::SUCCESS;
        }

        $user->setRoles([...$roles, 'ROLE_ADMIN']);
        $this->em->flush();

        $io->success("'$email' est maintenant ROLE_ADMIN.");
        return Command::SUCCESS;
    }
}
