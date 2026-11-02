<?php

use PrestaShop\Module\Pathly\Tests\Support\FakeTransport;

/**
 * The real module with the simulated API and a fixed clock.
 */
class TestablePathly extends Pathly
{
    /** @var FakeTransport */
    private $fake;

    /** @var int */
    private $clock;

    /** @var bool */
    public $failVersions = false;

    public function __construct(FakeTransport $fake, $clock)
    {
        $this->fake = $fake;
        $this->clock = $clock;
        parent::__construct();
    }

    protected function transport()
    {
        return $this->fake;
    }

    protected function now()
    {
        return $this->clock;
    }

    public function moduleVersions()
    {
        if ($this->failVersions) {
            throw new RuntimeException('database down');
        }

        return parent::moduleVersions();
    }
}
