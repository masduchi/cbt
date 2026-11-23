<?php

namespace App\Support;

class SoalText
{
    public static function hash(?string $html): string
    {
        return sha1(self::normalize($html));
    }

    public static function normalize(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower((string) preg_replace('/\s+/u', ' ', $text));

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $text);
    }

    public static function plain(?string $html): string
    {
        $text = str_ireplace(
            ['<br>', '<br/>', '<br />', '</p>', '</li>', '</div>'],
            "\n",
            (string) $html,
        );
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);

        return trim($text);
    }
}
