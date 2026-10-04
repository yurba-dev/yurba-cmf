<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yurba\Cmf\Facades\Yurba;
use Yurba\Cmf\Fields\Tags;
use Yurba\Cmf\Resources\Resource;
use Yurba\Cmf\Support\Csv;

class ImportExportController extends Controller
{
    protected function resolve(string $key): Resource
    {
        $resource = Yurba::find($key);
        abort_if($resource === null, 404);

        return $resource;
    }

    public function export(Request $request, string $resource)
    {
        $res = $this->resolve($resource);
        abort_unless($res->canExport() && $res->canViewAny(Yurba::user()), 403);

        $columns = $res->exportColumns();
        $filename = $res->uriKey().'-'.date('Ymd-His').'.csv';

        $rows = (function () use ($res, $request, $columns) {
            foreach ($res->indexQuery($request)->cursor() as $record) {
                yield array_map(fn ($col) => $this->cell($record->{$col}), $columns);
            }
        })();

        return Csv::download($filename, $columns, $rows);
    }

    protected function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        // json_encode would wrap these in quotes ("2026-01-02T10:00:00.000000Z")
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    public function importForm(string $resource)
    {
        $res = $this->resolve($resource);
        abort_unless($res->canImport() && $res->canCreate(Yurba::user()), 403);

        return view('yurba::resource.import', ['res' => $res]);
    }

    public function import(Request $request, string $resource)
    {
        $res = $this->resolve($resource);
        abort_unless($res->canImport() && $res->canCreate(Yurba::user()), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:8192'],
        ]);

        [$header, $rows] = Csv::read($request->file('file')->getRealPath());
        if (empty($header)) {
            return back()->withErrors(['file' => __('The file is empty or unreadable.')]);
        }

        $model = $res->newModel();
        $key = $model->getKeyName();
        $casts = $model->getCasts();
        $allowed = $res->exportColumns();
        $user = Yurba::user();

        // values go through each field's own fill() like the form; readonly, virtual and index-only columns are not written
        $fields = [];
        foreach ($res->formFields() as $field) {
            if (! $field->virtual && ! $field->readonly && in_array($field->column(), $allowed, true)) {
                $fields[$field->column()] = $field;
            }
        }

        $created = $updated = $failed = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // header is line 1

            $id = $row[$key] ?? null;
            $existing = ! empty($id) ? $res->query()->find($id) : null;
            if ($existing && ! $res->canUpdate($user, $existing)) {
                $failed++;
                $errors[] = "Row {$line}: ".__('You may not update this record.');

                continue;
            }

            // unchanged columns are skipped on update so a unique rule doesn't trip over the record's own value
            $input = [];
            $present = [];
            foreach ($row as $col => $val) {
                if (! isset($fields[$col])) {
                    continue;
                }
                if ($existing && (string) $val === $this->cell($existing->{$col})) {
                    continue;
                }
                $val = ($val == '' ? null : $val);
                if ($val !== null && in_array($casts[$col] ?? null, ['array', 'json', 'object', 'collection'], true)) {
                    $decoded = json_decode($val, true);
                    $val = json_last_error() == JSON_ERROR_NONE ? $decoded : $val;
                }
                if (is_array($val) && $fields[$col] instanceof Tags) {
                    $val = implode(', ', $val);
                }
                $input[$fields[$col]->name] = $val;
                $present[] = $fields[$col];
            }

            $rules = [];
            foreach ($present as $field) {
                if (! empty($field->rules)) {
                    $rules[$field->name] = $field->rules;
                }
            }
            if ($rules) {
                $validator = Validator::make($input, $rules);
                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Row {$line}: ".$validator->errors()->first();

                    continue;
                }
            }

            if ($existing && ! $present) {
                continue;
            }

            try {
                $record = $existing ?? $res->newModel();
                $sub = Request::create('/', 'POST', $input);
                foreach ($present as $field) {
                    $field->fill($sub, $record);
                }
                $record->save();
                foreach ($present as $field) {
                    $field->afterSave($sub, $record);
                }
                if (! $existing || $record->wasChanged()) {
                    $res->recordRevision($record, $user);
                }
                $existing ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Row {$line}: ".$e->getMessage();
            }
        }

        $summary = __('Import complete - :created created, :updated updated', ['created' => $created, 'updated' => $updated])
            .($failed ? __(', :failed failed', ['failed' => $failed]) : '').'.';

        $redirect = redirect()
            ->route('yurba.resource.index', $res->uriKey())
            ->with('yurba_status', $summary);

        if ($errors) {
            $redirect->withErrors(array_slice($errors, 0, 10));
        }

        return $redirect;
    }
}
