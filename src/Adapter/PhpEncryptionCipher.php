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

use PrestaShop\Module\Pathly\Config\Cipher;

if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

/**
 * Encrypts with PrestaShop's PhpEncryption and the shop's own secret (_NEW_COOKIE_KEY_).
 */
class PhpEncryptionCipher implements Cipher
{
    /** @var \PhpEncryption */
    private $engine;

    /**
     * @param string $secret
     */
    public function __construct($secret)
    {
        $this->engine = new \PhpEncryption($secret);
    }

    public function encrypt($plain)
    {
        return (string) $this->engine->encrypt($plain);
    }

    public function decrypt($encrypted)
    {
        try {
            $plain = $this->engine->decrypt($encrypted);
        } catch (\Exception $e) {
            return null;
        }

        return is_string($plain) && $plain !== '' ? $plain : null;
    }
}
