<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class WordCountFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'wordCount' => [
                (new Field())
                    ->setName('Display Characters')
                    ->setKey('displayCharacters')
                    ->setType(FieldType::BOOLEAN)
                    ->setValue(true),
                (new Field())
                    ->setName('Display Words')
                    ->setKey('displayWords')
                    ->setType(FieldType::BOOLEAN)
                    ->setValue(true),

            ],
        ];
    }
}
