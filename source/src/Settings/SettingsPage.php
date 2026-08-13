<?php

namespace Yurba\Cmf\Settings;

use Illuminate\Support\Str;

// a settings "tab": a group of Fields read from / written to the flat Store.
// register subclasses in config('yurba.settings').
abstract class SettingsPage
{
    /** @return \Yurba\Cmf\Fields\Field[] */
    abstract public function fields(): array;

    public function label(): string
    {
        return Str::headline(str_replace('Settings', '', class_basename(static::class)));
    }

    // url segment, e.g. "header"
    public function uriKey(): string
    {
        return Str::kebab(str_replace('Settings', '', class_basename(static::class)));
    }

    // optional sidebar icon (raw html); null hides it
    public function icon(): ?string
    {
        return null;
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
