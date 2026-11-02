<?php

namespace PrestaShop\Module\Pathly\Tests;

use PrestaShop\Module\Pathly\Api\ApiException;
use PrestaShop\Module\Pathly\Api\Response;
use PrestaShop\Module\Pathly\Tests\Support\TestCase;

class ClientTest extends TestCase
{
    public function testOnlyCallsTheApiOfPathlyWithTheKeyAndVersions()
    {
        $this->api->on('GET', '/v1/account', 200, ['organization' => ['name' => 'Boutique']]);
        $this->assertSame('Boutique', $this->client->account()['organization']['name']);
        $call = $this->api->calls[0];
        $this->assertSame('https://api.pathlyhq.com/v1/account', $call['url']);
        $this->assertSame('Bearer sp_testkey1234', $call['headers']['Authorization']);
        $this->assertSame('PathlyPrestaShop/1.0.0 PrestaShop/8.2.0', $call['headers']['User-Agent']);
        $this->assertArrayNotHasKey('Content-Type', $call['headers']);
        $this->assertNull($call['body']);
    }

    public function testFollowsThePagesOfScenarios()
    {
        $this->api
            ->on('GET', '/v1/scenarios', 200, ['items' => [['id' => 'a'], 'junk'], 'nextCursor' => 'c1'])
            ->on('GET', '/v1/scenarios', 200, ['items' => [['id' => 'b']], 'nextCursor' => null]);
        $this->assertSame([['id' => 'a'], ['id' => 'b']], $this->client->listScenarios());
        $this->assertSame(
            ['GET /v1/scenarios?limit=100', 'GET /v1/scenarios?limit=100&cursor=c1'],
            $this->api->paths()
        );
    }

    public function testStopsAfterTenPages()
    {
        $this->api->on('GET', '/v1/scenarios', 200, ['items' => [['id' => 'a']], 'nextCursor' => 'again']);
        $this->assertCount(10, $this->client->listScenarios());
    }

    public function testListsAcceptABareArrayOrNothing()
    {
        $this->api->on('GET', '/v1/status-pages', 200, [['id' => 'p1']]);
        $this->assertSame([['id' => 'p1']], $this->client->listStatusPages());
        $this->api->on('GET', '/v1/runs', 200, ['other' => 1]);
        $this->assertSame([], $this->client->listRuns('s1', 5));
        $this->assertContains('GET /v1/runs?scenarioId=s1&limit=5', $this->api->paths());
    }

    public function testWritesSendJsonAndIdempotencyKeys()
    {
        $this->api
            ->on('POST', '/v1/scenarios', 201, ['id' => 'p'])
            ->on('POST', '/v1/scenarios/p%2F1/run', 200, ['ok' => true])
            ->on('GET', '/v1/scenarios/p', 200, ['id' => 'p'])
            ->on('GET', '/v1/runs/r1', 200, ['id' => 'r1'])
            ->on('POST', '/v1/flow-assistant/detect', 200, ['available' => true])
            ->on('POST', '/v1/flow-assistant/trials', 202, ['id' => 't'])
            ->on('GET', '/v1/flow-assistant/trials/t', 200, ['item' => []])
            ->on('POST', '/v1/flow-assistant/trials/t/save', 201, ['item' => ['id' => 'f']])
            ->on('POST', '/v1/status-pages', 201, ['id' => 'sp'])
            ->on('PATCH', '/v1/status-pages/sp', 200, ['id' => 'sp']);
        $this->client->createScenario(['name' => 'x'], 'idem-123456');
        $this->client->runScenario('p/1');
        $this->client->getScenario('p');
        $this->client->getRun('r1');
        $this->client->detect('https://shop.example/');
        $this->client->startTrial('https://shop.example/', 'idem-trial');
        $this->client->getTrial('t');
        $this->client->saveTrial('t', 2);
        $this->client->createStatusPage(['public' => true]);
        $this->client->updateStatusPage('sp', ['public' => false]);
        $calls = $this->api->calls;
        $this->assertSame('idem-123456', $calls[0]['headers']['Idempotency-Key']);
        $this->assertSame('application/json', $calls[0]['headers']['Content-Type']);
        $this->assertSame([], $calls[1]['body']);
        $this->assertSame(['url' => 'https://shop.example/'], $calls[4]['body']);
        $this->assertSame(['url' => 'https://shop.example/', 'confirmOwner' => true], $calls[5]['body']);
        $this->assertSame('idem-trial', $calls[5]['headers']['Idempotency-Key']);
        $this->assertSame(['index' => 2], $calls[7]['body']);
        $this->assertSame(['public' => false], $calls[9]['body']);
        foreach ($calls as $call) {
            $this->assertStringStartsWith('https://api.pathlyhq.com/v1/', $call['url']);
        }
    }

    public function testRefusalsBecomeApiExceptions()
    {
        $this->api->on('POST', '/v1/flow-assistant/trials', 429, ['error' => 'busy', 'code' => 'assistant_busy'], ['Retry-After' => '60']);
        try {
            $this->client->startTrial('https://shop.example/', 'idem-trial');
            $this->fail('expected an exception');
        } catch (ApiException $e) {
            $this->assertSame(429, $e->getStatus());
            $this->assertSame('assistant_busy', $e->getErrorCode());
            $this->assertSame('busy', $e->getMessage());
            $this->assertSame(60, $e->getRetryAfter());
            $this->assertSame('assistant_busy', $e->getPayload()['code']);
            $this->assertFalse($e->isAuthError());
        }
    }

    public function testErrorWithoutJsonBody()
    {
        $this->api->on('GET', '/v1/account', 401, 'nope');
        try {
            $this->client->account();
            $this->fail('expected an exception');
        } catch (ApiException $e) {
            $this->assertSame('HTTP 401', $e->getMessage());
            $this->assertNull($e->getErrorCode());
            $this->assertNull($e->getRetryAfter());
            $this->assertTrue($e->isAuthError());
        }
    }

    public function testRedirectIsNotSuccess()
    {
        $this->api->on('GET', '/v1/account', 302, '');
        $this->expectException(ApiException::class);
        $this->client->account();
    }

    public function testResponseHelpers()
    {
        $response = new Response('200', ['X-Thing' => 'v'], 'not json');
        $this->assertSame([], $response->json());
        $this->assertSame('v', $response->header('x-thing'));
        $this->assertNull($response->header('missing'));
    }
}
