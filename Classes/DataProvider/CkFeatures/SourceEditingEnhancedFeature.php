<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class SourceEditingEnhancedFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'sourceEditingEnhanced' => [
                (new Field())
                    ->setName('Theme')
                    ->setKey('theme')
                    ->setType(FieldType::SELECT)
                    ->setValue([
                        'Default' => 'default',
                        'Dark' => 'dark',
                    ]),
                (new Field())
                    ->setName('Allow Collaboration Features')
                    ->setKey('allowCollaborationFeatures')
                    ->setType(FieldType::BOOLEAN)
                    ->setValue(true),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-source-editing-enhanced',
                'exports' => 'SourceEditingEnhanced',
            ],
        ];
    }
}
