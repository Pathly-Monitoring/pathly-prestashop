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
 * The checkout Flow prepared by Lumi, then validated by the merchant in one click.
 */
class FlowService
{
    public const RUNNING = ['waiting', 'exploring', 'queued', 'replaying'];

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
     * Asks Lumi to explore the shop. Nothing starts without the owner's confirmation.
     *
     * @param string $shopUrl
     * @param int $now
     *
     * @return array<string, mixed> state: ready|running|unavailable
     */
    public function prepare($shopUrl, $now)
    {
        if ($this->settings->get(Settings::FLOW_ID) !== null) {
            return ['state' => 'ready'];
        }
        $detect = $this->client->detect($shopUrl);
        if (empty($detect['available'])) {
            return [
                'state' => 'unavailable',
                'reason' => isset($detect['reason']) ? (string) $detect['reason'] : 'off',
                'unlock' => isset($detect['unlock']) ? $detect['unlock'] : null,
            ];
        }
        $trial = $this->client->startTrial(
            $shopUrl,
            'prestashop-trial-' . sha1(Links::normalize($shopUrl)) . '-' . gmdate('YmdHi', $now)
        );
        $this->settings->set(Settings::TRIAL_ID, (string) $trial['id']);

        return ['state' => 'running', 'siteKind' => isset($detect['siteKind']) ? $detect['siteKind'] : null];
    }

    /**
     * Where Lumi stands. Checkout proposals come first.
     *
     * @return array<string, mixed>|null null when no trial is open
     */
    public function state()
    {
        $trialId = $this->settings->get(Settings::TRIAL_ID);
        if ($trialId === null) {
            return null;
        }
        try {
            $data = $this->client->getTrial($trialId);
        } catch (ApiException $e) {
            if ($e->getStatus() === 404) {
                $this->settings->set(Settings::TRIAL_ID, null);

                return null;
            }
            throw $e;
        }
        $item = isset($data['item']) && is_array($data['item']) ? $data['item'] : [];
        $status = isset($item['status']) ? (string) $item['status'] : 'failed';
        if (in_array($status, self::RUNNING, true)) {
            return ['state' => 'running', 'status' => $status];
        }
        $proposals = isset($item['proposals']) && is_array($item['proposals']) ? $item['proposals'] : [];
        if ($status !== 'ready' || $proposals === []) {
            return [
                'state' => 'failed',
                'reason' => isset($item['reason']) ? (string) $item['reason'] : $status,
            ];
        }
        $list = [];
        foreach ($proposals as $proposal) {
            $list[] = [
                'index' => (int) $proposal['index'],
                'kind' => isset($proposal['kind']) ? (string) $proposal['kind'] : 'custom',
                'steps' => isset($proposal['steps']) && is_array($proposal['steps']) ? count($proposal['steps']) : 0,
                'stoppedBefore' => isset($proposal['stoppedBefore']) ? (string) $proposal['stoppedBefore'] : null,
            ];
        }
        usort($list, function ($a, $b) {
            $rank = ($a['kind'] === 'cart' ? 0 : 1) - ($b['kind'] === 'cart' ? 0 : 1);

            return $rank !== 0 ? $rank : $a['index'] - $b['index'];
        });

        return ['state' => 'proposals', 'proposals' => $list];
    }

    /**
     * Saves the chosen proposal as the shop's Flow. Pathly runs it right away.
     *
     * @param int $index
     *
     * @return string the Flow id
     */
    public function validate($index)
    {
        $trialId = (string) $this->settings->get(Settings::TRIAL_ID);
        try {
            $saved = $this->client->saveTrial($trialId, $index);
            $flowId = (string) $saved['item']['id'];
        } catch (ApiException $e) {
            $payload = $e->getPayload();
            if ($e->getErrorCode() !== 'already_saved' || !isset($payload['monitorId'])) {
                throw $e;
            }
            $flowId = (string) $payload['monitorId'];
        }
        $this->settings->set(Settings::FLOW_ID, $flowId);
        $this->settings->set(Settings::TRIAL_ID, null);
        $this->settings->set(Settings::DASHBOARD_CACHE, null);

        return $flowId;
    }

    /**
     * Drops the open trial (nothing is deleted on Pathly).
     *
     * @return void
     */
    public function cancel()
    {
        $this->settings->set(Settings::TRIAL_ID, null);
    }
}
