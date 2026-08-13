<?php

namespace Yurba\Cmf\Fields;

use DOMDocument;
use DOMElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

// rich-text field backed by YurbaEditor. stores allowlist-sanitized html in a
// text column, stripped text on the index. security lives in fill(), not the
// browser: it re-sanitizes every POST so a forged request can't smuggle script.
class Editor extends Field
{
    // bump on yurba-editor update
    public const ASSET_VERSION = '1.0.0';

    // tags kept when sanitizing; everything else is dropped to plain text
    public const ALLOWED_TAGS = [
        'p', 'div', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
        'a', 'span', 'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'h4',
        'code', 'pre', 'img', 'iframe',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    // non-style attributes kept per tag (style handled via STYLE_PROPS)
    private const ATTRS = [
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'frameborder', 'title'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    // css properties kept in a surviving style="" attribute
    private const STYLE_PROPS = [
        'p' => ['text-align', 'margin-left'],
        'div' => ['text-align', 'margin-left'],
        'h1' => ['text-align'], 'h2' => ['text-align'], 'h3' => ['text-align'], 'h4' => ['text-align'],
        'li' => ['text-align'], 'blockquote' => ['text-align', 'margin-left'],
        'td' => ['text-align'], 'th' => ['text-align'],
        'span' => ['color', 'background-color'],
        'img' => ['width', 'height', 'float'],
        'table' => ['width'],
    ];

    // only these hosts may live in an <iframe src>
    private const EMBED_HOSTS = [
        'youtube.com', 'www.youtube.com',
        'youtube-nocookie.com', 'www.youtube-nocookie.com',
        'player.vimeo.com',
    ];

    // toolbar layout; "|" is a separator
    public array $toolbar = [
        'undo', 'redo', '|',
        'heading', '|',
        'bold', 'italic', 'underline', 'strike', '|',
        'forecolor', 'backcolor', '|',
        'alignleft', 'aligncenter', 'alignright', '|',
        'ul', 'ol', 'outdent', 'indent', '|',
        'link', 'image', 'video', 'table', 'hr', '|',
        'blockquote', 'codeblock', 'clear', 'find', '|',
        'source', 'fullscreen',
    ];

    public int $minHeight = 260;

    public bool $uploads = true;

    public int $maxChars = 0;

    public function __construct(string $name, ?string $label = null)
    {
        parent::__construct($name, $label);
        $this->onIndex = false; // rich text stays off the table by default
    }

    public function uploads(bool $enabled = true): static
    {
        $this->uploads = $enabled;

        return $this;
    }

    public function toolbar(array $buttons): static
    {
        $this->toolbar = $buttons;

        return $this;
    }

    public function minHeight(int $px): static
    {
        $this->minHeight = $px;

        return $this;
    }

    public function maxChars(int $chars): static
    {
        $this->maxChars = $chars;

        return $this;
    }

    public function indexValue(Model $model): string
    {
        return Str::limit(trim(strip_tags((string) $this->value($model))), 60);
    }

    public function fill(Request $request, Model $model): void
    {
        $model->{$this->column()} = $this->sanitize($request->input($this->name));
    }

    public function component(): string
    {
        return 'yurba::fields.editor';
    }

    public function isHtml(): bool
    {
        return true;
    }

    // allowlist-sanitize editor html: drop tags outside ALLOWED_TAGS (keep inner
    // text), strip handlers/unknown attrs, filter style="", refuse unsafe/off-host urls
    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html == '') {
            return '';
        }

        // drop these tags *with their contents* first - strip_tags keeps the inner
        // text, so a lone "<script>alert(1)" would otherwise survive. (iframe is not
        // here: valid embeds are kept and validated below.)
        $html = preg_replace('#<(script|style|object|embed|noscript|template)\b[^>]*>.*?</\1\s*>#is', '', (string) $html);

        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $html = strip_tags((string) $html, $allowed);

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        // the xml PI pins utf-8 so multibyte text survives; libxml wraps the
        // fragment in <html><body>, read back out below.
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        foreach (iterator_to_array($dom->getElementsByTagName('*')) as $el) {
            $this->cleanElement($el);
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $out = '';
        if ($body) {
            foreach ($body->childNodes as $child) {
                $out .= $dom->saveHTML($child);
            }
        }
        $out = trim($out);

        // blank unless there's real text or an embed (an empty editor leaves a
        // lone <br> or blank paragraphs that shouldn't count as content)
        $hasText = trim(strip_tags($out)) != '';
        $hasEmbed = (bool) preg_match('#<(img|iframe|hr|table)\b#i', $out);

        return $hasText || $hasEmbed ? $out : '';
    }

    private function cleanElement(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);
        $attrsAllowed = self::ATTRS[$tag] ?? [];
        $styleAllowed = self::STYLE_PROPS[$tag] ?? [];

        // snapshot the attributes before mutating - removing during live
        // iteration of DOMNamedNodeMap skips entries
        $attributes = [];
        foreach ($el->attributes as $attr) {
            $attributes[] = $attr;
        }

        foreach ($attributes as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;

            if ($name == 'style' && $styleAllowed) {
                $clean = $this->filterStyle($value, $styleAllowed);
                if ($clean == '') {
                    $el->removeAttribute($attr->name);
                } else {
                    $el->setAttribute('style', $clean);
                }

                continue;
            }

            if (! in_array($name, $attrsAllowed, true)) {
                $el->removeAttribute($attr->name);

                continue;
            }

            if (($name == 'href' && ! $this->safeUrl($value))
                || (in_array($name, ['width', 'height', 'colspan', 'rowspan', 'frameborder'], true) && ! preg_match('/^\d{1,4}$/', $value))
                || ($name == 'allow' && ! preg_match('/^[\w\s;-]+$/', $value))) {
                $el->removeAttribute($attr->name);
            }
        }

        // media with an unusable/unsafe source is removed outright
        if ($tag == 'img' && ! $this->safeUrl((string) $el->getAttribute('src'))) {
            $el->parentNode?->removeChild($el);

            return;
        }
        if ($tag == 'iframe' && ! $this->safeEmbedUrl((string) $el->getAttribute('src'))) {
            $el->parentNode?->removeChild($el);

            return;
        }

        // links: honor an explicit same-tab choice, otherwise default to a safe new
        // tab (target + rel to keep the opener from the target)
        if ($tag == 'a' && $el->hasAttribute('href')) {
            if (strtolower($el->getAttribute('target')) == '_self') {
                $el->setAttribute('target', '_self');
                $el->removeAttribute('rel');
            } else {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
    }

    private function filterStyle(string $style, array $allowedProps): string
    {
        $out = [];

        foreach (explode(';', $style) as $decl) {
            if (! str_contains($decl, ':')) {
                continue;
            }

            [$prop, $value] = explode(':', $decl, 2);
            $prop = strtolower(trim($prop));
            $value = trim($value);

            if (in_array($prop, $allowedProps, true) && $this->safeStyleValue($prop, $value)) {
                $out[] = $prop.': '.$value;
            }
        }

        return implode('; ', $out);
    }

    private function safeStyleValue(string $prop, string $value): bool
    {
        if (preg_match('#url\(|expression|/\*#i', $value)) {
            return false;
        }

        return match ($prop) {
            'text-align' => in_array($value, ['left', 'right', 'center', 'justify'], true),
            'float' => in_array($value, ['left', 'right', 'none'], true),
            'margin-left' => (bool) preg_match('/^\d{1,3}px$/', $value),
            'width', 'height' => (bool) preg_match('/^\d{1,4}(px|%)?$/', $value),
            'color', 'background-color' => (bool) preg_match(
                '/^(#[0-9a-f]{3,8}|rgba?\([\d.,\s]+\)|[a-z]{3,20})$/i',
                $value
            ),
            default => false,
        };
    }

    private function safeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url == '' || preg_match('#^\s*javascript:#i', $url)) {
            return false;
        }

        // explicit safe schemes, root-relative, fragment - plus bare relative
        // paths with no scheme. blocks data:/vbscript:/etc.
        return (bool) preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $url)
            || ! str_contains($url, ':');
    }

    private function safeEmbedUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! $parts || ($parts['scheme'] ?? '') != 'https') {
            return false;
        }

        return in_array(strtolower($parts['host'] ?? ''), self::EMBED_HOSTS, true);
    }
}
