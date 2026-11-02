<?php

/**
 * Pathly for PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    Pathly <https://pathlyhq.com>
 * @copyright Pathly
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace PrestaShop\Module\Pathly\Service;

if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

/**
 * Links to Pathly and URL comparison.
 */
class Links
{
    public const SITE = 'https://pathlyhq.com';

    /**
     * @param string $scenarioId
     *
     * @return string
     */
    public static function scenario($scenarioId)
    {
        return self::SITE . '/monitors/' . rawurlencode($scenarioId);
    }

    /**
     * "Create a free account" link, in the employee's language when Pathly speaks it.
     *
     * @param string $isoLang
     *
     * @return string
     */
    public static function signup($isoLang)
    {
        $lang = in_array($isoLang, ['fr', 'en', 'es', 'de'], true) ? $isoLang : 'en';

        return self::SITE . '/' . $lang . '/login?' . http_build_query([
            'mode' => 'signup',
            'ref' => 'prestashop',
            'utm_source' => 'prestashop',
            'utm_medium' => 'cms_module',
        ]);
    }

    /**
     * "host/path" without scheme, "www." nor trailing slash, to find an existing Ping.
     *
     * @param string|null $url
     *
     * @return string
     */
    public static function normalize($url)
    {
        $parts = parse_url(trim((string) $url));
        if (!is_array($parts) || !isset($parts['host'])) {
            return '';
        }
        $host = preg_replace('/^www\./', '', strtolower($parts['host']));
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return $host . $path;
    }

    /**
     * @param string|null $url
     *
     * @return string
     */
    public static function host($url)
    {
        $normalized = self::normalize($url);
        $slash = strpos($normalized, '/');

        return $slash === false ? $normalized : substr($normalized, 0, $slash);
    }

    /**
     * True only for an https URL on pathlyhq.com: badge links come from the API
     * and are checked again before being printed on the shop.
     *
     * @param mixed $url
     *
     * @return bool
     */
    public static function isPathly($url)
    {
        if (!is_string($url) || stripos($url, 'https://') !== 0) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'pathlyhq.com' || substr($host, -13) === '.pathlyhq.com';
    }

    /**
     * @param mixed $url
     *
     * @return bool
     */
    public static function isHttps($url)
    {
        return is_string($url) && stripos($url, 'https://') === 0;
    }
}
