<?php

namespace Yurba\Cmf\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Yurba\Cmf\Fields\Field;
use Yurba\Cmf\Pages\Page;

// a content page with its OWN field schema, persisted as one json document.
// subclass, declare fields() (any Yurba\Cmf\Fields\*), register in yurba.pages.
// read back on the frontend via Content::get($uriKey) / Content::field(...).
abstract class ContentPage extends Page
{
    /** @return Field[] */
    abstract public function fields(): array;

    public function render(Request $request): mixed
    {
        return view('yurba::content.form', [
            'page' => $this,
            'record' => $this->record(),
        ]);
    }

    public function handle(Request $request): mixed
    {
        $request->validate($this->validationRules());

        // start from the stored values so fields left untouched (e.g. an image
        // that wasn't re-uploaded) keep their value, then fill the submitted ones
        $record = $this->record();
        foreach ($this->fields() as $field) {
            if ($field->readonly || ! $field->passesCondition($request->all())) {
                continue;
            }
            $field->fill($request, $record);
        }

        Content::put($this->uriKey(), $record->getAttributes());

        return redirect()
            ->route('yurba.page.show', ['page' => $this->uriKey()])
            ->with('yurba_status', $this->label().' saved.');
    }

    // throwaway model preloaded with the page's current values, so the field
    // components render exactly like they do on a resource form
    protected function record(): Model
    {
        $record = new class extends Model
        {
            protected $guarded = [];

            public $timestamps = false;
        };
        $record->exists = true;

        $stored = Content::get($this->uriKey());
        foreach ($this->fields() as $field) {
            $col = $field->column();
            $record->{$col} = $stored[$col] ?? $field->default;
        }

        return $record;
    }

    /** @return array<string, mixed> */
    public function validationRules(): array
    {
        $rules = [];
        foreach ($this->fields() as $field) {
            if (! empty($field->rules)) {
                $rules[$field->name] = $field->rules;
            }
        }

        return $rules;
    }
}
