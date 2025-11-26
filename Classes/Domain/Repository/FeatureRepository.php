<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Repository;

use T3Planet\RteCkeditorPack\Domain\Model\Feature;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class FeatureRepository extends Repository
{
    protected ?QuerySettingsInterface $querySettings = null;

    public function injectQuerySettings(QuerySettingsInterface $querySettings): void
    {
        $this->querySettings = $querySettings;
    }

    public function initializeObject(): void
    {
        $this->setDefaultQuerySettings($this->querySettings->setRespectStoragePage(false));
    }

    /**
     * Find features by preset UID
     *
     * @param int $presetUid
     * @return array
     */
    public function findByPresetUid(int $presetUid): array
    {
        $query = $this->createQuery();
        $query->matching($query->equals('presetUid', $presetUid));
        $query->setOrderings(['sorting' => QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray();
    }

    /**
     * Find enabled features by preset UID
     *
     * @param int $presetUid
     * @return array
     */
    public function findEnabledByPresetUid(int $presetUid): array
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('presetUid', $presetUid),
                $query->equals('enable', 1)
            )
        );
        $query->setOrderings(['sorting' => QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray();
    }

    /**
     * Find feature by preset UID and config key
     *
     * @param int $presetUid
     * @param string $configKey
     * @return Feature|null
     */
    public function findByPresetUidAndConfigKey(int $presetUid, string $configKey): ?Feature
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('presetUid', $presetUid),
                $query->equals('configKey', $configKey)
            )
        );
        $query->setLimit(1);
        return $query->execute()->getFirst();
    }

    /**
     * Find feature by config key (across all presets)
     *
     * @param string $configKey
     * @return array
     */
    public function findByConfigKey(string $configKey): array
    {
        $query = $this->createQuery();
        $query->matching($query->equals('configKey', $configKey));
        $query->setOrderings(['sorting' => QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray();
    }

    /**
     * Find all enabled features
     *
     * @return array
     */
    public function findEnabled(): array
    {
        $query = $this->createQuery();
        $query->matching($query->equals('enable', 1));
        $query->setOrderings(['configKey' => QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray();
    }

    /**
     * Remove all features by preset UID
     *
     * @param int $presetUid
     * @return bool
     */
    public function removeByPresetId(int $presetUid): bool
    {
        $features = $this->findByPresetUid($presetUid);
        foreach ($features as $feature) {
            $this->remove($feature);
        }
        return true;
    }
}