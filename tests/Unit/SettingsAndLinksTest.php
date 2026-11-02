<?php

namespace PrestaShop\Module\Pathly\Tests;

use PrestaShop\Module\Pathly\Config\Settings;
use PrestaShop\Module\Pathly\Service\Links;
use PrestaShop\Module\Pathly\Tests\Support\TestCase;

class SettingsAndLinksTest extends TestCase
{
    public function testTheKeyIsStoredEncryptedWithOnlyAHint()
    {
        $this->assertFalse($this->settings->isConfigured());
        $this->assertNull($this->settings->apiKey());
        $this->settings->saveApiKey('sp_secretvalue9876');
        $this->assertNotContains('sp_secretvalue9876', $this->store->values);
        $this->assertSame('9876', $this->store->values[Settings::KEY_HINT]);
        $this->assertSame('sp_secretvalue9876', $this->settings->apiKey());
        $this->assertTrue($this->settings->isConfigured());
    }

    public function testAnUnreadableKeyCountsAsMissing()
    {
        $this->store->values[Settings::API_KEY] = 'garbage';
        $this->assertNull($this->settings->apiKey());
        $this->store->values[Settings::API_KEY] = '';
        $this->assertFalse($this->settings->isConfigured());
    }

    public function testForgetAccountKeepsOnlyTheSnapshot()
    {
        foreach (Settings::allKeys() as $key) {
            $this->store->values[$key] = 'v';
        }
        $this->settings->forgetAccount();
        $this->assertSame(
            [Settings::SNAPSHOT => 'v', Settings::SNAPSHOT_CHECKED_AT => 'v'],
            $this->store->values
        );
        $this->settings->deleteAll();
        $this->assertSame([], $this->store->values);
    }

    public function testValuesFlagsAndJson()
    {
        $this->settings->set(Settings::PING_ID, 'p1');
        $this->assertSame('p1', $this->settings->get(Settings::PING_ID));
        $this->settings->set(Settings::PING_ID, '');
        $this->assertNull($this->settings->get(Settings::PING_ID));
        $this->settings->set(Settings::PING_ID, null);
        $this->assertArrayNotHasKey(Settings::PING_ID, $this->store->values);
        $this->settings->set(Settings::BADGE_ENABLED, 1);
        $this->assertTrue($this->settings->flag(Settings::BADGE_ENABLED));
        $this->assertNull($this->settings->json(Settings::SNAPSHOT));
        $this->store->values[Settings::SNAPSHOT] = 'not json';
        $this->assertNull($this->settings->json(Settings::SNAPSHOT));
        $this->settings->setJson(Settings::SNAPSHOT, ['a' => 1]);
        $this->assertSame(['a' => 1], $this->settings->json(Settings::SNAPSHOT));
    }

    public function testSignupLinkCarriesTheSourceInTheRightLanguage()
    {
        $this->assertSame(
            'https://pathlyhq.com/de/login?mode=signup&ref=prestashop&utm_source=prestashop&utm_medium=cms_module',
            Links::signup('de')
        );
        $this->assertStringStartsWith('https://pathlyhq.com/en/login?', Links::signup('it'));
        $this->assertSame('https://pathlyhq.com/monitors/a%2Fb', Links::scenario('a/b'));
    }

    public function testNormalizeAndHost()
    {
        $this->assertSame('shop.example', Links::normalize('https://WWW.Shop.example/'));
        $this->assertSame('shop.example/fr', Links::normalize('http://shop.example/fr/'));
        $this->assertSame('', Links::normalize('not a url'));
        $this->assertSame('', Links::normalize(null));
        $this->assertSame('shop.example', Links::host('https://shop.example/fr/'));
        $this->assertSame('shop.example', Links::host('https://shop.example'));
    }

    public function testOnlyPathlyHttpsLinksAreTrusted()
    {
        $this->assertTrue(Links::isPathly('https://pathlyhq.com/status/x'));
        $this->assertTrue(Links::isPathly('https://api.pathlyhq.com/public/status/x/badge.svg'));
        $this->assertFalse(Links::isPathly('http://pathlyhq.com/status/x'));
        $this->assertFalse(Links::isPathly('https://evilpathlyhq.com/'));
        $this->assertFalse(Links::isPathly(null));
        $this->assertTrue(Links::isHttps('https://storage.example/shot.jpg'));
        $this->assertFalse(Links::isHttps('javascript:alert(1)'));
        $this->assertFalse(Links::isHttps(42));
    }
}
