<?php

namespace Yansongda\Supports\Tests;

use Yansongda\Supports\Log;

class LogTest extends TestCase
{
    public function testDebug()
    {
        $result = Log::debug('test debug', ['foo' => 'bar']);

        $this->assertTrue($result);
    }
}
