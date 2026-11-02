<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/CommerceShoppingPilotPolicy.php';
$keys=['COMMERCE_REBUILT_CART_PAYMENT_ENABLED','COMMERCE_STRIPE_CHECKOUT_ENABLED','COMMERCE_STRIPE_TEST_ENABLED','COMMERCE_HOSTED_CART_PAYMENT_PILOT_ENABLED'];
$original=[];foreach($keys as $key)$original[$key]=getenv($key);
$assert=static function(bool $actual,string $label):void{if(!$actual)throw new RuntimeException($label);echo "PASS $label\n";};
try{
    foreach($keys as $key)putenv($key.'=0');
    $assert(!CommerceShoppingPilotPolicy::canStartPayment(false,true),'Hosted payment is disabled by default');
    foreach($keys as $key)putenv($key.'=1');
    $assert(CommerceShoppingPilotPolicy::canStartPayment(false,true),'Explicit hosted administrator test pilot is allowed');
    $assert(!CommerceShoppingPilotPolicy::canStartPayment(false,false),'Non-administrators cannot initiate hosted pilot payment');
    $assert(CommerceShoppingPilotPolicy::canStartPayment(true,false),'Local restricted simulator remains usable');
    foreach($keys as $key){putenv($key.'=0');$assert(!CommerceShoppingPilotPolicy::canStartPayment(false,true),'Missing '.$key.' blocks hosted payment');putenv($key.'=1');}
    putenv('COMMERCE_HOSTED_CART_PAYMENT_PILOT_ENABLED=0');
    $assert(CommerceShoppingPilotPolicy::canStartPayment(true,false),'Local tests do not require hosted activation');
}finally{foreach($original as $key=>$value)putenv($value===false?$key:$key.'='.$value);}
