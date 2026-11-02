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
 * Availability badge in the shop footer. Off until the merchant turns it on.
 */
class BadgeService
{
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
     * Publishes the status page of the key (created when missing) and shows the badge.
     *
     * @return void
     */
    public function enable()
    {
        $pages = $this->client->listStatusPages();
        if ($pages === []) {
            $page = $this->client->createStatusPage(['public' => true]);
            $this->settings->set(Settings::STATUS_PAGE_OWNED, '1');
        } else {
            $page = $pages[0];
            if (empty($page['public'])) {
                $page = $this->client->updateStatusPage((string) $page['id'], ['public' => true]);
                $this->settings->set(Settings::STATUS_PAGE_OWNED, '1');
            }
        }
        $this->settings->set(Settings::STATUS_PAGE_ID, (string) $page['id']);
        $this->settings->set(Settings::BADGE_URL, (string) $page['badgeUrl']);
        $this->settings->set(Settings::STATUS_URL, (string) $page['url']);
        $this->settings->set(Settings::BADGE_ENABLED, '1');
    }

    /**
     * Hides the badge, and unpublishes the status page only when the module published it.
     *
     * @return void
     */
    public function disable()
    {
        $this->settings->set(Settings::BADGE_ENABLED, '0');
        $pageId = $this->settings->get(Settings::STATUS_PAGE_ID);
        if ($pageId !== null && $this->settings->flag(Settings::STATUS_PAGE_OWNED)) {
            $this->client->updateStatusPage($pageId, ['public' => false]);
            $this->settings->set(Settings::STATUS_PAGE_OWNED, null);
        }
    }

    /**
     * What the footer prints: two pathlyhq.com links, nothing else.
     *
     * @param Settings $settings
     *
     * @return array{badgeUrl: string, statusUrl: string}|null
     */
    public static function view(Settings $settings)
    {
        $badge = $settings->get(Settings::BADGE_URL);
        $status = $settings->get(Settings::STATUS_URL);
        if (!$settings->flag(Settings::BADGE_ENABLED) || !Links::isPathly($badge) || !Links::isPathly($status)) {
            return null;
        }

        return ['badgeUrl' => (string) $badge, 'statusUrl' => (string) $status];
    }
}
