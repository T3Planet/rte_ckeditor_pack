<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class MentionFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'mention' => [
                (new Field())
                    ->setName('Autocomplete list limit')
                    ->setKey('dropdownLimit')
                    ->setType(FieldType::NUMBER)
                    ->setValue(1),

                (new Field())
                    ->setName('Feeds')
                    ->setKey('feeds')
                    ->setType(FieldType::ARRAY)
                    ->setValue([
                        (new Field())
                            ->setName('Default @ mention')
                            ->setType(FieldType::ARRAY)
                            ->setValue([
                                (new Field())
                                    ->setName('Annotation triggering character')
                                    ->setKey('marker')
                                    ->setType(FieldType::INPUT)
                                    ->setValue('@'),

                                (new Field())
                                    ->setName('Minimal mention character')
                                    ->setKey('minimumCharacters')
                                    ->setType(FieldType::NUMBER)
                                    ->setValue(1),

                                (new Field())
                                    ->setName('Mention List')
                                    ->setKey('feed')
                                    ->setType(FieldType::ARRAY)
                                    ->setValue([
                                        '@Barney',
                                        '@Lily',
                                        '@Marry Ann',
                                        '@Marshall',
                                        '@Robin',
                                        '@Ted',
                                    ]),
                            ]),
                    ]),
            ],
        ];
    }

}
