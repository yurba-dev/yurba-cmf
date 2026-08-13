<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Http\Request;
use Yurba\Cmf\Facades\Yurba;
use Yurba\Cmf\Fields\Field;
use Yurba\Cmf\Resources\Resource;
use Yurba\Cmf\Revisions\Revision;

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
        $sort = $request->query('sort');
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        return view('yurba::resource.index', compact('res', 'records', 'search', 'sort', 'dir'));
    }

    public function create(string $resource)
    {
        $res = $this->resolve($resource);
        abort_unless($res->canCreate(Yurba::user()), 403);

        return view('yurba::resource.form', ['res' => $res, 'record' => $res->newModel()]);
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
            ->with('yurba_status', $res->label().' created.');
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

        // ?revision=N loads that snapshot's values into the edit form so it can be
        // tweaked and re-saved; saving records it as the newest revision (update()).
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

        return view('yurba::resource.form', [
            'res' => $res,
            'record' => $record,
            'loadedRevision' => $loadedRevision,
        ]);
    }

    // roll back to a previous revision (itself recorded as a new revision)
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
            ->with('yurba_status', 'Reverted to the selected revision.');
    }

    public function update(Request $request, string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        $record = $res->query()->findOrFail($id);
        abort_unless($res->canUpdate(Yurba::user(), $record), 403);

        $fields = $this->activeFields($res, $request);
        $request->validate($this->rulesFor($fields));

        $this->fill($request, $fields, $record);
        $record->save();
        $this->afterSave($request, $res, $record);
        if ($record->wasChanged()) {
            $res->recordRevision($record, Yurba::user());
        }

        if ($request->input('after') === 'edit') {
            return redirect()
                ->route('yurba.resource.edit', [$res->uriKey(), $record->getKey()])
                ->with('yurba_status', $res->label().' updated.');
        }

        return redirect()
            ->route('yurba.resource.index', $res->uriKey())
            ->with('yurba_status', $res->label().' updated.');
    }

    public function destroy(string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        $record = $res->query()->findOrFail($id);
        abort_unless($res->canDelete(Yurba::user(), $record), 403);

        $record->delete();

        return back()->with('yurba_status', $res->label().' deleted.');
    }

    public function restore(string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        abort_unless($res->usesSoftDeletes(), 404);

        $record = $res->query()->withTrashed()->findOrFail($id);
        abort_unless($res->canDelete(Yurba::user(), $record), 403);

        $record->restore();

        return back()->with('yurba_status', $res->label().' restored.');
    }

    public function forceDelete(string $resource, int|string $id)
    {
        $res = $this->resolve($resource);
        abort_unless($res->usesSoftDeletes(), 404);

        $record = $res->query()->withTrashed()->findOrFail($id);
        abort_unless($res->canDelete(Yurba::user(), $record), 403);

        $record->forceDelete();

        return back()->with('yurba_status', $res->label().' permanently deleted.');
    }

    public function bulk(Request $request, string $resource)
    {
        $res = $this->resolve($resource);

        $action = (string) $request->input('action');
        $actions = $res->bulkActions();
        abort_unless(isset($actions[$action]), 404);

        $ids = array_filter((array) $request->input('ids', []));
        if (empty($ids)) {
            return back()->with('yurba_status', 'No rows were selected.');
        }

        $records = $res->query()->whereKey($ids)->get();

        if ($action === 'delete') {
            $user = Yurba::user();
            $records = $records->filter(fn ($record) => $res->canDelete($user, $record))->values();
            $records->each->delete();
        } else {
            $res->runBulk($action, $records);
        }

        return back()->with('yurba_status', $records->count().' '.$res->pluralLabel().' — '.$actions[$action].'.');
    }

    // persist a drag-and-drop reorder: writes 1..N into the position column in the
    // order the ids arrive (only for resources that declare reorderable())
    public function reorder(Request $request, string $resource)
    {
        $res = $this->resolve($resource);
        $column = $res->reorderable();
        abort_unless($column, 404);
        abort_unless($res->canUpdate(Yurba::user(), $res->newModel()), 403);

        $ids = array_values(array_filter((array) $request->input('ids', [])));
        foreach ($ids as $pos => $id) {
            $res->query()->whereKey($id)->update([$column => $pos + 1]);
        }

        return response()->json(['ok' => true, 'count' => count($ids)]);
    }

    public function action(Request $request, string $resource)
    {
        $res = $this->resolve($resource);

        $action = (string) $request->input('action');
        abort_unless(isset($res->rowActions()[$action]), 404);

        $record = $res->query()->findOrFail($request->input('id'));
        abort_unless($res->canUpdate(Yurba::user(), $record), 403);

        // resolve the label against the record's current state before mutating
        $def = $res->rowActions($record)[$action] ?? $action;
        $label = is_array($def) ? ($def['label'] ?? $action) : $def;
        $res->runAction($action, $record);

        return back()->with('yurba_status', $label.' — done.');
    }

    // form fields for this request: not virtual, and show-when condition satisfied
    /** @return Field[] */
    protected function activeFields(Resource $res, Request $request): array
    {
        $input = $request->all();

        return array_values(array_filter(
            $res->formFields(),
            fn (Field $f) => ! $f->virtual && $f->passesCondition($input)
        ));
    }

    /**
     * @param  Field[]  $fields
     * @return array<string, array>
     */
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
    /** @param  Field[]  $fields */
    protected function fill(Request $request, array $fields, $record): void
    {
        foreach ($fields as $field) {
            if ($field->readonly) {
                continue;
            }
            $field->fill($request, $record);
        }
    }

    // run every condition-passing field's afterSave once saved (e.g. m2m pivot sync)
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
