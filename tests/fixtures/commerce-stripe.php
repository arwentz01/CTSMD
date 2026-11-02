<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/src/CommerceStripeService.php';
function commerceStripeConfig():void
{
    putenv('COMMERCE_STRIPE_TEST_ENABLED=1');putenv('COMMERCE_STRIPE_CHECKOUT_ENABLED=1');putenv('COMMERCE_STRIPE_TEST_SECRET_KEY=sk_test_'.bin2hex(random_bytes(20)));putenv('COMMERCE_STRIPE_TEST_WEBHOOK_SECRET=whsec_'.bin2hex(random_bytes(20)));putenv('COMMERCE_STRIPE_ACCOUNT_ID=acct_synthetic');putenv('COMMERCE_PUBLIC_ORIGIN=http://127.0.0.1:8787');putenv('APP_BASE_PATH=');
}
final class CommerceStripeFixture
{
    public array $sessions=[],$returns=[],$keys=[],$requests=[];
    public bool $unavailable=false,$loseCreationResponse=false,$refundPending=false,$refundFailed=false;
    public function adapter():CommerceStripeProvider{return new CommerceStripeProvider(function($method,$path,$params,$key){return$this->request($method,$path,$params,$key);});}
    public function request(string $method,string $path,array $params,?string $key):array
    {
        if($this->unavailable)throw new RuntimeException('Synthetic provider outage');$this->requests[]=[$method,$path,$params,$key];if($path==='/v1/account')return['id'=>'acct_synthetic'];
        if($path==='/v1/checkout/sessions'&&$method==='POST'){
            if(isset($this->keys[$key]))return$this->sessions[$this->keys[$key]];$id='cs_test_'.bin2hex(random_bytes(10));$total=0;foreach($params['line_items'] as $l)$total+=$l['price_data']['unit_amount']*$l['quantity'];$session=['id'=>$id,'object'=>'checkout.session','livemode'=>false,'mode'=>'payment','status'=>'open','payment_status'=>'unpaid','amount_total'=>$total,'currency'=>'usd','metadata'=>$params['metadata'],'client_reference_id'=>$params['client_reference_id'],'payment_intent'=>null,'url'=>'https://checkout.stripe.com/c/pay/'.$id];$this->sessions[$id]=$session;$this->keys[$key]=$id;if($this->loseCreationResponse){$this->loseCreationResponse=false;throw new RuntimeException('Synthetic connection lost after creation');}return$session;
        }
        if(preg_match('~^/v1/checkout/sessions/(cs_test_[a-zA-Z0-9_]+)(/expire)?$~',$path,$m)){if(!empty($m[2]))$this->sessions[$m[1]]['status']='expired';return$this->sessions[$m[1]];}
        if($path==='/v1/refunds'&&$method==='GET')return['data'=>array_values($this->returns[$params['payment_intent']]??[]),'has_more'=>false];
        if($path==='/v1/refunds'&&$method==='POST'){foreach($this->returns[$params['payment_intent']]??[] as $r)if($r['key']===$key)return$r;$r=['id'=>'re_'.bin2hex(random_bytes(10)),'payment_intent'=>$params['payment_intent'],'amount'=>$params['amount'],'currency'=>'usd','status'=>$this->refundFailed?'failed':($this->refundPending?'pending':'succeeded'),'key'=>$key];$this->returns[$params['payment_intent']][$r['id']]=$r;return$r;}
        if(preg_match('~^/v1/payment_intents/(pi_[a-zA-Z0-9_]+)/cancel$~',$path,$m)){foreach($this->sessions as &$s)if(($s['payment_intent']['id']??null)===$m[1]){$s['payment_intent']['status']='canceled';return$s['payment_intent'];}unset($s);}
        throw new RuntimeException('Unexpected synthetic provider request');
    }
    public function pay(string $id,?int $captured=null):void
    {
        $s=&$this->sessions[$id];$amount=$s['amount_total'];$captured??=$amount;$payment='pi_'.bin2hex(random_bytes(10));$s['status']='complete';$s['payment_status']='paid';$s['payment_intent']=['id'=>$payment,'object'=>'payment_intent','livemode'=>false,'amount'=>$amount,'amount_received'=>$captured,'currency'=>$s['currency'],'metadata'=>$s['metadata'],'status'=>$captured===$amount?'succeeded':'requires_capture','latest_charge'=>['id'=>'ch_'.bin2hex(random_bytes(10)),'payment_intent'=>$payment,'livemode'=>false,'amount_captured'=>$captured,'currency'=>$s['currency'],'paid'=>true,'captured'=>true]];
    }
    public function event(string $session,string $type='checkout.session.completed',?string $id=null):array
    {
        $raw=json_encode(['id'=>$id??'evt_'.bin2hex(random_bytes(10)),'type'=>$type,'livemode'=>false,'data'=>['object'=>$this->sessions[$session]]],JSON_THROW_ON_ERROR);$timestamp=time();$sig='t='.$timestamp.',v1='.hash_hmac('sha256',$timestamp.'.'.$raw,(string)getenv('COMMERCE_STRIPE_TEST_WEBHOOK_SECRET'));return[$raw,$sig];
    }
}
