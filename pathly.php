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
if (!defined('_PS_VERSION_')) {
    exit; // @codeCoverageIgnore
}

require_once __DIR__ . '/vendor/autoload.php';

use PrestaShop\Module\Pathly\Adapter\ConfigurationStore;
use PrestaShop\Module\Pathly\Adapter\InstalledModules;
use PrestaShop\Module\Pathly\Adapter\PhpEncryptionCipher;
use PrestaShop\Module\Pathly\Api\ApiException;
use PrestaShop\Module\Pathly\Api\Client;
use PrestaShop\Module\Pathly\Api\CurlTransport;
use PrestaShop\Module\Pathly\Config\Settings;
use PrestaShop\Module\Pathly\Service\BadgeService;
use PrestaShop\Module\Pathly\Service\DashboardService;
use PrestaShop\Module\Pathly\Service\FlowService;
use PrestaShop\Module\Pathly\Service\Links;
use PrestaShop\Module\Pathly\Service\PingService;
use PrestaShop\Module\Pathly\Service\ReplayService;

class Pathly extends Module
{
    /**
     * actionModuleUpgradeAfter exists from PrestaShop 9, actionBeforeUpgradeModule
     * from PrestaShop 8. On 1.7 the comparison of versions does the work alone.
     */
    public const HOOKS = [
        'dashboardZoneOne',
        'displayBackOfficeHeader',
        'actionModuleUpgradeAfter',
        'actionBeforeUpgradeModule',
        'displayFooter',
    ];

    /** @var bool */
    private $shutdownRegistered = false;

    public function __construct()
    {
        $this->name = 'pathly';
        $this->tab = 'administration';
        $this->version = '1.0.0'; // x-release-please-version
        $this->author = 'Pathly';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.7.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Pathly - checkout monitoring');
        $this->description = $this->l('Ping checks that your shop answers. Flow walks your checkout in a real browser, up to the payment page, and replays it after every update.');
        $this->confirmUninstall = $this->l('The module settings will be deleted. Your Ping and Flow stay in your Pathly account.');
    }

    /**
     * Nothing is sent to Pathly before an administrator saves an API key.
     *
     * @return bool
     */
    public function install()
    {
        return parent::install() && $this->registerHook(self::HOOKS);
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        $this->settings()->deleteAll();

        return parent::uninstall();
    }

    // --- Back office: settings page ------------------------------------------------

    /**
     * @return string
     */
    public function getContent()
    {
        if (!$this->isAdmin()) {
            return $this->displayError($this->l('Only an administrator can set up Pathly.'));
        }
        $output = '';
        if (Tools::isSubmit('submitPathly')) {
            $output = Tools::getValue('pathly_token') === $this->formToken()
                ? $this->handle((string) Tools::getValue('pathly_action'))
                : $this->displayError($this->l('The form has expired. Reload the page and try again.'));
        }

        return $output . $this->renderSettings();
    }

    /**
     * @param string $action
     *
     * @return string
     */
    private function handle($action)
    {
        try {
            switch ($action) {
                case 'save_key':
                    return $this->saveKey(trim((string) Tools::getValue('pathly_api_key')));
                case 'disconnect':
                    $this->settings()->forgetAccount();

                    return $this->displayConfirmation($this->l('The API key is deleted from this shop.'));
                case 'lumi':
                    return $this->startLumi();
                case 'validate':
                    $this->flowService()->validate((int) Tools::getValue('pathly_index'));

                    return $this->displayConfirmation($this->l('Your Flow is saved. Pathly runs it now, then on its schedule.'));
                case 'cancel_trial':
                    $this->flowService()->cancel();

                    return '';
                case 'badge_on':
                    (new BadgeService($this->client(), $this->settings()))->enable();

                    return $this->displayConfirmation($this->l('The badge is shown in your shop footer.'));
                case 'badge_off':
                    (new BadgeService($this->client(), $this->settings()))->disable();

                    return $this->displayConfirmation($this->l('The badge is hidden.'));
                case 'replay':
                    return $this->replayService()->replay($this->l('manual'), $this->now())
                        ? $this->displayConfirmation($this->l('Pathly is running your Flow now.'))
                        : $this->displayWarning($this->l('Nothing to run yet, or a run was already started less than 10 minutes ago.'));
            }
        } catch (ApiException $e) {
            return $this->displayError($this->apiMessage($e));
        }

        return '';
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function saveKey($key)
    {
        if (!preg_match('/^sp_[A-Za-z0-9_-]{8,200}$/', $key)) {
            return $this->displayError($this->l('This is not a Pathly API key. It starts with sp_.'));
        }
        $account = $this->client($key)->account();
        $settings = $this->settings();
        $settings->saveApiKey($key);
        $settings->set(Settings::ORG_NAME, isset($account['organization']['name']) ? (string) $account['organization']['name'] : '');
        $settings->set(Settings::DASHBOARD_CACHE, null);
        $this->replayService()->baseline(_PS_VERSION_, $this->moduleVersions(), $this->now());
        $output = $this->displayConfirmation($this->l('Your shop is connected to Pathly.'));
        try {
            $ping = (new PingService($this->client(), $settings))->ensure($this->shopUrl(), $this->now());
        } catch (ApiException $e) {
            return $output . $this->displayWarning($this->l('The Ping of the shop could not be created:') . ' ' . $this->apiMessage($e));
        }

        return $output . $this->displayConfirmation($ping['created']
            ? $this->l('A Ping now checks your shop every 5 minutes.')
            : $this->l('The existing Ping of your shop is attached.'));
    }

    /**
     * @return string
     */
    private function startLumi()
    {
        if (Tools::getValue('pathly_confirm_owner') !== '1') {
            return $this->displayError($this->l('Confirm that you manage this shop.'));
        }
        $result = $this->flowService()->prepare($this->shopUrl(), $this->now());
        if ($result['state'] !== 'unavailable') {
            return '';
        }
        $reasons = [
            'quota' => $this->l('You have used all of today\'s Lumi trials. Try again tomorrow, or choose a larger plan.'),
            'unreachable' => $this->l('Pathly cannot reach your shop from the internet. Check that it is online and not in maintenance mode.'),
            'url_blocked' => $this->l('This address cannot be checked from the internet (local or private address).'),
        ];

        return $this->displayWarning(isset($reasons[$result['reason']])
            ? $reasons[$result['reason']]
            : $this->l('Lumi is paused for now. You can record your Flow in Pathly.'));
    }

    /**
     * @param ApiException $e
     *
     * @return string
     */
    private function apiMessage(ApiException $e)
    {
        if ($e->isAuthError()) {
            return $this->l('Pathly refused this API key. Check that it is valid and not revoked.');
        }
        $messages = [
            'network' => $this->l('Pathly cannot be reached from your server. Try again in a few minutes.'),
            'assistant_busy' => $this->l('Lumi is very busy. Try again in a minute.'),
            'assistant_quota' => $this->l('You have used all of today\'s Lumi trials. Try again tomorrow, or choose a larger plan.'),
            'assistant_off' => $this->l('Lumi is paused for now. You can record your Flow in Pathly.'),
            'url_blocked' => $this->l('This address cannot be checked from the internet (local or private address).'),
            'PLAN_LIMIT' => $this->l('Your Pathly plan has reached its limit. Change plan in Pathly to add more.'),
        ];
        $code = (string) $e->getErrorCode();
        if (isset($messages[$code])) {
            return $messages[$code];
        }
        if ($e->getStatus() === 403) {
            return $this->l('This API key lacks a permission. Create a key with the scopes listed in the documentation.');
        }

        return $this->l('Pathly answered with an error:') . ' ' . $e->getMessage();
    }

    /**
     * @return string
     */
    private function renderSettings()
    {
        $settings = $this->settings();
        $lumi = null;
        $lumiError = null;
        if ($settings->isConfigured() && $settings->get(Settings::FLOW_ID) === null) {
            try {
                $lumi = $this->flowService()->state();
            } catch (ApiException $e) {
                $lumiError = $this->apiMessage($e);
            }
        }
        $pingId = $settings->get(Settings::PING_ID);
        $flowId = $settings->get(Settings::FLOW_ID);
        $iso = (string) $this->context->language->iso_code;
        $this->context->smarty->assign([
            'pathly' => [
                'action' => $this->settingsLink(),
                'token' => $this->formToken(),
                'configured' => $settings->isConfigured(),
                'keyHint' => (string) $settings->get(Settings::KEY_HINT),
                'organization' => (string) $settings->get(Settings::ORG_NAME),
                'signupUrl' => Links::signup($iso),
                'shopUrl' => $this->shopUrl(),
                'pingUrl' => $pingId === null ? null : Links::scenario($pingId),
                'flowUrl' => $flowId === null ? null : Links::scenario($flowId),
                'lumi' => $lumi,
                'lumiError' => $lumiError,
                'badgeEnabled' => $settings->flag(Settings::BADGE_ENABLED),
                'jsUrl' => $this->_path . 'views/js/configure.js?v=' . $this->version,
            ],
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
    }

    // --- Back office: dashboard ----------------------------------------------------

    /**
     * @param array<string, mixed> $params
     *
     * @return string
     */
    public function hookDashboardZoneOne($params)
    {
        if (!$this->isAdmin()) {
            return '';
        }
        $data = (new DashboardService($this->client(), $this->settings()))->build($this->now());
        if (!empty($data['lastFailure']['at'])) {
            $data['lastFailure']['at'] = $this->formatDate(strtotime((string) $data['lastFailure']['at']));
        }
        if (!empty($data['lastReplay'])) {
            $data['lastReplay']['at'] = $this->formatDate($data['lastReplay']['at']);
        }
        $this->context->smarty->assign([
            'pathly' => $data,
            'pathly_settings_url' => $this->settingsLink(),
            'pathly_signup_url' => Links::signup((string) $this->context->language->iso_code),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/dashboard.tpl');
    }

    /**
     * Compares the installed versions (every 5 minutes at most) and loads the CSS.
     *
     * @param array<string, mixed> $params
     *
     * @return string
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        $this->checkVersions(false);
        if (Tools::getValue('controller') === 'AdminDashboard' || Tools::getValue('configure') === $this->name) {
            return '<link rel="stylesheet" href="' . $this->_path . 'views/css/admin.css?v=' . $this->version . '">';
        }

        return '';
    }

    // --- Updates -------------------------------------------------------------------

    /**
     * PrestaShop 9: called once a module is upgraded.
     *
     * @param array<string, mixed> $params module_name, old_version, new_version
     *
     * @return void
     */
    public function hookActionModuleUpgradeAfter($params)
    {
        $name = isset($params['module_name']) ? (string) $params['module_name'] : '';
        $old = isset($params['old_version']) ? (string) $params['old_version'] : '';
        $new = isset($params['new_version']) ? (string) $params['new_version'] : '';
        $replay = $this->replayService();
        $now = $this->now();
        $this->safely(function () use ($replay, $name, $old, $new, $now) {
            $replay->replay($name . ' ' . $old . ' → ' . $new, $now);
        });
        $this->checkVersions(true);
    }

    /**
     * PrestaShop 8: called before a module upgrade. The comparison runs at the
     * end of the request, once the new version is written.
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function hookActionBeforeUpgradeModule($params)
    {
        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function([$this, 'checkAfterUpgrade']);
        }
    }

    /**
     * @return void
     */
    public function checkAfterUpgrade()
    {
        $this->checkVersions(true);
    }

    /**
     * @param bool $force
     *
     * @return void
     */
    private function checkVersions($force)
    {
        $replay = $this->replayService();
        $modules = [$this, 'moduleVersions'];
        $now = $this->now();
        $this->safely(function () use ($replay, $modules, $now, $force) {
            $replay->check(_PS_VERSION_, $modules, $now, $force);
        });
    }

    /**
     * The back office must never break because of Pathly.
     *
     * @param callable $work
     *
     * @return void
     */
    private function safely(callable $work)
    {
        try {
            $work();
        } catch (Exception $e) {
            // Network or database error: the next check tries again.
        }
    }

    // --- Front office: badge -------------------------------------------------------

    /**
     * Off by default. No call to Pathly here: both links were stored when the badge was turned on.
     *
     * @param array<string, mixed> $params
     *
     * @return string
     */
    public function hookDisplayFooter($params)
    {
        $badge = BadgeService::view($this->settings());
        if ($badge === null) {
            return '';
        }
        $this->context->smarty->assign(['pathly_badge' => $badge]);

        return $this->display(__FILE__, 'views/templates/hook/badge.tpl');
    }

    // --- Wiring (overridden in tests) ----------------------------------------------

    /**
     * @return Settings
     */
    public function settings()
    {
        return new Settings(new ConfigurationStore(), new PhpEncryptionCipher(_NEW_COOKIE_KEY_));
    }

    /**
     * @return array<string, string>
     */
    public function moduleVersions()
    {
        return InstalledModules::versions();
    }

    /**
     * @param string|null $key
     *
     * @return Client
     */
    protected function client($key = null)
    {
        return new Client(
            $key !== null ? $key : (string) $this->settings()->apiKey(),
            $this->transport(),
            'PathlyPrestaShop/' . $this->version . ' PrestaShop/' . _PS_VERSION_
        );
    }

    /**
     * @return PrestaShop\Module\Pathly\Api\Transport
     */
    protected function transport()
    {
        return new CurlTransport(10);
    }

    /**
     * @return int
     */
    protected function now()
    {
        return time();
    }

    /**
     * @return FlowService
     */
    private function flowService()
    {
        return new FlowService($this->client(), $this->settings());
    }

    /**
     * @return ReplayService
     */
    private function replayService()
    {
        return new ReplayService($this->client(), $this->settings());
    }

    /**
     * @return string
     */
    private function shopUrl()
    {
        return (string) $this->context->shop->getBaseURL(true);
    }

    /**
     * @return bool
     */
    private function isAdmin()
    {
        return isset($this->context->employee) && $this->context->employee->isSuperAdmin();
    }

    /**
     * @return string
     */
    private function settingsLink()
    {
        return (string) $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]);
    }

    /**
     * Same token as the module page URL: tied to the employee and the shop secret.
     *
     * @return string
     */
    private function formToken()
    {
        return Tools::getAdminTokenLite('AdminModules');
    }

    /**
     * @param int|false $timestamp
     *
     * @return string
     */
    private function formatDate($timestamp)
    {
        return $timestamp ? date('Y-m-d H:i', (int) $timestamp) : '';
    }
}
