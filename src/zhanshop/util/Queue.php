<?php

namespace zhanshop\util;

use Swoole\Timer;
use zhanshop\App;
use zhanshop\Log;

class Queue
{
    public function __construct()
    {
        $queues = App::config()->get("queue", []);
        foreach ($queues as $queue) {
            $this->make($queue);
        }

        Timer::tick(3000, function () use ($queues){
            try {
                foreach ($queues as $queue) {
                    if (!App::make($queue)->isEmpty()){
                        App::make($queue)->consume();
                    }
                }
            }catch (\Throwable $e){
                Log::errorLog(5, $e->getMessage().PHP_EOL.$e->getFile().':'.$e->getLine().PHP_EOL.$e->getTraceAsString());
            }
        });

    }

    private function make(string $queue)
    {
        App::make($queue);
    }

}