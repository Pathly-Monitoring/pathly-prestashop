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

use PrestaShop\Module\Pathly\Api\ApiException;
use PrestaShop\Module\Pathly\Api\Client;
use PrestaShop\Module\Pathly\Config\Settings;

if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

/**
 * Replays the Flow after an update of PrestaShop or of a module.
 *
 * Two signals: the native hooks when the PrestaShop version has one, and a
 * comparison of the installed versions, made at most every 5 minutes from the
 * back office. The list of modules stays in the shop: only the run request
 * goes to Pathly.
 */
class ReplayService
{
    /** Seconds between two comparisons of the installed versions. */
    public const CHECK_EVERY = 300;

    /** Seconds between two replays: a batch of updates triggers only one. */
    public const REPLAY_EVERY = 600;

    /** @var Client */
    private $client;

    /** @var Settings */
    private $settings;

    public function __construct(Client $client, Settings $settings)
    {
        $this->client = $client;
        $this->settings = $settings;
    }

    /**
     * Compares the installed versions with the last snapshot.
     *
     * @param string $coreVersion
     * @param callable $modules returns array<string, string> name => version
     * @param int $now
     * @param bool $force skip the 5-minute pause (right after an update)
     *
     * @return string|null the reason of the replay, null when nothing changed
     */
    public function check($coreVersion, callable $modules, $now, $force = false)
    {
        if (!$this->settings->isConfigured()) {
            return null;
        }
        $checkedAt = (int) $this->settings->get(Settings::SNAPSHOT_CHECKED_AT);
        if (!$force && $now - $checkedAt < self::CHECK_EVERY) {
            return null;
        }
        $this->settings->set(Settings::SNAPSHOT_CHECKED_AT, $now);
        $current = ['core' => (string) $coreVersion, 'modules' => call_user_func($modules)];
        $previous = $this->settings->json(Settings::SNAPSHOT);
        $this->settings->setJson(Settings::SNAPSHOT, $current);
        if ($previous === null) {
            return null;
        }
        $reason = self::difference($previous, $current);
        if ($reason !== null) {
            $this->replay($reason, $now);
        }

        return $reason;
    }

    /**
     * Stores the current versions without replaying (right after configuration).
     *
     * @param string $coreVersion
     * @param array<string, string> $modules
     * @param int $now
     *
     * @return void
     */
    public function baseline($coreVersion, array $modules, $now)
    {
        $this->settings->setJson(Settings::SNAPSHOT, ['core' => (string) $coreVersion, 'modules' => $modules]);
        $this->settings->set(Settings::SNAPSHOT_CHECKED_AT, $now);
    }

    /**
     * Starts a run of the Flow, or of the Ping when there is no Flow yet.
     *
     * @param string $reason
     * @param int $now
     *
     * @return bool true when Pathly accepted the run
     */
    public function replay($reason, $now)
    {
        $target = $this->settings->get(Settings::FLOW_ID);
        if ($target === null) {
            $target = $this->settings->get(Settings::PING_ID);
        }
        if ($target === null) {
            return false;
        }
        $last = (int) $this->settings->get(Settings::LAST_REPLAY_AT);
        if ($now - $last < self::REPLAY_EVERY) {
            return false;
        }
        try {
            $this->client->runScenario($target);
        } catch (ApiException $e) {
            return false;
        }
        $this->settings->set(Settings::LAST_REPLAY_AT, $now);
        $this->settings->set(Settings::LAST_REPLAY_REASON, $reason);
        $this->settings->set(Settings::DASHBOARD_CACHE, null);

        return true;
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return string|null "PrestaShop 8.1.0 → 8.2.0", "ps_checkout 1.0 → 1.1", "ps_checkout"
     */
    public static function difference(array $before, array $after)
    {
        $oldCore = isset($before['core']) ? (string) $before['core'] : '';
        if ($oldCore !== $after['core']) {
            return 'PrestaShop ' . $oldCore . ' → ' . $after['core'];
        }
        $old = isset($before['modules']) && is_array($before['modules']) ? $before['modules'] : [];
        foreach ($after['modules'] as $name => $version) {
            if (!isset($old[$name])) {
                return $name . ' ' . $version;
            }
            if ((string) $old[$name] !== (string) $version) {
                return $name . ' ' . $old[$name] . ' → ' . $version;
            }
        }
        $removed = array_diff_key($old, $after['modules']);

        return $removed === [] ? null : (string) key($removed) . ' ×';
    }
}
