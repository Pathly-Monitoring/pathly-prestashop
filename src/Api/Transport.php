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

namespace PrestaShop\Module\Pathly\Api;

if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

/**
 * Sends one HTTP request. Swapped for a fake in tests.
 */
interface Transport
{
    /**
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param string|null $body
     *
     * @return Response
     *
     * @throws ApiException status 0 on network failure
     */
    public function send($method, $url, array $headers, $body = null);
}
