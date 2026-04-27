<?php

namespace App\Command;

use App\Entity\Organisation;
use App\Entity\User;
use App\Entity\UserOrganisation;
use App\Repository\OrganisationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a new user',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly OrganisationRepository $organisationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'User email address')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'User display name')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant admin role');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create Workoflow User');

        $email = $input->getOption('email') ?? $io->ask('Email address', null, static function (?string $value): string {
            if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Please enter a valid email address.');
            }
            return $value;
        });

        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('User "%s" already exists.', $email));
            return Command::FAILURE;
        }

        $name = $input->getOption('name') ?? $io->ask('Display name', null, static function (?string $value): string {
            if (empty(trim((string) $value))) {
                throw new \RuntimeException('Name cannot be empty.');
            }
            return trim((string) $value);
        });

        $isAdmin = $input->getOption('admin') || $io->confirm('Grant admin role?', false);

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);

        $roles = [User::ROLE_USER, User::ROLE_MEMBER];
        if ($isAdmin) {
            $roles[] = User::ROLE_ADMIN;
        }
        $user->setRoles($roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $organisation = $this->askForOrganisation($io);

        if ($organisation !== null) {
            $orgRole = $io->choice('Organisation role', ['MEMBER', 'ADMIN'], 'MEMBER');
            $workflowUserId = $io->ask('Workflow user ID', Uuid::v4()->toRfc4122());

            $userOrganisation = new UserOrganisation();
            $userOrganisation->setUser($user);
            $userOrganisation->setOrganisation($organisation);
            $userOrganisation->setRole($orgRole);
            $userOrganisation->setWorkflowUserId($workflowUserId);

            $this->entityManager->persist($userOrganisation);
            $this->entityManager->flush();
        }

        $io->success(sprintf('User "%s" (%s) created.', $name, $email));

        $io->definitionList(
            ['Email' => $email],
            ['Name' => $name],
            ['Roles' => implode(', ', $roles)],
            ['Organisation' => $organisation ? sprintf('%s (%s)', $organisation->getName(), $organisation->getUuid()) : '—'],
        );

        return Command::SUCCESS;
    }

    private function askForOrganisation(SymfonyStyle $io): ?Organisation
    {
        $organisations = $this->organisationRepository->findAll();

        $skipLabel = 'Skip (no organisation)';
        $createLabel = 'Create new organisation';

        $choices = [$skipLabel];
        $orgMap = [];

        foreach ($organisations as $org) {
            $label = sprintf('%s (%s)', $org->getName(), $org->getUuid());
            $choices[] = $label;
            $orgMap[$label] = $org;
        }

        $choices[] = $createLabel;

        $selected = $io->choice('Assign to organisation', $choices, $skipLabel);

        if ($selected === $skipLabel) {
            return null;
        }

        if ($selected === $createLabel) {
            $orgName = $io->ask('Organisation name', null, static function (?string $value): string {
                if (empty(trim((string) $value))) {
                    throw new \RuntimeException('Organisation name cannot be empty.');
                }
                return trim((string) $value);
            });

            $org = new Organisation();
            $org->setUuid(Uuid::v4()->toRfc4122());
            $org->setName($orgName);

            $this->entityManager->persist($org);
            $this->entityManager->flush();

            return $org;
        }

        return $orgMap[$selected];
    }
}
