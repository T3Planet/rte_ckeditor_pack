<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Domain\Repository;

use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ConfigurationRepository extends Repository
{
    protected ?QuerySettingsInterface $querySettings = null;

    private const TABLE_NAME = 'tx_rteckeditorpack_domain_model_configuration';

    public function injectQuerySettings(QuerySettingsInterface $querySettings): void
    {
        $this->querySettings = $querySettings;
    }

    public function initializeObject(): void
    {
        $this->setDefaultQuerySettings($this->querySettings->setRespectStoragePage(false));
    }


}
