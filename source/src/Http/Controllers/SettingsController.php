<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Yurba\Cmf\Settings\SettingsPage;
use Yurba\Cmf\Settings\Store;

class SettingsController extends Controller
{
    protected function resolve(string $key): SettingsPage
    {
        $page = app('yurba.cmf')->findSettings($key);
        abort_if($page === null, 404);

        return $page;
    }

    public function show(string $page)
    {
        $settings = $this->resolve($page);

        return view('yurba::settings.form', [
            'page' => $settings,
            'record' => $this->record($settings),
        ]);
    }

    public function save(Request $request, string $page)
    {
        $settings = $this->resolve($page);
        $input = $request->all();
        $request->validate($settings->visibleValidationRules($input));

        // readonly/hidden fields are not posted, so they are skipped and keep their stored value
        $record = $this->record($settings);
        $values = [];
        foreach ($settings->fields() as $field) {
            if ($field->readonly || $field->virtual || ! $field->passesCondition($input)) {
                continue;
            }
            $field->fill($request, $record);
            $values[$field->column()] = $record->{$field->column()};
        }
        Store::setMany($values);

        return redirect()
            ->route('yurba.settings.show', $settings->uriKey())
            ->with('yurba_status', __(':name settings saved.', ['name' => $settings->label()]));
    }

    protected function record(SettingsPage $page): Model
    {
        $record = new class extends Model
        {
            protected $guarded = [];

            public $timestamps = false;
        };
        $record->exists = true;

        foreach ($page->fields() as $field) {
            $record->{$field->column()} = Store::get($field->column(), $field->default);
        }

        return $record;
    }
}
