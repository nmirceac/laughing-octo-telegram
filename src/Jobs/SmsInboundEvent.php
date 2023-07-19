<?php namespace SmsTools\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SmsInboundEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    protected $messageId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($messageId)
    {
        $this->messageId = $messageId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $message = \App\SmsInbound::find($this->messageId);
        \App\SmsInbound::runSmsInboundEvent($message);
    }
}
