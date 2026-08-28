<?php

namespace Yurba\Cmf\Translations;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Yurba\Cmf\Facades\Yurba;

/**
 * Per-locale field values kept in yurba_translations. Which fields are
 * translatable is declared on the resource (Field::translatable()); this trait
 * only stores and resolves the values, falling back to the base row.
 */
trait HasTranslations
{
    protected ?array $translationCache = null;

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    // localized value of a field, or the base value when there is no translation
    public function tr(string $field, ?string $locale = null): mixed
    {
        $locale = $locale ?: Yurba::contentLocale();

        if (! Yurba::multilangEnabled() || $locale === Yurba::defaultLocale()) {
            return $this->{$field};
        }

        $value = $this->translationValue($locale, $field);

        return $value === null ? $this->{$field} : $value;
    }

    public function translationValue(string $locale, string $field): mixed
    {
        return $this->translationMap()[$locale][$field] ?? null;
    }

    /** @return array<string, string> [field => value] for one locale */
    public function localeValues(string $locale): array
    {
        return $this->translationMap()[$locale] ?? [];
    }

    public function putTranslation(string $locale, string $field, mixed $value): void
    {
        $this->translations()->updateOrCreate(
            ['locale' => $locale, 'field' => $field],
            ['value' => $value]
        );
        $this->translationCache = null;
    }

    public function forgetTranslations(?string $locale = null): void
    {
        $query = $this->translations();
        if ($locale !== null) {
            $query->where('locale', $locale);
        }
        $query->delete();
        $this->translationCache = null;
    }

    /** @return array<string, array<string, string>> [locale => [field => value]] */
    protected function translationMap(): array
    {
        if ($this->translationCache !== null) {
            return $this->translationCache;
        }

        $map = [];
        foreach ($this->translations as $row) {
            $map[$row->locale][$row->field] = $row->value;
        }

        return $this->translationCache = $map;
    }
}
