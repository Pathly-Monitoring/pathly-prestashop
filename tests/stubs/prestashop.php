<?php

/**
 * Minimal doubles of the PrestaShop classes used by the module, for unit tests only.
 * The integration tests run the module in a real PrestaShop.
 */
define('_PS_VERSION_', '8.2.0');
define('_DB_PREFIX_', 'ps_');
define('_NEW_COOKIE_KEY_', 'test-secret');

class Configuration
{
    /** @var array<string, string> */
    public static $values = [];

    public static function get($key)
    {
        return array_key_exists($key, self::$values) ? self::$values[$key] : false;
    }

    public static function updateValue($key, $value)
    {
        self::$values[$key] = (string) $value;

        return true;
    }

    public static function deleteByName($key)
    {
        unset(self::$values[$key]);

        return true;
    }
}

class Tools
{
    /** @var array<string, mixed> */
    public static $values = [];

    public static function getValue($key, $default = false)
    {
        return array_key_exists($key, self::$values) ? self::$values[$key] : $default;
    }

    public static function isSubmit($key)
    {
        return array_key_exists($key, self::$values);
    }

    public static function getAdminTokenLite($controller)
    {
        return 'token-' . $controller;
    }
}

class Db
{
    /** @var array<int, array<string, string>>|false */
    public static $rows = [];

    /** @var string */
    public static $lastQuery = '';

    public static function getInstance()
    {
        return new self();
    }

    public function executeS($sql)
    {
        self::$lastQuery = $sql;

        return self::$rows;
    }
}

class PhpEncryption
{
    public function __construct($key)
    {
    }

    public function encrypt($plain)
    {
        return 'enc:' . base64_encode($plain);
    }

    public function decrypt($cipher)
    {
        if (strpos($cipher, 'enc:') !== 0) {
            throw new Exception('Wrong key');
        }

        return base64_decode(substr($cipher, 4));
    }
}

class StubShop
{
    public $url = 'https://shop.example/';

    public function getBaseURL($ssl = false)
    {
        return $this->url;
    }
}

class StubEmployee
{
    public $superAdmin = true;

    public function isSuperAdmin()
    {
        return $this->superAdmin;
    }
}

class StubSmarty
{
    /** @var array<string, mixed> */
    public $vars = [];

    public function assign($vars)
    {
        $this->vars = array_merge($this->vars, $vars);
    }

    public function fetch($template)
    {
        return '[fetch ' . basename($template) . ']';
    }
}

class StubLink
{
    public function getAdminLink($controller, $withToken = true, $sfParams = [], $params = [])
    {
        return 'index.php?controller=' . $controller . '&' . http_build_query($params);
    }
}

class StubController
{
    /** @var string[] */
    public $css = [];

    /** @var string[] */
    public $js = [];

    public function addCSS($path, $media = 'all')
    {
        $this->css[] = $path . ' ' . $media;
    }

    public function addJS($path)
    {
        $this->js[] = $path;
    }
}

class Context
{
    /** @var Context|null */
    public static $instance;

    public $shop;
    public $employee;
    public $language;
    public $smarty;
    public $link;
    public $controller;

    public static function getContext()
    {
        if (self::$instance === null) {
            self::reset();
        }

        return self::$instance;
    }

    public static function reset()
    {
        $context = new self();
        $context->shop = new StubShop();
        $context->employee = new StubEmployee();
        $context->language = (object) ['iso_code' => 'fr'];
        $context->smarty = new StubSmarty();
        $context->link = new StubLink();
        $context->controller = new StubController();
        self::$instance = $context;
    }
}

class Module
{
    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $ps_versions_compliancy;
    public $bootstrap;
    public $displayName;
    public $description;
    public $confirmUninstall;
    public $context;
    public $local_path = '/var/www/modules/pathly/';
    public $_path = '/modules/pathly/';

    /** @var bool */
    public static $installResult = true;

    /** @var array<int, string> */
    public $registeredHooks = [];

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public function install()
    {
        return self::$installResult;
    }

    public function uninstall()
    {
        return true;
    }

    public function registerHook($hooks)
    {
        $this->registeredHooks = (array) $hooks;

        return true;
    }

    public function l($string)
    {
        return $string;
    }

    public function displayError($message)
    {
        return '[error] ' . $message;
    }

    public function displayConfirmation($message)
    {
        return '[ok] ' . $message;
    }

    public function displayWarning($message)
    {
        return '[warning] ' . $message;
    }

    public function display($file, $template)
    {
        return '[display ' . basename($template) . ']';
    }
}
