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

namespace PrestaShop\Module\Pathly\Adapter;

use PrestaShop\Module\Pathly\Config\Store;

if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

/**
 * Store backed by PrestaShop's Configuration table.
 */
class ConfigurationStore implements Store
{
    public function get($key)
    {
        $value = \Configuration::get($key);

        return $value === false || $value === null ? null : (string) $value;
    }

    public function set($key, $value)
    {
        \Configuration::updateValue($key, $value);
    }

    public function delete($key)
    {
        \Configuration::deleteByName($key);
    }
}
