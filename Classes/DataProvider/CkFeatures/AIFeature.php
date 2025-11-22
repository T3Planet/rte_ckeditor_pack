<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AIFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'ai' => [
                (new Field())
                    ->setName('openAI')
                    ->setKey('openAI')
                    ->setType(FieldType::ARRAY)  // Array type to indicate nested fields
                    ->setValue([  // Nested fields as the value
                        (new Field())
                            ->setName('Api Url')
                            ->setKey('apiUrl')
                            ->setType(FieldType::INPUT)
                            ->setValue(''),
                        (new Field())
                            ->setName('requestHeaders')
                            ->setKey('requestHeaders')
                            ->setType(FieldType::ARRAY)
                            ->setValue([
                                (new Field())
                                    ->setName('Authorization')
                                    ->setKey('Authorization')
                                    ->setType(FieldType::INPUT)
                                    ->setValue('')
                                    ->setPlaceholder($this->translateLabel('field.placeholder.authorization')),
                            ]),
                    ]),
            ],
        ];
    }

    private function translateLabel(string $key): string
    {
        return LocalizationUtility::translate($key, 'RteCkeditorPack') ?? '';
    }
}
