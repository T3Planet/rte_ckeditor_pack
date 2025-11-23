<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class CodeBlockFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'codeBlock' => [
                (new Field())
                    ->setName('Languages')
                    ->setKey('languages')
                    ->setType(FieldType::ITERATIVE)
                    ->setValue(
                        [
                            [
                                (new Field())
                                    ->setName('Language')
                                    ->setKey('language')
                                    ->setType(FieldType::INPUT)
                                    ->setValue('javascript'),

                                (new Field())
                                    ->setName('Label')
                                    ->setKey('label')
                                    ->setType(FieldType::INPUT)
                                    ->setValue('JavaScript'),

                                (new Field())
                                    ->setName('Class')
                                    ->setKey('class')
                                    ->setType(FieldType::INPUT)
                                    ->setValue('js javascript js-code'),

                            ],
                        ]
                    ),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-code-block',
                'exports' => 'CodeBlock',
            ],
        ];
    }
}
