<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yurba\Cmf\Facades\Yurba;
use Yurba\Cmf\Fields\Field;
use Yurba\Cmf\Resources\Resource;
use Yurba\Cmf\Revisions\Revision;
use Yurba\Cmf\Seo\Seo;

class ResourceController extends Controller
{
    protected function resolve(string $key): Resource
    {
        $resource = Yurba::find($key);
        abort_if($resource === null, 404);

        return $resource;
    }

    public function index(Request $request, string $resource)
    {
        $res = $this->resolve($resource);
        abort_unless($res->canViewAny(Yurba::user()), 403);

        $records = $res->indexQuery($request)->paginate($res->perPage())->withQueryString();

        $search = trim((string) $request->query('q', ''));
        $sort = is_string($request->query('sort')) ? $request->query('sort') : null;
        $dir = $request->query('dir') == 'desc' ? 'desc' : 'asc';

        return view('yurba::resource.index', compact('res', 'records', 'search', 'sort', 'dir'));
    }

    public function create(string $resource)
    {
        $res = $this->resolve($resource);
        abort_unless($res->canCreate(Yurba::user()), 403);

        return view('yurba::resource.form', ['res' => $res, 'record' => $res->newModel(), 'locale' => null]);
    }

    public function store(Request $request, string $resource)
    {
        $res = $this->resolve($resource);
        abort_unless($res->canCreate(Yurba::user()), 403);

        $fields = $this->activeFields($res, $request);
        $request->validate($this->rulesFor($fields));

        $record = $res->newModel();
        $this->fill($request, $fields, $record);
        $record->save();
        $this->afterSave($request, $res, $record);
        $res->recordRevision($record, Yurba::user());

        return redirect()
            ->route('yurba.resource.index', $res->uriKey())
            ->with('yurba_status', __(':name created.', ['name' => $res->label()]));
    }

    public function show(string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        $record = $res->query()->findOrFail($id);
        abort_unless($res->canView(Yurba::user(), $record), 403);

        return view('yurba::resource.show', ['res' => $res, 'record' => $record]);
    }

    public function edit(Request $request, string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        $record = $res->query()->findOrFail($id);
        abort_unless($res->canUpdate(Yurba::user(), $record), 403);

        $loadedRevision = null;
        if ($res->hasRevisions() && $request->filled('revision')) {
            $loadedRevision = Revision::query()
                ->where('revisionable_type', $record->getMorphClass())
                ->where('revisionable_id', $record->getKey())
                ->find($request->integer('revision'));

            if ($loadedRevision) {
                $data = $loadedRevision->data;
                unset($data[$record->getKeyName()], $data['created_at'], $data['updated_at']);
                $record->forceFill($data);
            }
        }

        $locale = $this->activeLocale($res, $request);
        if ($locale !== null && $locale != Yurba::defaultLocale()) {
            $this->applyLocale($res, $record, $locale);
        }

        return view('yurba::resource.form', [
            'res' => $res,
            'record' => $record,
            'loadedRevision' => $loadedRevision,
            'locale' => $locale,
        ]);
    }

    public function restoreRevision(string $resource, int|string $id, int|string $revision)
    {
        $res = $this->resolve($resource);
        abort_unless($res->hasRevisions(), 404);

        $record = $res->query()->findOrFail($id);
        abort_unless($res->canUpdate(Yurba::user(), $record), 403);

        $rev = Revision::query()
            ->where('revisionable_type', $record->getMorphClass())
            ->where('revisionable_id', $record->getKey())
            ->findOrFail($revision);

        $data = collect($rev->data)
            ->except([$record->getKeyName(), $record->getCreatedAtColumn(), $record->getUpdatedAtColumn()])
            ->all();

        $record->fill($data)->save();
        $res->recordRevision($record, Yurba::user());

        return redirect()
            ->route('yurba.resource.edit', [$res->uriKey(), $record->getKey()])
            ->with('yurba_status', __('Reverted to the selected revision.'));
    }

    public function update(Request $request, string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        $record = $res->query()->findOrFail($id);
        abort_unless($res->canUpdate(Yurba::user(), $record), 403);

        $fields = $this->activeFields($res, $request);
        $locale = $this->activeLocale($res, $request);

        $rules = $this->rulesFor($fields);
        if ($locale !== null && $locale != Yurba::defaultLocale()) {
            $rules = $this->relaxTranslatable($rules, $fields);
        }
        $request->validate($rules);

        if ($locale === null || $locale == Yurba::defaultLocale()) {
            $this->fill($request, $fields, $record);
            $record->save();
            $this->afterSave($request, $res, $record);
        } else {
            $this->fillLocalized($request, $fields, $record, $locale, $res);
        }

        if ($record->wasChanged()) {
            $res->recordRevision($record, Yurba::user());
        }

        if ($request->input('_after') == 'edit') {
            $params = [$res->uriKey(), $record->getKey()];
            if ($locale !== null && $locale != Yurba::defaultLocale()) {
                $params['locale'] = $locale;
            }

            return redirect()
                ->route('yurba.resource.edit', $params)
                ->with('yurba_status', __(':name updated.', ['name' => $res->label()]));
        }

        return redirect()
            ->route('yurba.resource.index', $res->uriKey())
            ->with('yurba_status', __(':name updated.', ['name' => $res->label()]));
    }

    public function destroy(string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        $record = $res->query()->findOrFail($id);
        abort_unless($res->canDelete(Yurba::user(), $record), 403);

        $record->delete();
        if (! $res->usesSoftDeletes()) {
            $this->purgeSideRows($record);
        }

        return back()->with('yurba_status', __(':name deleted.', ['name' => $res->label()]));
    }

    public function restore(string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        abort_unless($res->usesSoftDeletes(), 404);

        $record = $res->query()->withTrashed()->findOrFail($id);
        abort_unless($res->canDelete(Yurba::user(), $record), 403);

        $record->restore();

        return back()->with('yurba_status', __(':name restored.', ['name' => $res->label()]));
    }

    public function forceDelete(string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        abort_unless($res->usesSoftDeletes(), 404);

        $record = $res->query()->withTrashed()->findOrFail($id);
        abort_unless($res->canDelete(Yurba::user(), $record), 403);

        $record->forceDelete();
        $this->purgeSideRows($record);

        return back()->with('yurba_status', __(':name permanently deleted.', ['name' => $res->label()]));
    }

    public function bulk(Request $request, string $resource)
    {
        $res = $this->resolve($resource);
        $user = Yurba::user();
        abort_unless($res->canViewAny($user), 403);

        $action = (string) $request->input('action');
        $actions = $res->bulkActions();
        abort_unless(isset($actions[$action]), 404);

        $ids = array_filter((array) $request->input('ids', []));
        if (empty($ids)) {
            return back()->with('yurba_status', __('No rows were selected.'));
        }

        $records = $res->query()->whereKey($ids)->get();

        if ($action == 'delete') {
            $records = $records->filter(fn ($record) => $res->canDelete($user, $record))->values();
            foreach ($records as $record) {
                $record->delete();
                if (! $res->usesSoftDeletes()) {
                    $this->purgeSideRows($record);
                }
            }
        } else {
            $records = $records->filter(fn ($record) => $res->canUpdate($user, $record))->values();
            if ($records->isNotEmpty()) {
                $res->runBulk($action, $records);
            }
        }

        return back()->with('yurba_status', __(':count :items - :action.', ['count' => $records->count(), 'items' => $res->pluralLabel(), 'action' => $actions[$action]]));
    }

    // the posted ids are only the visible rows: they swap among the slots they held, other rows keep theirs, then all are renumbered 1..N
    public function reorder(Request $request, string $resource)
    {
        $res = $this->resolve($resource);
        $column = $res->reorderable();
        abort_unless($column, 404);
        abort_unless($res->canUpdate(Yurba::user(), $res->newModel()), 403);

        $key = $res->newModel()->getKeyName();
        $base = fn () => $res->usesSoftDeletes() ? $res->query()->withTrashed() : $res->query();

        $default = $res->defaultSort();
        $desc = $default && $default[0] == $column && strtolower($default[1] ?? 'asc') == 'desc';

        $current = [];
        foreach ($base()->reorder()->orderBy($column, $desc ? 'desc' : 'asc')->orderBy($key)->get([$key, $column]) as $row) {
            $current[(string) $row->getKey()] = $row->{$column};
        }

        $moved = [];
        foreach ((array) $request->input('ids', []) as $id) {
            $id = (string) $id;
            if (isset($current[$id]) && ! isset($moved[$id])) {
                $moved[$id] = true;
            }
        }

        $queue = array_keys($moved);
        $order = [];
        foreach (array_keys($current) as $id) {
            $order[] = isset($moved[$id]) ? (string) array_shift($queue) : (string) $id;
        }

        $total = count($order);
        DB::transaction(function () use ($order, $current, $base, $column, $desc, $total) {
            foreach ($order as $i => $id) {
                $position = $desc ? $total - $i : $i + 1;
                if ((string) $current[$id] != (string) $position) {
                    $base()->whereKey($id)->update([$column => $position]);
                }
            }
        });

        return response()->json(['ok' => true, 'count' => count($moved)]);
    }

    public function action(Request $request, string $resource)
    {
        $res = $this->resolve($resource);

        $action = (string) $request->input('action');
        abort_unless(isset($res->rowActions()[$action]), 404);

        $id = $request->input('id');
        abort_unless(is_scalar($id), 404);
        $record = $res->query()->findOrFail($id);
        abort_unless($res->canUpdate(Yurba::user(), $record), 403);

        // resolve the label against the record's current state before mutating
        $def = $res->rowActions($record)[$action] ?? $action;
        $label = is_array($def) ? ($def['label'] ?? $action) : $def;
        $res->runAction($action, $record);

        return back()->with('yurba_status', __(':label - done.', ['label' => $label]));
    }

    protected function activeFields(Resource $res, Request $request): array
    {
        $input = $request->all();

        return array_values(array_filter(
            $res->formFields(),
            fn (Field $f) => ! $f->virtual && $f->passesCondition($input)
        ));
    }

    protected function rulesFor(array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            if (! empty($field->rules)) {
                $rules[$field->name] = $field->rules;
            }
        }

        return $rules;
    }

    // read-only fields are skipped so their stored value stays authoritative
    protected function fill(Request $request, array $fields, $record): void
    {
        foreach ($fields as $field) {
            if ($field->readonly) {
                continue;
            }
            $field->fill($request, $record);
        }
    }

    // a non-default locale may be left blank to fall back, so drop required rules
    protected function relaxTranslatable(array $rules, array $fields): array
    {
        foreach ($fields as $field) {
            if ($field->translatable && isset($rules[$field->name])) {
                $rules[$field->name] = array_values(array_filter(
                    $rules[$field->name],
                    fn ($rule) => ! is_string($rule) || $rule != 'required'
                ));
            }
        }

        return $rules;
    }

    protected function activeLocale(Resource $res, Request $request): ?string
    {
        if (! $res->isMultilingual()) {
            return null;
        }

        $requested = (string) ($request->input('_locale') ?: $request->query('locale', ''));

        return isset(Yurba::contentLocales()[$requested]) ? $requested : Yurba::defaultLocale();
    }

    protected function applyLocale(Resource $res, Model $record, string $locale): void
    {
        if (! method_exists($record, 'localeValues')) {
            return;
        }

        $values = $record->localeValues($locale);
        foreach ($res->formFields() as $field) {
            if (! $field->translatable) {
                continue;
            }
            if (array_key_exists($field->name, $values)) {
                $record->{$field->name} = $values[$field->name];
            } elseif (! $field->copyOnCreate) {
                $record->{$field->name} = null;
            }
        }
    }

    protected function fillLocalized(Request $request, array $fields, Model $record, string $locale, ?Resource $res = null): void
    {
        $translations = [];
        foreach ($fields as $field) {
            if ($field->readonly) {
                continue;
            }
            if ($field->translatable) {
                $translations[$field->name] = $this->localizedValue($request, $field, $record, $locale);
            } else {
                $field->fill($request, $record);
            }
        }

        $record->save();

        // virtual fields (seo, m2m) are not in $fields but still persist here
        $input = $request->all();
        foreach ($res ? $res->formFields() : $fields as $field) {
            if (! $field->readonly && ! $field->translatable && $field->passesCondition($input)) {
                $field->afterSave($request, $record);
            }
        }

        if (method_exists($record, 'putTranslation')) {
            foreach ($translations as $name => $value) {
                $record->putTranslation($locale, $name, $value);
            }
        }
    }

    // fill() runs on a detached copy so the base row keeps its default-locale value
    protected function localizedValue(Request $request, Field $field, Model $record, string $locale): mixed
    {
        $column = $field->column();
        $current = method_exists($record, 'translationValue') ? $record->translationValue($locale, $field->name) : null;

        $tmp = $record->newInstance([], true);
        $tmp->setRawAttributes([$record->getKeyName() => $record->getKey(), $column => $current]);
        $field->fill($request, $tmp);

        $value = $tmp->getAttributes()[$column] ?? null;

        return is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }

    // seo and revision rows are polymorphic with no db cascade (translations clean up in HasTranslations)
    protected function purgeSideRows(Model $record): void
    {
        $type = $record->getMorphClass();
        $id = $record->getKey();

        try {
            Seo::query()->where('seoable_type', $type)->where('seoable_id', $id)->delete();
            Revision::query()->where('revisionable_type', $type)->where('revisionable_id', $id)->delete();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function afterSave(Request $request, Resource $res, $record): void
    {
        $input = $request->all();
        foreach ($res->formFields() as $field) {
            if (! $field->readonly && $field->passesCondition($input)) {
                $field->afterSave($request, $record);
            }
        }
    }
}
