<?php

namespace GDI\ApiLogger\Console;

use Illuminate\Console\Command;
use GDI\ApiLogger\Contracts\ApiLoggerInterface;

class ClearLogsCommand extends Command
{
    protected $signature   = 'apilogger:clear';
    protected $description = 'Clear all stored API log entries';

    public function handle(ApiLoggerInterface $logger): int
    {
        if (!$this->confirm('This will permanently delete all API logs. Continue?', true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $logger->clear();

        $this->info('All API logs have been cleared.');

        return self::SUCCESS;
    }
}
