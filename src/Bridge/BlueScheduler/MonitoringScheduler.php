<?php

declare(strict_types=1);

namespace VerteXVaaR\BlueLog\Bridge\BlueScheduler;

use Sentry\CheckInStatus;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\MonitorScheduleUnit;
use Sentry\SentrySdk;
use Throwable;
use VerteXVaaR\BlueScheduler\CliRequest;
use VerteXVaaR\BlueScheduler\Scheduler;
use VerteXVaaR\BlueScheduler\Task\AbstractTask;

use function ceil;
use function microtime;
use function Sentry\captureCheckIn;

readonly class MonitoringScheduler extends Scheduler
{
    protected function runTask(
        AbstractTask $task,
        int|string $identifier,
        CliRequest $cliRequest,
        array $taskConfiguration,
    ): void {
        $monitorSchedule = MonitorSchedule::interval(
            (int)ceil($taskConfiguration['interval'] / 60),
            MonitorScheduleUnit::minute(),
        );
        $monitorConfig = new MonitorConfig($monitorSchedule);

        $hub = SentrySdk::getCurrentHub();

        $checkInId = $hub->captureCheckIn(
            $identifier,
            CheckInStatus::inProgress(),
            null,
            $monitorConfig,
        );

        $start = microtime(true);
        try {
            parent::runTask($task, $identifier, $cliRequest, $taskConfiguration);
        } catch (Throwable $exception) {
            $end = microtime(true);
            $hub->captureCheckIn(
                $identifier,
                CheckInStatus::error(),
                $end - $start,
                $monitorConfig,
                $checkInId,
            );
            throw $exception;
        }

        $end = microtime(true);
        captureCheckIn(
            $identifier,
            CheckInStatus::ok(),
            $end - $start,
            $monitorConfig,
            $checkInId,
        );
    }
}
