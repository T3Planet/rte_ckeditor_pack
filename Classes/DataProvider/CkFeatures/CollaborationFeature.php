<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class CollaborationFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'allow' => [
                (new Field())
                    ->setName('Presence list')
                    ->setKey('presenceList')
                    ->setType(FieldType::BOOLEAN)
                    ->setValue(false),
            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-collaboration-core',
                'exports' => 'Users',
            ],
            [
                'library' => '@t3planet/RteCkeditorPack/users-collaboration-guard.js',
            ],
            [
                'library' => '@t3planet/RteCkeditorPack/realtime-adapter.js',
            ],
            [
                'library' => '@ckeditor/ckeditor5-cloud-services',
                'exports' => 'CloudServices',
            ],
            [
                'library' => '@ckeditor/ckeditor5-real-time-collaboration',
                'exports' => 'RealTimeCollaborativeEditing,PresenceList',
            ],
        ];
    }
}
