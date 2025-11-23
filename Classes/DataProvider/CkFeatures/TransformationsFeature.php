<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class TransformationsFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'typing' => [
                (new Field())
                    ->setName('Transformations')
                    ->setKey('transformations')
                    ->setType(FieldType::ARRAY)
                    ->setValue(
                        [
                            (new Field())
                                ->setName('')
                                ->setKey('extra')
                                ->setType(FieldType::ITERATIVE)
                                ->setValue(
                                    [
                                        [
                                            (new Field())
                                                ->setName('From')
                                                ->setKey('from')
                                                ->setType(FieldType::INPUT)
                                                ->setValue(':)'),

                                            (new Field())
                                                ->setName('To')
                                                ->setKey('to')
                                                ->setType(FieldType::INPUT)
                                                ->setValue('🙂'),

                                        ],
                                    ]
                                ),
                        ]
                    ),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-typing',
                'exports' => 'TextTransformation',
            ],
        ];
    }
}
