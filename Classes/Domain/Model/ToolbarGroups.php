<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Model;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class ToolbarGroups extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    protected string $label =  '';

    protected string $tooltip =  '';

    protected string $icon =  '';

    protected string $items = '';

    protected string $customIcon = '';

    protected array $toolBarIcons = [
        'bold',
        'threeVerticalDots',
        'alignLeft',
        'importExport',
        'paragraph',
        'text',
        'plus',
        'dragIndicator',
        'pilcrow',
        'other',
    ];

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setTooltip(string $tooltip): void
    {
        $this->tooltip = $tooltip;
    }

    public function getTooltip(): string
    {
        return $this->tooltip;
    }

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getItems(): string
    {
        return $this->items;
    }

    public function setItems(string $items): void
    {
        $this->items = $items;
    }

    public function getItemValues(): array
    {
        return GeneralUtility::trimExplode(',', $this->items);
    }

    public function setCustomIcon(string $customIcon): void
    {
        $this->customIcon = $customIcon;
    }

    public function getCustomIcon(): string
    {
        return $this->customIcon;
    }

    public function getToolBarIcon(): array
    {
        return $this->toolBarIcons;
    }

    public function getToolBarIconValues(): string
    {
        return implode(',', $this->toolBarIcons);
    }
}
