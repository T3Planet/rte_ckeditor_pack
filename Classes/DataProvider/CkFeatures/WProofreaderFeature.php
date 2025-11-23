<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class WProofreaderFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'wproofreader' => [
                (new Field())
                    ->setName('Auto Search')
                    ->setKey('autoSearch')
                    ->setType(FieldType::BOOLEAN)
                    ->setValue(true),

                (new Field())
                    ->setName('Enable Grammar')
                    ->setKey('enableGrammar')
                    ->setType(FieldType::BOOLEAN)
                    ->setValue(true),

                (new Field())
                    ->setName('ServiceId')
                    ->setKey('serviceId')
                    ->setType(FieldType::INPUT)
                    ->setValue(''),

                (new Field())
                    ->setName('Language')
                    ->setKey('lang')
                    ->setType(FieldType::INPUT)
                    ->setValue('auto'),

                (new Field())
                    ->setName('Source Url')
                    ->setKey('srcUrl')
                    ->setType(FieldType::INPUT)
                    ->setValue(''),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@t3planet/RteCkeditorPack/spell-check',
                'exports' => 'WProofreader',
            ],
        ];
    }
}
