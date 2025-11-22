<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class FootnotesFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'footnotes' => [
                (new Field())
                    ->setName('multiBlock')
                    ->setKey('multiBlock')
                    ->setType(FieldType::BOOLEAN)
                    ->setValue(true),
                (new Field())
                    ->setName('Footnotes Properties')
                    ->setKey('footnotesProperties')
                    ->setType(FieldType::ARRAY)
                    ->setValue([
                        (new Field())
                            ->setName('Default list style')
                            ->setKey('defaultListStyle')
                            ->setType(FieldType::SELECT)
                            ->setValue([
                                'Decimal' => 'decimal',
                                'Decimal-Leading-Zero' => 'decimal-leading-zero',
                                'Lower-Roman' => 'lower-roman',
                                'Upper-Roman' => 'upper-roman',
                                'Lower-Latin' => 'lower-latin',
                                'Upper-Latin' => 'upper-latin',
                            ]),
                        (new Field())
                            ->setName('Default start index')
                            ->setKey('defaultStartIndex')
                            ->setType(FieldType::NUMBER)
                            ->setValue(1),
                    ]),
            ],
        ];
    }

}
