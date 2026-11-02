<?php

namespace PrestaShop\Module\Pathly\Tests\Support;

use PrestaShop\Module\Pathly\Api\Client;
use PrestaShop\Module\Pathly\Config\Settings;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** @var FakeTransport */
    protected $api;

    /** @var MemoryStore */
    protected $store;

    /** @var Settings */
    protected $settings;

    /** @var Client */
    protected $client;

    protected function setUp(): void
    {
        $this->api = new FakeTransport();
        $this->store = new MemoryStore();
        $this->settings = new Settings($this->store, new ReverseCipher());
        $this->client = new Client('sp_testkey1234', $this->api, 'PathlyPrestaShop/1.0.0 PrestaShop/8.2.0');
    }

    protected function connect()
    {
        $this->settings->saveApiKey('sp_testkey1234');
    }
}
