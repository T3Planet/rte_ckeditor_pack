<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Repository;

use T3Planet\RteCkeditorPack\Domain\Model\Preset;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class PresetRepository extends Repository
{
    protected ?QuerySettingsInterface $querySettings = null;

    public function injectQuerySettings(QuerySettingsInterface $querySettings): void
    {
        $this->querySettings = $querySettings;
    }


    public function initializeObject(): void
    {
        $this->setDefaultQuerySettings($this->querySettings->setRespectStoragePage(false));
        $this->setDefaultQuerySettings($this->querySettings->setIgnoreEnableFields(true));
    }

    /**
     * Find preset by preset key
     *
     * @param string $presetKey
     * @return Preset|null
     */
    public function findByPresetKey(string $presetKey): ?Preset
    {
        $query = $this->createQuery();
        $query->matching($query->equals('presetKey', $presetKey));
        $query->setLimit(1);
        return $query->execute()->getFirst();
    }

    /**
     * Find all presets
     *
     * @return array
     */
    public function findAll(): array
    {
        $query = $this->createQuery();
        $query->setOrderings(['presetKey' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray();
    }

    /**
     *  Find preset by UID
     *
     * @param int $uid The identifier of the object to find
     * @return object|null The matching object if found, otherwise NULL
     * @phpstan-return T|null
     */
    public function findByUid($uid)
    {
        $query = $this->createQuery();
        $query->matching($query->equals('uid', $uid));
        $query->setLimit(1);
        return $query->execute()->getFirst();
    }

    /**
     * Find preset by preset key
     *
     * @param string $presetKey
     * @return Preset|null
     */
    public function findByUsage(string $presetKey): ?Preset
    {
        $query = $this->createQuery();
        
        $querySettings = $query->getQuerySettings();
        $querySettings->setIgnoreEnableFields(false);
        $query->setQuerySettings($querySettings);

        $query->matching(
            $query->logicalAnd(
                $query->equals('presetKey', $presetKey),
                $query->equals('usageSource', 0)
            )
        );
        $query->setLimit(1);
        return $query->execute()->getFirst();
    }
}

