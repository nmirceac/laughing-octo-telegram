<?php namespace SmsTools;

use Illuminate\Database\Eloquent\Model;

class SmsInbound extends Model
{
    public $connection = 'sqlsrv3';
    public $table = 'Communications.dbo.sms_messages';


    public function getDetailsAttribute()
    {
        return json_decode($this->attributes['details'], true);
    }

    public function setDetailsAttribute($details)
    {
        $this->attributes['details'] = json_encode($details);
    }

    public static function createFromPayload($payload) {
        $message = static::where('gateway_id', $payload['message']['id'])->first();
        if(!is_null($message)) {
            return false;
        }

        $message = new static();
        $message->gateway_id = $payload['message']['id'];
        $message->recipient = $payload['message']['recipient'];
        $message->sender = $payload['message']['sender'];
        $message->content = $payload['message']['content'];
        $message->created_at = $payload['message']['created_at'];
        $message->details = $payload['message'];
        $message->save();

        self::triggerSmsInboundEvent($message);

        return true;
    }

    public static function triggerSmsInboundEvent(\App\SmsInbound $message)
    {
        Jobs\SmsInboundEvent::dispatch($message->id);
    }

    public static function runSmsInboundEvent(\App\SmsInbound $message)
    {
        if(method_exists($message, 'afterMessageReceived')) {
            $message->afterMessageReceived();
        }
    }
}
