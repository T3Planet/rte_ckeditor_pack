<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class HtmlSupportFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'htmlSupport' => [
                (new Field())
                    ->setName('Allow')
                    ->setKey('allow')
                    ->setType(FieldType::ITERATIVE)
                    ->setValue([
                        [
                            (new Field())
                                ->setName('Name')
                                ->setKey('name')
                                ->setType(FieldType::INPUT)
                                ->setValue('')
                                ->setPlaceholder( "p"),
                            (new Field())
                                ->setName('Attributes')
                                ->setKey('attributes')
                                ->setType(FieldType::INPUT)
                                ->setValue('')
                                ->setPlaceholder( " pattern: 'data-.+'"),
                            (new Field())
                                ->setName('Classes')
                                ->setKey('classes')
                                ->setType(FieldType::INPUT)
                                ->setValue('')
                                ->setPlaceholder( "TRUE"),
                            (new Field())
                                ->setName('Styles')
                                ->setKey('styles')
                                ->setType(FieldType::INPUT)
                                ->setValue('')
                                ->setPlaceholder( "TRUE"),
                        ],
                    ]),
                (new Field())
                    ->setName('Allow Empty')
                    ->setKey('allowEmpty')
                    ->setType(FieldType::VALUE_LIST)
                    ->setValue(['span']),

            ],
        ];
    }

    public function getModules(): array
    {
        return [];
    }
}
