<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Model;

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Preset extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    protected string $presetKey = '';

    protected bool $isCustom = false;

    protected bool $hidden = false;

    protected int $usageSource = 0;

    protected string $toolbarItems = '';

    public function getPresetKey(): string
    {
        return $this->presetKey;
    }

    public function setPresetKey(string $presetKey): void
    {
        $this->presetKey = $presetKey;
    }

    public function getIsCustom(): bool
    {
        return $this->isCustom;
    }

    public function setIsCustom(bool $isCustom): void
    {
        $this->isCustom = $isCustom;
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getUsageSource(): int
    {
        return $this->usageSource;
    }

    public function setUsageSource(int $usageSource): void
    {
        $this->usageSource = $usageSource;
    }

    public function getToolbarItems(): string
    {
        return $this->toolbarItems;
    }

    public function setToolbarItems(string $toolbarItems): void
    {
        $this->toolbarItems = $toolbarItems;
    }

    /**
     * Get usage source based on hidden status
     * hidden = 0 (active) → usage = 1 (use CKEditor Pack)
     * hidden = 1 (inactive) → usage = 0 (use YAML)
     *
     * @return int
     */
    public function getUsage(): int
    {
        return $this->hidden ? 0 : 1;
    }

}

