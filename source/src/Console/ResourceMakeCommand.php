<?php

namespace Yurba\Cmf\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

// scaffolds a Resource in App\Admin. --from-schema maps each table column to a
// field; --register appends the class to config so it shows in the sidebar.
class ResourceMakeCommand extends GeneratorCommand
{
    protected $name = 'yurba:resource';

    protected $description = 'Create a new panel resource';

    protected $type = 'Resource';

    // field imports collected while mapping the schema (fqn => true)
    protected array $imports = [];

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/resource.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Admin';
    }

    // force the class name to end in "Resource" (yurba:resource Job => JobResource)
    protected function getNameInput(): string
    {
        $name = trim((string) $this->argument('name'));
        $name = Str::studly(Str::replaceLast('Resource', '', $name));

        return $name.'Resource';
    }

    protected function buildClass($name): string
    {
        $model = $this->modelClass($name);
        $modelShort = class_basename($model);

        $this->imports = [
            $model => true,
            'Yurba\\Cmf\\Resources\\Resource' => true,
        ];

        [$fields, $specials] = $this->option('from-schema')
            ? $this->fieldsFromSchema($model, $modelShort)
            : $this->skeletonFields();

        $stub = $this->files->get($this->getStub());
        $this->replaceNamespace($stub, $name);

        $stub = strtr($stub, [
            '{{ imports }}' => $this->renderImports(),
            '{{ model }}' => $modelShort,
            '{{ specials }}' => $specials,
            '{{ icon }}' => '<span class="material-symbols-rounded">table_rows</span>',
            '{{ fields }}' => $fields,
        ]);

        // collapse the blank line left when there is no {{ specials }} block
        $stub = preg_replace("/\n{3,}/", "\n\n", $stub);

        return $this->replaceClass($stub, $name);
    }

    // model fqn from --model, or by stripping "Resource" off the name
    protected function modelClass(string $name): string
    {
        $model = (string) $this->option('model');
        if ($model !== '') {
            return Str::startsWith($model, '\\') ? ltrim($model, '\\') : $model;
        }

        $base = Str::replaceLast('Resource', '', class_basename($name));

        return $this->rootNamespace().'Models\\'.$base;
    }

    /** @return array{0: string, 1: string} [fields code, specials block] */
    protected function fieldsFromSchema(string $model, string $modelShort): array
    {
        if (! class_exists($model)) {
            $this->components->warn("Model {$model} not found — generating an empty resource.");

            return $this->skeletonFields();
        }

        $instance = new $model;
        $table = $instance->getTable();
        $key = $instance->getKeyName();
        $sluggable = in_array('Cviebrock\\EloquentSluggable\\Sluggable', class_uses_recursive($model), true);
        $uriPlural = Str::plural(Str::kebab($modelShort));

        try {
            $columns = Schema::getColumns($table);
        } catch (\Throwable $e) {
            $this->components->warn("Could not read the '{$table}' schema ({$e->getMessage()}). Generating an empty resource.");

            return $this->skeletonFields();
        }

        $skip = [$key, 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];
        $titleColumn = $this->titleColumn($columns);

        $lines = [];
        foreach ($columns as $col) {
            $colName = $col['name'];
            if (in_array($colName, $skip, true) || ($col['auto_increment'] ?? false)) {
                continue;
            }

            $lines[] = $this->mapColumn($col, [
                'title' => $titleColumn,
                'sluggable' => $sluggable,
                'uri' => $uriPlural,
            ]);
        }

        if (empty($lines)) {
            return $this->skeletonFields();
        }

        return [implode("\n", $lines), $this->specialsBlock($modelShort)];
    }

    /** @return array{0: string, 1: string} */
    protected function skeletonFields(): array
    {
        $this->imports['Yurba\\Cmf\\Fields\\Text'] = true;

        $line = str_repeat(' ', 12)."Text::make('name')->searchable()->sortable()->rules('required|string|max:255'),"
            ."\n".str_repeat(' ', 12).'// TODO: add your fields';

        return [$line, ''];
    }

    // map one column descriptor to an indented field line (with optional // TODO)
    protected function mapColumn(array $col, array $ctx): string
    {
        $name = $col['name'];
        $lower = Str::lower($name);
        $typeName = Str::lower((string) ($col['type_name'] ?? ''));
        $fullType = Str::lower((string) ($col['type'] ?? ''));
        $nullable = (bool) ($col['nullable'] ?? true);

        $expr = null;
        $todo = null;

        // slug backed by the sluggable trait -> our permalink field
        if ($name === 'slug' && $ctx['sluggable']) {
            $this->use('Slug');
            $expr = "Slug::make('slug', 'Permalink')->baseUrl(url('/{$ctx['uri']}'))";
            $todo = 'verify the public base URL';
        }
        // foreign key -> belongsto
        elseif (Str::endsWith($lower, '_id')) {
            $base = Str::beforeLast($name, '_id');
            $related = Str::studly(Str::singular($base));
            $this->use('BelongsTo');
            $this->imports[$this->rootNamespace().'Models\\'.$related] = true;
            $label = Str::headline($base);
            $expr = "BelongsTo::make('{$name}', '{$label}')->relatedModel({$related}::class)->title('name')"
                .($nullable ? '->nullable()' : '');
            if (! class_exists($this->rootNamespace().'Models\\'.$related)) {
                $todo = "confirm related model {$related}";
            }
        }
        // boolean
        elseif ($typeName === 'boolean' || $fullType === 'tinyint(1)') {
            $this->use('Boolean');
            $label = Str::headline(Str::startsWith($lower, 'is_') ? Str::after($name, 'is_') : $name);
            $expr = "Boolean::make('{$name}', '{$label}')";
        }
        // enum -> select
        elseif (Str::startsWith($fullType, 'enum(')) {
            $this->use('Select');
            $expr = "Select::make('{$name}')->options([{$this->enumOptions($fullType)}])";
        }
        // dates
        elseif (in_array($typeName, ['datetime', 'timestamp'], true)) {
            $this->use('Date');
            $expr = "Date::make('{$name}')->withTime()->onlyOnForm()";
        } elseif ($typeName === 'date') {
            $this->use('Date');
            $expr = "Date::make('{$name}')->onlyOnForm()";
        }
        // numbers
        elseif (in_array($typeName, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'float', 'double', 'numeric'], true)) {
            $this->use('Number');
            $expr = "Number::make('{$name}')";
        }
        // json
        elseif (in_array($typeName, ['json', 'jsonb'], true)) {
            $this->use('Tags');
            $expr = "Tags::make('{$name}')->onlyOnForm()";
            $todo = 'Tags or Repeater?';
        }
        // long text
        elseif (in_array($typeName, ['text', 'mediumtext', 'longtext', 'tinytext'], true)) {
            if (in_array($lower, ['description', 'content', 'body'], true)) {
                $this->use('Editor');
                $expr = "Editor::make('{$name}')->onlyOnForm()";
            } else {
                $this->use('Textarea');
                $expr = "Textarea::make('{$name}')->onlyOnForm()";
            }
        }
        // string-ish: image / email / url / title / plain
        elseif (Str::contains($lower, ['avatar', 'image', 'photo', 'logo', 'thumbnail', 'cover', 'picture'])) {
            $this->use('Image');
            $label = Str::headline($name);
            $expr = "Image::make('{$name}', '{$label}')->dir('{$ctx['uri']}')";
        } elseif (Str::contains($lower, 'email')) {
            $this->use('Email');
            $expr = "Email::make('{$name}')->rules('nullable|email')";
        } elseif (Str::contains($lower, ['url', 'link'])) {
            $this->use('Text');
            $expr = "Text::make('{$name}')->onlyOnForm()->rules('nullable|url|max:1024')";
        } elseif ($name === $ctx['title']) {
            $this->use('Text');
            $max = $this->lengthOf($fullType) ?? 255;
            $expr = "Text::make('{$name}')->searchable()->sortable()->rules('required|string|max:{$max}')";
        } else {
            $this->use('Text');
            $expr = "Text::make('{$name}')";
        }

        return str_repeat(' ', 12).$expr.','.($todo ? '  // TODO: '.$todo : '');
    }

    // column used as the record's title (searchable + required)
    protected function titleColumn(array $columns): ?string
    {
        $names = array_map(fn ($c) => $c['name'], $columns);
        foreach (['title', 'name', 'email'] as $candidate) {
            if (in_array($candidate, $names, true)) {
                return $candidate;
            }
        }

        return null;
    }

    // build "'publish' => 'Publish', 'draft' => 'Draft'" from an enum type
    protected function enumOptions(string $fullType): string
    {
        preg_match_all("/'([^']*)'/", $fullType, $m);
        $pairs = array_map(
            fn ($v) => "'{$v}' => '".Str::headline($v)."'",
            $m[1] ?? []
        );

        return implode(', ', $pairs);
    }

    // length from a type like varchar(150)
    protected function lengthOf(string $fullType): ?int
    {
        return preg_match('/\((\d+)\)/', $fullType, $m) ? (int) $m[1] : null;
    }

    // uriKey()/pluralLabel() overrides when the model name is uncountable
    protected function specialsBlock(string $modelShort): string
    {
        $kebab = Str::kebab($modelShort);
        if (Str::plural($kebab) !== $kebab) {
            return '';
        }

        $plural = $kebab.'s';
        $label = Str::headline($plural);

        return "\n    // \"{$modelShort}\" is uncountable; set the plural forms explicitly.\n"
            ."    public function uriKey(): string\n    {\n        return '{$plural}';\n    }\n\n"
            ."    public function pluralLabel(): string\n    {\n        return '{$label}';\n    }\n";
    }

    protected function use(string $field): void
    {
        $this->imports['Yurba\\Cmf\\Fields\\'.$field] = true;
    }

    protected function renderImports(): string
    {
        $classes = array_keys($this->imports);
        sort($classes);

        return implode("\n", array_map(fn ($c) => "use {$c};", $classes));
    }

    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result !== false && $this->option('register')) {
            $this->registerInConfig($this->qualifyClass($this->getNameInput()));
        }

        return $result;
    }

    // add the resource (and its use import) to the resources array in config
    protected function registerInConfig(string $fqn): void
    {
        $path = $this->laravel->configPath('yurba.php');
        if (! $this->files->exists($path)) {
            $this->components->warn('config/yurba.php not found — add '.class_basename($fqn).'::class to the resources array yourself.');

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
        $lines = $this->insertIntoResources($lines, str_repeat(' ', 8).$short.'::class,');

        $this->files->put($path, implode($eol, $lines));
        $this->components->info($short.' registered in config/yurba.php.');
    }

    // insert a use line alphabetically among the App\Admin\*Resource imports
    protected function insertUse(array $lines, string $use): array
    {
        $matches = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^use App\\\\Admin\\\\\w+Resource;/', $line)) {
                $matches[] = $i;
            }
        }

        // no existing resource imports: drop it after the opening <?php tag
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

    // insert an entry at the top of the resources array
    protected function insertIntoResources(array $lines, string $entry): array
    {
        foreach ($lines as $i => $line) {
            if (preg_match("/'resources'\s*=>\s*\[/", $line)) {
                array_splice($lines, $i + 1, 0, [$entry]);

                return $lines;
            }
        }

        $this->components->warn("Could not find the 'resources' array — add {$entry} manually.");

        return $lines;
    }

    protected function getOptions(): array
    {
        return [
            ['model', null, InputOption::VALUE_OPTIONAL, 'The Eloquent model class (defaults to App\\Models\\{Name})'],
            ['from-schema', null, InputOption::VALUE_NONE, 'Generate fields by introspecting the model table'],
            ['register', null, InputOption::VALUE_NONE, 'Append the resource to config/yurba.php'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the resource if it already exists'],
        ];
    }
}
