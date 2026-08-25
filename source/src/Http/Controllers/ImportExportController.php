<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yurba\Cmf\Facades\Yurba;
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

    // stream the current (searched/sorted) list as a csv download
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
        $rules = $res->validationRules();
        $allowed = $res->exportColumns();

        $created = $updated = $failed = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // header is line 1

            // keep only known columns; decode json for array-cast attributes
            $data = [];
            foreach ($row as $col => $val) {
                if (! in_array($col, $allowed, true)) {
                    continue;
                }
                $val = ($val === '' ? null : $val);
                if ($val !== null && in_array($casts[$col] ?? null, ['array', 'json', 'object', 'collection'], true)) {
                    $decoded = json_decode($val, true);
                    $val = json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
                }
                $data[$col] = $val;
            }

            // validate the columns that carry rules and are present in this row
            $applicable = array_intersect_key($rules, $data);
            if ($applicable) {
                $validator = Validator::make($data, $applicable);
                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Row {$line}: ".$validator->errors()->first();

                    continue;
                }
            }

            $id = $data[$key] ?? null;
            unset($data[$key]);

            try {
                if (! empty($id) && ($existing = $res->query()->find($id))) {
                    $existing->fill($data)->save();
                    $updated++;
                } else {
                    $res->newModel()->fill($data)->save();
                    $created++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Row {$line}: ".$e->getMessage();
            }
        }

        $summary = __('Import complete — :created created, :updated updated', ['created' => $created, 'updated' => $updated])
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
