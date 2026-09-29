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
use Yurba\Cmf\Revisions\Revision;

abstract class Resource
{
    public static string $model;

    abstract public function fields(): array;

    public function filters(): array
    {
        return [];
    }

    public function model(): string
    {
        return static::$model;
    }

    public function newModel(): Model
    {
        $class = static::$model;

        return new $class;
    }

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

    public function uriKey(): string
    {
        return Str::plural(Str::kebab(class_basename(static::$model)));
    }

    public function icon(): ?string
    {
        return null;
    }

    public function title(Model $record): string
    {
        foreach (['title', 'name', 'email'] as $attr) {
            if (filled($record->{$attr} ?? null)) {
                return (string) $record->{$attr};
            }
        }

        return '#'.$record->getKey();
    }

    // the integer column storing the position (e.g. 'sort') enables drag-and-drop reordering; null disables it
    public function reorderable(): ?string
    {
        return null;
    }

    // [column, 'asc'|'desc']
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

        if ($this->usesSoftDeletes()) {
            $trashed = $request->query('trashed');
            if ($trashed == 'only') {
                $query->onlyTrashed();
            } elseif ($trashed == 'with') {
                $query->withTrashed();
            }
        }

        $search = trim((string) $request->query('q', ''));
        $columns = $this->searchableColumns();
        if ($search != '' && $columns) {
            $query->where(function (Builder $q) use ($columns, $search) {
                foreach ($columns as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

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
        $dir = $request->query('dir') == 'desc' ? 'desc' : 'asc';
        if ($sort && in_array($sort, $sortable, true)) {
            // key as tiebreaker, or rows with equal values can repeat or vanish across pages
            $query->orderBy($sort, $dir)->orderBy($this->newModel()->getKeyName(), $dir);
        } elseif ($default = $this->defaultSort()) {
            $query->orderBy($default[0], $default[1] ?? 'asc');
            // stable ties, so the list matches the order reorder() renumbers from
            if ($default[0] == $this->reorderable()) {
                $query->orderBy($this->newModel()->getKeyName());
            }
        } else {
            $query->orderByDesc($this->newModel()->getKeyName());
        }

        return $query;
    }

    public function perPage(): int
    {
        return app('yurba.cmf')->perPage();
    }

    // each gate defers to a model Policy ability when defined; with neither, everything is allowed and only the panel entry gate applies

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

    // dispatched to runAction(); $record is null when only the set of keys is needed
    /** @return array<string, string|array{label: string, icon?: string}> */
    public function rowActions(?Model $record = null): array
    {
        return [];
    }

    public function runAction(string $action, Model $record): void
    {
    }

    public function hasRevisions(): bool
    {
        return false;
    }

    public function revisionsLimit(): int
    {
        return 25;
    }

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

    // e.g. ['status' => 'status', 'date' => 'published_at', 'draft' => 'draft', 'scheduled' => 'scheduled', 'published' => 'publish']
    public function publishing(): ?array
    {
        return null;
    }

    // [route name, params]; the panel wraps it in a temporary signed url the frontend allows via hasValidSignature()
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
    public function exportColumns(): array
    {
        $model = $this->newModel();
        $columns = [$model->getKeyName()];
        foreach ($this->fields() as $field) {
            if ($field->virtual || ! $field->exportable || $field->isSensitive($model)) {
                continue;
            }
            $col = $field->column();
            if (! in_array($col, $columns, true)) {
                $columns[] = $col;
            }
        }

        return $columns;
    }

    // non-empty enables the row checkboxes and bulk bar; "delete" is native, other keys go to runBulk()
    public function bulkActions(): array
    {
        return [];
    }

    public function runBulk(string $action, Collection $records): void
    {
    }

    public function indexFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->onIndex));
    }

    public function detailFields(?Model $record = null): array
    {
        $model = $record ?? $this->newModel();

        return array_values(array_filter($this->fields(), fn (Field $f) => $f->onDetail && ! $f->isSensitive($model)));
    }

    public function formFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->onForm));
    }

    public function translatableFields(): array
    {
        return array_values(array_map(
            fn (Field $f) => $f->name,
            array_filter($this->formFields(), fn (Field $f) => $f->translatable)
        ));
    }

    public function isMultilingual(): bool
    {
        return \Yurba\Cmf\Facades\Yurba::multilangEnabled() && $this->translatableFields() != [];
    }

    public function searchableColumns(): array
    {
        return array_values(array_map(
            fn (Field $f) => $f->column(),
            array_filter($this->fields(), fn (Field $f) => $f->searchable)
        ));
    }

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
