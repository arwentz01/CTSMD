<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/my-theatre-personas.php';
require_once dirname(__DIR__).'/src/CommerceAccess.php';
require_once dirname(__DIR__).'/src/CommercePaymentProvider.php';
$db=Database::connect(dirname(__DIR__));$fixture=null;$n=0;
$ok=static function(bool $v,string $label)use(&$n):void{if(!$v)throw new RuntimeException($label);++$n;echo "PASS $label\n";};
try{
 $fixture=myTheatreFixture($db);$admin=['id'=>$fixture['users']['Admin'],'roles'=>['administrator']];$vol=['id'=>$fixture['users']['Volunteer'],'roles'=>['volunteer']];
 putenv('COMMERCE_SANDBOX_ENABLED=0');$ok(!CommerceAccess::gate(),'Disabled by default');
 putenv('COMMERCE_SANDBOX_ENABLED=1');putenv('COMMERCE_ORGANIZATION_KEY=foundation-'.$fixture['suffix']);$org=CommerceAccess::bootstrap($db,$admin);$row=CommerceAccess::primary($db,$admin);
 $ok(!$row['ticketing_enabled']&&!$row['education_enabled']&&!$row['merchandise_enabled'],'Independent modules default disabled');
 $ok(CommerceAccess::can($db,$admin,$org,'configure'),'Configured owner administrator bootstrap');
 $ok(!CommerceAccess::can($db,$vol,$org,'sell'),'Volunteers gain no sales authority');
 $db->prepare('INSERT INTO commerce_organizations(organization_key,name) VALUES(?,?)')->execute(['other-'.bin2hex(random_bytes(6)),'Synthetic other owner']);$other=(int)$db->lastInsertId();
 $ok(!CommerceAccess::can($db,$admin,$other,'configure'),'App administrator does not inherit unrelated Commerce ownership');
 $provider=new CommerceSimulatedPaymentProvider();$now=new DateTimeImmutable();
 foreach(['success'=>'succeeded','declined'=>'failed','canceled'=>'canceled','timeout'=>'unknown'] as $scenario=>$expect)$ok($provider->inspect(['scenario'=>$scenario,'ready_at'=>$now->format('Y-m-d H:i:s')],$now)===$expect,'Simulated '.$scenario);
 $ok($provider->inspect(['scenario'=>'delayed','ready_at'=>$now->modify('+5 seconds')->format('Y-m-d H:i:s')],$now)==='pending','Delayed confirmation stays pending');
 echo "PASS $n Commerce foundation checks\n";
}finally{if($fixture)retireMyTheatreFixture($db,$fixture);}
