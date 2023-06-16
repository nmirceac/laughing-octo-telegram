<?php namespace SmsTools\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SmsPayloadUpdateEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    protected $messageId, $type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($messageId, $type)
    {
        $this->messageId = $messageId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $message = \App\SmsMessage::find($this->messageId);
        \App\SmsMessage::runPostPayloadUpdateEvent($message, $this->type);
    }
}
