<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Html;

use T3Planet\RteCkeditorPack\Utility\RteMarkupTransformationUtility;
use TYPO3\CMS\Core\Html\RteHtmlParser as CoreRteHtmlParser;

/**
 * TYPO3 v12 fallback for collaboration-marker restore.
 *
 * On v13+ this is handled by RestoreCollaborationMarkersListener via
 * Before/AfterTransformText* events. Those events do not exist in v12, so
 * htmlSanitize / processing can leave &lt;comment-start&gt;… as visible text
 * when General HTML Support is enabled alongside Comments.
 */
class RteHtmlParser extends CoreRteHtmlParser
{
    public function transformTextForRichTextEditor(string $value, array $processingConfiguration): string
    {
        $value = RteMarkupTransformationUtility::restoreEscapedCollaborationMarkers($value);
        $value = parent::transformTextForRichTextEditor($value, $processingConfiguration);

        return RteMarkupTransformationUtility::restoreEscapedCollaborationMarkers($value);
    }

    public function transformTextForPersistence(string $value, array $processingConfiguration): string
    {
        $value = RteMarkupTransformationUtility::restoreEscapedCollaborationMarkers($value);
        $value = parent::transformTextForPersistence($value, $processingConfiguration);

        // Critical: htmlSanitize may re-encode unknown tags; restore after parent returns.
        return RteMarkupTransformationUtility::restoreEscapedCollaborationMarkers($value);
    }
}
