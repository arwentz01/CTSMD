<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';
require_once __DIR__.'/CommerceVenueService.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/WorkspaceUI.php';
/** Restricted local sandbox. All commands delegate to permission-enforcing services. */
final class CommerceExperience
{
    public static function handles(string $route):bool{return $route==='/admin/commerce';}
    private static string $base='';
    private static int $org=0;
    private static array $user=[];
    private static PDO $db;
    public static function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
    public static function url(string $tab='overview',array $args=[]):string{return self::$base.'/admin/commerce?'.http_build_query(['tab'=>$tab]+$args);}
    private static function rows(string $sql,array $args=[]):array{$q=self::$db->prepare($sql);$q->execute($args);return$q->fetchAll();}
    private static function can(string $p):bool{return CommerceAccess::can(self::$db,self::$user,self::$org,$p);}
    public static function form(string $action,array $fields=[]):void
    {
        echo '<form method="post"><input type="hidden" name="csrf_token" value="'.self::e($_SESSION['commerce_csrf']).'"><input type="hidden" name="action" value="'.self::e($action).'">';
        foreach($fields as $key=>$value)echo '<input type="hidden" name="'.self::e($key).'" value="'.self::e($value).'">';
    }
    public static function input(string $name,string $label,string $type='text',string $value='',string $attrs=''):void{echo '<label>'.self::e($label).'<input name="'.self::e($name).'" type="'.$type.'" value="'.self::e($value).'" '.$attrs.'></label>';}
    public static function render(string $route,string $basePath):never
    {
        if(!CommerceAccess::gate()){http_response_code(404);exit('Not found');}
        self::$base=$basePath;Auth::startSession();self::$db=Database::connect(dirname(__DIR__));self::$user=Auth::currentUser(self::$db)?:[];
        if(!self::$user){header('Location: '.$basePath.'/login',true,303);exit;}
        header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow, noarchive');header('Referrer-Policy: same-origin');
        try{$owner=CommerceAccess::primary(self::$db,self::$user);}catch(PDOException $e){http_response_code(503);exit('Commerce sandbox schema is not installed. Review the local migration runbook.');}
        $_SESSION['commerce_csrf']??=bin2hex(random_bytes(32));
        if(!$owner){
            if(!Auth::hasRole(self::$user,'administrator')){http_response_code(403);exit('Commerce access required.');}
            if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='bootstrap'){self::csrf();CommerceAccess::bootstrap(self::$db,self::$user);self::redirect(self::url());}
            echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Initialize Commerce sandbox</title><h1>Commerce sandbox</h1><p>Local simulation only. All modules start disabled.</p>';self::form('bootstrap');echo '<button>Initialize local sandbox</button></form></html>';exit;
        }
        self::$org=(int)$owner['id'];$allowed=false;foreach(CommerceAccess::ROLES['administrator'] as $p)$allowed=$allowed||self::can($p);
        if(!$allowed){http_response_code(403);exit('Commerce access required.');}
        $tab=(string)($_GET['tab']??'overview');$error='';
        if($_SERVER['REQUEST_METHOD']==='POST'){
            self::csrf();try{$next=self::command((string)($_POST['action']??''));$_SESSION['commerce_notice']='Saved successfully.';self::redirect($next);}catch(PDOException $e){error_log('Commerce command failed: '.$e->getCode());$error='The change could not be saved. Reload and try again; no partial change was applied.';}catch(RuntimeException|JsonException|InvalidArgumentException $e){$error=$e->getMessage();}
        }
        $notice=$_SESSION['commerce_notice']??'';unset($_SESSION['commerce_notice']);
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Commerce sandbox · <?=self::e($owner['name'])?></title><link rel="stylesheet" href="<?=self::e($basePath)?>/assets/css/app.css"><link rel="stylesheet" href="<?=self::e($basePath)?>/assets/css/unified-navigation.css"><?php WorkspaceUI::operationsStyles($basePath);?><link rel="stylesheet" href="<?=self::e($basePath)?>/assets/css/commerce.css"></head><body class="app-body ux-workspace ux-operations"><div class="unified-shell"><?php AppNavigation::renderSidebar('/admin/commerce',$basePath,self::$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Commerce','Sandbox',$basePath);?><div class="commerce-page"><header><h1>Commerce sandbox</h1><p class="commerce-banner">Simulation only · No real payments · No email is sent</p></header><nav class="commerce-tabs" aria-label="Commerce workspace"><?php foreach(['overview'=>['Overview',null],'venues'=>['Venues & seating','venues'],'performances'=>['Performances','inventory'],'orders'=>['Orders','report'],'checkin'=>['Box office','checkin'],'configure'=>['Configuration','configure']] as $key=>[$label,$permission]):if($permission&&!self::can($permission))continue;?><a href="<?=self::e(self::url($key))?>" <?=$tab===$key?'aria-current="page"':''?>><?=self::e($label)?></a><?php endforeach;?></nav><?php if($error):?><div role="alert" class="commerce-error"><?=self::e($error)?></div><?php endif;?><?php if($notice):?><p role="status"><?=self::e($notice)?></p><?php endif;?><?php
        try{match($tab){'venues'=>self::venues(),'layout'=>self::layout(),'configure'=>self::configuration(),default=>self::extension($tab)};}catch(RuntimeException $e){echo '<p role="alert">'.self::e($e->getMessage()).'</p>';}
        ?></div></main></div><script src="<?=self::e($basePath)?>/assets/js/unified-navigation.js"></script><script src="<?=self::e($basePath)?>/assets/js/commerce.js" defer></script></body></html><?php exit;
    }
    private static function csrf():void{if(empty($_SESSION['commerce_csrf'])||!hash_equals($_SESSION['commerce_csrf'],(string)($_POST['csrf_token']??''))){http_response_code(403);exit('Session token invalid. Reload the page before retrying.');}}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function command(string $a):string
    {
        $db=self::$db;$u=self::$user;$o=self::$org;$id=(int)($_POST['id']??0);
        switch($a){
            case 'venue':CommerceVenueService::venue($db,$u,$o,(string)($_POST['name']??''),(string)($_POST['location']??''));return self::url('venues');
            case 'space':$space=CommerceVenueService::space($db,$u,$o,(int)($_POST['venue']??0),(string)($_POST['name']??''));$r=self::rows('SELECT id FROM commerce_layouts WHERE organization_id=? AND space_id=?',[$o,$space]);return self::url('layout',['id'=>$r[0]['id']]);
            case 'save_layout':CommerceVenueService::save($db,$u,$o,$id,json_decode((string)($_POST['document']??''),true,flags:JSON_THROW_ON_ERROR),(string)($_POST['expected']??''));return self::url('layout',['id'=>$id]);
            case 'publish':CommerceVenueService::publish($db,$u,$o,$id);return self::url('layout',['id'=>$id]);
            case 'clone':$id=CommerceVenueService::cloneVersion($db,$u,$o,$id);return self::url('layout',['id'=>$id]);
            case 'configure':CommerceStore::configure($db,$u,$o,$_POST);return self::url('configure');
            case 'grant':CommerceStore::grant($db,$u,$o,(int)($_POST['recipient']??0),(string)($_POST['role']??''),($_POST['enabled']??'')==='1');return self::url('configure');
            default:return self::extensionCommand($a);
        }
    }
    private static function venues():void
    {
        CommerceAccess::demand(self::$db,self::$user,self::$org,'venues');echo '<h2>Venues & seating</h2><p>Create a space, arrange its seats, then publish a reusable version. Publishing freezes the layout.</p><div class="commerce-columns"><section><h3>Add venue</h3>';self::form('venue');self::input('name','Venue name','text','','required maxlength="190"');self::input('location','Location','text','','maxlength="500"');echo '<button>Create venue</button></form></section><section><h3>Add performance space</h3>';self::form('space');echo '<label>Venue<select name="venue" required>';foreach(self::rows('SELECT * FROM commerce_venues WHERE organization_id=? ORDER BY name',[self::$org]) as $v)echo '<option value="'.(int)$v['id'].'">'.self::e($v['name']).'</option>';echo '</select></label>';self::input('name','Auditorium or space name','text','','required maxlength="190"');echo '<button>Create space</button></form></section></div><h3>Layout versions</h3><div class="commerce-records">';
        foreach(self::rows('SELECT l.*,s.name space_name,v.name venue_name FROM commerce_layouts l JOIN commerce_spaces s ON s.id=l.space_id AND s.organization_id=l.organization_id JOIN commerce_venues v ON v.id=s.venue_id AND v.organization_id=s.organization_id WHERE l.organization_id=? ORDER BY s.id,l.version_number DESC',[self::$org]) as $l)echo '<a href="'.self::e(self::url('layout',['id'=>$l['id']])).'">'.self::e($l['venue_name'].' / '.$l['space_name']).' · Version '.(int)$l['version_number'].' · '.self::e($l['status']).'</a>';
        echo '</div>';
    }
    private static function layout():void
    {
        CommerceAccess::demand(self::$db,self::$user,self::$org,'venues');$l=CommerceStore::row(self::$db,'layouts',self::$org,(int)($_GET['id']??0));$locked=$l['status']==='published';
        echo '<h2>Seating layout · Version '.(int)$l['version_number'].'</h2><p>'.($locked?'Published and immutable. Clone this version to edit a new template.':'Draft. Select an item on the map or list, then edit its attributes. Drag seats or use the position fields.').'</p><div class="commerce-actions">';self::form('clone',['id'=>$l['id']]);echo '<button>Clone as new version</button></form>';if(!$locked){self::form('publish',['id'=>$l['id']]);echo '<button>Publish saved version</button></form>';}echo '</div>';
        self::form('save_layout',['id'=>$l['id'],'expected'=>hash('sha256',$l['document_json'])]);echo '<div data-layout-editor data-locked="'.($locked?'1':'0').'"><input type="hidden" name="document" data-document value="'.self::e($l['document_json']).'"><div class="commerce-editor-grid"><section><div class="commerce-map-toolbar"><button type="button" data-zoom="in">Zoom in</button><button type="button" data-zoom="out">Zoom out</button><button type="button" data-pan="left">Pan left</button><button type="button" data-pan="right">Pan right</button><button type="button" data-pan="up">Pan up</button><button type="button" data-pan="down">Pan down</button><button type="button" data-fit>Fit layout</button></div><div class="commerce-map-wrap"><svg class="commerce-map" data-map aria-label="Seating layout preview" role="group" viewBox="0 0 800 500"></svg></div><p data-editor-status role="status">Choose an item to edit.</p><label>Keyboard editing: select item<select data-item-list></select></label></section><section><fieldset '.($locked?'disabled':'').'><legend>Selected item</legend><label>Label / seat number<input data-attr="label" maxlength="100"></label><div class="commerce-columns"><label>X position<input type="number" data-attr="x" min="0" max="5000"></label><label>Y position<input type="number" data-attr="y" min="0" max="5000"></label></div><label>Row<select data-attr="row"></select></label><label>Pricing zone<input data-attr="zone" pattern="[a-zA-Z0-9_-]{1,80}"></label><label><input type="checkbox" data-attr="wheelchair"> Wheelchair position</label><label>Companion of wheelchair seat<select data-attr="companion"></select></label><label><input type="checkbox" data-attr="restricted"> Restricted / obstructed view</label><label>Accessible seating / view description<textarea data-attr="description" maxlength="500"></textarea></label><button type="button" data-remove>Remove selected item</button></fieldset></section></div><fieldset '.($locked?'disabled':'').'><legend>Structure & bulk tools</legend><div class="commerce-columns"><section><h3>Sections and rows</h3><p>Keys preserve references. Edit names and display order below.</p><div data-structure></div><button type="button" data-add-section>Add section</button><button type="button" data-add-row>Add row</button></section><section><h3>Generate a row of seats</h3><label>Target row<select data-bulk-row></select></label><label>Count<input type="number" data-bulk-count min="1" max="50" value="8"></label><label>Starting seat number<input type="number" data-bulk-start min="1" value="1"></label><label>Row Y position<input type="number" data-bulk-y min="0" max="5000" value="140"></label><button type="button" data-generate>Generate seats</button><h3>Add layout detail</h3><label>Item type<select data-new-kind><option value="aisle">Aisle</option><option value="space">Non-seat space</option><option value="stage">Stage</option></select></label><button type="button" data-add-item>Add detail</button></section></div></fieldset>'.(!$locked?'<button type="submit">Save draft layout</button>':'').'</div></form>';
    }
    private static function configuration():void
    {
        CommerceAccess::demand(self::$db,self::$user,self::$org,'configure');$o=CommerceStore::organization(self::$db,self::$org);echo '<h2>Sandbox configuration</h2><p>These switches affect only this local Commerce organization. Merchandise and education have no selling workflow yet. Provider: simulated. Currency: '.self::e($o['currency']).'.</p>';self::form('configure');foreach(['ticketing'=>'Reserved-seat ticketing','merchandise'=>'Merchandise foundation','education'=>'Paid education foundation'] as $key=>$label)echo '<label><input type="checkbox" name="'.$key.'_enabled" value="1" '.($o[$key.'_enabled']?'checked':'').'>'.$label.'</label>';self::input('hold_seconds','Hold duration (seconds)','number',(string)$o['hold_seconds'],'min="60" max="1800" required');echo '<button>Save configuration</button></form><h3>Commerce permissions</h3><p>Commerce grants do not change Connect roles. Only grant access to synthetic adult test accounts in this sandbox.</p>';self::form('grant');self::input('recipient','Active adult user ID','number','','min="1" required');echo '<label>Commerce role<select name="role">';foreach(CommerceAccess::ROLES as $role=>$caps)echo '<option>'.self::e($role).'</option>';echo '</select></label><label>Action<select name="enabled"><option value="1">Grant</option><option value="0">Revoke</option></select></label><button>Update Commerce grant</button></form>';
    }
    private static function extension(string $tab):void
    {
        echo '<h2>Connect Commerce</h2><p>Start in Venues & seating to create a reusable auditorium. Only authorized Commerce accounts can enter this local sandbox.</p><p>Published layouts are retained. Existing production, casting, family, external ticket and Playbill workflows are unchanged.</p>';
    }
    private static function extensionCommand(string $action):string{throw new RuntimeException('Unknown Commerce action.');}
}
