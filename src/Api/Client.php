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

namespace PrestaShop\Module\Pathly\Api;

if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

/**
 * Small client of the public Pathly API /v1.
 *
 * The base URL is fixed: the module never calls any other host.
 */
class Client
{
    public const BASE_URL = 'https://api.pathlyhq.com';

    /** @var string */
    private $apiKey;

    /** @var Transport */
    private $transport;

    /** @var string */
    private $userAgent;

    /**
     * @param string $apiKey
     * @param Transport $transport
     * @param string $userAgent module and PrestaShop versions only
     */
    public function __construct($apiKey, Transport $transport, $userAgent)
    {
        $this->apiKey = (string) $apiKey;
        $this->transport = $transport;
        $this->userAgent = (string) $userAgent;
    }

    /**
     * @return array<string, mixed>
     */
    public function account()
    {
        return $this->request('GET', '/v1/account');
    }

    /**
     * Every scenario visible to the key (follows the pages, 10 at most).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listScenarios()
    {
        $items = [];
        $cursor = null;
        for ($page = 0; $page < 10; ++$page) {
            $query = ['limit' => 100];
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }
            $data = $this->request('GET', '/v1/scenarios?' . http_build_query($query));
            $items = array_merge($items, self::listOf($data, 'items'));
            $cursor = isset($data['nextCursor']) && is_string($data['nextCursor']) ? $data['nextCursor'] : null;
            if ($cursor === null) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>
     */
    public function getScenario($id)
    {
        return $this->request('GET', '/v1/scenarios/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $input
     * @param string $idempotencyKey
     *
     * @return array<string, mixed>
     */
    public function createScenario(array $input, $idempotencyKey)
    {
        return $this->request('POST', '/v1/scenarios', $input, ['Idempotency-Key' => $idempotencyKey]);
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>
     */
    public function runScenario($id)
    {
        return $this->request('POST', '/v1/scenarios/' . rawurlencode($id) . '/run', []);
    }

    /**
     * @param string $scenarioId
     * @param int $limit
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRuns($scenarioId, $limit = 20)
    {
        $query = http_build_query(['scenarioId' => $scenarioId, 'limit' => (int) $limit]);

        return self::listOf($this->request('GET', '/v1/runs?' . $query), 'items');
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>
     */
    public function getRun($id)
    {
        return $this->request('GET', '/v1/runs/' . rawurlencode($id));
    }

    /**
     * Does Lumi recognise the shop? Uses no trial.
     *
     * @param string $url
     *
     * @return array<string, mixed>
     */
    public function detect($url)
    {
        return $this->request('POST', '/v1/flow-assistant/detect', ['url' => $url]);
    }

    /**
     * @param string $url
     * @param string $idempotencyKey
     *
     * @return array<string, mixed> {id}
     */
    public function startTrial($url, $idempotencyKey)
    {
        return $this->request(
            'POST',
            '/v1/flow-assistant/trials',
            ['url' => $url, 'confirmOwner' => true],
            ['Idempotency-Key' => $idempotencyKey]
        );
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>
     */
    public function getTrial($id)
    {
        return $this->request('GET', '/v1/flow-assistant/trials/' . rawurlencode($id));
    }

    /**
     * @param string $id
     * @param int $index
     *
     * @return array<string, mixed>
     */
    public function saveTrial($id, $index)
    {
        return $this->request(
            'POST',
            '/v1/flow-assistant/trials/' . rawurlencode($id) . '/save',
            ['index' => (int) $index]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listStatusPages()
    {
        return self::listOf($this->request('GET', '/v1/status-pages'), 'items');
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function createStatusPage(array $input)
    {
        return $this->request('POST', '/v1/status-pages', $input);
    }

    /**
     * @param string $id
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function updateStatusPage($id, array $input)
    {
        return $this->request('PATCH', '/v1/status-pages/' . rawurlencode($id), $input);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array<string, mixed>|null $body
     * @param array<string, string> $extraHeaders
     *
     * @return array<string, mixed>
     */
    private function request($method, $path, $body = null, array $extraHeaders = [])
    {
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ], $extraHeaders);
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = (string) json_encode((object) $body);
        }
        $response = $this->transport->send($method, self::BASE_URL . $path, $headers, $payload);
        if ($response->status < 200 || $response->status >= 300) {
            throw ApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $data
     * @param string $key
     *
     * @return array<int, array<string, mixed>>
     */
    private static function listOf(array $data, $key)
    {
        if (isset($data[$key]) && is_array($data[$key])) {
            return array_values(array_filter($data[$key], 'is_array'));
        }
        if (isset($data[0])) {
            return array_values(array_filter($data, 'is_array'));
        }

        return [];
    }
}
