<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WeCanSync\MultiTenancyBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

use WeCanSync\MultiTenancyBundle\DBAL\MultiDbConnectionWrapper;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use WeCanSync\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use WeCanSync\MultiTenancyBundle\Service\TenantService;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A console command that creates users and stores them in the database.
 *
 * To use this command, open a terminal window, enter into your project
 * directory and execute the following:
 *
 *     $ php bin/console app:add-user
 *
 * To output detailed information, increase the command verbosity:
 *
 *     $ php bin/console app:add-user -vv
 *
 * See https://symfony.com/doc/current/console.html
 *
 * We use the default services.yaml configuration, so command classes are registered as services.
 * See https://symfony.com/doc/current/console/commands_as_services.html
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
#[AsCommand(
    name: 'tenant:database:create',
    description: 'Creates databases for registered tenants'
)]
final class CreateDbCommand extends Command
{
    private SymfonyStyle $io;
    private $mainDb;

    public function __construct(
        private ParameterBagInterface $params,
        private readonly EntityManagerInterface $em,
        private ManagerRegistry $registry,
        private string $projectDir,
        private KernelInterface $kernel,
        private TenantService $tenantService
    ) {
        parent::__construct();
        $this->mainDb = $params->get("app.database_name");
    }

    protected function configure(): void
    {
        
        $this
            ->setHelp($this->getCommandHelp())
            // commands can optionally define arguments and/or options (mandatory and optional)
            // see https://symfony.com/doc/current/components/console/console_arguments.html
            ->addArgument('tenant', InputArgument::OPTIONAL, 'The email of the tenant')
        ;
        
    }

    /**
     * This optional method is the first one executed for a command after configure()
     * and is useful to initialize properties based on the input arguments and options.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // SymfonyStyle is an optional feature that Symfony provides so you can
        // apply a consistent look to the commands of your application.
        // See https://symfony.com/doc/current/console/style.html
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * This method is executed after initialize() and before execute(). Its purpose
     * is to check if some of the options/arguments are missing and interactively
     * ask the user for those values.
     *
     * This method is completely optional. If you are developing an internal console
     * command, you probably should not implement this method because it requires
     * quite a lot of work. However, if the command is meant to be used by external
     * users, this method is a nice way to fall back and prevent errors.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (null !== $input->getArgument('tenant')) {
            return;
        }

        $this->io->title('Create Tenant Database Command Interactive Wizard');
        $this->io->text([
            'If you prefer to not use this interactive wizard, provide the',
            'arguments required by this command as follows:',
            '',
            ' $ php bin/console tenant:database:create email@example.com',
            '',
            'Now we\'ll ask you for the value of all the missing command arguments.',
        ]);

        // Ask for the username if it's not defined
        $username = $input->getArgument('tenant');
        if (null !== $username) {
            $this->io->text(' > <info>Tenant</info>: '.$username);
        } else {
            $username = $this->io->ask('Username', null);
            $input->setArgument('tenant', $username);
        }

    }

    /**
     * This method is executed after interact() and initialize(). It usually
     * contains the logic to execute to complete this command task.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('create-tenant-database-command');

        /** @var string $username */
        $username = $input->getArgument('tenant');
       
        $tenant = $this->tenantService->findTenantEntityByIdentifier($username);

        if(!$tenant){
            $this->io->error(sprintf('%s not found (%s)', 'Tenant', $username));
        }else{
            $dbName = $tenant->getDbName();
            /** @var MultiDbConnectionWrapper $connection */
            $connection = $this->em->getConnection();
            $schemaManager = $connection->createSchemaManager();
            $alreadyExists = in_array($dbName, $schemaManager->listDatabases());
            if($alreadyExists){
                $this->io->success(sprintf('%s database(%s) already exists: %s (%s)', 'Tenant', $dbName, $tenant->getEmail(), $tenant->getName()));
            }else{
                $schemaManager->createDatabase($dbName);

                // set database status as created
                $tenant->setDbStatus(DatabaseStatusEnum::DATABASE_CREATED);
                $connection->changeDatabase($this->mainDb);
                $this->em->persist($tenant);
                $this->em->flush();

                $this->io->success(sprintf('%s database was successfully created(%s): %s (%s)', 'Tenant', $dbName, $tenant->getEmail(), $tenant->getName()));
            }
            try{
                $newInput = new ArrayInput([
                    'tenant' => $username
                ]);
                $newInput->setInteractive(false);
                $otherCommand = new MigrateDbCommand($this->em, $this->registry, $this->params, $this->projectDir, $this->kernel, $this->tenantService);
                $otherCommand->run($newInput, $output);
                
            }catch(Exception $e){
                $this->io->error(sprintf('%s migration error (%s)', 'Tenant', $username));
            }
            
        }


        $event = $stopwatch->stop('create-tenant-database-command');
        if ($output->isVerbose()) {
            $this->io->comment(sprintf('New tenant database id: %d / Elapsed time: %.2f ms / Consumed memory: %.2f MB', $tenant->getId(), $event->getDuration(), $event->getMemory() / (1024 ** 2)));
        }

        return Command::SUCCESS;
    }


    /**
     * The command help is usually included in the configure() method, but when
     * it's too long, it's better to define a separate method to maintain the
     * code readability.
     */
    private function getCommandHelp(): string
    {
        return <<<'HELP'
            The <info>%command.name%</info> command creates new users and saves them in the database:

              <info>php %command.full_name%</info> <comment>username password email</comment>

            By default the command creates regular users. To create administrator users,
            add the <comment>--admin</comment> option:

              <info>php %command.full_name%</info> username password email <comment>--admin</comment>

            If you omit any of the three required arguments, the command will ask you to
            provide the missing values:

              # command will ask you for the email
              <info>php %command.full_name%</info> <comment>username password</comment>

              # command will ask you for the email and password
              <info>php %command.full_name%</info> <comment>username</comment>

              # command will ask you for all arguments
              <info>php %command.full_name%</info>
            HELP;
    }
}