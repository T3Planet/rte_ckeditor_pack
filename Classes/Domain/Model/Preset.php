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

    /**
     * @var ObjectStorage<Feature>
     */
    protected ObjectStorage $features;

    public function __construct()
    {
        $this->features = new ObjectStorage();
    }

    public function getPresetKey(): string
    {
        return $this->presetKey;
    }

    public function setPresetKey(string $presetKey): void
    {
        $this->presetKey = $presetKey;
    }

    /**
     * Get features
     *
     * @return ObjectStorage<Feature>
     */
    public function getFeatures(): ObjectStorage
    {
        return $this->features;
    }

    /**
     * Set features
     *
     * @param ObjectStorage<Feature> $features
     */
    public function setFeatures(ObjectStorage $features): void
    {
        $this->features = $features;
    }

    /**
     * Add a feature
     *
     * @param Feature $feature
     */
    public function addFeature(Feature $feature): void
    {
        $this->features->attach($feature);
    }

    /**
     * Remove a feature
     *
     * @param Feature $feature
     */
    public function removeFeature(Feature $feature): void
    {
        $this->features->detach($feature);
    }
}

