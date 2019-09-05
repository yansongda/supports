<?php

namespace Yansongda\Supports;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as BaseLogger;
use Psr\Log\LoggerInterface;

/**
 * @method static void emergency($message, array $context = array())
 * @method static void alert($message, array $context = array())
 * @method static void critical($message, array $context = array())
 * @method static void error($message, array $context = array())
 * @method static void warning($message, array $context = array())
 * @method static void notice($message, array $context = array())
 * @method static void info($message, array $context = array())
 * @method static void debug($message, array $context = array())
 * @method static void log($message, array $context = array())
 */
class Logger
{
    /**
     * Logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Forward call.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $method
     * @param array  $args
     *
     * @throws Exception
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $logger = new static();

        return forward_static_call_array([$logger->getLogger(), $method], $args);
    }

    /**
     * Forward call.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $method
     * @param array  $args
     *
     * @throws Exception
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->getLogger(), $method], $args);
    }

    /**
     * Return the logger instance.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @throws Exception
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (is_null($this->logger)) {
            $this->logger = $this->createDefaultLogger();
        }

        return $this->logger;
    }

    /**
     * Set logger.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Tests if logger exists.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return bool
     */
    public function hasLogger()
    {
        return $this->logger ? true : false;
    }

    /**
     * Make a default log instance.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string     $file
     * @param string     $identify
     * @param int|string $level
     * @param string     $type
     * @param int        $max_files
     *
     * @throws Exception
     *
     * @return BaseLogger
     */
    public function createDefaultLogger($file = null, $identify = 'yansongda.supports', $level = BaseLogger::DEBUG, $type = 'daily', $max_files = 30)
    {
        $file = is_null($file) ? sys_get_temp_dir().'/logs/'.$identify.'.log' : $file;

        $handler = $type === 'single' ? new StreamHandler($file, $level) : new RotatingFileHandler($file, $max_files, $level);

        $handler->setFormatter(
            new LineFormatter("%datetime% > %channel%.%level_name% > %message% %context% %extra%\n\n", null, false, true)
        );

        $logger = new BaseLogger($identify);
        $logger->pushHandler($handler);

        return $logger;
    }
}
