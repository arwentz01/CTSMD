<?php
declare(strict_types=1);
require_once __DIR__.'/WorkspaceUI.php';

/** Member presentation only. Routes and services remain responsible for authorization. */
final class MemberUI
{
    private static bool $enabled=false;
    private static string $route='';
    public const ROUTES=[
        '/my-theatre'=>'My Theatre','/app'=>'Home','/family-hub'=>'Household','/parent'=>'Household','/onboarding'=>'Household','/family/manage'=>'Household',
        '/calendar'=>'Schedule','/schedule'=>'Production schedule','/cast'=>'Cast & company','/notifications'=>'Updates & actions',
        '/forms'=>'Forms','/forms/view'=>'Form details','/volunteer-readiness'=>'Volunteer readiness','/volunteer-shifts'=>'Opportunities & commitments','/volunteer/shift'=>'Shift details','/volunteer/approvals'=>'My approvals',
        '/volunteer/history'=>'Service history','/volunteer/service-record'=>'Service record','/volunteer/training'=>'Training','/volunteer/verifications'=>'Verified service','/volunteer/verification'=>'Service letter',
        '/theatre-history'=>'Theatre History','/theatre-history/resume'=>'Acting CV','/student-profile'=>'Performer profile','/student-profile/family'=>'Family & Pickup','/family/caregiver-schedule'=>'Caregiver schedule',
        '/resources'=>'Resources','/resources/view'=>'Resource details','/files'=>'Files','/files/view'=>'File details','/videos'=>'Video Library','/videos/view'=>'Video details',
        '/archive'=>'Past productions','/archive/production'=>'Production history','/archive/community'=>'Historical Community','/attendance'=>'Attendance','/production'=>'Production','/playbills'=>'Playbill',
    ];
    public static function styles(string $base,bool $enabled=true):void
    {
        $path=(string)parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH);
        self::$route=$base!==''&&str_starts_with($path,$base.'/')?substr($path,strlen($base)):$path;
        // Directories may resolve with a trailing slash (e.g. /my-theatre/).
        // Normalize presentation lookup without changing routing or permissions.
        self::$route=rtrim(self::$route,'/')?:'/';
        self::$enabled=$enabled&&isset(self::ROUTES[self::$route])&&($_GET['print']??'')!=='1';if(!self::$enabled)return;
        WorkspaceUI::styles($base);
        $root=htmlspecialchars($base.'/assets/',ENT_QUOTES,'UTF-8');
        echo '<link rel="stylesheet" data-member-ui href="'.$root.'css/ux-member.css?v=20261030-member-2">';
        echo '<script defer src="'.$root.'js/ux-member.js?v=20261030-member-2"></script>';
    }
    public static function bodyClass():string{return self::$enabled?' ux-member ux-workspace':'';}
    public static function wayfinding(string $base):void
    {
        if(!self::$enabled||self::$route==='/my-theatre')return;
        echo '<nav class="member-wayfinding" aria-label="My Theatre navigation"><a href="'.self::e($base.'/my-theatre').'">My Theatre</a><span aria-hidden="true">/</span><span>'.self::e(self::ROUTES[self::$route]).'</span></nav>';
    }
    public static function badge(string $text):void{echo '<span class="member-status">'.self::e($text).'</span>';}
    public static function e(string $text):string{return htmlspecialchars($text,ENT_QUOTES,'UTF-8');}
}
