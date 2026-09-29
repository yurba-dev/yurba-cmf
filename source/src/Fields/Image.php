<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Yurba\Cmf\Support\Upload;

class Image extends Field
{
    // bump on yurba-pv update
    public const VIEWER_ASSET_VERSION = '1.0.3';

    public string $dir = 'admin';

    public function __construct(string $name, ?string $label = null)
    {
        parent::__construct($name, $label);
        $this->onIndex = true;
    }

    public function dir(string $dir): static
    {
        $this->dir = trim($dir, '/');

        return $this;
    }

    public function fill(Request $request, Model $model): void
    {
        if (! $request->hasFile($this->name)) {
            return; // keep the existing image when no new file is uploaded
        }

        $model->{$this->column()} = Upload::publicImage($request->file($this->name), 'uploads/'.$this->dir, $this->name);
    }

    public function indexComponent(): string
    {
        return 'image';
    }

    public function component(): string
    {
        return 'yurba::fields.image';
    }
}
