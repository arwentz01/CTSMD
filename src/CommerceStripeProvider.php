<?php
declare(strict_types=1);
require_once __DIR__.'/CommercePaymentProvider.php';
/** Transport injection is a PHP-only test seam; no request/environment override of Stripe's host. */
final class CommerceStripeProvider implements CommerceHostedPaymentProvider
{
    private bool $accountChecked=false;
    public function __construct(private ?Closure $transport=null){}
    public static function configured():bool
    {
        return getenv('COMMERCE_STRIPE_TEST_ENABLED')==='1'&&preg_match('/^(?:sk|rk)_test_[A-Za-z0-9]+$/',(string)getenv('COMMERCE_STRIPE_TEST_SECRET_KEY'))===1&&preg_match('/^acct_[A-Za-z0-9]+$/',(string)getenv('COMMERCE_STRIPE_ACCOUNT_ID'))===1&&str_starts_with((string)getenv('COMMERCE_STRIPE_TEST_WEBHOOK_SECRET'),'whsec_');
    }
    private function request(string $method,string $path,array $params=[],?string $key=null):array
    {
        if(!self::configured())throw new RuntimeException('Stripe test configuration is unavailable. Live keys are never accepted.');
        if(!$this->accountChecked&&$path!=='/v1/account'){$a=$this->request('GET','/v1/account');if(($a['id']??null)!==getenv('COMMERCE_STRIPE_ACCOUNT_ID'))throw new RuntimeException('Stripe merchant account does not match this installation.');$this->accountChecked=true;}
        if($this->transport)return($this->transport)($method,$path,$params,$key);
        $url='https://api.stripe.com'.$path;if($method==='GET'&&$params)$url.='?'.http_build_query($params);$headers=['Authorization: Bearer '.getenv('COMMERCE_STRIPE_TEST_SECRET_KEY'),'Stripe-Version: 2026-10-28.endive'];if($key)$headers[]='Idempotency-Key: '.$key;
        $ch=curl_init($url);$body='';curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body){if(strlen($body)+strlen($chunk)>1048576)return 0;$body.=$chunk;return strlen($chunk);}]);if($method==='POST')curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($params));$done=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($done===false||$status<200||$status>=300)throw new RuntimeException('Stripe inquiry is unresolved. Inventory remains protected; retry reconciliation.');
        try{$value=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Stripe returned an unreadable result. Inventory remains protected.');}if(!is_array($value))throw new RuntimeException('Invalid Stripe response.');return$value;
    }
    public function createCheckout(array $parameters,string $key):array{return$this->request('POST','/v1/checkout/sessions',$parameters,$key);}
    public function checkout(string $id):array
    {self::id($id,'cs_test_');return$this->request('GET','/v1/checkout/sessions/'.$id,['expand'=>['payment_intent.latest_charge']]);}
    public function expire(string $id,string $key):array{self::id($id,'cs_test_');return$this->request('POST','/v1/checkout/sessions/'.$id.'/expire',[],$key);}
    public function cancelIntent(string $id,string $key):array{self::id($id,'pi_');return$this->request('POST','/v1/payment_intents/'.$id.'/cancel',[],$key);}
    public function refunds(string $payment):array{self::id($payment,'pi_');return$this->request('GET','/v1/refunds',['payment_intent'=>$payment,'limit'=>100]);}
    public function refund(string $payment,int $amount,string $key,array $metadata):array{self::id($payment,'pi_');return$this->request('POST','/v1/refunds',['payment_intent'=>$payment,'amount'=>$amount,'metadata'=>$metadata],$key);}
    public function inspect(array $attempt,DateTimeImmutable $now):string{throw new LogicException('Stripe requires authoritative Checkout and capture validation, not scenario inspection.');}
    public static function id(string $id,string $prefix):void{if(!preg_match('/^'.preg_quote($prefix,'/').'[A-Za-z0-9_]{1,240}$/',$id))throw new RuntimeException('Invalid Stripe object identifier.');}
    public function verifyWebhook(string $raw,string $signature,int $now):array
    {
        if(!self::configured()||strlen($raw)>1048576||strlen($signature)>4096)throw new RuntimeException('Webhook unavailable.');$timestamp=null;$signatures=[];foreach(explode(',',$signature) as $part){$parts=explode('=',trim($part),2);if(count($parts)!==2)continue;if($parts[0]==='t'&&ctype_digit($parts[1]))$timestamp=(int)$parts[1];if($parts[0]==='v1')$signatures[]=$parts[1];}if(!$timestamp||abs($now-$timestamp)>300)throw new RuntimeException('Webhook signature is invalid or stale.');$expected=hash_hmac('sha256',$timestamp.'.'.$raw,(string)getenv('COMMERCE_STRIPE_TEST_WEBHOOK_SECRET'));$valid=false;foreach($signatures as $candidate)$valid=$valid||hash_equals($expected,$candidate);if(!$valid)throw new RuntimeException('Webhook signature is invalid.');
        $event=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($event)||($event['livemode']??null)!==false||!is_string($event['id']??null)||!preg_match('/^evt_[A-Za-z0-9_]{1,240}$/',$event['id'])||!is_array($event['data']['object']??null))throw new RuntimeException('Only signed Stripe test events are accepted.');if(isset($event['account'])&&$event['account']!==getenv('COMMERCE_STRIPE_ACCOUNT_ID'))throw new RuntimeException('Webhook merchant mismatch.');return$event;
    }
}
