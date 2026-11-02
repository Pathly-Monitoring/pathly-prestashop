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

use PrestaShop\Module\Pathly\Api\Client;
use PrestaShop\Module\Pathly\Config\Settings;

if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

/**
 * The Ping of the shop: attached when it already exists, created otherwise.
 */
class PingService
{
    /** Five minutes: allowed by every Pathly plan, Solo included. */
    public const INTERVAL_SEC = 300;

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
     * @param string $shopUrl
     * @param int $now
     *
     * @return array{id: string, created: bool, flowId: string|null}
     */
    public function ensure($shopUrl, $now)
    {
        $scenarios = $this->client->listScenarios();
        $wanted = Links::normalize($shopUrl);
        $host = Links::host($shopUrl);
        $pingId = null;
        $flowId = null;
        foreach ($scenarios as $scenario) {
            $type = isset($scenario['type']) ? $scenario['type'] : null;
            $url = isset($scenario['url']) ? $scenario['url'] : null;
            if ($pingId === null && $type === 'http' && Links::normalize($url) === $wanted) {
                $pingId = (string) $scenario['id'];
            }
            if ($flowId === null && $type === 'browser' && Links::host($url) === $host) {
                $flowId = (string) $scenario['id'];
            }
        }
        $created = false;
        if ($pingId === null) {
            $ping = $this->client->createScenario([
                'type' => 'http',
                'name' => 'PrestaShop · ' . $host,
                'url' => $shopUrl,
                'intervalSec' => self::INTERVAL_SEC,
                'tags' => ['prestashop'],
            ], 'prestashop-ping-' . sha1($wanted) . '-' . gmdate('YmdH', $now));
            $pingId = (string) $ping['id'];
            $created = true;
        }
        $this->settings->set(Settings::PING_ID, $pingId);
        if ($flowId !== null && $this->settings->get(Settings::FLOW_ID) === null) {
            $this->settings->set(Settings::FLOW_ID, $flowId);
        }

        return ['id' => $pingId, 'created' => $created, 'flowId' => $this->settings->get(Settings::FLOW_ID)];
    }
}
