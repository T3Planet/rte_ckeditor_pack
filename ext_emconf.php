<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CKEditor Pack',
    'description' => 'CKEditor Pack TYPO3 extension provides a modern, integrated CKEditor build many optimized features, accessibility tools, AI assistance, and collaboration-friendly options—all without managing scattered YAML files. Built for stability and clean integration, it streamlines editing workflows and enhances TYPO3 content quality.',
    'category' => 'be',
    'author' => 'Team T3Planet',
    'author_email' => 'ckeditor@t3planet.de',
    'author_company' => 'T3Planet',
    'state' => 'stable',
    'version' => '3.2.2',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.5.99',
            'typo3' => '12.4.25-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'T3Planet\\RteCkeditorPack\\' => 'Classes',
        ],
    ],
];
