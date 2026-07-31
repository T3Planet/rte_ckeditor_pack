<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\EventListener;

use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use T3Planet\RteCkeditorPack\Service\PresetSyncService;
use T3Planet\RteCkeditorPack\Service\SyncMode;

/**
 * Syncs preset features before export (additive merge).
 */
class SyncFeaturesBeforeExportListener
{
    public function __construct(
        protected readonly PresetSyncService $presetSyncService,
    ) {}

    public function syncPresetFeatures(Preset $preset): void
    {
        // SyncResult never throws; failures must not block export.
        $this->presetSyncService->syncPreset((int)$preset->getUid(), SyncMode::Additive);
    }
}
