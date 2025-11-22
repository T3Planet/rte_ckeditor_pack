<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class AlignmentFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'alignment' => [
                (new Field())
                    ->setName('Options')
                    ->setKey('options')
                    ->setType(FieldType::ITERATIVE)
                    ->setValue([
                        [
                            (new Field())
                                ->setName('Name')
                                ->setKey('name')
                                ->setType(FieldType::INPUT)
                                ->setValue('left'),

                            (new Field())
                                ->setName('Class Name')
                                ->setKey('className')
                                ->setType(FieldType::INPUT)
                                ->setValue('text-start'),
                        ],
                        [
                            (new Field())
                                ->setName('Name')
                                ->setKey('name')
                                ->setType(FieldType::INPUT)
                                ->setValue('center'),

                            (new Field())
                                ->setName('Class Name')
                                ->setKey('className')
                                ->setType(FieldType::INPUT)
                                ->setValue('text-center'),
                        ],
                        [
                            (new Field())
                                ->setName('Name')
                                ->setKey('name')
                                ->setType(FieldType::INPUT)
                                ->setValue('right'),

                            (new Field())
                                ->setName('Class Name')
                                ->setKey('className')
                                ->setType(FieldType::INPUT)
                                ->setValue('text-end'),
                        ],
                        [
                            (new Field())
                                ->setName('Name')
                                ->setKey('name')
                                ->setType(FieldType::INPUT)
                                ->setValue('justify'),

                            (new Field())
                                ->setName('Class Name')
                                ->setKey('className')
                                ->setType(FieldType::INPUT)
                                ->setValue('text-justify'),
                        ],
                    ]),
            ],
        ];
    }
}
