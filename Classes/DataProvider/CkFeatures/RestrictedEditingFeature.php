<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

/**
 * Restricted editing (CKEditor premium RED) — one Pack feature; Mode selects the plugin.
 *
 * Standard: mark editable regions. Restricted: edit only those regions.
 * Modes cannot load together — RteConfigurationModifier swaps StandardEditingMode / RestrictedEditingMode.
 *
 * @see https://ckeditor.com/docs/ckeditor5/latest/features/restricted-editing.html
 */
class RestrictedEditingFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'restrictedEditing' => [
                (new Field())
                    ->setName('Mode')
                    ->setKey('mode')
                    ->setType(FieldType::SELECT)
                    ->setValue([
                        'Standard' => 'standard',
                        'Restricted' => 'restricted',
                    ])
                    ->setNote('Standard: mark editable regions. Restricted: lock content outside those regions. Use a separate RTE preset per mode.'),
            ],
        ];
    }

    public function getModules(): array
    {
        // Default; RteConfigurationModifier swaps exports to RestrictedEditingMode when mode=restricted.
        return [
            [
                'library' => '@ckeditor/ckeditor5-restricted-editing',
                'exports' => 'StandardEditingMode',
            ],
        ];
    }
}
