<?php

namespace GDI\ApiLogger\Jobs;
 
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WriteApiLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $data) {}

    /**
     * Get the queue connection to use for this job.
     */
    public function viaConnection(): string
    {
        return config('apilogger.queue.connection', 'default');
    }

    /**
     * Get the queue name to use for this job.
     */
    public function viaQueue(): string
    {
        return config('apilogger.queue.queue', 'default');
    }

    public function handle(GDI\ApiLogger\Contracts\ApiLoggerInterface $logger): void
    {
        $logger->save($this->data);
    }
}