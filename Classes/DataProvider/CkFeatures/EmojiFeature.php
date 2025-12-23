<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class EmojiFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'emoji' => [
                (new Field())
                    ->setName('Dropdown Limit')
                    ->setKey('dropdownLimit')
                    ->setType(FieldType::NUMBER)
                    ->setValue(5),

                (new Field())
                    ->setName('Skin Tone')
                    ->setKey('skinTone')
                    ->setType(FieldType::SELECT)
                    ->setValue([
                        'Light' => 'light',
                        'Medium-Light' => 'medium-light',
                        'Medium' => 'medium',
                        'Medium-Dark' => 'medium-dark',
                        'Dark' => 'dark',
                    ]),

                (new Field())
                    ->setName('Definitions URL')
                    ->setKey('definitionsUrl')
                    ->setType(FieldType::INPUT)
                    ->setValue('')
                    ->setPlaceholder('https://example.com/emoji-definitions.json'),

                (new Field())
                    ->setName('Version')
                    ->setKey('version')
                    ->setType(FieldType::NUMBER)
                    ->setValue(15),

                (new Field())
                    ->setName('Use Custom Font')
                    ->setKey('useCustomFont')
                    ->setType(FieldType::BOOLEAN)
                    ->setValue(true),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-mention',
                'exports' => 'Mention',
            ],
            [
                'library' => '@ckeditor/ckeditor5-emoji',
                'exports' => 'Emoji',
            ],
        ];
    }
}

