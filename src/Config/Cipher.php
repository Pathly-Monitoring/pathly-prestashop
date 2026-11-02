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

namespace PrestaShop\Module\Pathly\Config;

if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

/**
 * Encrypts the API key at rest with the shop's own secret.
 */
interface Cipher
{
    /**
     * @param string $plain
     *
     * @return string
     */
    public function encrypt($plain);

    /**
     * @param string $encrypted
     *
     * @return string|null null when the value cannot be read
     */
    public function decrypt($encrypted);
}
