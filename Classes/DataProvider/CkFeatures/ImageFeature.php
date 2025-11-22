<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class ImageFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'image' => [
                (new Field())
                    ->setName('Toolbar items')
                    ->setKey('toolbar')
                    ->setType(FieldType::SELECT)
                    ->setMultiple(true)
                    ->setValue([
                        'Image captions' => 'toggleImageCaption',
                        'Inline Image' => 'imageStyle:inline',
                        'Side Image' => 'imageStyle:side',
                        'Wrap Text Image' => 'imageStyle:wrapText',
                        'Break Text Image' => 'imageStyle:breakText',
                    ]),
                (new Field())
                    ->setName('Toolbar items')
                    ->setKey('exports')
                    ->setType(FieldType::ARRAY)
                    ->setValue([
                        (new Field())
                            ->setName('Resizing images')
                            ->setKey('ImageResize')
                            ->setType(FieldType::BOOLEAN)
                            ->setValue(true),
                    ]),
            ],
        ];
    }
}
