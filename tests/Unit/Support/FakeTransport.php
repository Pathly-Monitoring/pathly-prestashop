<?php

namespace PrestaShop\Module\Pathly\Tests\Support;

use PrestaShop\Module\Pathly\Api\ApiException;
use PrestaShop\Module\Pathly\Api\Response;
use PrestaShop\Module\Pathly\Api\Transport;

/**
 * Simulated Pathly API: answers by "METHOD /path" (query string ignored) and records every call.
 */
class FakeTransport implements Transport
{
    /** @var array<string, array<int, Response|ApiException>> */
    private $routes = [];

    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: mixed}> */
    public $calls = [];

    /**
     * @param string $method
     * @param string $path
     * @param int $status
     * @param array<mixed>|string $body
     * @param array<string, string> $headers
     *
     * @return $this
     */
    public function on($method, $path, $status, $body = [], array $headers = [])
    {
        $this->routes[$method . ' ' . $path][] = new Response($status, $headers, is_string($body) ? $body : json_encode($body));

        return $this;
    }

    /**
     * Replaces every answer queued for this route.
     *
     * @return $this
     */
    public function set($method, $path, $status, $body = [])
    {
        unset($this->routes[$method . ' ' . $path]);

        return $this->on($method, $path, $status, $body);
    }

    /**
     * @return $this
     */
    public function failNetwork($method, $path)
    {
        $this->routes[$method . ' ' . $path][] = new ApiException(0, 'Pathly is unreachable: timeout', 'network');

        return $this;
    }

    public function send($method, $url, array $headers, $body = null)
    {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body === null ? null : json_decode($body, true),
        ];
        $path = (string) parse_url($url, PHP_URL_PATH);
        $key = $method . ' ' . $path;
        if (empty($this->routes[$key])) {
            return new Response(404, [], json_encode(['error' => 'no route ' . $key]));
        }
        $answer = count($this->routes[$key]) > 1 ? array_shift($this->routes[$key]) : $this->routes[$key][0];
        if ($answer instanceof ApiException) {
            throw $answer;
        }

        return $answer;
    }

    /**
     * @return array<int, string> "METHOD /path?query"
     */
    public function paths()
    {
        $out = [];
        foreach ($this->calls as $call) {
            $query = parse_url($call['url'], PHP_URL_QUERY);
            $out[] = $call['method'] . ' ' . parse_url($call['url'], PHP_URL_PATH) . ($query ? '?' . $query : '');
        }

        return $out;
    }
}
