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

class Configuration extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    protected string $configKey;

    protected string $fields =  '';

    protected string $preset =  '';

    protected bool $enable = false;

    public function setConfigKey(string $configKey): void
    {
        $this->configKey = $configKey;
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
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

    public function getFields(): string
    {
        return $this->fields;
    }

    public function setFields(string $fields): void
    {
        $this->fields = $fields;
    }

    public function setPreset(string $preset): void
    {
        $this->preset = $preset;
    }

    public function getPreset(): string
    {
        return $this->preset;
    }

    public function getPresetArray(): array
    {
        return $this->preset != null && trim($this->preset) !== '' ? GeneralUtility::trimExplode(',', $this->preset) : [];
    }
}
