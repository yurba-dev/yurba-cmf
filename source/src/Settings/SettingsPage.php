<?php

namespace Yurba\Cmf\Settings;

use Illuminate\Support\Str;

abstract class SettingsPage
{
    abstract public function fields(): array;

    public function label(): string
    {
        return Str::headline(str_replace('Settings', '', class_basename(static::class)));
    }

    public function uriKey(): string
    {
        return Str::kebab(str_replace('Settings', '', class_basename(static::class)));
    }

    public function icon(): ?string
    {
        return null;
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
