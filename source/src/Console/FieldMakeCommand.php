<?php

namespace Yurba\Cmf\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

// scaffolds a custom Field subclass + its blade form partial, wiring component()
class FieldMakeCommand extends GeneratorCommand
{
    protected $name = 'yurba:field';

    protected $description = 'Create a custom panel field and its Blade partial';

    protected $type = 'Field';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/field.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Admin\Fields';
    }

    protected function getNameInput(): string
    {
        return Str::studly(trim((string) $this->argument('name')));
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return str_replace('{{ view }}', $this->viewName($name), $stub);
    }

    // dotted blade view name, e.g. "admin.fields.color-picker"
    protected function viewName(string $name): string
    {
        return 'admin.fields.'.Str::kebab(class_basename($name));
    }

    public function handle(): ?bool
    {
        $result = parent::handle();
        if ($result === false) {
            return $result;
        }

        $this->writeView($this->qualifyClass($this->getNameInput()));

        return $result;
    }

    protected function writeView(string $name): void
    {
        $kebab = Str::kebab(class_basename($name));
        $path = $this->laravel->resourcePath('views/admin/fields/'.$kebab.'.blade.php');

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->warn('View already exists: '.$path);

            return;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $view = $this->files->get(__DIR__.'/../../stubs/field.view.stub');
        $view = str_replace('{{ class }}', class_basename($name), $view);
        $this->files->put($path, $view);

        $this->components->info('View created: '.$path);
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the field and view if they exist'],
        ];
    }
}
