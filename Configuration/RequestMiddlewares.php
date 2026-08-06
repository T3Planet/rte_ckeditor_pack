<?php

use T3Planet\RteCkeditorPack\Middleware\CommentTread;
use T3Planet\RteCkeditorPack\Middleware\ParsedHtmlForFrontend;
use T3Planet\RteCkeditorPack\Middleware\RevisionHistory;
use T3Planet\RteCkeditorPack\Middleware\Suggestions;
use T3Planet\RteCkeditorPack\Middleware\TokenGenerate;
use T3Planet\RteCkeditorPack\Middleware\VisualEditorStylesMiddleware;

return [
    'frontend' => [
        // Non-RTC Comments adapter calls /comments* on the site host (not /typo3/ajax).
        // Must run after FE backend-user auth so Visual Editor sessions are recognized.
        't3planet/threadcomment' => [
            'target' => CommentTread::class,
            'after' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
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
        // After FE backend-user auth so Visual Editor sessions are recognized (v13+).
        't3planet/visual-editor-styles' => [
            'target' => VisualEditorStylesMiddleware::class,
            'after' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
            'before' => [
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
            'after' => [
                'typo3/cms-backend/authentication',
            ],
        ],
    ],
];
