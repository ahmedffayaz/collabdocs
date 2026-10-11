<?php

namespace App\Support;

class DocumentContent
{
    private const ALLOWED_TAGS = '<p><div><h1><h2><h3><strong><b><em><i><u><s><del><ul><ol><li><blockquote><br><hr><pre><code>';

    public static function sanitize(string $html): string
    {
        $html = preg_replace_callback(
            '/<(\/?)h[4-6]\b[^>]*>/i',
            fn (array $match) => $match[1] === '/' ? '</h3>' : '<h3>',
            $html,
        ) ?? $html;

        $html = preg_replace_callback('/<tr\b[^>]*>(.*?)<\/tr>/is', function (array $match): string {
            preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $match[1], $cells);
            if (empty($cells[1])) return $match[0];

            $textCells = array_map(function (string $cell): string {
                $text = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
                return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }, $cells[1]);

            return '<p>'.implode(' | ', $textCells).'</p>';
        }, $html) ?? $html;

        $html = strip_tags($html, self::ALLOWED_TAGS);

        return preg_replace_callback(
            '/<([a-z][a-z0-9]*)(?:\s[^>]*)?>/i',
            fn (array $match) => '<'.strtolower($match[1]).'>',
            $html,
        ) ?? '';
    }
}
