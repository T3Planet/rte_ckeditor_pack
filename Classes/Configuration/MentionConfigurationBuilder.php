<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Configuration;

use TYPO3\CMS\Beuser\Domain\Repository\BackendUserRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class MentionConfigurationBuilder
 *
 * Handles the configuration building for CKEditor Mention feature.
 */
class MentionConfigurationBuilder
{
    /**
     * Build mention configuration from field configuration array
     *
     * @param array $fieldConfigArray
     * @return array
     */
    public function buildConfiguration(array $fieldConfigArray): array
    {
        $mentionConfig = $fieldConfigArray['mention'] ?? [];

        $dropdownLimit = max(1, (int)($mentionConfig['dropdownLimit'] ?? 10));

        $feeds = [];
        $configuredFeeds = $mentionConfig['feeds'] ?? [];
        if (!is_array($configuredFeeds)) {
            $configuredFeeds = [$configuredFeeds];
        }

        foreach ($configuredFeeds as $configuredFeed) {
            if (!is_array($configuredFeed)) {
                $configuredFeed = ['feed' => $configuredFeed];
            }
            $feeds[] = $this->buildMentionFeedEntry($configuredFeed);
        }

        if (!$feeds) {
            $feeds[] = $this->buildMentionFeedEntry([]);
        }

        return [
            'dropdownLimit' => $dropdownLimit,
            'feeds' => $feeds,
        ];
    }

    private function buildMentionFeedEntry(array $configuredFeed): array
    {
        $marker = $configuredFeed['marker'] ?? '@';
        $minimumCharacters = max(0, (int)($configuredFeed['minimumCharacters'] ?? 1));
        $fallbackFeed = $configuredFeed['feed'] ?? [];

        return [
            'marker' => $marker,
            'minimumCharacters' => $minimumCharacters,
            'feed' => $this->resolveBackendUserMentions($marker, $fallbackFeed),
        ];
    }

    private function resolveBackendUserMentions(string $marker, array|string $fallback): array
    {
        $mentions = [];

        // First, check if configured feed values exist
        $normalizedFallback = $this->normalizeMentionFeedValues($fallback);

        if (!empty($normalizedFallback)) {
            // If configured feed values exist, use ONLY those
            return array_map(
                static function (string $value) use ($marker): string {
                    return str_starts_with($value, $marker)
                        ? $value
                        : $marker . ltrim($value, $marker);
                },
                $normalizedFallback
            );
        }

        // Only fetch backend users if no configured feed values exist
        // Each user gets their own realName (or username if realName doesn't exist)
        /** @var BackendUserRepository $backendUserRepository */
        $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
        $backendUsers = $backendUserRepository->findAll();

        foreach ($backendUsers as $backendUser) {
            // Use realName if available, otherwise use username for that specific user
            $name = $backendUser->getRealName() ?: $backendUser->getUsername();
            if ($name) {
                $mentions[] = $marker . $name;
            }
        }

        return $mentions;
    }

    private function normalizeMentionFeedValues(array|string $feed): array
    {
        if (is_string($feed)) {
            $feedItems = preg_split('/[\r\n,]+/', $feed) ?: [];
        } elseif (is_array($feed)) {
            $feedItems = $feed;
        } else {
            $feedItems = [];
        }

        $normalized = array_values(array_unique(array_filter(
            array_map('trim', $feedItems),
            static fn(string $item): bool => $item !== ''
        )));

        return $normalized;
    }
}


