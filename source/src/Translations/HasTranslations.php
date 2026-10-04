<?php

namespace Yurba\Cmf\Translations;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Yurba\Cmf\Facades\Yurba;

trait HasTranslations
{
    protected ?array $translationCache = null;

    // morphMany without a db cascade; a soft delete can be restored, so translations go only with the row itself
    public static function bootHasTranslations(): void
    {
        static::deleted(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }
            try {
                $model->forgetTranslations();
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function tr(string $field, ?string $locale = null): mixed
    {
        $locale = $locale ?: Yurba::contentLocale();

        if (! Yurba::multilangEnabled() || $locale == Yurba::defaultLocale()) {
            return $this->{$field};
        }

        $value = $this->translationValue($locale, $field);

        // a blank translation falls back too: an emptied editor field is stored as '', not null
        if ($value === null || $value === '') {
            return $this->{$field};
        }

        // json-cast fields are stored encoded, like the base column
        return $this->hasCast($field, ['array', 'json', 'object', 'collection']) ? $this->castAttribute($field, $value) : $value;
    }

    public function translationValue(string $locale, string $field): mixed
    {
        return $this->translationMap()[$locale][$field] ?? null;
    }

    public function localeValues(string $locale): array
    {
        return $this->translationMap()[$locale] ?? [];
    }

    // the loaded relation is dropped too, or translationMap() would rebuild from the stale rows
    public function putTranslation(string $locale, string $field, mixed $value): void
    {
        $this->translations()->updateOrCreate(
            ['locale' => $locale, 'field' => $field],
            ['value' => $value]
        );
        $this->translationCache = null;
        $this->unsetRelation('translations');
    }

    public function forgetTranslations(?string $locale = null): void
    {
        $query = $this->translations();
        if ($locale !== null) {
            $query->where('locale', $locale);
        }
        $query->delete();
        $this->translationCache = null;
        $this->unsetRelation('translations');
    }

    // [locale => [field => value]]
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
