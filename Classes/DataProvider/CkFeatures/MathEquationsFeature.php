<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class MathEquationsFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'mathTypeParameters' => [
                (new Field())
                    ->setName('Editor Language')
                    ->setKey('editorParameters')
                    ->setType(FieldType::ARRAY)
                    ->setValue([
                        (new Field())
                            ->setName('Language')
                            ->setKey('language')
                            ->setType(FieldType::INPUT)
                            ->setValue('en'),
                        (new Field())
                            ->setName('Save Mode (image or xml)')
                            ->setKey('wiriseditorsavemode')
                            ->setType(FieldType::INPUT)
                            ->setValue('image'),
                    ]),
                (new Field())
                    ->setName('Service Provider')
                    ->setKey('serviceProviderProperties')
                    ->setType(FieldType::ARRAY)
                    ->setValue([
                        (new Field())
                            ->setName('URI')
                            ->setKey('URI')
                            ->setType(FieldType::INPUT)
                            ->setValue('https://www.wiris.net/demo/plugins/app'),
                        (new Field())
                            ->setName('Server')
                            ->setKey('server')
                            ->setType(FieldType::INPUT)
                            ->setValue('https://www.wiris.net'),
                    ]),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@t3planet/RteCkeditorPack/mathtype.js',
                'exports' => 'MathType',
            ],
        ];
    }
}
