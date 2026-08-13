<?php

namespace Yurba\Cmf\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

// scaffolds a settings page in App\Admin\Settings; --register appends it to config
class SettingsMakeCommand extends GeneratorCommand
{
    protected $name = 'yurba:settings';

    protected $description = 'Create a panel settings page';

    protected $type = 'Settings page';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/settings.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Admin\Settings';
    }

    // force the class name to end in "Settings" (yurba:settings Seo => SeoSettings)
    protected function getNameInput(): string
    {
        $name = Str::studly(Str::replaceLast('Settings', '', trim((string) $this->argument('name'))));

        return $name.'Settings';
    }

    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result !== false && $this->option('register')) {
            $this->registerInConfig($this->qualifyClass($this->getNameInput()));
        }

        return $result;
    }

    // add the page (and its use import) to the settings array in config
    protected function registerInConfig(string $fqn): void
    {
        $path = $this->laravel->configPath('yurba.php');
        if (! $this->files->exists($path)) {
            $this->components->warn('config/yurba.php not found — add '.class_basename($fqn).'::class to the settings array yourself.');

            return;
        }

        $content = $this->files->get($path);
        $short = class_basename($fqn);

        if (Str::contains($content, $short.'::class')) {
            $this->components->info($short.' is already registered in config/yurba.php.');

            return;
        }

        $eol = Str::contains($content, "\r\n") ? "\r\n" : "\n";
        $lines = explode($eol, $content);

        $lines = $this->insertUse($lines, "use {$fqn};");
        $lines = $this->insertIntoArray($lines, "'settings'", str_repeat(' ', 8).$short.'::class,');

        $this->files->put($path, implode($eol, $lines));
        $this->components->info($short.' registered in config/yurba.php.');
    }

    // insert a use line alphabetically among the App\Admin\Settings\* imports
    protected function insertUse(array $lines, string $use): array
    {
        $matches = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^use App\\\\Admin\\\\Settings\\\\\w+;/', $line)) {
                $matches[] = $i;
            }
        }

        if (empty($matches)) {
            array_splice($lines, 1, 0, ['', $use]);

            return $lines;
        }

        foreach ($matches as $i) {
            if ($lines[$i] > $use) {
                array_splice($lines, $i, 0, [$use]);

                return $lines;
            }
        }

        array_splice($lines, end($matches) + 1, 0, [$use]);

        return $lines;
    }

    // insert an entry at the top of a named config array
    protected function insertIntoArray(array $lines, string $key, string $entry): array
    {
        foreach ($lines as $i => $line) {
            if (preg_match('/'.preg_quote($key, '/').'\s*=>\s*\[/', $line)) {
                array_splice($lines, $i + 1, 0, [$entry]);

                return $lines;
            }
        }

        $this->components->warn("Could not find the {$key} array — add {$entry} manually.");

        return $lines;
    }

    protected function getOptions(): array
    {
        return [
            ['register', null, InputOption::VALUE_NONE, 'Append the page to config/yurba.php'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the page if it already exists'],
        ];
    }
}
