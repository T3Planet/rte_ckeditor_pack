<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

/**
 * Frontend helper: normalize MathML so browsers/MathJax can render formulas.
 */
final class MathMlFrontendRenderer
{
    /**
     * TypoScript userFunc entry point.
     *
     * @param string $content
     * @param array<string, mixed> $conf
     */
    public function process(string $content, array $conf = []): string
    {
        if ($content === '' || !str_contains($content, 'math')) {
            return $content;
        }

        // Restore MathML that was HTML-escaped into visible text.
        $content = preg_replace_callback(
            '/&lt;math\b[\s\S]*?&lt;\/math&gt;/i',
            static function (array $matches): string {
                return html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            },
            $content
        ) ?? $content;

        // Ensure xmlns so MathJax/browsers treat the node as MathML.
        $content = preg_replace_callback(
            '/<math\b([^>]*)>/i',
            static function (array $matches): string {
                $attrs = $matches[1] ?? '';
                if (stripos($attrs, 'xmlns=') === false) {
                    $attrs .= ' xmlns="http://www.w3.org/1998/Math/MathML"';
                }
                if (stripos($attrs, 'display=') === false) {
                    $attrs .= ' display="inline"';
                }
                return '<math' . $attrs . '>';
            },
            $content
        ) ?? $content;

        return $content;
    }
}
