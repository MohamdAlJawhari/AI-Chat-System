<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\IngestQueueWorker::class,
        \App\Console\Commands\SummaryQueueWorker::class,
        \App\Console\Commands\SummaryBackfill::class,
    ];
}
