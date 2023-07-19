<?php namespace SmsTools;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    public $table = 'sms_messages';

    const WEBHOOK_TYPE_WEBHOOK_TEST=1;
    const WEBHOOK_TYPE_MESSAGE_QUEUED=2;
    const WEBHOOK_TYPE_DELIVERY_REPORT=3;
    const WEBHOOK_TYPE_REPLY=4;
    const WEBHOOK_TYPE_INBOUND=5;

    public function getDeliveriesAttribute()
    {
        return json_decode($this->attributes['deliveries'], true);
    }

    public function setDeliveriesAttribute($deliveries)
    {
        $this->attributes['deliveries'] = json_encode($deliveries);
    }

    public function getRepliesAttribute()
    {
        return json_decode($this->attributes['replies'], true);
    }

    public function setRepliesAttribute($replies)
    {
        $this->attributes['replies'] = json_encode($replies);
    }

    public function getDetailsAttribute()
    {
        return json_decode($this->attributes['details'], true);
    }

    public function setDetailsAttribute($details)
    {
        $this->attributes['details'] = json_encode($details);
    }

    public static function queue(int $recipient, $content)
    {
        $message = new static();
        $message->content = static::cleanContent($content);
        $message->recipient = $recipient;
        $message->deliveries = [];
        $message->replies = [];
        $message->details = [];
        $message->recipient = $recipient;
        $message->save();

        dispatch(new \SmsTools\Jobs\SendToGateway($message->id));

        return $message;
    }

    public static function setWebhook()
    {
        $requestData = [
            'url'=>route(config('sms.router.namedPrefix').'.'.config('sms.router.webhookEndpoint'))
        ];

        $session = curl_init(config('sms.api.endpoint').'/webhook');
        curl_setopt ($session, CURLOPT_POST, true);
        curl_setopt ($session, CURLOPT_POSTFIELDS, $requestData);

        curl_setopt($session, CURLOPT_HTTPHEADER, array(
            'x-api-key: '.config('sms.api.key'),
            'x-api-secret: '.config('sms.api.secret')
        ));

        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($session);
        curl_close($session);

        return json_decode($response, true);
    }

    public function sendToGateway()
    {
        if(!$this->queued) {
            $requestData = [
                'recipient'=>$this->recipient,
                'content'=>$this->content
            ];

            $session = curl_init(config('sms.api.endpoint').'/send');
            curl_setopt ($session, CURLOPT_POST, true);
            curl_setopt ($session, CURLOPT_POSTFIELDS, $requestData);

            curl_setopt($session, CURLOPT_HTTPHEADER, array(
                'x-api-key: '.config('sms.api.key'),
                'x-api-secret: '.config('sms.api.secret')
            ));

            curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($session, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($session);
            curl_close($session);

            $response = json_decode($response, true);

            if(!empty($response)) {
                if(isset($response['id'])) {
                    $this->queued = true;
                    $this->gateway_id = $response['id'];
                    $this->save();
                }

                return true;
            }

            return false;
        }
    }

    public static function checkWebhookSignature()
    {
        $payload = request()->get('payload');
        $signature = request()->get('signature');

        $calculatedSignature  = md5(config('sms.api.key').'---'.trim(json_encode($payload), '{}').'---'.config('sms.api.secret'));

        if($signature != $calculatedSignature) {
            throw new \Exception('Wrong signature');
        }
    }

    public static function updateFromPayload($payload, $type=null) {
        $message = static::where('gateway_id', $payload['message']['id'])->first();
        if(is_null($message)) {
            return false;
        }

        $message->sent = $payload['message']['sent'];
        $message->delivered = $payload['message']['delivered'];
        $message->failed = $payload['message']['failed'];
        $message->replied = $payload['message']['replied'];
        $message->deliveries = $payload['message']['deliveries'];
        $message->replies = $payload['message']['replies'];
        $message->details = ['sender'=>$payload['message']['sender']];
        $message->delivered_at = $payload['message']['delivered_at'];
        $message->save();

        self::triggerPostPayloadUpdateEvent($message, $type);

        return true;
    }

    public static function triggerPostPayloadUpdateEvent(\App\SmsMessage $message, $type)
    {
        Jobs\SmsPayloadUpdateEvent::dispatch($message->id, $type);
    }

    public static function runPostPayloadUpdateEvent(\App\SmsMessage $message, $type)
    {
        switch($type) {
            case self::WEBHOOK_TYPE_MESSAGE_QUEUED :
                if(method_exists($message, 'afterMessageQueued')) {
                    $message->afterMessageQueued();
                }
                break;

            case self::WEBHOOK_TYPE_DELIVERY_REPORT :
                if(method_exists($message, 'afterMessageDelivered')) {
                    $message->afterMessageDelivered();
                }
                break;

            case self::WEBHOOK_TYPE_REPLY :
                if(method_exists($message, 'afterMessageReplied')) {
                    $replies = $message->replies;
                    $message->afterMessageReplied(end($replies));
                }
                break;

            default:
                break;
        }
    }

    public static function processRequest()
    {
        try {
            static::checkWebhookSignature();
        } catch (\Exception $e) {
            return response()->json(['error' => [
                    'message' => 'Invalid signature - unauthorized',
                    'status_code' => 403
                ]
            ], 403);
        }

        $type = request()->get('type');
        $payload = request()->get('payload');

        switch($type) {
            case self::WEBHOOK_TYPE_WEBHOOK_TEST :
                return response()->json(['tested'=>true]);
                break;

            case self::WEBHOOK_TYPE_MESSAGE_QUEUED :
                return response()->json(['success'=>static::updateFromPayload($payload, $type)]);
                break;

            case self::WEBHOOK_TYPE_DELIVERY_REPORT :
                return response()->json(['success'=>static::updateFromPayload($payload, $type)]);
                break;

            case self::WEBHOOK_TYPE_REPLY :
                return response()->json(['success'=>static::updateFromPayload($payload, $type)]);
                break;

            case self::WEBHOOK_TYPE_INBOUND :
                return response()->json(['success'=>\App\SmsInbound::createFromPayload($payload)]);
                break;

            default:
                return response()->json(['error' => [
                    'message' => 'Invalid type',
                    'status_code' => 422
                ]
                ], 403);
                break;
        }


        $message = static::where('gateway_id', $messageInfo['id']);
        if(is_null($message)) {
            return response('No message');
        }

        $delivery = new Delivery();
        $delivery->message_id = $message->id;
        $delivery->delivered = $status['delivered'];
        $delivery->failed = $status['failed'];
        $delivery->queued = $status['queued'];
        $delivery->sent = $status['sent'];
        $delivery->rejected = $status['rejected'];
        $delivery->accepted = $status['accepted'];
        $delivery->save();

        $message->sender = $details['oa'];
        if($status['sent']) {
            $message->sent = true;
        }
        if($status['delivered']) {
            $message->delivered = true;
            $message->delivered_at = $details['submitted'];
        }
        if($status['failed']) {
            $message->failed = true;
        }



        $message->update();
        return response('Ok');
    }

    public static function cleanContent($content)
    {
        $safe = iconv(mb_detect_encoding($content), "ASCII//IGNORE//TRANSLIT", trim($content));
        return trim($safe);
    }

}
