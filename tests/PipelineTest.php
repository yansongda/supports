<?php

declare(strict_types=1);

namespace Yansongda\Supports\Tests;

use Yansongda\Supports\Pipeline;
use Yansongda\Supports\Tests\Stubs\FooPipeline;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class PipelineTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testPipelineBasicUsage()
    {
        $pipeTwo = function ($piped, $next) {
            $_SERVER['__test.pipe.two'] = $piped;

            return $next($piped);
        };

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeOne::class, $pipeTwo])
            ->then(function ($piped) {
                return $piped;
            });

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);
        static::assertSame('foo', $_SERVER['__test.pipe.two']);

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two']);
    }

    public function testPipelineThroughWithArray()
    {
        $pipeTwo = function ($piped, $next) {
            $_SERVER['__test.pipe.two'] = $piped;

            return $next($piped);
        };

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeOne::class, $pipeTwo])
            ->then(function ($piped) {
                return $piped;
            });

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);
        static::assertSame('foo', $_SERVER['__test.pipe.two']);

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two']);
    }

    public function testPipelineThroughWithVariadic()
    {
        $pipeTwo = function ($piped, $next) {
            $_SERVER['__test.pipe.two'] = $piped;

            return $next($piped);
        };

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through(PipelineTestPipeOne::class, $pipeTwo)
            ->then(function ($piped) {
                return $piped;
            });

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);
        static::assertSame('foo', $_SERVER['__test.pipe.two']);

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two']);
    }

    public function testPipelineThroughWithSinglePipe()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through(PipelineTestPipeOne::class)
            ->then(function ($piped) {
                return $piped;
            });

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineThroughWithCallableArrayPipe()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([[new PipelineTestPipeOne(), 'handle']])
            ->then(function ($piped) {
                return $piped;
            });

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineThroughWithObjectMethodCallableArray()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([new PipelineTestPipeOne(), 'handle'])
            ->then(function ($piped) {
                return $piped;
            });

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineThroughWithClosure()
    {
        $function = function ($piped, $next) {
            $_SERVER['__test.pipe.one'] = $piped;

            return $next($piped);
        };

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through($function)
            ->then(function ($piped) {
                return $piped;
            });

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineThroughWithArrayAndExtraArgumentThrowsTypeError()
    {
        // 旧实现静默丢弃额外参数（仅取 ['a']），新实现将额外参数纳入管道列表，
        // 首管道 ['a'] 非 callable 数组，执行期 then() -> carry() -> parsePipeString() 抛 TypeError。
        $pipeline = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through(['a'], 'b');

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('parsePipeString(): Argument #1 ($pipe) must be of type string, array given');

        $pipeline->then(function ($piped) {
            return $piped;
        });
    }

    public function testPipelineThroughWithNestedArrayThrowsTypeError()
    {
        // 新旧实现行为一致：一层展开后管道值仍为嵌套数组 ['a', 'b']，
        // 执行期 then() -> carry() -> parsePipeString() 收到 array 抛 TypeError。
        $pipeline = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([['a', 'b']]);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('parsePipeString(): Argument #1 ($pipe) must be of type string, array given');

        $pipeline->then(function ($piped) {
            return $piped;
        });
    }

    public function testPipelineUsageWithObjects()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([new PipelineTestPipeOne()])
            ->then(function ($piped) {
                return $piped;
            });

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithInvokableObjects()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([new PipelineTestPipeTwo()])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithCallable()
    {
        $function = function ($piped, $next) {
            $_SERVER['__test.pipe.one'] = 'foo';

            return $next($piped);
        };

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([$function])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);

        $result = (new Pipeline($this->getContainer()))
            ->send('bar')
            ->through($function)
            ->then(static function ($passable) {
                return $passable;
            });

        static::assertSame('bar', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithInvokableClass()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeTwo::class])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithParameters()
    {
        $parameters = ['one', 'two'];

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through(PipelineTestParameterPipe::class . ':' . implode(',', $parameters))
            ->then(function ($piped) {
                return $piped;
            });

        static::assertSame('foo', $result);
        static::assertEquals($parameters, $_SERVER['__test.pipe.parameters']);

        unset($_SERVER['__test.pipe.parameters']);
    }

    public function testPipelineViaChangesTheMethodBeingCalledOnThePipes()
    {
        $pipelineInstance = new Pipeline($this->getContainer());
        $result = $pipelineInstance->send('data')
            ->through(PipelineTestPipeOne::class)
            ->via('differentMethod')
            ->then(function ($piped) {
                return $piped;
            });
        static::assertSame('data', $result);
    }

    public function testPipelineThenReturnMethodRunsPipelineThenReturnsPassable()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeOne::class])
            ->then(static function ($passable) {
                return $passable;
            });

        static::assertSame('foo', $result);
        static::assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testHandleCarry()
    {
        $result = (new FooPipeline($this->getContainer()))
            ->send($id = rand(0, 99))
            ->through([PipelineTestPipeOne::class])
            ->via('incr')
            ->then(static function ($passable) {
                if (is_int($passable)) {
                    $passable += 3;
                }
                return $passable;
            });

        static::assertSame($id + 6, $result);
    }

    protected function getContainer()
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(PipelineTestPipeOne::class)->andReturn(new PipelineTestPipeOne());
        $container->shouldReceive('get')->with(PipelineTestPipeTwo::class)->andReturn(new PipelineTestPipeTwo());
        $container->shouldReceive('get')->with(PipelineTestParameterPipe::class)->andReturn(new PipelineTestParameterPipe());

        return $container;
    }
}

class PipelineTestPipeOne
{
    public function handle($piped, $next)
    {
        $_SERVER['__test.pipe.one'] = $piped;

        return $next($piped);
    }

    public function differentMethod($piped, $next)
    {
        return $next($piped);
    }

    public function incr($piped, $next)
    {
        return $next(++$piped);
    }
}

class PipelineTestPipeTwo
{
    public function __invoke($piped, $next)
    {
        $_SERVER['__test.pipe.one'] = $piped;

        return $next($piped);
    }
}

class PipelineTestParameterPipe
{
    public function handle($piped, $next, $parameter1 = null, $parameter2 = null)
    {
        $_SERVER['__test.pipe.parameters'] = [$parameter1, $parameter2];

        return $next($piped);
    }
}
