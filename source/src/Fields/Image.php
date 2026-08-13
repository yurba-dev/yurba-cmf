<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

// image upload under public/uploads/{dir}; column holds the web path, plain file move
class Image extends Field
{
    // bump on yurba-pv update
    public const VIEWER_ASSET_VERSION = '1.0.0';

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

        $file = $request->file($this->name);
        $target = public_path('uploads/'.$this->dir);
        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }
        $filename = date('Ymd').'-'.bin2hex(random_bytes(6)).'.'.$file->getClientOriginalExtension();
        $file->move($target, $filename);

        $model->{$this->column()} = '/uploads/'.$this->dir.'/'.$filename;
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
