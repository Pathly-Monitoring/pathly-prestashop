<?php

namespace PrestaShop\Module\Pathly\Tests\Support;

use PrestaShop\Module\Pathly\Config\Cipher;

class ReverseCipher implements Cipher
{
    public function encrypt($plain)
    {
        return 'x' . strrev($plain);
    }

    public function decrypt($encrypted)
    {
        return strpos($encrypted, 'x') === 0 ? strrev(substr($encrypted, 1)) : null;
    }
}
