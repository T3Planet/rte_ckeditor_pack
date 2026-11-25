<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

/**
 * WProofreader's init() throws when srcUrl is missing and aborts ClassicEditor.create.
 * Drop the plugin in that case and flag the editor so the pack notification can explain it.
 */
final class WProofreaderStartupGuard
{
    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public static function apply(array $configuration): array
    {
        $imports = $configuration['importModules'] ?? null;
        if (!is_array($imports) || !self::loadsWProofreader($imports)) {
            return $configuration;
        }
        if (self::hasSourceUrl($configuration['wproofreader'] ?? null)) {
            return $configuration;
        }

        $configuration['importModules'] = self::withoutWProofreader($imports);
        unset($configuration['wproofreader']);
        $configuration['packWproofreaderUnavailable'] = true;

        return $configuration;
    }

    /**
     * @param array<int, mixed> $imports
     */
    private static function loadsWProofreader(array $imports): bool
    {
        foreach ($imports as $import) {
            if (self::importExportsWProofreader($import)) {
                return true;
            }
        }

        return false;
    }

    private static function hasSourceUrl(mixed $options): bool
    {
        if (!is_array($options)) {
            return false;
        }

        return trim((string)($options['srcUrl'] ?? '')) !== '';
    }

    /**
     * @param array<int, mixed> $imports
     * @return list<mixed>
     */
    private static function withoutWProofreader(array $imports): array
    {
        $filtered = [];
        foreach ($imports as $import) {
            if (!self::importExportsWProofreader($import)) {
                $filtered[] = $import;
                continue;
            }
            if (!is_array($import)) {
                continue;
            }
            $exports = array_values(array_filter(
                (array)($import['exports'] ?? []),
                static fn (mixed $export): bool => $export !== 'WProofreader'
            ));
            if ($exports === []) {
                continue;
            }
            $import['exports'] = $exports;
            $filtered[] = $import;
        }

        return $filtered;
    }

    private static function importExportsWProofreader(mixed $import): bool
    {
        if (is_string($import)) {
            return str_contains($import, 'spell-check');
        }
        if (!is_array($import)) {
            return false;
        }
        $module = (string)($import['module'] ?? $import['library'] ?? '');
        $exports = (array)($import['exports'] ?? []);
        if (in_array('WProofreader', $exports, true)) {
            return true;
        }

        return str_contains($module, 'spell-check') && ($exports === [] || $exports === ['default']);
    }
}
