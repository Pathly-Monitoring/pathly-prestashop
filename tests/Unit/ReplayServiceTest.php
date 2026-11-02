<?php

namespace PrestaShop\Module\Pathly\Tests;

use PrestaShop\Module\Pathly\Config\Settings;
use PrestaShop\Module\Pathly\Service\ReplayService;
use PrestaShop\Module\Pathly\Tests\Support\TestCase;

class ReplayServiceTest extends TestCase
{
    public const NOW = 1793600000;

    /** @var ReplayService */
    private $replay;

    /** @var array<string, string> */
    private $modules = ['ps_checkout' => '1.0.0', 'ps_shoppingcart' => '3.0.0'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->replay = new ReplayService($this->client, $this->settings);
        $this->api->on('POST', '/v1/scenarios/flow-1/run', 200, ['ok' => true]);
        $this->api->on('POST', '/v1/scenarios/ping-1/run', 200, ['ok' => true]);
    }

    public function modules()
    {
        return $this->modules;
    }

    public function testNothingHappensBeforeConfiguration()
    {
        $this->assertNull($this->replay->check('8.2.0', [$this, 'modules'], self::NOW, true));
        $this->assertSame([], $this->store->values);
    }

    public function testFirstCheckOnlyTakesASnapshot()
    {
        $this->connect();
        $this->settings->set(Settings::FLOW_ID, 'flow-1');
        $this->assertNull($this->replay->check('8.2.0', [$this, 'modules'], self::NOW));
        $this->assertSame(['core' => '8.2.0', 'modules' => $this->modules], $this->settings->json(Settings::SNAPSHOT));
        $this->assertSame([], $this->api->calls);
    }

    public function testAModuleUpdateReplaysTheFlowOnce()
    {
        $this->connect();
        $this->settings->set(Settings::FLOW_ID, 'flow-1');
        $this->replay->baseline('8.2.0', $this->modules, self::NOW - 1000);
        $this->modules['ps_checkout'] = '1.1.0';
        $this->assertSame('ps_checkout 1.0.0 → 1.1.0', $this->replay->check('8.2.0', [$this, 'modules'], self::NOW));
        $this->assertSame(['POST /v1/scenarios/flow-1/run'], $this->api->paths());
        $this->assertSame('ps_checkout 1.0.0 → 1.1.0', $this->settings->get(Settings::LAST_REPLAY_REASON));
        $this->assertSame((string) self::NOW, $this->settings->get(Settings::LAST_REPLAY_AT));

        // A second update ten seconds later: compared because forced, but not replayed again.
        $this->modules['ps_shoppingcart'] = '3.1.0';
        $this->assertSame('ps_shoppingcart 3.0.0 → 3.1.0', $this->replay->check('8.2.0', [$this, 'modules'], self::NOW + 10, true));
        $this->assertCount(1, $this->api->calls);
    }

    public function testChecksArePaused()
    {
        $this->connect();
        $this->replay->baseline('8.2.0', $this->modules, self::NOW);
        $this->modules['ps_checkout'] = '2.0.0';
        $this->assertNull($this->replay->check('8.2.0', [$this, 'modules'], self::NOW + 60));
        $this->assertSame('1.0.0', $this->settings->json(Settings::SNAPSHOT)['modules']['ps_checkout']);
    }

    public function testCoreUpdateNewModuleAndRemovedModule()
    {
        $this->assertSame('PrestaShop 8.1.0 → 8.2.0', ReplayService::difference(['core' => '8.1.0'], ['core' => '8.2.0', 'modules' => []]));
        $this->assertSame('PrestaShop  → 8.2.0', ReplayService::difference([], ['core' => '8.2.0', 'modules' => []]));
        $this->assertSame('ps_new 1.0', ReplayService::difference(
            ['core' => '8', 'modules' => []],
            ['core' => '8', 'modules' => ['ps_new' => '1.0']]
        ));
        $this->assertSame('ps_old ×', ReplayService::difference(
            ['core' => '8', 'modules' => ['ps_old' => '1.0']],
            ['core' => '8', 'modules' => []]
        ));
        $this->assertNull(ReplayService::difference(
            ['core' => '8', 'modules' => 'broken'],
            ['core' => '8', 'modules' => []]
        ));
    }

    public function testReplayFallsBackToThePing()
    {
        $this->assertFalse($this->replay->replay('x', self::NOW));
        $this->settings->set(Settings::PING_ID, 'ping-1');
        $this->assertTrue($this->replay->replay('x', self::NOW));
        $this->assertSame(['POST /v1/scenarios/ping-1/run'], $this->api->paths());
    }

    public function testAnApiErrorDoesNotRecordTheReplay()
    {
        $this->settings->set(Settings::FLOW_ID, 'gone');
        $this->api->on('POST', '/v1/scenarios/gone/run', 404, ['error' => 'Scénario introuvable']);
        $this->assertFalse($this->replay->replay('x', self::NOW));
        $this->assertNull($this->settings->get(Settings::LAST_REPLAY_AT));
    }
}
