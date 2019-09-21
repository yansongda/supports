<?php

namespace Yansongda\Supports;

use Exception;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as BaseLogger;
use Psr\Log\LoggerInterface;

/**
 * @method bool emergency($message, array $context = array())
 * @method bool alert($message, array $context = array())
 * @method bool critical($message, array $context = array())
 * @method bool error($message, array $context = array())
 * @method bool warning($message, array $context = array())
 * @method bool notice($message, array $context = array())
 * @method bool info($message, array $context = array())
 * @method bool debug($message, array $context = array())
 * @method bool log($message, array $context = array())
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
     * formatter.
     *
     * @var \Monolog\Formatter\FormatterInterface
     */
    protected $formatter;

    /**
     * handler.
     *
     * @var AbstractHandler
     */
    protected $handler;

    /**
     * config.
     *
     * @var array
     */
    protected $config = [
        'file' => null,
        'identify' => 'yansongda.supports',
        'level' => BaseLogger::DEBUG,
        'type' => 'daily',
        'max_files' => 30,
    ];

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
     * @return bool
     */
    public function __call($method, $args): bool
    {
        $ret = call_user_func_array([$this->getLogger(), $method], $args);

        // Monolog v2 always returns null
        if (BaseLogger::API >= 2 && null === $ret) {
            return true;
        }

        return $ret;
    }

    /**
     * Set logger.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param LoggerInterface $logger
     *
     * @return Logger
     */
    public function setLogger(LoggerInterface $logger): Logger
    {
        $this->logger = $logger;

        return $this;
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
    public function getLogger(): LoggerInterface
    {
        if (is_null($this->logger)) {
            $this->logger = $this->createLogger();
        }

        return $this->logger;
    }

    /**
     * Make a default log instance.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @throws Exception
     *
     * @return BaseLogger
     */
    public function createLogger(): BaseLogger
    {
        $handler = $this->getHandler();

        $handler->setFormatter($this->getFormatter());

        $logger = new BaseLogger($this->config['identify']);

        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * setFormatter.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param \Monolog\Formatter\FormatterInterface $formatter
     *
     * @return $this
     */
    public function setFormatter(FormatterInterface $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * getFormatter.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return \Monolog\Formatter\FormatterInterface
     */
    public function getFormatter(): FormatterInterface
    {
        if (is_null($this->formatter)) {
            $this->formatter = $this->createFormatter();
        }

        return $this->formatter;
    }

    /**
     * createFormatter.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return \Monolog\Formatter\LineFormatter
     */
    public function createFormatter(): LineFormatter
    {
        return new LineFormatter(
            "%datetime% > %channel%.%level_name% > %message% %context% %extra%\n\n",
            null,
            false,
            true
        );
    }

    /**
     * setHandler.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param \Monolog\Handler\AbstractHandler $handler
     *
     * @return $this
     */
    public function setHandler(AbstractHandler $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * getHandler.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @throws \Exception
     *
     * @return AbstractHandler
     */
    public function getHandler(): AbstractHandler
    {
        if (is_null($this->handler)) {
            $this->handler = $this->createHandler();
        }

        return $this->handler;
    }

    /**
     * createHandler.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @throws \Exception
     *
     * @return \Monolog\Handler\RotatingFileHandler|\Monolog\Handler\StreamHandler
     */
    public function createHandler(): AbstractHandler
    {
        $file = $this->config['file'] ?? sys_get_temp_dir().'/logs/'.$this->config['identify'].'.log';

        if ('single' === $this->config['type']) {
            return new StreamHandler($file, $this->config['level']);
        }

        return new RotatingFileHandler($file, $this->config['max_files'], $this->config['level']);
    }

    /**
     * setConfig.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param array $config
     *
     * @return $this
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }
}
