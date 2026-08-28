<?php

namespace Yurba\Cmf\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Yurba\Cmf\Fields\Field;
use Yurba\Cmf\Filters\Filter;
use Yurba\Cmf\Revisions\Revision;

// a CRUD resource: declare a model and fields, the panel generates the list,
// create/edit forms, validation and persistence.
abstract class Resource
{
    /** @var class-string<Model> */
    public static string $model;

    /** @return Field[] */
    abstract public function fields(): array;

    /** @return Filter[] */
    public function filters(): array
    {
        return [];
    }

    // --- identity ---

    public function model(): string
    {
        return static::$model;
    }

    public function newModel(): Model
    {
        $class = static::$model;

        return new $class;
    }

    // uses SoftDeletes? (enables the trash/restore ui)
    public function usesSoftDeletes(): bool
    {
        return in_array(
            'Illuminate\\Database\\Eloquent\\SoftDeletes',
            class_uses_recursive(static::$model),
            true
        );
    }

    public function label(): string
    {
        return Str::headline(class_basename(static::$model));
    }

    public function pluralLabel(): string
    {
        return Str::plural($this->label());
    }

    // url segment, e.g. "job-applications"
    public function uriKey(): string
    {
        return Str::plural(Str::kebab(class_basename(static::$model)));
    }

    // optional sidebar icon (raw html); null hides it
    public function icon(): ?string
    {
        return null;
    }

    // human title for a single record (heading on edit)
    public function title(Model $record): string
    {
        foreach (['title', 'name', 'email'] as $attr) {
            if (filled($record->{$attr} ?? null)) {
                return (string) $record->{$attr};
            }
        }

        return '#'.$record->getKey();
    }

    // --- query / capabilities ---

    // enable drag-and-drop row reordering in the list; return the integer column
    // that stores the position (e.g. 'sort'), or null to disable
    public function reorderable(): ?string
    {
        return null;
    }

    // default list ordering when no ?sort is applied, as [column, direction];
    // a reorderable resource defaults to its position column ascending
    /** @return array{0: string, 1?: string}|null */
    public function defaultSort(): ?array
    {
        return $this->reorderable() ? [$this->reorderable(), 'asc'] : null;
    }

    public function query(): Builder
    {
        return static::$model::query();
    }

    // shared by the list screen and csv export so both honour the same filters
    public function indexQuery(Request $request): Builder
    {
        $query = $this->query();

        // soft-delete scope: active / only trashed / all
        if ($this->usesSoftDeletes()) {
            $trashed = $request->query('trashed');
            if ($trashed === 'only') {
                $query->onlyTrashed();
            } elseif ($trashed === 'with') {
                $query->withTrashed();
            }
        }

        $search = trim((string) $request->query('q', ''));
        $columns = $this->searchableColumns();
        if ($search !== '' && $columns) {
            $query->where(function (Builder $q) use ($columns, $search) {
                foreach ($columns as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        // structured filters (f[key]=value)
        $active = (array) $request->query('f', []);
        foreach ($this->filters() as $filter) {
            $value = $active[$filter->key] ?? null;
            if ($filter->isActive($value)) {
                $filter->apply($query, $value);
            }
        }

        $sortable = array_map(
            fn (Field $f) => $f->column(),
            array_filter($this->indexFields(), fn (Field $f) => $f->sortable)
        );
        $sort = $request->query('sort');
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        if ($sort && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } elseif ($default = $this->defaultSort()) {
            $query->orderBy($default[0], $default[1] ?? 'asc');
        } else {
            $query->orderByDesc($this->newModel()->getKeyName());
        }

        return $query;
    }

    public function perPage(): int
    {
        return app('yurba.cmf')->perPage();
    }

    // --- authorization ---
    // each gate defers to a model Policy ability if one is defined (viewAny/view/
    // create/update/delete), else override here. with neither, everything is
    // allowed - access is still gated by the panel's entry gate.

    public function canViewAny($user = null): bool
    {
        return $this->policyCheck('viewAny', $user) ?? true;
    }

    public function canView($user, Model $record): bool
    {
        return $this->policyCheck('view', $user, $record) ?? true;
    }

    public function canCreate($user = null): bool
    {
        return $this->policyCheck('create', $user) ?? true;
    }

    public function canUpdate($user, Model $record): bool
    {
        return $this->policyCheck('update', $user, $record) ?? true;
    }

    public function canDelete($user = null, ?Model $record = null): bool
    {
        return $this->policyCheck('delete', $user, $record) ?? true;
    }

    // null = no such policy method (no opinion)
    protected function policyCheck(string $ability, $user, ?Model $record = null): ?bool
    {
        $policy = Gate::getPolicyFor(static::$model);

        if ($policy && method_exists($policy, $ability)) {
            return Gate::forUser($user)->allows($ability, $record ?? static::$model);
        }

        return null;
    }

    public function globallySearchable(): bool
    {
        return true;
    }

    public function canExport(): bool
    {
        return true;
    }

    public function canImport(): bool
    {
        return false;
    }

    // custom per-row actions next to Edit/Delete, keyed by action key (dispatched
    // to runAction()). $record is null when only the set of keys is needed.
    /** @return array<string, string|array{label: string, icon?: string}> */
    public function rowActions(?Model $record = null): array
    {
        return [];
    }

    public function runAction(string $action, Model $record): void
    {
    }

    // --- revisions ---

    public function hasRevisions(): bool
    {
        return false;
    }

    // older revisions beyond this are pruned
    public function revisionsLimit(): int
    {
        return 25;
    }

    // snapshot current attributes as a revision (and prune)
    public function recordRevision(Model $model, mixed $user = null): void
    {
        if (! $this->hasRevisions()) {
            return;
        }

        Revision::create([
            'revisionable_type' => $model->getMorphClass(),
            'revisionable_id' => $model->getKey(),
            'data' => $model->getAttributes(),
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user->name ?? $user->email ?? null,
        ]);

        $q = fn () => Revision::query()
            ->where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', $model->getKey());

        $keep = $q()->orderByDesc('id')->limit($this->revisionsLimit())->pluck('id');
        $q()->whereNotIn('id', $keep)->delete();
    }

    // --- publishing (drafts / scheduling) ---

    // declare the publish workflow so the scheduler can promote due records and
    // the panel can build preview links, or null for none:
    //   ['status' => 'status', 'date' => 'published_at',
    //    'draft' => 'draft', 'scheduled' => 'scheduled', 'published' => 'publish']
    /** @return array<string, string>|null */
    public function publishing(): ?array
    {
        return null;
    }

    // frontend route to preview on, as [name, params]; the panel wraps it in a
    // temporary signed url the frontend allows via hasValidSignature()
    /** @return array{0: string, 1?: array}|null */
    public function previewRoute(Model $record): ?array
    {
        return null;
    }

    public function previewUrl(Model $record): ?string
    {
        $route = $this->previewRoute($record);
        if (! $route) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            $route[0], now()->addMinutes(30), $route[1] ?? []
        );
    }

    /** @return SupportCollection<int, Revision> newest first */
    public function revisions(Model $model): SupportCollection
    {
        return Revision::query()
            ->where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', $model->getKey())
            ->orderByDesc('id')
            ->limit($this->revisionsLimit())
            ->get();
    }

    // pk + every field's column, so an export round-trips back through import
    /** @return string[] */
    public function exportColumns(): array
    {
        $columns = [$this->newModel()->getKeyName()];
        foreach ($this->fields() as $field) {
            if ($field->virtual) {
                continue;
            }
            $col = $field->column();
            if (! in_array($col, $columns, true)) {
                $columns[] = $col;
            }
        }

        return $columns;
    }

    // [key => label]; non-empty enables the row checkboxes + bulk bar. "delete"
    // is handled natively, any other key is dispatched to runBulk().
    /** @return array<string, string> */
    public function bulkActions(): array
    {
        return [];
    }

    public function runBulk(string $action, Collection $records): void
    {
    }

    // --- derived field sets ---

    /** @return Field[] */
    public function indexFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->onIndex));
    }

    /** @return Field[] */
    public function formFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->onForm));
    }

    /** @return string[] names of the form fields declared translatable */
    public function translatableFields(): array
    {
        return array_values(array_map(
            fn (Field $f) => $f->name,
            array_filter($this->formFields(), fn (Field $f) => $f->translatable)
        ));
    }

    // does this resource edit per-language values (multilingual on + a translatable field)
    public function isMultilingual(): bool
    {
        return \Yurba\Cmf\Facades\Yurba::multilangEnabled() && $this->translatableFields() !== [];
    }

    /** @return string[] searchable column names */
    public function searchableColumns(): array
    {
        return array_values(array_map(
            fn (Field $f) => $f->column(),
            array_filter($this->fields(), fn (Field $f) => $f->searchable)
        ));
    }

    /** @return array<string, array> validation rules keyed by field name */
    public function validationRules(): array
    {
        $rules = [];
        foreach ($this->formFields() as $field) {
            if (! empty($field->rules)) {
                $rules[$field->name] = $field->rules;
            }
        }

        return $rules;
    }
}
