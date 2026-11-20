<?php

namespace App\\Support;

use Illuminate\\Support\\Facades\\Artisan;
use RachidLaasri\\LaravelInstaller\\Helpers\\DatabaseManager as BaseDatabaseManager;
use Symfony\\Component\\Console\\Output\\BufferedOutput;

/**
 * Make the legacy web installer respect Artisan exit codes.
 *
 * The upstream installer only catches thrown exceptions. Artisan::call()
 * normally reports command failures through its return code, so the package
 * can otherwise continue to seed and display a success message after a
 * failed migration.
 */
class SafeInstallerDatabaseManager extends BaseDatabaseManager
{
    public function migrateAndSeed()
    {
        $output = new BufferedOutput();

        $migrationExitCode = Artisan::call('migrate', ['--force' => true], $output);

        if ($migrationExitCode !== 0) {
            return [
                'status' => 'error',
                'message' => 'Database migrations failed. Seeding was stopped; installation is not complete.',
                'dbOutputLog' => $output->fetch(),
            ];
        }

        $seedExitCode = Artisan::call('db:seed', ['--force' => true], $output);

        if ($seedExitCode !== 0) {
            return [
                'status' => 'error',
                'message' => 'Database migrations completed, but database seeding failed. Installation is not complete.',
                'dbOutputLog' => $output->fetch(),
            ];
        }

        return [
            'status' => 'success',
            'message' => trans('installer_messages.final.finished'),
            'dbOutputLog' => $output->fetch(),
        ];
    }
}
