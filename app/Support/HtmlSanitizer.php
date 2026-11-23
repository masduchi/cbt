<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
        'ol', 'ul', 'li', 'blockquote', 'code', 'pre', 'span', 'div',
        'img', 'a', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'h1', 'h2',
        'h3', 'h4', 'h5', 'h6',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'img' => ['src', 'alt', 'width', 'height'],
        'a' => ['href', 'title', 'target', 'rel'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    private const GLOBAL_ATTRIBUTES = ['class'];

    public static function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        if (! preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $html)) {
            return nl2br(e($html), false);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8"?><div id="cbt-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('cbt-root');

        if (! $root) {
            return '';
        }

        self::walk($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function walk(DOMNode $node): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'select', 'button'], true)) {
                    $node->removeChild($child);

                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    self::walk($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }

                self::cleanAttributes($child, $tag);
                self::walk($child);

                continue;
            }

            self::walk($child);
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(
            self::GLOBAL_ATTRIBUTES,
            self::ALLOWED_ATTRIBUTES[$tag] ?? []
        );

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                if (! self::safeUrl($attribute->value)) {
                    $element->removeAttribute($attribute->name);

                    continue;
                }

                $element->setAttribute($name, self::secureMediaUrl($attribute->value));
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * Gambar soal lama tersimpan sebagai /storage/soal/...; arahkan ke rute
     * terautentikasi agar berkas tidak bisa diambil tanpa login.
     */
    private static function secureMediaUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/soal/')) {
            return $url;
        }

        return url('/media/'.substr($path, strlen('/storage/')));
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(strtolower($url));

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, 'data:image/')) {
            return true;
        }

        if (str_starts_with($url, 'javascript:') || str_starts_with($url, 'vbscript:')) {
            return false;
        }

        return ! preg_match('/^\s*(javascript|vbscript|data):/i', $url);
    }
}
