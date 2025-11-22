<?php

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Form\Element;

use T3Planet\RteCkeditorPack\Domain\Repository\RevisionHistoryRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Trait for revision history functionality in RichTextElement
 */
trait RevisionHistoryTrait
{
    /**
     * Check if revision history is enabled in the CKEditor toolbar
     *
     * @param array $ckeditorConfiguration
     * @return bool
     */
    protected function isRevisionHistoryEnabled(array $ckeditorConfiguration): bool
    {
        return isset($ckeditorConfiguration['toolbar']['items'])
            && in_array('revisionHistory', $ckeditorConfiguration['toolbar']['items'], true);
    }

    /**
     * Get revision history JSON data for the given field
     *
     * @param string $fieldId
     * @return string JSON encoded revision history data
     */
    protected function getRevisionHistoryJson(string $fieldId): string
    {
        $revisionHistoryRepository = GeneralUtility::makeInstance(RevisionHistoryRepository::class);
        $revisionsData = $revisionHistoryRepository->fetchRevisionsById($fieldId);

        if (!$revisionsData) {
            return json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $dataForRevisionJs = $this->processRevisionHistoryData($revisionsData);
        return json_encode($dataForRevisionJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: json_encode([]);
    }

    /**
     * Process raw revision history data into format needed for JavaScript
     *
     * @param array $revisionsData
     * @return array
     */
    protected function processRevisionHistoryData(array $revisionsData): array
    {
        $dataForRevisionJs = [];

        foreach ($revisionsData as $revision) {
            $diffData = $this->extractDiffData($revision);
            $authors = $this->unserializeAuthors($revision['authors'] ?? '');

            $dataForRevisionJs[] = [
                'id' => $revision['id'],
                'name' => $revision['name'],
                'creatorId' => $authors[0] ?? null,
                'authorsIds' => $authors,
                'diffData' => $diffData,
                'createdAt' => $revision['created_at'],
                'attributes' => ['new_draft_req' => false],
                'fromVersion' => $revision['previous_version'],
                'toVersion' => $revision['current_version'],
            ];
        }

        return $dataForRevisionJs;
    }

    /**
     * Extract and validate diff data from revision
     *
     * @param array $revision
     * @return array|null
     */
    protected function extractDiffData(array $revision): ?array
    {
        if (empty($revision['diff_data'])) {
            return null;
        }

        $decoded = json_decode($revision['diff_data'], true);

        if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
            return null;
        }

        if ((is_array($decoded) && count($decoded) > 0) || is_object($decoded)) {
            return $decoded;
        }

        return null;
    }

    /**
     * Unserialize authors data safely
     *
     * @param string $authorsSerialized
     * @return array
     */
    protected function unserializeAuthors(string $authorsSerialized): array
    {
        if (empty($authorsSerialized)) {
            return [];
        }

        $authors = @unserialize($authorsSerialized, ['allowed_classes' => false]);
        return is_array($authors) ? $authors : [];
    }

    /**
     * Render hidden textarea with revision history data
     *
     * @param string $fieldId
     * @param array $ckeditorConfiguration
     * @return string
     */
    protected function renderRevisionHistoryTextarea(string $fieldId, array $ckeditorConfiguration): string
    {
        $revisionsDataJson = $this->getRevisionHistoryJson($fieldId);
        // Escape closing tag to prevent injection
        $safeJson = str_replace('</textarea>', '&lt;/textarea&gt;', $revisionsDataJson);

        return '<textarea class="d-none" data-ckeditor5-premium-element-id="' . htmlspecialchars($fieldId) . '">' . $safeJson . '</textarea>';
    }
}
