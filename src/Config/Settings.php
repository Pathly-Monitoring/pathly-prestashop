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
 * Every value the module stores. Uninstall deletes them all.
 */
class Settings
{
    public const API_KEY = 'PATHLY_API_KEY';
    public const KEY_HINT = 'PATHLY_KEY_HINT';
    public const ORG_NAME = 'PATHLY_ORG_NAME';
    public const PING_ID = 'PATHLY_PING_ID';
    public const FLOW_ID = 'PATHLY_FLOW_ID';
    public const TRIAL_ID = 'PATHLY_TRIAL_ID';
    public const BADGE_ENABLED = 'PATHLY_BADGE_ENABLED';
    public const BADGE_URL = 'PATHLY_BADGE_URL';
    public const STATUS_URL = 'PATHLY_STATUS_URL';
    public const STATUS_PAGE_ID = 'PATHLY_STATUS_PAGE_ID';
    public const STATUS_PAGE_OWNED = 'PATHLY_STATUS_PAGE_OWNED';
    public const SNAPSHOT = 'PATHLY_SNAPSHOT';
    public const SNAPSHOT_CHECKED_AT = 'PATHLY_SNAPSHOT_CHECKED_AT';
    public const LAST_REPLAY_AT = 'PATHLY_LAST_REPLAY_AT';
    public const LAST_REPLAY_REASON = 'PATHLY_LAST_REPLAY_REASON';
    public const DASHBOARD_CACHE = 'PATHLY_DASHBOARD_CACHE';

    /** @var Store */
    private $store;

    /** @var Cipher */
    private $cipher;

    public function __construct(Store $store, Cipher $cipher)
    {
        $this->store = $store;
        $this->cipher = $cipher;
    }

    /**
     * @return string[]
     */
    public static function allKeys()
    {
        return [
            self::API_KEY, self::KEY_HINT, self::ORG_NAME, self::PING_ID, self::FLOW_ID, self::TRIAL_ID,
            self::BADGE_ENABLED, self::BADGE_URL, self::STATUS_URL, self::STATUS_PAGE_ID,
            self::STATUS_PAGE_OWNED, self::SNAPSHOT, self::SNAPSHOT_CHECKED_AT, self::LAST_REPLAY_AT,
            self::LAST_REPLAY_REASON, self::DASHBOARD_CACHE,
        ];
    }

    /**
     * @return string|null the plain key, never logged nor displayed
     */
    public function apiKey()
    {
        $stored = $this->store->get(self::API_KEY);
        if ($stored === null || $stored === '') {
            return null;
        }

        return $this->cipher->decrypt($stored);
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return $this->apiKey() !== null;
    }

    /**
     * Stores the key encrypted and keeps only its last four characters to show.
     *
     * @param string $key
     *
     * @return void
     */
    public function saveApiKey($key)
    {
        $this->store->set(self::API_KEY, $this->cipher->encrypt($key));
        $this->store->set(self::KEY_HINT, substr($key, -4));
    }

    /**
     * Forgets the key and everything tied to the Pathly account.
     *
     * @return void
     */
    public function forgetAccount()
    {
        foreach (self::allKeys() as $key) {
            if ($key !== self::SNAPSHOT && $key !== self::SNAPSHOT_CHECKED_AT) {
                $this->store->delete($key);
            }
        }
    }

    /**
     * @return void
     */
    public function deleteAll()
    {
        foreach (self::allKeys() as $key) {
            $this->store->delete($key);
        }
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function get($key)
    {
        $value = $this->store->get($key);

        return $value === '' ? null : $value;
    }

    /**
     * @param string $key
     * @param string|int|null $value null deletes
     *
     * @return void
     */
    public function set($key, $value)
    {
        if ($value === null) {
            $this->store->delete($key);

            return;
        }
        $this->store->set($key, (string) $value);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function flag($key)
    {
        return $this->get($key) === '1';
    }

    /**
     * @param string $key
     *
     * @return array<string, mixed>|null
     */
    public function json($key)
    {
        $raw = $this->get($key);
        $data = $raw === null ? null : json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param string $key
     * @param array<string, mixed> $value
     *
     * @return void
     */
    public function setJson($key, array $value)
    {
        $this->store->set($key, (string) json_encode($value));
    }
}
