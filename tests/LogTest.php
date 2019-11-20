<?php

namespace Yansongda\Supports\Tests;

use Yansongda\Supports\Log;

class LogTest extends TestCase
{
    public function testDebug()
    {
        $this->assertNull(Log::debug('test debug', ['foo' => 'bar']));

        $log = Log::getInstance();
        $this->assertNull($log->debug('test debug', ['foo' => 'bar']));
    }
}
