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
 * Error answered by the Pathly API, or network failure (status 0).
 * The message never contains the API key.
 */
class ApiException extends \RuntimeException
{
    /** @var int */
    private $status;

    /** @var string|null */
    private $errorCode;

    /** @var array<string, mixed> */
    private $payload;

    /** @var int|null */
    private $retryAfter;

    /**
     * @param int $status
     * @param string $message
     * @param string|null $errorCode
     * @param array<string, mixed> $payload
     * @param int|null $retryAfter
     */
    public function __construct($status, $message, $errorCode = null, array $payload = [], $retryAfter = null)
    {
        parent::__construct($message, (int) $status);
        $this->status = (int) $status;
        $this->errorCode = $errorCode;
        $this->payload = $payload;
        $this->retryAfter = $retryAfter;
    }

    /**
     * @param Response $response
     *
     * @return self
     */
    public static function fromResponse(Response $response)
    {
        $data = $response->json();
        $message = isset($data['error']) && is_string($data['error']) ? $data['error'] : 'HTTP ' . $response->status;
        $code = isset($data['code']) && is_string($data['code']) ? $data['code'] : null;
        $retry = $response->header('retry-after');

        return new self($response->status, $message, $code, $data, $retry !== null ? (int) $retry : null);
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string|null
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return int|null
     */
    public function getRetryAfter()
    {
        return $this->retryAfter;
    }

    /**
     * True when the key is missing, revoked or lacks a scope.
     *
     * @return bool
     */
    public function isAuthError()
    {
        return $this->status === 401;
    }
}
