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
        $request->validate($settings->validationRules());

        // reuse each field's fill() against a throwaway model, then persist the attribute
        $record = $this->record($settings);
        foreach ($settings->fields() as $field) {
            $field->fill($request, $record);
            Store::set($field->column(), $record->{$field->column()});
        }

        return redirect()
            ->route('yurba.settings.show', $settings->uriKey())
            ->with('yurba_status', $settings->label().' settings saved.');
    }

    // non-persisted model preloaded with current values so the fields render
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
