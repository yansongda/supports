<?php

declare(strict_types=1);

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
 * @method static void emergency($message, array $context = [])
 * @method static void alert($message, array $context = [])
 * @method static void critical($message, array $context = [])
 * @method static void error($message, array $context = [])
 * @method static void warning($message, array $context = [])
 * @method static void notice($message, array $context = [])
 * @method static void info($message, array $context = [])
 * @method static void debug($message, array $context = [])
 * @method static void log($message, array $context = [])
 */
class Logger
{
    /**
     * Logger instance.
     *
     * @var \Psr\Log\LoggerInterface
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
     * @var \Monolog\Handler\AbstractHandler
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
     * Bootstrap.
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    /**
     * Forward call.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @throws Exception
     */
    public function __call(string $method, array $args): void
    {
        call_user_func_array([$this->getLogger(), $method], $args);
    }

    /**
     * Set logger.
     *
     * @author yansongda <me@yansongda.cn>
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
     * @return $this
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * getConfig.
     *
     * @author yansongda <me@yansongda.cn>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
