<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class IndentFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'indentBlock' => [
                (new Field())
                    ->setName('Configuring the block indentation feature using offset and unit')
                    ->setKey('indentType')
                    ->setType(FieldType::BOOLEAN)
                    ->setClass('indent-feature-toggle')
                    ->setValue(true),
                (new Field())
                    ->setName('Indent Offset')
                    ->setKey('offset')
                    ->setType(FieldType::NUMBER)
                    ->setClass('custom-indent')
                    ->setValue(1),
                (new Field())
                    ->setName('Indent Unit')
                    ->setKey('unit')
                    ->setType(FieldType::SELECT)
                    ->setClass('custom-indent')
                    ->setValue([
                        'em' => 'em',
                        'px' => 'px',
                    ]),
                (new Field())
                    ->setName('Class List')
                    ->setKey('classes')
                    ->setType(FieldType::VALUE_LIST)
                    ->setClass('use-indent-classes d-none')
                    ->setValue(['custom-block-indent-a']),
            ],
            'outdentBlock' => [
                (new Field())
                    ->setName('Configuring the block outdentation feature using offset and unit')
                    ->setKey('outdentType')
                    ->setType(FieldType::BOOLEAN)
                    ->setClass('outdent-feature-toggle')
                    ->setValue(true),
                (new Field())
                    ->setName('Outdent Offset')
                    ->setKey('offset')
                    ->setClass('custom-outdent')
                    ->setType(FieldType::NUMBER)
                    ->setValue(1),
                (new Field())
                    ->setName('Outdent Unit')
                    ->setKey('unit')
                    ->setClass('custom-outdent')
                    ->setType(FieldType::SELECT)
                    ->setValue([
                        'em' => 'em',
                        'px' => 'px',
                    ]),
                (new Field())
                    ->setName('Class List')
                    ->setKey('classes')
                    ->setType(FieldType::VALUE_LIST)
                    ->setClass('use-outdent-classes d-none')
                    ->setValue(['custom-block-indent-a']),
            ],
        ];
    }
}
