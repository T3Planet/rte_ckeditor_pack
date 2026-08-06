<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\EventListener;

use TYPO3\CMS\Core\Core\Event\BootCompletedEvent;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * Register backend JS language labels (must not run during ext_localconf).
 */
final class AddInlineLanguageLabelsListener
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
    ) {}

    public function __invoke(BootCompletedEvent $event): void
    {
        $this->pageRenderer->addInlineLanguageLabelFile(
            'EXT:rte_ckeditor_pack/Resources/Private/Language/locallang_notifications.xlf'
        );
        $this->pageRenderer->addInlineLanguageLabelFile(
            'EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf'
        );
    }
}
