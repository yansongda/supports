<?php

namespace Yansongda\Supports\Tests\Traits;

use Yansongda\Supports\Collection;
use Yansongda\Supports\Config;
use Yansongda\Supports\Tests\Stubs\TraitStub;
use Yansongda\Supports\Tests\TestCase;

class ArrayAccessTest extends TestCase
{
    protected $class;

    protected function setUp(): void
    {
        $this->class = new TraitStub();
    }

    public function testAccess()
    {
        self::assertEquals('yansongda', $this->class->name);

        $this->class->name = 'you';
        self::assertEquals('you', $this->class->name);
    }

    public function testArray()
    {
        self::assertEqualsCanonicalizing(['name' => 'yansongda', 'foo_bar' => 'name'], $this->class->toArray());
    }
}
