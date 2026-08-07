<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\SourceKeyword;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\SourceScheme;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Type\Map;

/**
 * CSP for CKEditor Cloud Services (token, WebSocket, API), export/import converters,
 * license proxy, emoji CDN definitions, WebSpellChecker, MathType (Wiris), and MathJax (frontend).
 */
return Map::fromEntries([
    Scope::backend(),
    new MutationCollection(
        // Export PDF opens the generated file in a blob: iframe.
        new Mutation(MutationMode::Extend, Directive::FrameSrc, SourceScheme::blob),
        // Token URL, API base URL, converters, WebSocket, license proxy, MathType services.
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
            new UriValue('https://www.wiris.net'),
            new UriValue('https://*.wiris.net'),
            new UriValue('https://www.wiris.com'),
            new UriValue('https://*.wiris.com'),
            new UriValue('https://data.wiris.cloud'),
            new UriValue('https://*.wiris.cloud'),
        ),
        // MathType telemeter WASM needs wasm-unsafe-eval for WebAssembly.instantiateStreaming().
        new Mutation(
            MutationMode::Extend,
            Directive::ScriptSrc,
            SourceScheme::data,
            SourceKeyword::wasmUnsafeEval,
            new UriValue('https://svc.webspellchecker.net'),
            new UriValue('https://www.wiris.net'),
            new UriValue('https://*.wiris.net'),
            new UriValue('https://www.wiris.com'),
            new UriValue('https://*.wiris.com'),
        ),
        // Backend uses a separate script-src-elem policy (with nonces); ScriptSrc alone is not enough.
        new Mutation(
            MutationMode::Extend,
            Directive::ScriptSrcElem,
            SourceKeyword::wasmUnsafeEval,
            new UriValue('https://svc.webspellchecker.net'),
            new UriValue('https://www.wiris.net'),
            new UriValue('https://*.wiris.net'),
            new UriValue('https://www.wiris.com'),
            new UriValue('https://*.wiris.com'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::FontSrc,
            new UriValue('https://svc.webspellchecker.net'),
            new UriValue('https://www.wiris.net'),
            new UriValue('https://*.wiris.net'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::ImgSrc,
            new UriValue('https://svc.webspellchecker.net'),
            new UriValue('https://www.wiris.net'),
            new UriValue('https://*.wiris.net'),
            new UriValue('https://www.wiris.com'),
            new UriValue('https://*.wiris.com'),
            new UriValue('https://*.wiris.cloud'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::StyleSrcElem,
            new UriValue('https://svc.webspellchecker.net'),
            new UriValue('https://www.wiris.net'),
            new UriValue('https://*.wiris.net'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::FrameSrc,
            new UriValue('https://www.wiris.net'),
            new UriValue('https://*.wiris.net'),
            new UriValue('https://www.wiris.com'),
            new UriValue('https://*.wiris.com'),
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
            new UriValue('https://cdn.jsdelivr.net'),
        ),
        // MathType may store formulas as Wiris images; MathJax fonts from jsDelivr.
        new Mutation(
            MutationMode::Extend,
            Directive::ImgSrc,
            new UriValue('https://www.wiris.net'),
            new UriValue('https://*.wiris.net'),
            new UriValue('https://cdn.jsdelivr.net'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::FontSrc,
            new UriValue('https://cdn.jsdelivr.net'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::ScriptSrcElem,
            new UriValue('https://cdn.jsdelivr.net'),
        ),
        new Mutation(
            MutationMode::Extend,
            Directive::StyleSrcElem,
            new UriValue('https://cdn.jsdelivr.net'),
        ),
    ),
]);
