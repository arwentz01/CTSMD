<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/GeneralEmailTemplate.php';
$ok=static function(bool $condition,string $name):void{
    if(!$condition)throw new RuntimeException('FAIL '.$name);
    echo "PASS ".$name."\n";
};
putenv('ORGANIZATION_NAME=CTSMD');
putenv('ORGANIZATION_PRIMARY_COLOR=#8b233b');
$samples=[
    ['Verify your CTSMD Connect account',"Hi Alex,\n\nWelcome to CTSMD Connect. Verify your email within 24 hours to finish creating your account:\nhttps://ctsmd.me/verify-email?token=abc123&source=email\n\nAfter verification, you can set up your household and add your child profiles. Production access is assigned separately by CTSMD staff.\n\n— Children’s Theatre of Southern Maryland",'Verify my email','24 hours'],
    ['Finish your CTSMD Connect student account',"Hi Sam,\n\nYour parent or guardian invited you to finish setting up your CTSMD Connect student account. Use this link within 48 hours to verify your email and choose your own password:\nhttps://ctsmd.me/student/activate?token=abc\n\nIf you were not expecting this invitation, you can ignore it.\n\nChildren's Theatre of Southern Maryland",'Set up my account','48 hours'],
    ['Reset your CTSMD Connect password',"Hi Alex,\n\nA password reset was requested for your CTSMD Connect account. Use this private link within 2 hours:\nhttps://ctsmd.me/reset-password?token=abc\n\nIf you did not request this, you can ignore this email.",'Reset my password','2 hours'],
    ['Your CTSMD Connect invitation',"Hi Jordan,\n\nYou have been invited to CTSMD Connect. Activate your account and choose a password using this private link:\nhttps://ctsmd.me/activate?token=abc\n\nThis link expires in 7 days.",'Activate my account','7 days'],
];
foreach($samples as [$subject,$plain,$action,$duration]){
    $html=GeneralEmailTemplate::render('account_security',$subject,$plain);
    $ok(str_contains($html,'CTSMD')&&str_contains($html,'ctsmd-email-card'),$subject.' branded');
    $ok(str_contains($html,$action),$subject.' action');
    $ok(str_contains($html,$duration),$subject.' expiry');
    $ok(str_contains($html,'href="https://ctsmd.me/'),$subject.' action URL');
    $ok(!str_contains($html,'<script'),$subject.' safe markup');
}
$approved=GeneralEmailTemplate::render('account_security','Your CTSMD Connect membership is approved',"Hi Alex,\n\nYour CTSMD Connect membership has been approved. You can now access CTSMD's general member Community.\n\n— Children’s Theatre of Southern Maryland");
$ok(str_contains($approved,'Welcome to the community!'),'approval celebration');
$ok(!str_contains($approved,'ctsmd-email-cta'),'approval avoids nonexistent link');
$ok(str_contains($approved,'general member Community'),'approval instructions preserved');
$escaped=GeneralEmailTemplate::render('account_security','Your CTSMD Connect invitation',"Hi A <Person>,\n\nActivate:\nhttps://ctsmd.me/activate?token=abc&next=join");
$ok(str_contains($escaped,'&amp;next=join'),'signed URL HTML escaping');
$ok(!str_contains($escaped,'<Person>'),'dynamic content HTML escaping');
$ok(!AccountEmailTemplate::supports('An unrelated notification'),'unknown notices retain generic renderer');
echo "Account email theme checks passed\n";
