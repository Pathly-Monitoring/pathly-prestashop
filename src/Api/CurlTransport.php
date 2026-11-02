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
 * cURL transport: no redirect, short timeouts, TLS verification on.
 */
class CurlTransport implements Transport
{
    /** @var int */
    private $timeout;

    /**
     * @param int $timeout seconds
     */
    public function __construct($timeout = 10)
    {
        $this->timeout = (int) $timeout;
    }

    public function send($method, $url, array $headers, $body = null)
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $received = [];
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$received) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $received[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        unset($handle);
        if ($result === false) {
            throw new ApiException(0, 'Pathly is unreachable: ' . $error, 'network');
        }

        return new Response($status, $received, (string) $result);
    }
}
