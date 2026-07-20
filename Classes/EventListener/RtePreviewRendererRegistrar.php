<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\EventListener;

use T3Planet\RteCkeditorPack\Backend\Preview\RteImagePreviewRenderer;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;

/**
 * Registers RteImagePreviewRenderer for all tt_content types with RTE-enabled bodytext.
 * Ensures backend page module previews strip CKEditor collaboration markup.
 */
final readonly class RtePreviewRendererRegistrar
{
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $tca = $event->getTca();
        $tableName = 'tt_content';

        if (!isset($tca[$tableName]['types']) || !is_array($tca[$tableName]['types'])) {
            return;
        }

        $tableConfig = $tca[$tableName];
        if (!is_array($tableConfig)) {
            return;
        }

        foreach ($tca[$tableName]['types'] as &$typeConfig) {
            if (!is_array($typeConfig) || !$this->hasRteEnabledBodytext($typeConfig, $tableConfig)) {
                continue;
            }

            if (!$this->shouldRegisterPreviewRenderer($typeConfig['previewRenderer'] ?? null)) {
                continue;
            }

            $typeConfig['previewRenderer'] = RteImagePreviewRenderer::class;
        }
        unset($typeConfig);

        $event->setTca($tca);
    }

    private function shouldRegisterPreviewRenderer(mixed $previewRenderer): bool
    {
        if (!is_string($previewRenderer) || $previewRenderer === '') {
            return true;
        }

        return $previewRenderer === StandardContentPreviewRenderer::class;
    }

    /**
     * @param array<mixed> $typeConfig
     * @param array<mixed> $tableConfig
     */
    private function hasRteEnabledBodytext(array $typeConfig, array $tableConfig): bool
    {
        $columnsOverrides = $typeConfig['columnsOverrides'] ?? null;
        if (is_array($columnsOverrides)) {
            $bodytextOverride = $columnsOverrides['bodytext'] ?? null;
            if (is_array($bodytextOverride)) {
                $config = $bodytextOverride['config'] ?? null;
                if (is_array($config) && isset($config['enableRichtext'])) {
                    return (bool)$config['enableRichtext'];
                }
            }
        }

        $columns = $tableConfig['columns'] ?? null;
        if (!is_array($columns)) {
            return false;
        }

        $bodytext = $columns['bodytext'] ?? null;
        if (!is_array($bodytext)) {
            return false;
        }

        $config = $bodytext['config'] ?? null;
        if (!is_array($config)) {
            return false;
        }

        return (bool)($config['enableRichtext'] ?? false);
    }
}
