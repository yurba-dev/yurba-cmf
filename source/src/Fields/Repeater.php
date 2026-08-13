<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

// editable table of rows backed by an array/json column; empty rows dropped on save
class Repeater extends Field
{
    /** @var array<string, string> column key => header label */
    public array $columns = ['label' => 'Label', 'value' => 'Value'];

    public bool $withVisible = true;

    public string $addLabel = 'Add row';

    // 'table' (one row per line) or 'stacked' (one card per item)
    public string $layout = 'table';

    public function __construct(string $name, ?string $label = null)
    {
        parent::__construct($name, $label);
        $this->onIndex = false;
    }

    /** @param array<string, string> $columns key => header label */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function withVisible(bool $v = true): static
    {
        $this->withVisible = $v;

        return $this;
    }

    public function addLabel(string $label): static
    {
        $this->addLabel = $label;

        return $this;
    }

    public function layout(string $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function stacked(bool $v = true): static
    {
        $this->layout = $v ? 'stacked' : 'table';

        return $this;
    }

    public function formValue(Model $model): mixed
    {
        $value = old($this->name, $model->exists ? $this->value($model) : $this->default);

        return is_array($value) ? $value : [];
    }

    public function fill(Request $request, Model $model): void
    {
        // rows can arrive from POST (text/hidden inputs) and FILES (image uploads);
        // union the indexes so an image-only new row isn't missed
        $posted = (array) $request->input($this->name, []);
        $indexes = array_unique(array_merge(
            array_keys($posted),
            array_keys((array) $request->file($this->name, []))
        ));

        $clean = [];
        foreach ($indexes as $i) {
            $row = (array) ($posted[$i] ?? []);
            $entry = [];
            $empty = true;

            foreach ($this->columns as $col => $conf) {
                if (is_array($conf) && ! empty($conf['image'])) {
                    $file = $request->file("{$this->name}.{$i}.{$col}");
                    $entry[$col] = $file
                        ? $this->storeImage($file, $conf['dir'] ?? 'content')
                        : trim((string) ($row[$col] ?? '')); // hidden input keeps the current image
                } else {
                    $entry[$col] = trim((string) ($row[$col] ?? ''));
                }

                if ($entry[$col] !== '') {
                    $empty = false;
                }
            }

            if ($this->withVisible) {
                $entry['visible'] = ! empty($row['visible']);
            }

            if (! $empty) {
                $clean[] = $entry;
            }
        }

        $model->{$this->column()} = array_values($clean);
    }

    // move an uploaded image under public/uploads/{dir}, return its web path
    protected function storeImage($file, string $dir): string
    {
        $dir = trim($dir, '/');
        $target = public_path('uploads/'.$dir);
        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }
        $filename = date('Ymd').'-'.bin2hex(random_bytes(6)).'.'.$file->getClientOriginalExtension();
        $file->move($target, $filename);

        return '/uploads/'.$dir.'/'.$filename;
    }

    public function indexValue(Model $model): string
    {
        return (string) count((array) $this->value($model)).' items';
    }

    public function component(): string
    {
        return 'yurba::fields.repeater';
    }
}
