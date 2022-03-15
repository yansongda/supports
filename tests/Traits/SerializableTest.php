<?php

namespace Yansongda\Supports\Tests\Traits;

use PHPUnit\Framework\TestCase;
use Yansongda\Supports\Tests\Stubs\TraitStub;

class SerializableTest extends TestCase
{
    protected $class;

    protected function setUp(): void
    {
        $this->class = new TraitStub();
    }

    public function test()
    {
        $s = 'O:4:"Test":1:{s:4:"name";s:9:"yansongda";}';

        self::assertEquals($s, serialize($this->class));
        self::assertEquals('yansongda', unserialize($s)->getName());
    }
}
