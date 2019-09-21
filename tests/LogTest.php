<?php

namespace Yansongda\Supports\Tests;

use Yansongda\Supports\Log;

class LogTest extends TestCase
{
    public function testDebug()
    {
        $this->assertTrue(Log::debug('test debug', ['foo' => 'bar']));

        $log = Log::getInstance();
        $this->assertTrue($log->debug('test debug', ['foo' => 'bar']));
    }
}
