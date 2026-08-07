<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';

final class PlaybillExperience
{
    private const ROUTES = ['/playbills','/admin/playbill','/playbill'];

    public static function handles(string $route): bool { return in_array($route, self::ROUTES, true); }

    public static function render(string $route, string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $db = Database::connect(dirname(__DIR__));

        if ($route === '/playbill') {
            self::publicPage($db, $basePath);
        }

        $user = self::currentUser($db);
        $_SESSION['playbill_csrf'] ??= bin2hex(random_bytes(24));
        $production = ProductionContext::selected($db, $user);

        if ($route === '/admin/playbill' && !AccessPolicy::canManageProduction($user)) self::forbidden($basePath, $user);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $route === '/admin/playbill') self::handlePost($db, $basePath, $user, $production);

        $playbill = $production ? self::playbillForProduction($db, (int)$production['id']) : null;
        $sections = $playbill ? self::sections($db, (int)$playbill['id'], $route === '/admin/playbill') : [];
        $roster = $production ? self::roster($db, (int)$production['id']) : [];

        self::page($route, $basePath, $user, $production, $playbill, $sections, $roster);
    }

    private static function handlePost(PDO $db, string $basePath, array $user, ?array $production): never
    {
        $token=(string)($_POST['csrf_token']??'');
        if (!hash_equals((string)($_SESSION['playbill_csrf']??''),$token)) {
            self::flash('error','Your Playbill session expired. Please try again.');
            self::redirect($basePath.'/admin/playbill');
        }
        if (!$production) {
            self::flash('error','Select an active production before managing its Playbill.');
            self::redirect($basePath.'/production');
        }

        try {
            $action=(string)($_POST['action']??'');
            if ($action==='save_playbill') {
                self::savePlaybill($db,$user,$production,$_POST);
                self::flash('success','Playbill details saved.');
            } elseif ($action==='save_section') {
                self::saveSection($db,$user,$production,$_POST);
                self::flash('success','Playbill section saved.');
            } elseif ($action==='toggle_section') {
                self::toggleSection($db,$user,$production,(int)(filter_input(INPUT_POST,'section_id',FILTER_VALIDATE_INT)?:0));
                self::flash('success','Section visibility updated.');
            } elseif ($action==='publish') {
                self::changeStatus($db,$user,$production,'current');
                self::flash('success','Playbill published.');
            } elseif ($action==='archive') {
                self::changeStatus($db,$user,$production,'archived');
                self::flash('success','Playbill archived.');
            } elseif ($action==='draft') {
                self::changeStatus($db,$user,$production,'draft');
                self::flash('success','Playbill returned to draft.');
            } else throw new RuntimeException('Choose a valid Playbill action.');
        } catch (RuntimeException $e) {
            self::flash('error',$e->getMessage());
        }
        self::redirect($basePath.'/admin/playbill');
    }

    private static function savePlaybill(PDO $db,array $actor,array $production,array $input): void
    {
        $title=trim((string)($input['display_title']??''));
        $subtitle=trim((string)($input['subtitle']??''));
        $cover=trim((string)($input['cover_note']??''));
        $slug=strtolower(trim((string)($input['public_slug']??'')));
        $slug=preg_replace('/[^a-z0-9-]+/','-',$slug) ?: '';
        $slug=trim($slug,'-');
        if ($title===''||mb_strlen($title)>190) throw new RuntimeException('Enter a Playbill title no longer than 190 characters.');
        if (mb_strlen($subtitle)>255) throw new RuntimeException('Keep the subtitle under 255 characters.');
        if (mb_strlen($cover)>500) throw new RuntimeException('Keep the cover note under 500 characters.');
        if ($slug===''||mb_strlen($slug)>190) throw new RuntimeException('Enter a valid public slug.');

        $db->beginTransaction();
        try {
            $stmt=$db->prepare('SELECT id,status FROM playbills WHERE production_id=:production_id LIMIT 1 FOR UPDATE');
            $stmt->execute(['production_id'=>(int)$production['id']]);
            $existing=$stmt->fetch();
            if ($existing) {
                $update=$db->prepare('UPDATE playbills SET display_title=:title,subtitle=:subtitle,cover_note=:cover,public_slug=:slug WHERE id=:id');
                $update->execute(['title'=>$title,'subtitle'=>$subtitle!==''?$subtitle:null,'cover'=>$cover!==''?$cover:null,'slug'=>$slug,'id'=>(int)$existing['id']]);
                $id=(int)$existing['id'];
                $event='playbill.updated';
            } else {
                $insert=$db->prepare("INSERT INTO playbills (production_id,display_title,subtitle,cover_note,status,public_slug,created_by_user_id) VALUES (:production_id,:title,:subtitle,:cover,'draft',:slug,:creator)");
                $insert->execute(['production_id'=>(int)$production['id'],'title'=>$title,'subtitle'=>$subtitle!==''?$subtitle:null,'cover'=>$cover!==''?$cover:null,'slug'=>$slug,'creator'=>(int)$actor['id']]);
                $id=(int)$db->lastInsertId();
                $event='playbill.created';
            }
            self::audit($db,(int)$actor['id'],$event,'playbill',$id,'Saved digital Playbill details.',['production_id'=>(int)$production['id'],'slug'=>$slug]);
            $db->commit();
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ((string)$e->getCode()==='23000') throw new RuntimeException('That public slug is already in use. Choose another.');
            throw new RuntimeException('The Playbill could not be saved.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The Playbill could not be saved.');
        }
    }

    private static function saveSection(PDO $db,array $actor,array $production,array $input): void
    {
        $playbill=self::playbillForProduction($db,(int)$production['id']);
        if (!$playbill) throw new RuntimeException('Save the Playbill cover details before adding sections.');
        $id=(int)(filter_input(INPUT_POST,'section_id',FILTER_VALIDATE_INT)?:0);
        $type=trim((string)($input['section_type']??'custom'));
        $heading=trim((string)($input['heading']??''));
        $body=trim((string)($input['body']??''));
        $order=(int)($input['sort_order']??0);
        if ($heading===''||mb_strlen($heading)>190) throw new RuntimeException('Enter a section heading no longer than 190 characters.');
        if ($body===''||mb_strlen($body)>20000) throw new RuntimeException('Enter section content up to 20,000 characters.');
        if ($type===''||mb_strlen($type)>80) throw new RuntimeException('Choose a valid section type.');

        if ($id>0) {
            $stmt=$db->prepare('UPDATE playbill_sections SET section_type=:type,heading=:heading,body=:body,sort_order=:sort_order WHERE id=:id AND playbill_id=:playbill_id');
            $stmt->execute(['type'=>$type,'heading'=>$heading,'body'=>$body,'sort_order'=>$order,'id'=>$id,'playbill_id'=>(int)$playbill['id']]);
            if ($stmt->rowCount()<1) {
                $check=$db->prepare('SELECT id FROM playbill_sections WHERE id=:id AND playbill_id=:playbill_id');
                $check->execute(['id'=>$id,'playbill_id'=>(int)$playbill['id']]);
                if (!$check->fetchColumn()) throw new RuntimeException('That section does not belong to this Playbill.');
            }
            $event='playbill.section_updated';
        } else {
            $stmt=$db->prepare('INSERT INTO playbill_sections (playbill_id,section_type,heading,body,sort_order,active) VALUES (:playbill_id,:type,:heading,:body,:sort_order,1)');
            $stmt->execute(['playbill_id'=>(int)$playbill['id'],'type'=>$type,'heading'=>$heading,'body'=>$body,'sort_order'=>$order]);
            $id=(int)$db->lastInsertId();
            $event='playbill.section_created';
        }
        self::audit($db,(int)$actor['id'],$event,'playbill_section',$id,'Saved Playbill section.',['playbill_id'=>(int)$playbill['id'],'production_id'=>(int)$production['id']]);
    }

    private static function toggleSection(PDO $db,array $actor,array $production,int $sectionId): void
    {
        $playbill=self::playbillForProduction($db,(int)$production['id']);
        if (!$playbill||$sectionId<1) throw new RuntimeException('That Playbill section could not be found.');
        $stmt=$db->prepare('SELECT id,active FROM playbill_sections WHERE id=:id AND playbill_id=:playbill_id');
        $stmt->execute(['id'=>$sectionId,'playbill_id'=>(int)$playbill['id']]);
        $row=$stmt->fetch();
        if (!$row) throw new RuntimeException('That section does not belong to this Playbill.');
        $next=(int)$row['active']?0:1;
        $db->prepare('UPDATE playbill_sections SET active=:active WHERE id=:id')->execute(['active'=>$next,'id'=>$sectionId]);
        self::audit($db,(int)$actor['id'],'playbill.section_visibility_changed','playbill_section',$sectionId,'Changed Playbill section visibility.',['active'=>(bool)$next]);
    }

    private static function changeStatus(PDO $db,array $actor,array $production,string $status): void
    {
        if (!in_array($status,['draft','current','archived'],true)) throw new RuntimeException('Choose a valid Playbill status.');
        $playbill=self::playbillForProduction($db,(int)$production['id']);
        if (!$playbill) throw new RuntimeException('Create the Playbill before changing its publication status.');
        if ($status==='current' && empty($playbill['public_slug'])) throw new RuntimeException('Add a public slug before publishing.');
        $published=$status==='current'?date('Y-m-d H:i:s'):null;
        $stmt=$db->prepare('UPDATE playbills SET status=:status,published_at=:published WHERE id=:id');
        $stmt->execute(['status'=>$status,'published'=>$published,'id'=>(int)$playbill['id']]);
        self::audit($db,(int)$actor['id'],'playbill.status_changed','playbill',(int)$playbill['id'],'Changed Playbill publication status.',['status'=>$status,'production_id'=>(int)$production['id']]);
    }

    private static function playbillForProduction(PDO $db,int $productionId): ?array
    {
        $stmt=$db->prepare('SELECT pb.*,p.title production_title,p.season FROM playbills pb JOIN productions p ON p.id=pb.production_id WHERE pb.production_id=:production_id ORDER BY pb.id DESC LIMIT 1');
        $stmt->execute(['production_id'=>$productionId]);
        return $stmt->fetch() ?: null;
    }

    private static function sections(PDO $db,int $playbillId,bool $includeInactive=false): array
    {
        $sql='SELECT id,section_type,heading,body,sort_order,active FROM playbill_sections WHERE playbill_id=:playbill_id'.($includeInactive?'':" AND active=1").' ORDER BY sort_order,id';
        $stmt=$db->prepare($sql);$stmt->execute(['playbill_id'=>$playbillId]);return $stmt->fetchAll();
    }

    private static function roster(PDO $db,int $productionId): array
    {
        $stmt=$db->prepare("SELECT pm.audience_type,pm.participation_role,CONCAT(u.first_name,' ',u.last_name) name FROM production_memberships pm JOIN users u ON u.id=pm.user_id WHERE pm.production_id=:production_id AND pm.status='active' AND u.active=1 ORDER BY FIELD(pm.audience_type,'student','staff','guardian'),pm.participation_role,u.last_name,u.first_name");
        $stmt->execute(['production_id'=>$productionId]);return $stmt->fetchAll();
    }

    private static function publicPage(PDO $db,string $basePath): never
    {
        $slug=trim((string)($_GET['slug']??''));
        $stmt=$db->prepare("SELECT pb.*,p.title production_title,p.season FROM playbills pb JOIN productions p ON p.id=pb.production_id WHERE pb.public_slug=:slug AND pb.status='current' LIMIT 1");
        $stmt->execute(['slug'=>$slug]);$playbill=$stmt->fetch();
        if (!$playbill) { http_response_code(404); self::renderPublic($basePath,null,[],[]); }
        $sections=self::sections($db,(int)$playbill['id']);$roster=self::roster($db,(int)$playbill['production_id']);
        self::renderPublic($basePath,$playbill,$sections,$roster);
    }

    private static function page(string $route,string $basePath,array $user,?array $production,?array $playbill,array $sections,array $roster): never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p;$esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $staff=AccessPolicy::canManageProduction($user);$flash=$_SESSION['playbill_flash']??null;unset($_SESSION['playbill_flash']);
        $subnav=[['label'=>'Overview','href'=>'/production','active'=>false],['label'=>'Schedule','href'=>'/schedule','active'=>false],['label'=>'Resources','href'=>'/resources','active'=>false],['label'=>'Playbill','href'=>'/playbills','active'=>true]];
        header('Content-Type:text/html;charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Playbill · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/playbill-management.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production',$route==='/admin/playbill'?'Manage Playbill':'Playbill',$basePath,$subnav);?><div class="pb-page"><?php if($flash):?><div class="pb-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?>
        <?php if(!$production):?><section class="pb-empty"><h2>No production selected.</h2><a class="button" href="<?= $url('/production') ?>">Choose a production</a></section>
        <?php elseif($route==='/admin/playbill'):?>
        <section class="pb-hero admin"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Build the program people will actually read.</h2><p>The cast and production team come from the live roster. Staff controls the cover, editorial sections, order, and publication state.</p></div><span><?= $playbill?$esc(strtoupper($playbill['status'])):'NOT CREATED' ?></span></section>
        <div class="pb-admin-grid"><form method="post" class="pb-card"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['playbill_csrf']) ?>"><input type="hidden" name="action" value="save_playbill"><small>COVER & PUBLIC LINK</small><label>Display title<input name="display_title" maxlength="190" required value="<?= $esc((string)($playbill['display_title']??$production['title'])) ?>"></label><label>Subtitle<input name="subtitle" maxlength="255" value="<?= $esc((string)($playbill['subtitle']??$production['season']??'')) ?>"></label><label>Cover note<textarea name="cover_note" maxlength="500" rows="3"><?= $esc((string)($playbill['cover_note']??'')) ?></textarea></label><label>Public slug<input name="public_slug" maxlength="190" required value="<?= $esc((string)($playbill['public_slug']??strtolower(preg_replace('/[^a-z0-9]+/i','-',$production['title'])))) ?>"></label><button class="button" type="submit">Save Playbill</button></form>
        <section class="pb-card"><small>PUBLICATION</small><h3><?= $playbill?$esc(ucfirst($playbill['status'])):'Create cover first' ?></h3><?php if($playbill):?><p><?= $playbill['status']==='current'?'This Playbill is publicly available.':'Draft and archived Playbills are not exposed publicly.' ?></p><div class="pb-actions"><?php foreach(['draft'=>'Return to draft','current'=>'Publish','archived'=>'Archive'] as $value=>$label):?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['playbill_csrf']) ?>"><input type="hidden" name="action" value="<?= $value==='current'?'publish':$value ?>"><button type="submit"<?= $playbill['status']===$value?' disabled':'' ?>><?= $esc($label) ?></button></form><?php endforeach;?></div><?php if($playbill['status']==='current'):?><a href="<?= $url('/playbill?slug='.urlencode((string)$playbill['public_slug'])) ?>" target="_blank">Open public Playbill →</a><?php endif;?><?php endif;?></section></div>
        <?php if($playbill):?><section class="pb-editor"><header><div><small>EDITORIAL SECTIONS</small><h3>Program content</h3></div><span><?= count($sections) ?> sections</span></header><form method="post" class="pb-section-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['playbill_csrf']) ?>"><input type="hidden" name="action" value="save_section"><div><label>Type<select name="section_type"><option value="welcome">Welcome</option><option value="director_note">Director's Note</option><option value="synopsis">Synopsis</option><option value="acknowledgments">Acknowledgments</option><option value="sponsors">Sponsors</option><option value="custom">Custom</option></select></label><label>Order<input type="number" name="sort_order" value="10"></label></div><label>Heading<input name="heading" maxlength="190" required placeholder="Director's Note"></label><label>Content<textarea name="body" rows="6" maxlength="20000" required></textarea></label><button class="button" type="submit">Add section</button></form><div class="pb-section-list"><?php foreach($sections as $section):?><article class="<?= (int)$section['active']?'':'inactive' ?>"><div><small><?= $esc(strtoupper(str_replace('_',' ',$section['section_type']))) ?> · ORDER <?= (int)$section['sort_order'] ?></small><h4><?= $esc($section['heading']) ?></h4><p><?= $esc(mb_strimwidth($section['body'],0,220,'…')) ?></p></div><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['playbill_csrf']) ?>"><input type="hidden" name="action" value="toggle_section"><input type="hidden" name="section_id" value="<?= (int)$section['id'] ?>"><button type="submit"><?= (int)$section['active']?'Hide':'Show' ?></button></form></article><?php endforeach;?></div></section><?php endif;?>
        <?php else:?>
        <section class="pb-hero"><div><small><?= $esc(strtoupper($production['season']??'')) ?></small><h2><?= $esc((string)($playbill['display_title']??$production['title'])) ?></h2><p><?= $playbill?$esc((string)($playbill['cover_note']??'')):'The Playbill has not been created yet.' ?></p></div><?php if($staff):?><a class="button" href="<?= $url('/admin/playbill') ?>">Manage Playbill</a><?php endif;?></section>
        <?php if($playbill): self::renderProgramContent($esc,$sections,$roster); else:?><section class="pb-empty"><h3>No Playbill yet.</h3><p>Staff can create one for this production.</p></section><?php endif;?>
        <?php endif;?></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function renderProgramContent(callable $esc,array $sections,array $roster): void
    {
        $cast=array_values(array_filter($roster,static fn(array $r):bool=>$r['audience_type']==='student'));
        $team=array_values(array_filter($roster,static fn(array $r):bool=>$r['audience_type']==='staff'));
        ?><div class="pb-program"><section class="pb-roster"><small>CAST</small><h3>Company</h3><?php if(!$cast):?><p>Cast roster not yet available.</p><?php else:foreach($cast as $person):?><div><b><?= $esc($person['name']) ?></b><span><?= $esc((string)$person['participation_role']) ?></span></div><?php endforeach;endif;?></section><section class="pb-roster"><small>PRODUCTION TEAM</small><h3>Behind the curtain</h3><?php if(!$team):?><p>Production team not yet available.</p><?php else:foreach($team as $person):?><div><b><?= $esc($person['name']) ?></b><span><?= $esc((string)$person['participation_role']) ?></span></div><?php endforeach;endif;?></section><?php foreach($sections as $section):?><section class="pb-copy"><small><?= $esc(strtoupper(str_replace('_',' ',$section['section_type']))) ?></small><h3><?= $esc($section['heading']) ?></h3><p><?= nl2br($esc($section['body'])) ?></p></section><?php endforeach;?></div><?php
    }

    private static function renderPublic(string $basePath,?array $playbill,array $sections,array $roster): never
    {
        $esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        header('Content-Type:text/html;charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $playbill?$esc((string)($playbill['display_title']??$playbill['production_title'])):'Playbill not found' ?> · CTSMD</title><link rel="stylesheet" href="<?= ($basePath?:'') ?>/assets/css/app.css"><link rel="stylesheet" href="<?= ($basePath?:'') ?>/assets/css/playbill-management.css"></head><body class="pb-public"><header class="pb-public-brand">CTSMD <span>PLAYBILL</span></header><?php if(!$playbill):?><main class="pb-public-main"><section class="pb-empty"><h1>Playbill not found.</h1></section></main><?php else:?><main class="pb-public-main"><section class="pb-cover"><small>CHILDREN'S THEATRE OF SOUTHERN MARYLAND</small><h1><?= $esc((string)($playbill['display_title']??$playbill['production_title'])) ?></h1><h2><?= $esc((string)($playbill['subtitle']??$playbill['season']??'')) ?></h2><p><?= $esc((string)($playbill['cover_note']??'')) ?></p></section><?php self::renderProgramContent($esc,$sections,$roster);?></main><?php endif;?></body></html><?php exit;
    }

    private static function currentUser(PDO $db): array
    {
        $row=$db->query("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role,initials FROM users WHERE is_demo_current_user=1 AND active=1 LIMIT 1")->fetch();
        if(!$row) throw new RuntimeException('Demo user is missing.');return $row;
    }
    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $metadata): void
    {
        $stmt=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,:metadata)');
        $stmt->execute(['actor'=>$actor,'event'=>$event,'type'=>$type,'id'=>$id,'summary'=>$summary,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }
    private static function flash(string $type,string $message):void{$_SESSION['playbill_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function forbidden(string $basePath,array $user):never{http_response_code(403);header('Content-Type:text/html;charset=utf-8');echo '<h1>Staff only</h1><p>This account cannot manage Playbills.</p>';exit;}
}
