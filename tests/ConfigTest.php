<?php

namespace Yansongda\Supports\Tests;

use Yansongda\Supports\Collection;
use Yansongda\Supports\Config;

class ConfigTest extends TestCase
{
    public function testBootstrap()
    {
        $config = [];

        $this->assertInstanceOf(Collection::class, new Config($config));
    }
}
