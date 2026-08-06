<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\SourceScheme;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Type\Map;

/**
 * CSP for CKEditor Cloud Services (token, WebSocket, API), export/import converters,
 * license proxy, emoji CDN definitions, and WebSpellChecker.
 */
return Map::fromEntries([
    Scope::backend(),
    new MutationCollection(
        // Export PDF opens the generated file in a blob: iframe.
        new Mutation(MutationMode::Extend, Directive::FrameSrc, SourceScheme::blob),
        // Token URL, API base URL, converters, WebSocket, license proxy.
        new Mutation(
            MutationMode::Extend,
            Directive::ConnectSrc,
            SourceScheme::data,
            new UriValue('https://*.cke-cs.com'),
            new UriValue('wss://*.cke-cs.com'),
            new UriValue('https://proxy-event.ckeditor.com'),
            new UriValue('https://cdn.ckeditor.com'),
            new UriValue('https://pdf-converter.cke-cs.com/'),
            new UriValue('https://docx-converter.cke-cs.com/'),
            new UriValue('https://docx-converter.cke-cs.com/v2/convert/docx-html'),
            new UriValue('https://docx-converter.cke-cs.com/v2/convert/html-docx'),
            new UriValue('https://svc.webspellchecker.net'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::ScriptSrc,
            SourceScheme::data,
            new UriValue('https://svc.webspellchecker.net')
        ),
        // Backend uses a separate script-src-elem policy (with nonces); ScriptSrc alone is not enough.
        new Mutation(
            MutationMode::Extend,
            Directive::ScriptSrcElem,
            new UriValue('https://svc.webspellchecker.net')
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::FontSrc,
            new UriValue('https://svc.webspellchecker.net')
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::ImgSrc,
            new UriValue('https://svc.webspellchecker.net')
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::StyleSrcElem,
            new UriValue('https://svc.webspellchecker.net')
        ),
    ),
    Scope::frontend(),
    new MutationCollection(
        new Mutation(MutationMode::Extend, Directive::FrameSrc, SourceScheme::blob),
        new Mutation(
            MutationMode::Extend,
            Directive::ConnectSrc,
            new UriValue('https://*.cke-cs.com'),
            new UriValue('wss://*.cke-cs.com'),
            new UriValue('https://proxy-event.ckeditor.com'),
            new UriValue('https://cdn.ckeditor.com'),
            new UriValue('https://pdf-converter.cke-cs.com/'),
            new UriValue('https://docx-converter.cke-cs.com/'),
            new UriValue('https://docx-converter.cke-cs.com/v2/convert/docx-html'),
            new UriValue('https://docx-converter.cke-cs.com/v2/convert/html-docx'),
        ),
    ),
]);
