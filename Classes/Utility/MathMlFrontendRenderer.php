<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

/**
 * Frontend helper: normalize MathML so browsers/MathJax can render formulas.
 */
final class MathMlFrontendRenderer
{
    private const WIRIS_RENDER_SVG = 'https://www.wiris.net/demo/editor/render.svg';

    /**
     * TypoScript userFunc entry point.
     *
     * @param string $content
     * @param array<string, mixed> $conf
     */
    public function process(string $content, array $conf = []): string
    {
        if ($content === '' || !str_contains(strtolower($content), 'math')) {
            return $content;
        }

        $content = $this->normalizeMathMarkup($content);

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

    public function prepareForBackendPreview(string $content): string
    {
        if ($content === '' || !$this->containsMathMarkup($content)) {
            return $content;
        }

        $content = $this->normalizeMathMarkup($content);

        return preg_replace_callback(
            '/<math\b[^>]*>.*?<\/math>/is',
            function (array $matches): string {
                $mathml = $this->stripWirisEditorAnnotations($matches[0]);
                $isChemistry = (bool)preg_match('/\bclass\s*=\s*["\'][^"\']*wrs_chemistry/i', $matches[0])
                    || str_contains($mathml, 'wrs_chemistry');
                $src = self::WIRIS_RENDER_SVG . '?' . http_build_query([
                    'mml' => $mathml,
                ], '', '&', PHP_QUERY_RFC3986);
                $modifier = $isChemistry ? 'chem' : 'math';

                return sprintf(
                    '<span class="rte-ckeditor-pack-formula rte-ckeditor-pack-formula--%s">'
                    . '<img class="Wirisformula" src="%s" alt="%s" role="math" loading="lazy" decoding="async" />'
                    . '</span>',
                    $modifier,
                    htmlspecialchars($src, ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($this->buildAccessibleAlt($mathml), ENT_QUOTES | ENT_HTML5)
                );
            },
            $content
        ) ?? $content;
    }

    public function containsMathMarkup(string $content): bool
    {
        return (bool)preg_match('/<math\b|&lt;math\b|«math\b/iu', $content);
    }

    public function normalizeMathMarkup(string $content): string
    {
        // Restore MathML that was HTML-escaped into visible text.
        $content = preg_replace_callback(
            '/&lt;math\b[\s\S]*?&lt;\/math&gt;/i',
            static function (array $matches): string {
                return html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            },
            $content
        ) ?? $content;

        if (str_contains($content, '«math') || str_contains($content, '«MATH')) {
            $content = preg_replace_callback(
                '/«math\b[^»]*».*?«\/math»/isu',
                static function (array $matches): string {
                    return str_replace(
                        ['«', '»', '¨', '§', '`'],
                        ['<', '>', '"', '&', "'"],
                        $matches[0]
                    );
                },
                $content
            ) ?? $content;
        }

        return $content;
    }

    public function stripWirisEditorAnnotations(string $mathml): string
    {
        $mathml = preg_replace(
            '/<annotation\b[^>]*encoding=["\']application\/vnd\.wiris\.mtweb-params\+json["\'][^>]*>.*?<\/annotation>/is',
            '',
            $mathml
        ) ?? $mathml;

        $mathml = preg_replace(
            '/<semantics>\s*(.*?)\s*<\/semantics>/is',
            '$1',
            $mathml
        ) ?? $mathml;

        return trim($mathml);
    }

    private function buildAccessibleAlt(string $mathml): string
    {
        $text = strip_tags($mathml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text !== '' ? $text : 'Formula';
    }
}
