<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class CaseChangeFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'caseChange' => [
                (new Field())
                    ->setName('Title Case')
                    ->setKey('titleCase')
                    ->setType(FieldType::ARRAY)
                    ->setValue([
                        (new Field())
                            ->setName('Exclude Words (Only for Title Case)')
                            ->setKey('excludeWords')
                            ->setType(FieldType::VALUE_LIST)
                            ->setValue(['a', 'an', 'and', 'as', 'at', 'but', 'by', 'en', 'for', 'if', 'in', 'nor', 'of', 'on', 'or', 'per', 'the', 'to', 'vs', 'vs.', 'via']),

                    ]),
            ],
        ];
    }
}
