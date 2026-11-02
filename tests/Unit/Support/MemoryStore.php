<?php

namespace PrestaShop\Module\Pathly\Tests\Support;

use PrestaShop\Module\Pathly\Config\Store;

class MemoryStore implements Store
{
    /** @var array<string, string> */
    public $values = [];

    public function get($key)
    {
        return isset($this->values[$key]) ? $this->values[$key] : null;
    }

    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }

    public function delete($key)
    {
        unset($this->values[$key]);
    }
}
