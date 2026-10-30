<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/AppNavigation.php';
Auth::startSession();unset($_SESSION['auth_user_id']);
$user=new ReflectionProperty(AppNavigation::class,'headerUser');$user->setValue(null,['first_name'=>'A & B','last_name'=>'Theatre','email'=>'a+theatre@example.test','initials'=>'AT']);
ob_start();AppNavigation::renderHeader('Theatre','A production title','/connect');$html=ob_get_clean();
$dom=new DOMDocument();@$dom->loadHTML($html);$x=new DOMXPath($dom);
$require=static function(bool $value,string $label):void{if(!$value)throw new RuntimeException($label);};
$require($x->query('//a[@class="unified-notifications-link" and @href="/connect/notifications" and @aria-label="Notifications"]/*[local-name()="svg"]')->length===1,'Bell retains notification destination and accessible name.');
$require(str_contains($html,'A &amp; B Theatre')&&str_contains($html,'a+theatre@example.test'),'Account identity is escaped and shown.');
$require($x->query('//details[@data-account-menu]/summary[@aria-expanded="false"]')->length===1,'Native keyboard-operable account disclosure.');
foreach(['/account','/notification-preferences','/help'] as $path)$require($x->query('//details//a[@href="/connect'.$path.'"]')->length===1,'Existing account destination '.$path);
$require($x->query('//details//form[@method="post" and @action="/connect/logout"]//input[@name="csrf_token"]')->item(0)?->getAttribute('value')===($_SESSION['auth_csrf']??null),'Logout remains POST with existing session CSRF token.');
$source=file_get_contents(dirname(__DIR__).'/src/AppNavigation.php');
$footer=substr($source,strpos($source,'<div class="unified-sidebar-foot">'),strpos($source,'</aside>')-strpos($source,'<div class="unified-sidebar-foot">'));
foreach(['/account','/notifications','/notification-preferences','/logout'] as $path)$require(!str_contains($footer,"url('".$path."')"),'No redundant sidebar account link '.$path);
$require(str_contains($source,"\$notificationUnread>99?'99+':\$notificationUnread"),'Bell badges cap visible counts at 99+.');
echo "PASS: shared navigation links, escaped identity, native disclosure, capped badge and POST/CSRF logout contract. No browser or database used.\n";
