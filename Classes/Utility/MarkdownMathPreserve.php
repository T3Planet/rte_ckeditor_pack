<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

/**
 * Markdown's data processor turns Wiris formula images into Markdown image syntax.
 * Load a pack plugin that keeps formula markup when both features are enabled.
 */
final class MarkdownMathPreserve
{
    public const MODULE = '@t3planet/RteCkeditorPack/preserve-math-markdown';

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function apply(array $configuration): array
    {
        $imports = $configuration['importModules'] ?? null;
        if (!is_array($imports)) {
            return $configuration;
        }

        $hasMarkdown = false;
        $hasMath = false;
        foreach ($imports as $import) {
            $module = self::moduleName($import);
            if (str_contains($module, 'ckeditor5-markdown-gfm')) {
                $hasMarkdown = true;
            }
            if (str_contains($module, 'mathtype')) {
                $hasMath = true;
            }
            if ($module === self::MODULE) {
                return $configuration;
            }
        }

        if (!$hasMarkdown || !$hasMath) {
            return $configuration;
        }

        $configuration['importModules'][] = [
            'module' => self::MODULE,
            'exports' => ['PreserveMathInMarkdown'],
        ];

        return $configuration;
    }

    private static function moduleName(mixed $import): string
    {
        if (is_string($import)) {
            return $import;
        }
        if (!is_array($import)) {
            return '';
        }

        return (string)($import['module'] ?? $import['library'] ?? '');
    }
}
