<?php

namespace PrestaShop\Module\Pathly\Tests;

use PrestaShop\Module\Pathly\Config\Settings;
use PrestaShop\Module\Pathly\Service\BadgeService;
use PrestaShop\Module\Pathly\Service\DashboardService;
use PrestaShop\Module\Pathly\Tests\Support\TestCase;

class DashboardAndBadgeTest extends TestCase
{
    public const NOW = 1793600000;

    private function account()
    {
        return [
            'organization' => ['id' => 'o', 'name' => 'Boutique'],
            'space' => ['id' => 's', 'name' => 'Client A'],
            'plan' => ['id' => 'pro', 'name' => 'Pro', 'priceMonthlyEur' => 69],
            'billingUrl' => 'https://pathlyhq.com/billing',
            'quotas' => [
                'ping' => ['used' => 3, 'limit' => 50, 'remaining' => 47],
                'flow' => ['used' => 1, 'limit' => null, 'remaining' => null],
                'chain' => ['remaining' => 1],
                'runs' => 'odd',
                'assistantTrials' => 'odd',
            ],
        ];
    }

    public function testNotConfigured()
    {
        $this->assertSame(['configured' => false], (new DashboardService($this->client, $this->settings))->build(self::NOW));
    }

    public function testFullWidgetWithLastFailureAndScreenshot()
    {
        $this->connect();
        $this->settings->set(Settings::PING_ID, 'ping-1');
        $this->settings->set(Settings::FLOW_ID, 'flow-1');
        $this->settings->set(Settings::LAST_REPLAY_AT, self::NOW - 50);
        $this->settings->set(Settings::LAST_REPLAY_REASON, 'ps_checkout 1 → 2');
        $this->api
            ->on('GET', '/v1/account', 200, $this->account())
            ->on('GET', '/v1/scenarios/ping-1', 200, ['lastStatus' => 'ok'])
            ->on('GET', '/v1/scenarios/flow-1', 200, ['lastStatus' => 'fail'])
            ->on('GET', '/v1/runs', 200, ['items' => [
                ['id' => 'r0', 'status' => 'ok'],
                ['id' => 'rq'],
                ['id' => 'r1', 'status' => 'fail', 'created_at' => '2026-11-02T10:00:00Z'],
            ]])
            ->on('GET', '/v1/runs/r1', 200, ['message' => 'Add to cart missing', 'screenshotUrl' => 'https://storage.example/shot.jpg']);
        $data = (new DashboardService($this->client, $this->settings))->build(self::NOW);
        $this->assertSame('Boutique', $data['account']['organization']);
        $this->assertSame('Client A', $data['account']['space']);
        $this->assertSame('Pro', $data['account']['plan']);
        $this->assertSame(
            ['ping' => ['used' => 3, 'limit' => 50], 'flow' => ['used' => 1, 'limit' => null], 'chain' => ['used' => 0, 'limit' => null]],
            $data['account']['quotas']
        );
        $this->assertSame(['id' => 'ping-1', 'status' => 'ok', 'url' => 'https://pathlyhq.com/monitors/ping-1'], $data['ping']);
        $this->assertSame('fail', $data['flow']['status']);
        $this->assertSame(
            ['at' => '2026-11-02T10:00:00Z', 'message' => 'Add to cart missing', 'screenshotUrl' => 'https://storage.example/shot.jpg'],
            $data['lastFailure']
        );
        $this->assertSame(['at' => self::NOW - 50, 'reason' => 'ps_checkout 1 → 2'], $data['lastReplay']);
        $this->assertContains('GET /v1/runs?scenarioId=flow-1&limit=20', $this->api->paths());

        // Kept five minutes.
        $calls = count($this->api->calls);
        (new DashboardService($this->client, $this->settings))->build(self::NOW + 200);
        $this->assertCount($calls, $this->api->calls);
        (new DashboardService($this->client, $this->settings))->build(self::NOW + 400);
        $this->assertGreaterThan($calls, count($this->api->calls));
    }

    public function testPingOnlyEmptyAccountAndUnsafeScreenshot()
    {
        $this->connect();
        $this->settings->set(Settings::PING_ID, 'ping-1');
        $this->api
            ->on('GET', '/v1/account', 200, ['billingUrl' => 'https://evil.example/', 'quotas' => 'odd'])
            ->on('GET', '/v1/scenarios/ping-1', 200, [])
            ->on('GET', '/v1/runs', 200, ['items' => [['id' => 'r1', 'status' => 'error']]])
            ->on('GET', '/v1/runs/r1', 200, ['created_at' => '2026-11-01', 'screenshotUrl' => 'javascript:x']);
        $data = (new DashboardService($this->client, $this->settings))->build(self::NOW);
        $this->assertSame(
            ['organization' => '', 'space' => null, 'plan' => '', 'billingUrl' => 'https://pathlyhq.com/billing', 'quotas' => []],
            $data['account']
        );
        $this->assertSame('pending', $data['ping']['status']);
        $this->assertNull($data['flow']);
        $this->assertSame(['at' => '2026-11-01', 'message' => '', 'screenshotUrl' => null], $data['lastFailure']);
        $this->assertNull($data['lastReplay']);
    }

    public function testNoFailureNoDateAndNoScenario()
    {
        $this->connect();
        $this->api->on('GET', '/v1/account', 200, $this->account());
        $data = (new DashboardService($this->client, $this->settings))->build(self::NOW);
        $this->assertNull($data['ping']);
        $this->assertNull($data['lastFailure']);

        $this->settings->set(Settings::DASHBOARD_CACHE, null);
        $this->settings->set(Settings::PING_ID, 'ping-1');
        $this->api
            ->on('GET', '/v1/scenarios/ping-1', 200, ['lastStatus' => 'ok'])
            ->on('GET', '/v1/runs', 200, ['items' => [['id' => 'r0', 'status' => 'ok']]]);
        $this->assertNull((new DashboardService($this->client, $this->settings))->build(self::NOW)['lastFailure']);

        $this->settings->set(Settings::DASHBOARD_CACHE, null);
        $this->api
            ->set('GET', '/v1/runs', 200, ['items' => [['id' => 'r9', 'status' => 'fail']]])
            ->on('GET', '/v1/runs/r9', 200, []);
        $this->assertNull((new DashboardService($this->client, $this->settings))->build(self::NOW)['lastFailure']['at']);
    }

    public function testACacheWithoutDateIsRefreshed()
    {
        $this->connect();
        $this->settings->setJson(Settings::DASHBOARD_CACHE, ['configured' => true]);
        $this->api->on('GET', '/v1/account', 200, $this->account());
        $data = (new DashboardService($this->client, $this->settings))->build(self::NOW);
        $this->assertSame(self::NOW, $data['fetchedAt']);
    }

    public function testDeletedScenarioIsShownAsMissing()
    {
        $this->connect();
        $this->settings->set(Settings::PING_ID, 'ping-1');
        $this->api
            ->on('GET', '/v1/account', 200, $this->account())
            ->on('GET', '/v1/scenarios/ping-1', 404, ['error' => 'Scénario introuvable'])
            ->on('GET', '/v1/runs', 404, ['error' => 'Scénario introuvable']);
        $data = (new DashboardService($this->client, $this->settings))->build(self::NOW);
        $this->assertSame('missing', $data['ping']['status']);
        $this->assertTrue($data['unreachable']);
    }

    public function testRejectedKeyIsCachedButNetworkErrorsAreNot()
    {
        $this->connect();
        $this->api->on('GET', '/v1/account', 401, ['error' => 'Clé révoquée']);
        $data = (new DashboardService($this->client, $this->settings))->build(self::NOW);
        $this->assertTrue($data['keyRejected']);
        $this->assertFalse($data['unreachable']);
        $this->assertNotNull($this->settings->get(Settings::DASHBOARD_CACHE));

        $this->settings->set(Settings::DASHBOARD_CACHE, null);
        $this->settings->set(Settings::PING_ID, 'ping-1');
        $this->api
            ->set('GET', '/v1/account', 200, $this->account())
            ->on('GET', '/v1/scenarios/ping-1', 500, ['error' => 'boom']);
        $data = (new DashboardService($this->client, $this->settings))->build(self::NOW);
        $this->assertTrue($data['unreachable']);
        $this->assertNull($this->settings->get(Settings::DASHBOARD_CACHE));
    }

    public function testBadgeIsOffByDefault()
    {
        $this->assertNull(BadgeService::view($this->settings));
    }

    public function testEnableCreatesAPublicStatusPage()
    {
        $page = ['id' => 'sp1', 'public' => true, 'url' => 'https://pathlyhq.com/status/shop', 'badgeUrl' => 'https://api.pathlyhq.com/public/status/shop/badge.svg'];
        $this->api->on('GET', '/v1/status-pages', 200, ['items' => []])->on('POST', '/v1/status-pages', 201, $page);
        (new BadgeService($this->client, $this->settings))->enable();
        $this->assertSame(['public' => true], $this->api->calls[1]['body']);
        $this->assertTrue($this->settings->flag(Settings::STATUS_PAGE_OWNED));
        $this->assertSame(
            ['badgeUrl' => $page['badgeUrl'], 'statusUrl' => $page['url']],
            BadgeService::view($this->settings)
        );
    }

    public function testEnablePublishesAnExistingPageOrReusesAPublicOne()
    {
        $private = ['id' => 'sp1', 'public' => false, 'url' => 'https://pathlyhq.com/status/a', 'badgeUrl' => 'https://api.pathlyhq.com/b.svg'];
        $public = ['id' => 'sp1', 'public' => true] + $private;
        $this->api
            ->on('GET', '/v1/status-pages', 200, ['items' => [$private]])
            ->on('GET', '/v1/status-pages', 200, ['items' => [$public]])
            ->on('PATCH', '/v1/status-pages/sp1', 200, $public);
        $badge = new BadgeService($this->client, $this->settings);
        $badge->enable();
        $this->assertSame(['public' => true], $this->api->calls[1]['body']);
        $this->assertTrue($this->settings->flag(Settings::STATUS_PAGE_OWNED));

        $this->settings->set(Settings::STATUS_PAGE_OWNED, null);
        $badge->enable();
        $this->assertCount(3, $this->api->calls);
        $this->assertFalse($this->settings->flag(Settings::STATUS_PAGE_OWNED));
    }

    public function testDisableUnpublishesOnlyAPageTheModulePublished()
    {
        $this->api->on('PATCH', '/v1/status-pages/sp1', 200, []);
        $badge = new BadgeService($this->client, $this->settings);
        $badge->disable();
        $this->settings->set(Settings::STATUS_PAGE_ID, 'sp1');
        $badge->disable();
        $this->assertSame([], $this->api->calls);
        $this->settings->set(Settings::STATUS_PAGE_OWNED, '1');
        $badge->disable();
        $this->assertSame(['public' => false], $this->api->calls[0]['body']);
        $this->assertFalse($this->settings->flag(Settings::STATUS_PAGE_OWNED));
        $this->assertFalse($this->settings->flag(Settings::BADGE_ENABLED));
    }

    public function testViewRefusesLinksOutsidePathly()
    {
        $this->settings->set(Settings::BADGE_ENABLED, '1');
        $this->settings->set(Settings::BADGE_URL, 'https://evil.example/b.svg');
        $this->settings->set(Settings::STATUS_URL, 'https://pathlyhq.com/status/a');
        $this->assertNull(BadgeService::view($this->settings));
        $this->settings->set(Settings::BADGE_URL, 'https://api.pathlyhq.com/b.svg');
        $this->settings->set(Settings::STATUS_URL, 'https://evil.example/');
        $this->assertNull(BadgeService::view($this->settings));
    }
}
