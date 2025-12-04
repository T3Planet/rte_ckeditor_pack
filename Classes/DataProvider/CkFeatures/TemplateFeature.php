<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\CkFeatures;

use T3Planet\RteCkeditorPack\DataProvider\Configuration\Field;
use T3Planet\RteCkeditorPack\DataProvider\Configuration\FieldType;

class TemplateFeature implements FeatureInterface
{
    public function getConfiguration(): array
    {
        return [
            'template' => [
                (new Field())
                    ->setName('Definitions')
                    ->setKey('definitions')
                    ->setType(FieldType::ITERATIVE)
                    ->setValue([
                        [
                            (new Field())
                                ->setName('Title')
                                ->setKey('title')
                                ->setType(FieldType::INPUT)
                                ->setValue('Pricing table'),

                            (new Field())
                                ->setName('Data(HTML)')
                                ->setKey('data')
                                ->setType(FieldType::TEXTAREA)
                                ->setValue('<table class=table><thead><tr><th scope=col>#<th scope=col>First<th scope=col>Last<th scope=col>Handle<tbody><tr><th scope=row>1<td>Mark<td>Otto<td>@mdo<tr><th scope=row>2<td>Jacob<td>Thornton<td>@fat<tr><th scope=row>3<td colspan=2>Larry the Bird<td>@twitter</table>'),

                            (new Field())
                                ->setName('Icon')
                                ->setKey('icon')
                                ->setType(FieldType::TEXTAREA)
                                ->setValue('<svg viewBox="0 0 45 45" fill="none" xmlns="http://www.w3.org/2000/svg"><mask id="a" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="2" y="2" width="41" height="41"><rect x="2" y="2" width="41" height="41" rx="5" fill="#59A5FF"/></mask><g mask="url(#a)"><rect x="2" y="2" width="41" height="41" rx="5" fill="#444"/><path fill="#ECECEC" d="M4 17h11v11H4z"/><path fill="#A9E6FA" d="M17 17h11v11H17z"/><path fill="#ECECEC" d="M30 17h11v11H30z"/><path d="M4 7a3 3 0 0 1 3-3h31a3 3 0 0 1 3 3v8H4V7Z" fill="#FF1A88"/><path d="M4 30h11v11H7a3 3 0 0 1-3-3v-8ZM17 30h11v11H17z" fill="#A9E6FA"/><path d="M30 30h11v8a3 3 0 0 1-3 3h-8V30Z" fill="#ECECEC"/></g></svg>'),

                            (new Field())
                                ->setName('Description')
                                ->setKey('description')
                                ->setType(FieldType::INPUT)
                                ->setValue('Product pricing table that compares individual plans'),
                        ],
                    ]),

            ],
        ];
    }

    public function getModules(): array
    {
        return [
            [
                'library' => '@ckeditor/ckeditor5-template',
                'exports' => 'Template',
            ],
        ];
    }
}
