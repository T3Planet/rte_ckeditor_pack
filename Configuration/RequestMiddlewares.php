<?php

use T3Planet\RteCkeditorPack\Middleware\CommentTread;
use T3Planet\RteCkeditorPack\Middleware\ParsedHtmlForFrontend;
use T3Planet\RteCkeditorPack\Middleware\RevisionHistory;
use T3Planet\RteCkeditorPack\Middleware\Suggestions;
use T3Planet\RteCkeditorPack\Middleware\TokenGenerate;

return [
    'frontend' => [
        't3planet/threadcomment' => [
            'target' => CommentTread::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
        't3planet/parsedcommenthtml' => [
            'target' => ParsedHtmlForFrontend::class,
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
        ],
        't3planet/token/generate' => [
            'target' => TokenGenerate::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
        't3planet/ckeditor-premium/suggestion' => [
            'target' => Suggestions::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
        't3planet/ckeditor-premium/revision-history' => [
            'target' => RevisionHistory::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
    'backend' => [
        't3planet/threadcomment' => [
            'target' => CommentTread::class,
            'before' => [
                'typo3/cms-backend/authentication',
            ],
        ],
    ],
];
