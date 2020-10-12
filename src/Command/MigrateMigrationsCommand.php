<?php

namespace Rikudou\DynamoDbOrm\Command;

use Rikudou\DynamoDbOrm\Service\Migration\MigrationInterface;
use Rikudou\DynamoDbOrm\Service\Migration\MigrationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MigrateMigrationsCommand extends Command
{
    protected static $defaultName = 'rikudou:dynamo:migrate';

    /**
     * @var MigrationManager
     */
    private $migrationManager;

    public function __construct(MigrationManager $migrationManager)
    {
        parent::__construct();
        $this->migrationManager = $migrationManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Apply migrations for Dynamo DB ORM')
            ->addArgument(
                'target',
                InputArgument::OPTIONAL,
                'The optional version to migrate to. Specify 0 to unapply all your migrations. Specify -1 to also delete migration table (you need to use -- before specifying -1 as value).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $target = $input->getArgument('target');
        if ($target !== null && !is_numeric($target)) {
            $io->error('The target must be a version number');

            return self::FAILURE;
        } elseif ($target !== null) {
            $target = (int) $target;
        }

        $migrationsToApply = $this->migrationManager->getMigrationsToApply($target);
        $migrationsCount = count($migrationsToApply['up'] + $migrationsToApply['down']);

        if (!$migrationsCount) {
            $io->success('No migrations to apply');

            return self::SUCCESS;
        }

        $io->note('Creating new indexes can take several minutes, please be patient if some of your migrations create them.');

        $i = 1;
        foreach ($migrationsToApply['down'] as $migration) {
            assert($migration instanceof MigrationInterface);
            $io->note("Processing migration {$i} of {$migrationsCount}");
            $migration->down();
            $this->migrationManager->markMigrationAsUndone($migration);
            ++$i;
        }

        foreach ($migrationsToApply['up'] as $migration) {
            assert($migration instanceof MigrationInterface);
            $io->note("Processing migration {$i} of {$migrationsCount}");
            $migration->up();
            $this->migrationManager->markMigrationAsDone($migration);
            ++$i;
        }

        $io->success('All migrations executed');

        return self::SUCCESS;
    }
}
