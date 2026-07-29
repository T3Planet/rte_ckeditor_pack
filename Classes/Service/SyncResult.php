<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Service;

/**
 * Result of a single preset sync/reset operation.
 */
final class SyncResult
{
    /**
     * @param list<array{title: string, message?: string, severity: int}> $notifications
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $presetKey = '',
        public readonly ?int $presetUid = null,
        public readonly string $message = '',
        public readonly array $notifications = [],
        public readonly SyncMode $mode = SyncMode::Additive,
        public readonly bool $skipped = false,
    ) {}

    /**
     * @param list<array{title: string, message?: string, severity: int}> $notifications
     */
    public static function success(
        string $presetKey,
        int $presetUid,
        SyncMode $mode,
        string $message = '',
        array $notifications = []
    ): self {
        return new self(true, $presetKey, $presetUid, $message, $notifications, $mode);
    }

    /**
     * @param list<array{title: string, message?: string, severity: int}> $notifications
     */
    public static function failure(
        string $message,
        SyncMode $mode = SyncMode::Additive,
        string $presetKey = '',
        ?int $presetUid = null,
        array $notifications = []
    ): self {
        return new self(false, $presetKey, $presetUid, $message, $notifications, $mode);
    }

    public static function skipped(
        string $presetKey,
        int $presetUid,
        SyncMode $mode,
        string $message,
        array $notifications = []
    ): self {
        return new self(true, $presetKey, $presetUid, $message, $notifications, $mode, true);
    }
}
