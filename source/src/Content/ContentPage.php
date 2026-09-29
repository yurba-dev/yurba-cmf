<?php

namespace Yurba\Cmf\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Yurba\Cmf\Pages\Page;

abstract class ContentPage extends Page
{
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
        $request->validate($this->visibleValidationRules($request->all()));

        // start from the stored values so untouched fields (an image not re-uploaded) keep them
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
            ->with('yurba_status', __(':name saved.', ['name' => $this->label()]));
    }

    // throwaway model preloaded with current values so the fields render exactly like on a resource form
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
            $field->hydrate($record, $stored);
        }

        return $record;
    }

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

    // readonly and visibleWhen()-hidden fields are skipped so a hidden required field can't block the form
    public function visibleValidationRules(array $input): array
    {
        $names = [];
        foreach ($this->fields() as $field) {
            if (! $field->readonly && $field->passesCondition($input)) {
                $names[$field->name] = true;
            }
        }

        return array_intersect_key($this->validationRules(), $names);
    }
}
