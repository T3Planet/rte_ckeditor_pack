<?php

/**
 * This file is part of the package T3Planet/rte-ckeditor-pack.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace T3Planet\RteCkeditorPack\Utility;

/**
 * Restores TYPO3-protected RTE embed markup for frontend output.
 */
final class RteMarkupTransformationUtility
{
    private const IFRAME_ATTRIBUTES = [
        'src',
        'title',
        'width',
        'height',
        'frameborder',
        'allow',
        'allowfullscreen',
        'loading',
        'sandbox',
        'name',
        'id',
        'class',
        'style',
    ];

    public static function transform(string $content): string
    {
        $content = self::stripCollaborationMarkup($content);
        $content = self::transformOembedTags($content);

        return self::transformIframeTags($content);
    }

    /**
     * Strip CKEditor collaboration markers for frontend and preview output.
     *
     * Insertions and other suggestions keep their text; pending deletions are removed entirely.
     */
    public static function stripCollaborationMarkup(string $content): string
    {
        $content = (string)preg_replace(
            '/<suggestion-start\b[^>]*\bname=(["\'])deletion:[^"\']*\1[^>]*>(?:<\/suggestion-start>)?.*?<suggestion-end\b[^>]*\bname=\1deletion:[^"\']*\1[^>]*>(?:<\/suggestion-end>)?/is',
            '',
            $content
        );

        $content = (string)preg_replace(
            '/&lt;suggestion-start\b[^&]*\bname=(&quot;|&#34;|")deletion:[^&]*\1[^&]*&gt;(?:&lt;\/suggestion-start&gt;)?.*?&lt;suggestion-end\b[^&]*\bname=\1deletion:[^&]*\1[^&]*&gt;(?:&lt;\/suggestion-end&gt;)?/is',
            '',
            $content
        );

        $patterns = [
            '/<comment-start\b[^>]*>(?:<\/comment-start>)?/is',
            '/<comment-end\b[^>]*>(?:<\/comment-end>)?/is',
            '/<suggestion-start\b[^>]*>(?:<\/suggestion-start>)?/is',
            '/<suggestion-end\b[^>]*>(?:<\/suggestion-end>)?/is',
            '/&lt;comment-start\b.*?&gt;(?:&lt;\/comment-start&gt;)?/is',
            '/&lt;comment-end\b.*?&gt;(?:&lt;\/comment-end&gt;)?/is',
            '/&lt;suggestion-start\b.*?&gt;(?:&lt;\/suggestion-start&gt;)?/is',
            '/&lt;suggestion-end\b.*?&gt;(?:&lt;\/suggestion-end&gt;)?/is',
        ];

        foreach ($patterns as $pattern) {
            $content = (string)preg_replace($pattern, '', $content);
        }

        return $content;
    }

    private static function transformOembedTags(string $content): string
    {
        $content = (string)preg_replace_callback(
            '/<oembed\\b([^>]*?)\\burl=(["\'])(.*?)\\2([^>]*)>(?:<\\/oembed>)?/is',
            static fn (array $matches): string => self::buildOembedIframe($matches[3]),
            $content
        );

        return (string)preg_replace_callback(
            '/&lt;oembed\\b(.*?)\\burl=(&quot;|&#34;|")(.*?)\\2(.*?)&gt;(?:&lt;\\/oembed&gt;)?/is',
            static function (array $matches): string {
                $url = html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return self::buildOembedIframe($url);
            },
            $content
        );
    }

    private static function transformIframeTags(string $content): string
    {
        $content = (string)preg_replace_callback(
            '/<iframe\\b([^>]*?)(?:>(?:<\\/iframe>)?|\\s*\\/?>)/is',
            static fn (array $matches): string => self::buildIframe($matches[1]),
            $content
        );

        return (string)preg_replace_callback(
            '/&lt;iframe\\b(.*?)&gt;(?:&lt;\\/iframe&gt;)?/is',
            static function (array $matches): string {
                $attributeString = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return self::buildIframe($attributeString);
            },
            $content
        );
    }

    private static function buildOembedIframe(string $url): string
    {
        $embedUrl = self::resolveOembedUrl(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($embedUrl === '') {
            return '';
        }

        $src = htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8');

        return '<div class="media media-oembed-content" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">'
            . '<iframe src="' . $src . '" style="position:absolute;top:0;left:0;width:100%;height:100%;" '
            . 'frameborder="0" allowfullscreen '
            . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share">'
            . '</iframe></div>';
    }

    private static function buildIframe(string $attributeString): string
    {
        $attributes = self::parseAttributes($attributeString);
        $src = $attributes['src'] ?? '';
        if ($src === '' || !self::isAllowedHttpSrc($src)) {
            return '';
        }

        $parts = ['<iframe'];
        foreach (self::IFRAME_ATTRIBUTES as $name) {
            if (!isset($attributes[$name])) {
                continue;
            }

            $parts[] = $name . '="' . htmlspecialchars($attributes[$name], ENT_QUOTES, 'UTF-8') . '"';
        }
        $parts[] = '></iframe>';

        return implode(' ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private static function parseAttributes(string $attributeString): array
    {
        $attributes = [];
        $pattern = '/\\b([a-zA-Z_:][\\w:.-]*)\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'|([^\\s"\'=<>`]+))/';

        if (preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);
                $attributes[$name] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $attributes;
    }

    private static function isAllowedHttpSrc(string $src): bool
    {
        $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return (bool)preg_match('#^https?://#i', $src);
    }

    private static function resolveOembedUrl(string $url): string
    {
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }

        if (preg_match('#youtube\\.com/embed/([\\w-]+)#i', $url)) {
            return $url;
        }

        if (preg_match('#youtube\\.com/watch\\?v=([\\w-]+)#i', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (preg_match('#youtu\\.be/([\\w-]+)#i', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (preg_match('#youtube\\.com/shorts/([\\w-]+)#i', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (preg_match('#player\\.vimeo\\.com/video/(\\d+)#i', $url)) {
            return $url;
        }

        if (preg_match('#vimeo\\.com/(\\d+)#i', $url, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        if (preg_match('#(?:dailymotion\\.com/video/|dai\\.ly/)([\\w]+)#i', $url, $matches)) {
            return 'https://www.dailymotion.com/embed/video/' . $matches[1];
        }

        if (preg_match('#open\\.spotify\\.com/(embed/)?([\\w/]+)#i', $url, $matches)) {
            return 'https://open.spotify.com/embed/' . ltrim($matches[2], '/');
        }

        if (str_contains($url, '/embed/')) {
            return $url;
        }

        return '';
    }
}
