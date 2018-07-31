<?php

namespace Yansongda\Supports;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Log
{
    /**
     * Logger instance.
     *
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * Return the logger instance.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return LoggerInterface
     */
    public static function getLogger()
    {
        return self::$logger ?: self::$logger = self::createLogger();
    }

    /**
     * Set logger.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }

    /**
     * Tests if logger exists.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return bool
     */
    public static function hasLogger(): bool
    {
        return self::$logger ? true : false;
    }

    /**
     * Make a default log instance.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $file
     * @param string $identify
     * @param int    $level
     *
     * @return \Monolog\Logger
     */
    public static function createLogger($file = null, $identify = null, $level = Logger::DEBUG)
    {
        $handler = new RotatingFileHandler(
            is_null($file) ? sys_get_temp_dir().'/logs/yansongda.supports.log' : $file,
            30,
            $level
        );
        $handler->setFormatter(
            new LineFormatter("%datetime% > %level_name% > %message% %context% %extra%\n\n", null, false, true)
        );

        $logger = new Logger(is_null($identify) ? 'yansongda.supports' : $identify);
        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * Forward call.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return forward_static_call_array([self::getLogger(), $method], $args);
    }

    /**
     * Forward call.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([self::getLogger(), $method], $args);
    }
}
