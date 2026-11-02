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

if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

/**
 * Names and versions of the installed modules. Read locally, never sent.
 */
class InstalledModules
{
    /**
     * @return array<string, string>
     */
    public static function versions()
    {
        $rows = \Db::getInstance()->executeS('SELECT `name`, `version` FROM `' . _DB_PREFIX_ . 'module` ORDER BY `name`');
        $versions = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $versions[(string) $row['name']] = (string) $row['version'];
        }

        return $versions;
    }
}
