<?php

namespace Yansongda\Supports;

use Monolog\Logger as BaseLogger;

/**
 * @method static bool emergency($message, array $context = array())
 * @method static bool alert($message, array $context = array())
 * @method static bool critical($message, array $context = array())
 * @method static bool error($message, array $context = array())
 * @method static bool warning($message, array $context = array())
 * @method static bool notice($message, array $context = array())
 * @method static bool info($message, array $context = array())
 * @method static bool debug($message, array $context = array())
 * @method static bool log($message, array $context = array())
 */
class Log extends Logger
{
    /**
     * instance.
     *
     * @var \Psr\Log\LoggerInterface
     */
    private static $instance;

    /**
     * Bootstrap.
     */
    private function __construct()
    {
    }

    /**
     * __call.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $method
     * @param array  $args
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        $ret = call_user_func_array([self::getInstance(), $method], $args);

        // Monolog v2 always returns null
        if (BaseLogger::API >= 2 && $ret === null) {
            return true;
        }

        return $ret;
    }

    /**
     * __callStatic.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $method
     * @param array  $args
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $ret = forward_static_call_array([self::getInstance(), $method], $args);

        // Monolog v2 always returns null
        if (BaseLogger::API >= 2 && $ret === null) {
            return true;
        }

        return $ret;
    }

    /**
     * getInstance.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @throws \Exception
     *
     * @return \Psr\Log\LoggerInterface
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = (new Logger())->getLogger();
        }

        return self::$instance;
    }
}
