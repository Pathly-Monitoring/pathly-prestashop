<?php

namespace PrestaShop\Module\Pathly\Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Pathly\Api\ApiException;
use PrestaShop\Module\Pathly\Api\CurlTransport;

/**
 * Real cURL calls against PHP's built-in server on 127.0.0.1.
 */
class CurlTransportTest extends TestCase
{
    /** @var resource|null */
    private static $server;

    /** @var int */
    private static $port;

    public static function setUpBeforeClass(): void
    {
        self::$port = 18000 + getmypid() % 1000;
        $command = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . self::$port . ' '
            . escapeshellarg(__DIR__ . '/../fixtures/echo-server.php');
        $quiet = ['file', '/dev/null', 'w'];
        self::$server = proc_open($command, [1 => $quiet, 2 => $quiet], $pipes);
        for ($i = 0; $i < 50; ++$i) {
            $socket = @fsockopen('127.0.0.1', self::$port);
            if ($socket) {
                fclose($socket);
                break;
            }
            usleep(100000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$server);
        proc_close(self::$server);
    }

    private function url($path)
    {
        return 'http://127.0.0.1:' . self::$port . $path;
    }

    public function testSendsMethodHeadersAndBody()
    {
        $response = (new CurlTransport(5))->send('POST', $this->url('/v1/x'), ['Authorization' => 'Bearer k'], '{"a":1}');
        $this->assertSame(200, $response->status);
        $this->assertSame('yes', $response->header('X-Echo'));
        $this->assertSame(
            ['method' => 'POST', 'uri' => '/v1/x', 'auth' => 'Bearer k', 'body' => '{"a":1}'],
            $response->json()
        );
    }

    public function testGetWithoutBodyAndErrorStatus()
    {
        $response = (new CurlTransport())->send('GET', $this->url('/missing'), []);
        $this->assertSame(404, $response->status);
        $this->assertSame('', $response->json()['body']);
    }

    public function testDoesNotFollowRedirects()
    {
        $response = (new CurlTransport())->send('GET', $this->url('/redirect'), []);
        $this->assertSame(302, $response->status);
    }

    public function testNetworkFailure()
    {
        try {
            (new CurlTransport(1))->send('GET', 'http://127.0.0.1:1/', []);
            $this->fail('expected an exception');
        } catch (ApiException $e) {
            $this->assertSame(0, $e->getStatus());
            $this->assertSame('network', $e->getErrorCode());
        }
    }
}
