<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Service;

/**
 * Sync modes for YAML ↔ DB preset synchronization.
 */
enum SyncMode: string
{
    /**
     * Keep DB toolbar order; append missing YAML items; merge feature configs (UI Sync).
     */
    case Additive = 'additive';

    /**
     * Keep DB-only items and insert missing YAML items near their YAML neighbours.
     */
    case Ordered = 'ordered';

    /**
     * YAML is authoritative: toolbar and feature configs are taken from YAML verbatim.
     */
    case Strict = 'strict';

    /**
     * Clear DB toolbar overrides and remove feature rows (UI Reset).
     */
    case Reset = 'reset';

    public static function fromInput(string $value): self
    {
        $normalized = strtolower(trim($value));
        return match ($normalized) {
            'additive', 'sync' => self::Additive,
            'ordered', 'position-aware' => self::Ordered,
            'strict', 'yaml' => self::Strict,
            'reset' => self::Reset,
            default => throw new \InvalidArgumentException(
                sprintf('Unknown sync mode "%s". Use additive, ordered, strict, or reset.', $value),
                1753770001
            ),
        };
    }
}
