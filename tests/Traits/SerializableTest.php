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
        self::assertStringContainsString('yansongda', serialize($this->class));
        self::assertEquals('yansongda', unserialize(serialize($this->class))->getName());
    }
}
