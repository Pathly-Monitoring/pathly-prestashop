<?php

namespace PrestaShop\Module\Pathly\Tests;

use PrestaShop\Module\Pathly\Api\ApiException;
use PrestaShop\Module\Pathly\Config\Settings;
use PrestaShop\Module\Pathly\Service\FlowService;
use PrestaShop\Module\Pathly\Service\PingService;
use PrestaShop\Module\Pathly\Tests\Support\TestCase;

class PingAndFlowTest extends TestCase
{
    public const NOW = 1793600000;

    public function testCreatesThePingWhenNoneExists()
    {
        $this->api
            ->on('GET', '/v1/scenarios', 200, ['items' => [['id' => 'other', 'type' => 'http', 'url' => 'https://elsewhere.example']]])
            ->on('POST', '/v1/scenarios', 201, ['id' => 'ping-1']);
        $result = (new PingService($this->client, $this->settings))->ensure('https://shop.example/', self::NOW);
        $this->assertSame(['id' => 'ping-1', 'created' => true, 'flowId' => null], $result);
        $body = $this->api->calls[1]['body'];
        $this->assertSame('http', $body['type']);
        $this->assertSame('https://shop.example/', $body['url']);
        $this->assertSame(300, $body['intervalSec']);
        $this->assertSame('PrestaShop · shop.example', $body['name']);
        $this->assertRegExpCompat('/^prestashop-ping-[0-9a-f]{40}-\d{10}$/', $this->api->calls[1]['headers']['Idempotency-Key']);
        $this->assertSame('ping-1', $this->settings->get(Settings::PING_ID));
    }

    public function testAttachesTheExistingPingAndFlow()
    {
        $this->api->on('GET', '/v1/scenarios', 200, ['items' => [
            ['id' => 'flow-1', 'type' => 'browser', 'url' => 'https://www.shop.example/product/1'],
            ['id' => 'flow-2', 'type' => 'browser', 'url' => 'https://shop.example/'],
            ['id' => 'ping-1', 'type' => 'http', 'url' => 'http://www.shop.example'],
            ['id' => 'ping-2', 'type' => 'http', 'url' => 'https://shop.example/'],
            ['id' => 'odd'],
        ]]);
        $result = (new PingService($this->client, $this->settings))->ensure('https://shop.example/', self::NOW);
        $this->assertSame(['id' => 'ping-1', 'created' => false, 'flowId' => 'flow-1'], $result);
        $this->assertCount(1, $this->api->calls);
    }

    public function testKeepsAFlowAlreadyChosen()
    {
        $this->settings->set(Settings::FLOW_ID, 'mine');
        $this->api->on('GET', '/v1/scenarios', 200, ['items' => [
            ['id' => 'flow-1', 'type' => 'browser', 'url' => 'https://shop.example/'],
            ['id' => 'ping-1', 'type' => 'http', 'url' => 'https://shop.example/'],
        ]]);
        $result = (new PingService($this->client, $this->settings))->ensure('https://shop.example/', self::NOW);
        $this->assertSame('mine', $result['flowId']);
    }

    public function testPrepareDoesNothingWhenAFlowExists()
    {
        $this->settings->set(Settings::FLOW_ID, 'f');
        $this->assertSame(['state' => 'ready'], (new FlowService($this->client, $this->settings))->prepare('https://shop.example/', self::NOW));
        $this->assertSame([], $this->api->calls);
    }

    public function testPrepareStopsWhenLumiIsUnavailable()
    {
        $this->api
            ->on('POST', '/v1/flow-assistant/detect', 200, ['available' => false, 'reason' => 'quota', 'unlock' => ['plan' => 'pro']])
            ->on('POST', '/v1/flow-assistant/detect', 200, ['available' => false]);
        $flow = new FlowService($this->client, $this->settings);
        $this->assertSame(
            ['state' => 'unavailable', 'reason' => 'quota', 'unlock' => ['plan' => 'pro']],
            $flow->prepare('https://shop.example/', self::NOW)
        );
        $this->assertSame(
            ['state' => 'unavailable', 'reason' => 'off', 'unlock' => null],
            $flow->prepare('https://shop.example/', self::NOW)
        );
        $this->assertNull($this->settings->get(Settings::TRIAL_ID));
    }

    public function testPrepareStartsATrial()
    {
        $this->api
            ->on('POST', '/v1/flow-assistant/detect', 200, ['available' => true, 'siteKind' => 'prestashop'])
            ->on('POST', '/v1/flow-assistant/detect', 200, ['available' => true])
            ->on('POST', '/v1/flow-assistant/trials', 202, ['id' => 'trial-1']);
        $flow = new FlowService($this->client, $this->settings);
        $this->assertSame(['state' => 'running', 'siteKind' => 'prestashop'], $flow->prepare('https://shop.example/', self::NOW));
        $this->assertSame('trial-1', $this->settings->get(Settings::TRIAL_ID));
        $this->assertSame(['url' => 'https://shop.example/', 'confirmOwner' => true], $this->api->calls[1]['body']);
        $this->assertStringStartsWith('prestashop-trial-', $this->api->calls[1]['headers']['Idempotency-Key']);
        $this->assertSame(['state' => 'running', 'siteKind' => null], $flow->prepare('https://shop.example/', self::NOW));
    }

    public function testStateFollowsTheTrial()
    {
        $flow = new FlowService($this->client, $this->settings);
        $this->assertNull($flow->state());
        $this->settings->set(Settings::TRIAL_ID, 't1');
        $this->api
            ->on('GET', '/v1/flow-assistant/trials/t1', 200, ['item' => ['status' => 'exploring']])
            ->on('GET', '/v1/flow-assistant/trials/t1', 200, ['item' => ['status' => 'not_found', 'reason' => 'no_cart']])
            ->on('GET', '/v1/flow-assistant/trials/t1', 200, ['item' => ['status' => 'ready', 'proposals' => []]])
            ->on('GET', '/v1/flow-assistant/trials/t1', 200, [])
            ->on('GET', '/v1/flow-assistant/trials/t1', 200, ['item' => ['status' => 'ready', 'proposals' => [
                ['index' => 0, 'kind' => 'login', 'steps' => [1, 2, 3]],
                ['index' => 1, 'kind' => 'contact'],
                ['index' => 2, 'kind' => 'cart', 'steps' => [1, 2], 'stoppedBefore' => 'Pay'],
                ['index' => 3],
            ]]]);
        $this->assertSame(['state' => 'running', 'status' => 'exploring'], $flow->state());
        $this->assertSame(['state' => 'failed', 'reason' => 'no_cart'], $flow->state());
        $this->assertSame(['state' => 'failed', 'reason' => 'ready'], $flow->state());
        $this->assertSame(['state' => 'failed', 'reason' => 'failed'], $flow->state());
        $state = $flow->state();
        $this->assertSame('proposals', $state['state']);
        $this->assertSame([2, 0, 1, 3], array_column($state['proposals'], 'index'));
        $this->assertSame(
            ['index' => 2, 'kind' => 'cart', 'steps' => 2, 'stoppedBefore' => 'Pay'],
            $state['proposals'][0]
        );
        $this->assertSame('custom', $state['proposals'][3]['kind']);
    }

    public function testAVanishedTrialIsForgotten()
    {
        $this->settings->set(Settings::TRIAL_ID, 't1');
        $this->api->on('GET', '/v1/flow-assistant/trials/t1', 404, ['error' => 'Essai introuvable']);
        $this->assertNull((new FlowService($this->client, $this->settings))->state());
        $this->assertNull($this->settings->get(Settings::TRIAL_ID));
    }

    public function testOtherTrialErrorsAreRaised()
    {
        $this->settings->set(Settings::TRIAL_ID, 't1');
        $this->api->on('GET', '/v1/flow-assistant/trials/t1', 500, ['error' => 'boom']);
        $this->expectException(ApiException::class);
        (new FlowService($this->client, $this->settings))->state();
    }

    public function testValidateSavesTheFlow()
    {
        $this->settings->set(Settings::TRIAL_ID, 't1');
        $this->settings->set(Settings::DASHBOARD_CACHE, '{}');
        $this->api->on('POST', '/v1/flow-assistant/trials/t1/save', 201, ['item' => ['id' => 'flow-9', 'name' => 'cart']]);
        $this->assertSame('flow-9', (new FlowService($this->client, $this->settings))->validate(2));
        $this->assertSame(['index' => 2], $this->api->calls[0]['body']);
        $this->assertSame('flow-9', $this->settings->get(Settings::FLOW_ID));
        $this->assertNull($this->settings->get(Settings::TRIAL_ID));
        $this->assertNull($this->settings->get(Settings::DASHBOARD_CACHE));
    }

    public function testValidateAttachesAFlowAlreadySaved()
    {
        $this->settings->set(Settings::TRIAL_ID, 't1');
        $this->api->on('POST', '/v1/flow-assistant/trials/t1/save', 409, ['code' => 'already_saved', 'monitorId' => 'flow-3']);
        $this->assertSame('flow-3', (new FlowService($this->client, $this->settings))->validate(0));
    }

    public function testValidateRaisesOtherRefusals()
    {
        $this->settings->set(Settings::TRIAL_ID, 't1');
        $this->api
            ->on('POST', '/v1/flow-assistant/trials/t1/save', 403, ['code' => 'PLAN_LIMIT'])
            ->on('POST', '/v1/flow-assistant/trials/t1/save', 409, ['code' => 'already_saved']);
        $flow = new FlowService($this->client, $this->settings);
        foreach (['PLAN_LIMIT', 'already_saved'] as $code) {
            try {
                $flow->validate(0);
                $this->fail('expected an exception');
            } catch (ApiException $e) {
                $this->assertSame($code, $e->getErrorCode());
            }
        }
        $this->assertNull($this->settings->get(Settings::FLOW_ID));
    }

    public function testCancel()
    {
        $this->settings->set(Settings::TRIAL_ID, 't1');
        (new FlowService($this->client, $this->settings))->cancel();
        $this->assertNull($this->settings->get(Settings::TRIAL_ID));
    }

    private function assertRegExpCompat($pattern, $value)
    {
        $this->assertSame(1, preg_match($pattern, $value), $value);
    }
}
