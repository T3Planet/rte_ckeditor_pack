<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class LanguageFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'language' => [
                (new Field())
                    ->setName('Language')
                    ->setKey('textPartLanguage')
                    ->setType(FieldType::ITERATIVE)
                    ->setValue([
                        [
                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('English'),

                            (new Field())
                                ->setName('Language Code')
                                ->setKey('languageCode')
                                ->setType(FieldType::INPUT)
                                ->setValue('en'),
                        ],
                        [
                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('German'),

                            (new Field())
                                ->setName('Language Code')
                                ->setKey('languageCode')
                                ->setType(FieldType::INPUT)
                                ->setValue('de'),
                        ],
                        [
                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('French'),

                            (new Field())
                                ->setName('Language Code')
                                ->setKey('languageCode')
                                ->setType(FieldType::INPUT)
                                ->setValue('fr'),
                        ],
                    ]),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-language',
                'exports' => 'TextPartLanguage',
            ],
        ];
    }
}
