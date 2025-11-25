<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Model;

class Feature extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    protected int $presetUid = 0;

    protected bool $enable = false;

    protected string $configKey = '';

    protected string $fields = '';

    protected string $toolbarItems = '';

    protected int $sorting = 0;

    public function getPresetUid(): int
    {
        return $this->presetUid;
    }

    public function setPresetUid(int $presetUid): void
    {
        $this->presetUid = $presetUid;
    }

    public function getEnable(): bool
    {
        return $this->enable;
    }

    public function setEnable(bool $enable): void
    {
        $this->enable = $enable;
    }

    public function isEnable(): bool
    {
        return $this->enable;
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    public function setConfigKey(string $configKey): void
    {
        $this->configKey = $configKey;
    }

    public function getFields(): string
    {
        return $this->fields;
    }

    public function setFields(string $fields): void
    {
        $this->fields = $fields;
    }

    public function getToolbarItems(): string
    {
        return $this->toolbarItems;
    }

    public function setToolbarItems(string $toolbarItems): void
    {
        $this->toolbarItems = $toolbarItems;
    }

    public function getSorting(): int
    {
        return $this->sorting;
    }

    public function setSorting(int $sorting): void
    {
        $this->sorting = $sorting;
    }
}


