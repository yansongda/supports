<?php

namespace Yansongda\Supports\Tests;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractHandler;
use Psr\Log\LoggerInterface;
use Yansongda\Supports\Logger;

class LoggerTest extends TestCase
{
    protected $logger;

    public function setUp()
    {
        $this->logger = new Logger();
        $this->logger->setConfig(['file' => './test.log']);
    }

    public function testGetFormatter()
    {
        $this->assertInstanceOf(FormatterInterface::class, $this->logger->getFormatter());
    }

    public function testGetHandler()
    {
        $this->assertInstanceOf(AbstractHandler::class, $this->logger->getHandler());
    }

    public function testGetLogger()
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->logger->getLogger());
    }

    public function testDebug()
    {
        $result = $this->logger->debug('test debug', ['foo' => 'bar']);
        $this->assertNull($result);
    }

    public function testInfo()
    {
        $result = $this->logger->info('test info', ['foo' => 'bar']);
        $this->assertNull($result);
    }
}