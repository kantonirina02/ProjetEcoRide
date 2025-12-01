<?php

namespace App\Command;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create an initial user (admin, employee or standard) with a strong password.'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email of the new user')
            ->addArgument('pseudo', InputArgument::OPTIONAL, 'Display name (defaults to the email prefix)')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Password (will prompt if omitted)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant ROLE_ADMIN')
            ->addOption('employee', null, InputOption::VALUE_NONE, 'Grant ROLE_EMPLOYEE')
            ->addOption('credits', 'c', InputOption::VALUE_OPTIONAL, 'Initial credits', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = strtolower(trim((string) $input->getArgument('email')));
        $pseudo = trim((string) ($input->getArgument('pseudo') ?? ''));
        $password = (string) ($input->getOption('password') ?? '');
        $isAdmin = (bool) $input->getOption('admin');
        $isEmployee = (bool) $input->getOption('employee');
        $credits = (int) $input->getOption('credits');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Email invalide</error>');
            return Command::INVALID;
        }

        if ($password === '') {
            $helper = $this->getHelper('question');
            $question = new Question('Mot de passe (caché) : ');
            $question->setHidden(true)->setHiddenFallback(false);
            $password = (string) $helper->ask($input, $output, $question);
        }

        if (!$this->isStrongPassword($password)) {
            $output->writeln('<error>Mot de passe trop faible (8+ caractères, minuscule, majuscule, chiffre, spécial)</error>');
            return Command::INVALID;
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln('<error>Un utilisateur avec cet email existe déjà</error>');
            return Command::FAILURE;
        }

        $roles = ['ROLE_USER'];
        if ($isEmployee) {
            $roles[] = 'ROLE_EMPLOYEE';
        }
        if ($isAdmin) {
            $roles[] = 'ROLE_ADMIN';
        }

        $user = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo !== '' ? $pseudo : explode('@', $email)[0])
            ->setRoles(array_values(array_unique($roles)))
            ->setCreditsBalance($credits)
            ->setCreatedAt(new DateTimeImmutable());

        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln(sprintf(
            '<info>Utilisateur créé :</info> id=%d, email=%s, roles=%s, credits=%d',
            $user->getId(),
            $user->getEmail(),
            implode(',', $user->getRoles()),
            $user->getCreditsBalance()
        ));

        return Command::SUCCESS;
    }

    private function isStrongPassword(string $password): bool
    {
        if (strlen($password) < 8) {
            return false;
        }
        $hasLower = (bool) preg_match('/[a-z]/', $password);
        $hasUpper = (bool) preg_match('/[A-Z]/', $password);
        $hasDigit = (bool) preg_match('/\\d/', $password);
        $hasSpecial = (bool) preg_match('/[^A-Za-z0-9]/', $password);

        return $hasLower && $hasUpper && $hasDigit && $hasSpecial;
    }
}
