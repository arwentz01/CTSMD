<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/GeneralEmailTemplate.php';
$assert=static function(bool $ok,string $label):void{
    if(!$ok)throw new RuntimeException('FAILED: '.$label);
    echo "PASS ".$label."\n";
};
putenv('ORGANIZATION_NAME=CTSMD');
putenv('ORGANIZATION_PRIMARY_COLOR=#8b233b');
$link='https://ctsmd.me/verify-email?token=abc123&source=email';
$text="Hi Test User,\n\nPlease confirm your email:\n".$link."\n\nThis code <must stay escaped>.\n\nIf you did not request this, ignore the message.";
$html=GeneralEmailTemplate::render('account_security','Verify your email',$text);
$assert(str_contains($html,'<title>Verify your email · CTSMD</title>'),'branded verification subject');
$assert(str_contains($html,'Verify email'),'clear verification action');
$assert(str_contains($html,'https://ctsmd.me/verify-email?token=abc123&amp;source=email'),'full secure link retained and safely escaped');
$assert(!str_contains($html,'<must stay escaped>')&&str_contains($html,'&lt;must stay escaped&gt;'),'unsafe content escaped');
$assert(str_contains($html,'If you did not request this'),'security instructions preserved');
$assert(str_contains($html,'width="600"'),'shared Commerce email layout');
$normal=GeneralEmailTemplate::render('volunteer','Volunteer shift tomorrow',"Your shift is tomorrow.\nPlease sign in.");
$assert(str_contains($normal,'VOLUNTEERING')&&str_contains($normal,'Please sign in.'),'ordinary notices retain contents');
$assert(!str_contains($normal,'<a class="ctsmd-email-cta"'),'no invented action link when none supplied');
echo "General email theme checks passed\n";
