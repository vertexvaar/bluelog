<?php

declare(strict_types=1);

namespace VerteXVaaR\BlueLog;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Sentry\ClientBuilder;
use Sentry\Monolog\Handler;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use VerteXVaaR\BlueContainer\Generated\PackageExtras;
use VerteXVaaR\BlueSprints\Environment\Context;
use VerteXVaaR\BlueSprints\Environment\Environment;

use function CoStack\Lib\concat_paths;
use function CoStack\Lib\mkdir_deep;
use function file_exists;
use function fopen;
use function getenv;

class LoggerFactory
{
    protected array $loggers = [];

    public function __construct(
        protected readonly PackageExtras $packageExtras,
        protected readonly Environment $environment,
    ) {
    }

    public function create(string $name): LoggerInterface
    {
        if (!isset($this->loggers[$name])) {
            $logger = new Logger($name);

            $this->addLogFileHandler($logger);
            $this->addSentryHandler($logger);
            $this->addDockerHandler($logger);

            $this->loggers[$name] = $logger;
        }
        return $this->loggers[$name];
    }

    protected function addLogFileHandler(Logger $logger): void
    {
        $logsPath = $this->packageExtras->getPath($this->packageExtras->rootPackageName, 'logs')
            ?? concat_paths($this->packageExtras->rootPath, 'var/log');
        mkdir_deep($logsPath);
        $handler = new StreamHandler(fopen(concat_paths($logsPath, '/bluesprints.log'), 'ab'));
        $logger->pushHandler($handler);
    }

    protected function addSentryHandler(Logger $logger): void
    {
        $sentryDsn = getenv('SENTRY_DSN');
        if (!$sentryDsn) {
            return;
        }
        $client = ClientBuilder::create([
            'dsn' => $sentryDsn,
            // Specify a fixed sample rate
            'traces_sample_rate' => 1.0,
            // Set a sampling rate for profiling - this is relative to traces_sample_rate
            'profiles_sample_rate' => 1.0,
            'release' => '1.0.0',
            'environment' => $this->environment->context->name,
            'attach_stacktrace' => $this->environment->context !== Context::Production,
            'enable_tracing' => $this->environment->context !== Context::Production,
        ])->getClient();

        $hub = new Hub($client);
        $handler = new Handler($hub, Level::Warning);

        SentrySdk::setCurrentHub($hub);

        $logger->pushHandler($handler);
    }

    protected function addDockerHandler(Logger $logger): void
    {
        if (!file_exists('/.dockerenv')) {
            return;
        }

        $handler = new StreamHandler(fopen('php://stdout', 'ab'));
        $logger->pushHandler($handler);
    }
}
