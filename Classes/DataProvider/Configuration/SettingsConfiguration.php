<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\DataProvider\Configuration;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class SettingsConfiguration
{
    /**
     * Get settings configuration array
     *
     * @return array
     */
    public static function getSettings(): array
    {
        return [
            'featureConfiguration' => [
                (new Field())
                    ->setName('License key')
                    ->setKey('licenseKey')
                    ->setType(FieldType::TEXTAREA)
                    ->setValue('')
                    ->setPlaceholder(self::translateLabel('field.placeholder.licenseKey'))
                    ->setNote(self::translateLabel('field.note.licenseKey')),

                (new Field())
                    ->setName('Authorization Type')
                    ->setKey('authType')
                    ->setType(FieldType::SELECT)
                    ->setValue(['None' => 'none', 'Key' => 'key', 'Development token' => 'dev_token'])
                    ->setPlaceholder(self::translateLabel('field.placeholder.authType'))
                    ->setNote(self::translateLabel('field.note.authType')),

                (new Field())
                    ->setName('Environment ID')
                    ->setKey('environmentId')
                    ->setType(FieldType::INPUT)
                    ->setValue('')
                    ->setClass('form-item-key-type')
                    ->setPlaceholder(self::translateLabel('field.placeholder.environmentId'))
                    ->setNote(self::translateLabel('field.note.environmentId')),

                (new Field())
                    ->setName('Access Key')
                    ->setKey('accessKey')
                    ->setType(FieldType::INPUT)
                    ->setValue('')
                    ->setClass('form-item-key-type')
                    ->setPlaceholder(self::translateLabel('field.placeholder.accessKey'))
                    ->setNote(self::translateLabel('field.note.accessKey')),

                (new Field())
                    ->setName('Token URL')
                    ->setKey('tokenUrl')
                    ->setType(FieldType::INPUT)
                    ->setValue('')
                    ->setClass('form-item-dev-token-type')
                    ->setPlaceholder(self::translateLabel('field.placeholder.tokenUrl'))
                    ->setNote(self::translateLabel('field.note.tokenUrl')),

                (new Field())
                    ->setName('Organization ID')
                    ->setKey('organizationId')
                    ->setType(FieldType::INPUT)
                    ->setValue('')
                    ->setPlaceholder(self::translateLabel('field.placeholder.organizationId'))
                    ->setNote(self::translateLabel('field.note.organizationId')),

                (new Field())
                    ->setName('API Key')
                    ->setKey('apiKey')
                    ->setType(FieldType::INPUT)
                    ->setValue('')
                    ->setPlaceholder(self::translateLabel('field.placeholder.apiKey'))
                    ->setNote(self::translateLabel('field.note.apiKey')),

            ],
            'advancedSettings' => [
                (new Field())
                ->setName('Web Socket URL')
                ->setKey('webSocketUrl')
                ->setType(FieldType::INPUT)
                ->setValue('wss://ORGANIZATION_ID.cke-cs.com/ws')
                ->setPlaceholder(self::translateLabel('field.placeholder.webSocketUrl'))
                ->setNote(self::translateLabel('field.note.webSocketUrl')),

                (new Field())
                ->setName('API Base URL')
                ->setKey('apiBaseUrl')
                ->setType(FieldType::INPUT)
                ->setValue('https://ORGANIZATION_ID.cke-cs.com/api/v5/ENVIRONMENT_ID/')
                ->setPlaceholder(self::translateLabel('field.placeholder.apiBaseUrl'))
                ->setNote(self::translateLabel('field.note.apiBaseUrl')),
            ],
        ];
    }

    /**
     * Translate label
     *
     * @param string $key
     * @return string
     */
    private static function translateLabel(string $key): string
    {
        return LocalizationUtility::translate($key, 'RteCkeditorPack') ?? '';
    }
}
