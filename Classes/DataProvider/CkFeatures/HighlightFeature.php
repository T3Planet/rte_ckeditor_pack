<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class HighlightFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'highlight' => [
                (new Field())
                    ->setName('Options')
                    ->setKey('options')
                    ->setType(FieldType::ITERATIVE)
                    ->setValue([
                        [
                            (new Field())
                                ->setName('Model')
                                ->setKey('model')
                                ->setType(FieldType::INPUT)
                                ->setValue('yellowMarker'),

                            (new Field())
                                ->setName('Class Name')
                                ->setKey('class')
                                ->setType(FieldType::INPUT)
                                ->setValue('marker-yellow'),

                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('Yellow marker'),

                            (new Field())
                                ->setName('Marker Type')
                                ->setKey('type')
                                ->setType(FieldType::SELECT)
                                ->setValue([
                                    'Marker' => 'marker',
                                    'Pen' => 'pen',
                                ]),

                            (new Field())
                                ->setName('Color')
                                ->setKey('color')
                                ->setType(FieldType::INPUT)
                                ->setValue('var(--ck-highlight-marker-yellow)'),

                        ],
                    ]),
            ],
        ];
    }
}
