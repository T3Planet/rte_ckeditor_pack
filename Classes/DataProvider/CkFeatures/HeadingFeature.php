<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class HeadingFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'heading' => [
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
                                ->setValue('heading1'),

                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('Heading 1'),

                            (new Field())
                                ->setName('View')
                                ->setKey('view')
                                ->setType(FieldType::INPUT)
                                ->setValue('h1'),

                        ],
                        [
                            (new Field())
                                ->setName('Model')
                                ->setKey('model')
                                ->setType(FieldType::INPUT)
                                ->setValue('heading2'),

                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('Heading 2'),

                            (new Field())
                                ->setName('View')
                                ->setKey('view')
                                ->setType(FieldType::INPUT)
                                ->setValue('h2'),

                        ],
                        [
                            (new Field())
                                ->setName('Model')
                                ->setKey('model')
                                ->setType(FieldType::INPUT)
                                ->setValue('heading3'),

                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('Heading 3'),

                            (new Field())
                                ->setName('View')
                                ->setKey('view')
                                ->setType(FieldType::INPUT)
                                ->setValue('h3'),

                        ],
                        [
                            (new Field())
                                ->setName('Model')
                                ->setKey('model')
                                ->setType(FieldType::INPUT)
                                ->setValue('heading4'),

                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('Heading 4'),

                            (new Field())
                                ->setName('View')
                                ->setKey('view')
                                ->setType(FieldType::INPUT)
                                ->setValue('h4'),

                        ],
                        [
                            (new Field())
                                ->setName('Model')
                                ->setKey('model')
                                ->setType(FieldType::INPUT)
                                ->setValue('heading5'),

                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('Heading 5'),

                            (new Field())
                                ->setName('View')
                                ->setKey('view')
                                ->setType(FieldType::INPUT)
                                ->setValue('h5'),

                        ],
                        [
                            (new Field())
                                ->setName('Model')
                                ->setKey('model')
                                ->setType(FieldType::INPUT)
                                ->setValue('heading6'),

                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('Heading 6'),

                            (new Field())
                                ->setName('View')
                                ->setKey('view')
                                ->setType(FieldType::INPUT)
                                ->setValue('h6'),

                        ],
                    ]),
            ],
        ];
    }

    public function getModules(): array
    {
        return [];
    }
}
