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

    public function testSerializeFunction()
    {
        self::assertStringContainsString('yansongda', serialize($this->class));
        self::assertEquals('yansongda', unserialize(serialize($this->class))->getName());
    }

    public function testUnserializeArray()
    {
        $traitStub = $this->class->unserializeArray(['name' => 'yansongda-a']);

        self::assertInstanceOf(TraitStub::class, $traitStub);
        self::assertEquals('yansongda-a', $traitStub->getName());
    }

    public function testUnserializeArrayIntKey()
    {
        $traitStub = $this->class->unserializeArray(['yansongda-a']);

        self::assertInstanceOf(TraitStub::class, $traitStub);
        self::assertEquals('yansongda', $traitStub->getName());
    }
}
