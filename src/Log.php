<?php

namespace Yansongda\Supports;

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
     * @return bool
     */
    public function __call($method, $args): bool
    {
        $ret = call_user_func_array([self::getInstance(), $method], $args);

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
     * @return bool
     */
    public static function __callStatic($method, $args): bool
    {
        $ret = forward_static_call_array([self::getInstance(), $method], $args);

        return $ret;
    }

    /**
     * getInstance.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return \Yansongda\Supports\Logger
     */
    public static function getInstance(): Logger
    {
        if (is_null(self::$instance)) {
            self::$instance = new Logger();
        }

        return self::$instance;
    }

    /**
     * setInstance.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param \Yansongda\Supports\Logger $logger
     *
     * @throws \Exception
     */
    public static function setInstance(Logger $logger): void
    {
        self::$instance = $logger;
    }
}
