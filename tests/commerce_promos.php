<?php
declare(strict_types=1);
require_once __DIR__.'/commerce_concurrency.php';
require_once __DIR__.'/fixtures/commerce-stripe.php';
require_once dirname(__DIR__).'/src/CommerceShoppingCheckoutService.php';
$db=Database::connect(dirname(__DIR__));$f=null;$n=0;
$ok=static function(bool $pass,string $name)use(&$n):void{if(!$pass)throw new RuntimeException($name);$n++;echo "PASS $name\n";};
$reject=static function(callable $callback):bool{try{$callback();return false;}catch(RuntimeException){return true;}};
try{
    if(getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE')))throw new RuntimeException('A disposable local test database is required.');
    if(!CommercePromoService::available($db)){
        foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/migrations/121_commerce_promo_codes.sql')) as $sql)if(trim($sql)!=='')$db->exec($sql);
    }
    $f=commerceFixture($db);commerceStripeConfig();putenv('COMMERCE_REBUILT_CART_PAYMENT_ENABLED=1');
    $org=$f['org'];$admin=$f['admin'];$tester=$f['tester'];
    foreach([$f['performance'],$f['performance2']] as $performance)CommerceInventoryService::configure($db,$admin,$org,$performance,$f['prices'],'open',true,100,600,0);
    $input=['code'=>'Opening_20','kind'=>'percent','value'=>'20.00','limit'=>'1','production'=>(string)$f['productions']['A'],'expires'=>gmdate('Y-m-d',time()+86400*6)];
    CommercePromoService::create($db,$admin,$org,$input);
    $cart=(int)CommerceShoppingCartService::current($db,$admin,$org)['id'];
    CommerceShoppingCartService::add($db,$admin,$org,$cart,$f['performance'],['a1'=>'Adult','a2'=>'Student']);
    CommerceShoppingCartService::add($db,$admin,$org,$cart,$f['performance2'],['a1'=>'Adult']);
    CommercePromoService::apply($db,$admin,$org,$cart,'opening_20');
    $quote=CommercePromoService::quote($db,$org,$cart,[$f['performance']=>['subtotal'=>3200,'discount'=>0],$f['performance2']=>['subtotal'=>2000,'discount'=>0]]);
    $ok($quote['code']==='OPENING_20'&&$quote['discount']===640&&$quote['allocations']===[$f['performance']=>640],'Promotion applies only to the selected production');
    $ok($reject(fn()=>CommercePromoService::apply($db,$tester,$org,$cart,'OPENING_20')),'Another account cannot change an existing cart promo');
    $mock=new CommerceStripeFixture();$provider=$mock->adapter();
    $buy=fn($who,$cartId)=>CommerceShoppingCheckoutService::start($db,$who,$org,$cartId,'Promo Tester','patron@commerce.invalid','',$provider);
    $p=$buy($admin,$cart);$groups=json_decode($p['snapshot_json'],true,flags:JSON_THROW_ON_ERROR);
    $ok(array_sum(array_column($groups,'promo_discount'))===640,'Promotion is frozen into the payment snapshot before Stripe checkout');
    $q=$db->prepare('SELECT state,discount_minor FROM commerce_promo_claims WHERE organization_id=? AND purchase_id=?');$q->execute([$org,$p['id']]);$claim=$q->fetch();
    $ok($claim['state']==='reserved'&&(int)$claim['discount_minor']===640,'One-use code is reserved before a payment can be initiated');
    $other=(int)CommerceShoppingCartService::current($db,$tester,$org)['id'];
    CommerceShoppingCartService::add($db,$tester,$org,$other,$f['performance'],['a3'=>'Adult']);
    CommercePromoService::apply($db,$tester,$org,$other,'OPENING_20');
    $ok($reject(fn()=>$buy($tester,$other)),'One-use promo blocks a second concurrent payment checkout');
    $q=$db->prepare('SELECT COUNT(*) FROM commerce_shopping_purchases WHERE organization_id=? AND cart_id=?');$q->execute([$org,$other]);$ok(!(int)$q->fetchColumn(),'Failed single-use claim rolls back second purchase');
    $mock->pay($p['remote_session_id']);
    $paid=CommerceShoppingCheckoutService::reconcile($db,$org,(int)$p['id'],$provider);
    $q->execute([$org,$other]);$ok($paid['state']==='succeeded'&&!(int)$q->fetchColumn(),'Verified payment fulfills only the original promotion purchase');
    $q=$db->prepare('SELECT state FROM commerce_promo_claims WHERE organization_id=? AND purchase_id=?');$q->execute([$org,$p['id']]);$ok($q->fetchColumn()==='redeemed','Code is redeemed exactly once after verified fulfillment');
    $mail=$db->prepare('SELECT q.text_body FROM commerce_shopping_deliveries d JOIN email_queue q ON q.id=d.email_queue_id WHERE d.organization_id=? AND d.purchase_id=?');
    $mail->execute([$org,$p['id']]);$receipt=(string)$mail->fetchColumn();
    $ok(str_contains($receipt,'Promo code: OPENING_20')&&str_contains($receipt,'saved USD 6.40'),'Consolidated receipt identifies redeemed promo and savings');
    CommerceShoppingCheckoutService::reconcile($db,$org,(int)$p['id'],$provider);
    $q=$db->prepare("SELECT COUNT(*) FROM commerce_promo_claims WHERE organization_id=? AND state='redeemed'");$q->execute([$org]);$ok((int)$q->fetchColumn()===1,'Payment replay does not redeem a code twice');
    CommercePromoService::apply($db,$tester,$org,$other,'');
    $ok(CommercePromoService::selected($db,$org,$other)===null,'Code can be removed without losing reserved tickets');
    $bad=$input;$bad['code']='BADPERCENT';$bad['value']='100.01';
    $ok($reject(fn()=>CommercePromoService::create($db,$admin,$org,$bad)),'Discount percentage above 100 rejected');
    $bad=$input;$bad['code']='EXPIRED';$bad['expires']='2020-01-01';
    $ok($reject(fn()=>CommercePromoService::create($db,$admin,$org,$bad)),'Expired codes cannot be created');
    $all=$input;$all['code']='ALLSHOWS5';$all['kind']='amount';$all['value']='5.00';$all['limit']='0';$all['production']='0';
    CommercePromoService::create($db,$admin,$org,$all);
    CommercePromoService::apply($db,$tester,$org,$other,'allshows5');
    $allQuote=CommercePromoService::quote($db,$org,$other,[$f['performance']=>['subtotal'=>2000,'discount'=>0]]);
    $ok($allQuote['discount']===500,'Fixed-amount promotion applies to all productions');
    $ok($reject(fn()=>CommercePromoService::quote($db,$org,$other,[$f['performance']=>['subtotal'=>2000,'discount'=>50]])),'Legacy and promo discounts cannot stack');
    echo "PASS $n Commerce promo and fulfillment checks\n";
}finally{if($f)retireMyTheatreFixture($db,$f);}
