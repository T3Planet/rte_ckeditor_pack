<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\Cards;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class Tabs
{
    public const STANDALONE = 'premium';
    public const COLLABORATION = 'collaboration';
    public const PRODUCTIVITY = 'productivity';
    public const FILEMANAGER = 'file_management';
    public const CORE = 'plugins';
    public const LAYOUT = 'layout';
    public const FEATURES = 'features';

    private static array $labels = [
        self::STANDALONE => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf:ckeditorKit.module.standalone',
        self::COLLABORATION => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf:ckeditorKit.module.collaboration',
        self::PRODUCTIVITY => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf:ckeditorKit.module.productivity',
        self::FILEMANAGER => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf:ckeditorKit.tab.file_management',
        self::CORE => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf:ckeditorKit.module.plugins',
        self::LAYOUT => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf:ckeditorKit.tab.layout',
        self::FEATURES => 'LLL:EXT:rte_ckeditor_pack/Resources/Private/Language/locallang.xlf:ckeditorKit.module.feature',
    ];

    public static function getLabel(string $tabConstant): string
    {
        if (!array_key_exists($tabConstant, self::$labels)) {
            return '';
        }
        return LocalizationUtility::translate(self::$labels[$tabConstant], 'rte_ckeditor_pack') ?? '';
    }

}
