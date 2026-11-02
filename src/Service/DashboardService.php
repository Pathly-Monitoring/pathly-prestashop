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
 * Data of the dashboard widget, kept 5 minutes to spare the API.
 */
class DashboardService
{
    public const CACHE_SEC = 300;

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
     * @param int $now
     *
     * @return array<string, mixed>
     */
    public function build($now)
    {
        if (!$this->settings->isConfigured()) {
            return ['configured' => false];
        }
        $cached = $this->settings->json(Settings::DASHBOARD_CACHE);
        if ($cached !== null && isset($cached['fetchedAt']) && $now - (int) $cached['fetchedAt'] < self::CACHE_SEC) {
            $data = $cached;
        } else {
            $data = $this->fetch($now);
            if (empty($data['unreachable'])) {
                $this->settings->setJson(Settings::DASHBOARD_CACHE, $data);
            }
        }
        $data['lastReplay'] = null;
        if ($this->settings->get(Settings::LAST_REPLAY_AT) !== null) {
            $data['lastReplay'] = [
                'at' => (int) $this->settings->get(Settings::LAST_REPLAY_AT),
                'reason' => (string) $this->settings->get(Settings::LAST_REPLAY_REASON),
            ];
        }

        return $data;
    }

    /**
     * @param int $now
     *
     * @return array<string, mixed>
     */
    private function fetch($now)
    {
        $data = [
            'configured' => true,
            'fetchedAt' => $now,
            'keyRejected' => false,
            'unreachable' => false,
            'account' => null,
            'ping' => null,
            'flow' => null,
            'lastFailure' => null,
        ];
        try {
            $data['account'] = self::account($this->client->account());
            $data['ping'] = $this->scenario($this->settings->get(Settings::PING_ID));
            $data['flow'] = $this->scenario($this->settings->get(Settings::FLOW_ID));
            $watched = $data['flow'] !== null ? $data['flow'] : $data['ping'];
            if ($watched !== null) {
                $data['lastFailure'] = $this->lastFailure($watched['id']);
            }
        } catch (ApiException $e) {
            $data['keyRejected'] = $e->isAuthError();
            $data['unreachable'] = !$e->isAuthError();
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    private static function account(array $raw)
    {
        $quotas = [];
        $rawQuotas = isset($raw['quotas']) && is_array($raw['quotas']) ? $raw['quotas'] : [];
        foreach (['ping', 'flow', 'chain', 'assistantTrials'] as $name) {
            if (isset($rawQuotas[$name]) && is_array($rawQuotas[$name])) {
                $quota = $rawQuotas[$name];
                $quotas[$name] = [
                    'used' => isset($quota['used']) ? (int) $quota['used'] : 0,
                    'limit' => isset($quota['limit']) ? (int) $quota['limit'] : null,
                ];
            }
        }

        return [
            'organization' => isset($raw['organization']['name']) ? (string) $raw['organization']['name'] : '',
            'space' => isset($raw['space']['name']) ? (string) $raw['space']['name'] : null,
            'plan' => isset($raw['plan']['name']) ? (string) $raw['plan']['name'] : '',
            'billingUrl' => isset($raw['billingUrl']) && Links::isPathly($raw['billingUrl']) ? $raw['billingUrl'] : Links::SITE . '/billing',
            'quotas' => $quotas,
        ];
    }

    /**
     * @param string|null $id
     *
     * @return array<string, mixed>|null
     */
    private function scenario($id)
    {
        if ($id === null) {
            return null;
        }
        try {
            $scenario = $this->client->getScenario($id);
        } catch (ApiException $e) {
            if ($e->getStatus() !== 404) {
                throw $e;
            }

            return ['id' => $id, 'status' => 'missing', 'url' => Links::scenario($id)];
        }

        return [
            'id' => $id,
            'status' => isset($scenario['lastStatus']) ? (string) $scenario['lastStatus'] : 'pending',
            'url' => Links::scenario($id),
        ];
    }

    /**
     * @param string $scenarioId
     *
     * @return array<string, mixed>|null
     */
    private function lastFailure($scenarioId)
    {
        foreach ($this->client->listRuns($scenarioId, 20) as $run) {
            $status = isset($run['status']) ? $run['status'] : null;
            if ($status !== 'fail' && $status !== 'error') {
                continue;
            }
            $detail = $this->client->getRun((string) $run['id']);
            $shot = isset($detail['screenshotUrl']) && Links::isHttps($detail['screenshotUrl']) ? $detail['screenshotUrl'] : null;
            $at = isset($run['created_at']) ? $run['created_at'] : (isset($detail['created_at']) ? $detail['created_at'] : null);

            return [
                'at' => $at === null ? null : (string) $at,
                'message' => isset($detail['message']) ? (string) $detail['message'] : '',
                'screenshotUrl' => $shot,
            ];
        }

        return null;
    }
}
