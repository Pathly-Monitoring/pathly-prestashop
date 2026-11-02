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
 * Key/value storage of the platform (Configuration in PrestaShop).
 */
interface Store
{
    /**
     * @param string $key
     *
     * @return string|null
     */
    public function get($key);

    /**
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    public function set($key, $value);

    /**
     * @param string $key
     *
     * @return void
     */
    public function delete($key);
}
