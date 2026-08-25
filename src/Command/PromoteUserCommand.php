<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Attribution du rôle d'administrateur.
 *
 * ROLE_ADMIN ouvre l'écran d'administration de MANAGER : liste de toutes les
 * organisations, abonnements et achats de parcours. C'est une élévation de
 * privilège, d'où la confirmation en mode interactif et l'affichage de
 * l'organisation du compte visé — deux comptes de test peuvent avoir des
 * adresses très proches.
 */
#[AsCommand(
    name: 'app:user:promote',
    description: "Donne (ou retire) le rôle d'administrateur à un compte",
)]
final class PromoteUserCommand extends Command
{
    private const string ROLE = 'ROLE_ADMIN';

    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Adresse du compte')
            ->addOption('revoke', null, InputOption::VALUE_NONE, "Retire le rôle au lieu de l'attribuer")
            ->addOption('list', null, InputOption::VALUE_NONE, 'Liste les comptes administrateurs')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Sans confirmation (requis en mode non interactif)')
            ->setHelp(<<<'TXT'
                Attribuer le rôle :
                  <info>php %command.full_name% simon@blum.dev</info>

                Le retirer :
                  <info>php %command.full_name% simon@blum.dev --revoke</info>

                Voir qui est administrateur :
                  <info>php %command.full_name% --list</info>

                En script (déploiement, provisionnement) :
                  <info>php %command.full_name% simon@blum.dev --force --no-interaction</info>
                TXT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('list')) {
            return $this->listAdmins($io);
        }

        $email = $input->getArgument('email');
        if (!is_string($email) || '' === trim($email)) {
            $io->error("Indiquez une adresse, ou utilisez --list pour voir les administrateurs.");

            return Command::INVALID;
        }

        $user = $this->users->findOneByEmail(trim($email));
        if (!$user instanceof User) {
            $io->error(sprintf('Aucun compte avec l\'adresse « %s ».', $email));

            return Command::FAILURE;
        }

        $revoke = (bool) $input->getOption('revoke');
        $roles = $user->getRoles();
        $isAdmin = in_array(self::ROLE, $roles, true);

        // Idempotent : relancer la commande ne doit ni échouer ni écrire pour rien.
        if ($isAdmin === !$revoke) {
            $io->note(sprintf(
                '%s %s déjà administrateur. Rien à faire.',
                $user->getEmail(),
                $revoke ? "n'est pas" : 'est',
            ));

            return Command::SUCCESS;
        }

        $organization = $user->getOrganization();
        $io->definitionList(
            ['Compte' => $user->getEmail()],
            ['Organisation' => $organization?->getName() ?? '(aucune)'],
            ['Rôles actuels' => implode(', ', $roles)],
            ['Action' => $revoke ? 'retirer '.self::ROLE : 'attribuer '.self::ROLE],
        );

        // En mode non interactif, `confirm()` renvoie la valeur par défaut et la
        // commande s'arrêterait sans rien dire — un script croirait avoir réussi.
        // On exige donc --force, et on échoue franchement s'il manque.
        if (!$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('Mode non interactif : ajoutez --force pour confirmer.');

                return Command::FAILURE;
            }

            if (!$io->confirm($revoke ? 'Confirmer le retrait ?' : 'Confirmer cette élévation de privilège ?', false)) {
                $io->warning('Abandon, aucune modification.');

                return Command::SUCCESS;
            }
        }

        // getRoles() ajoute ROLE_USER à la volée ; on repart du champ stocké pour
        // ne pas le figer en base.
        $stored = array_values(array_filter(
            $roles,
            static fn (string $role): bool => self::ROLE !== $role && 'ROLE_USER' !== $role,
        ));
        if (!$revoke) {
            $stored[] = self::ROLE;
        }

        $user->setRoles($stored);
        $this->entityManager->flush();

        $io->success(sprintf(
            '%s : %s',
            $user->getEmail(),
            $revoke ? 'rôle retiré' : 'administrateur',
        ));

        return Command::SUCCESS;
    }

    private function listAdmins(SymfonyStyle $io): int
    {
        // Le champ roles est un JSON : on filtre en PHP plutôt que d'écrire une
        // requête dépendante du moteur.
        $admins = array_filter(
            $this->users->findAll(),
            static fn (User $user): bool => in_array(self::ROLE, $user->getRoles(), true),
        );

        if (!$admins) {
            $io->warning('Aucun compte administrateur.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Adresse', 'Organisation'],
            array_map(
                static fn (User $user): array => [
                    $user->getEmail(),
                    $user->getOrganization()?->getName() ?? '(aucune)',
                ],
                $admins,
            ),
        );

        return Command::SUCCESS;
    }
}
