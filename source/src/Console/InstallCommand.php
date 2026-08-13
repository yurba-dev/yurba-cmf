<?php

namespace Yurba\Cmf\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

// first-run setup: publishes config + assets and creates App\Admin
class InstallCommand extends Command
{
    protected $signature = 'yurba:install {--force : Overwrite already-published files}';

    protected $description = 'Publish YurbaCMF config and assets';

    public function handle(Filesystem $files): int
    {
        $force = (bool) $this->option('force');

        $this->call('vendor:publish', ['--tag' => 'yurba-config', '--force' => $force]);
        $this->call('vendor:publish', ['--tag' => 'yurba-assets', '--force' => $force]);

        $dir = $this->laravel['path'].DIRECTORY_SEPARATOR.'Admin';
        if (! $files->isDirectory($dir)) {
            $files->ensureDirectoryExists($dir);
            $this->components->info('Created '.$dir);
        }

        $this->components->info('YurbaCMF installed.');
        $this->newLine();
        $this->line('  Next steps:');
        $this->line('  1. Gate access in a service provider: <info>Yurba::authorizeUsing(fn ($u) => $u->is_admin);</info>');
        $this->line('  2. Scaffold a resource: <info>php artisan yurba:resource Post --from-schema --register</info>');
        $this->newLine();

        return self::SUCCESS;
    }
}
