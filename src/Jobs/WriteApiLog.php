<?php

namespace GDI\ApiLogger\Jobs;
 
use Illuminate\Contracts\Queue\ShouldQueue;

class WriteApiLog implements ShouldQueue
{
    public function __construct(public array $data) {}

    public function handle(GDI\ApiLogger\Contracts\ApiLoggerInterface $logger): void
    {
        $logger->save($this->data);
    }
}