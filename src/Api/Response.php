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
 * Raw HTTP answer returned by a transport.
 */
class Response
{
    /** @var int */
    public $status;

    /** @var array<string, string> lower-cased header names */
    public $headers;

    /** @var string */
    public $body;

    /**
     * @param int $status
     * @param array<string, string> $headers
     * @param string $body
     */
    public function __construct($status, array $headers, $body)
    {
        $this->status = (int) $status;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = (string) $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function json()
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param string $name
     *
     * @return string|null
     */
    public function header($name)
    {
        $key = strtolower($name);

        return isset($this->headers[$key]) ? $this->headers[$key] : null;
    }
}
