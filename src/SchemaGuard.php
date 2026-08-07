<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class SchemaGuard
{
    public static function requireCurrentSchema(string $projectRoot,string $basePath):void
    {
        try{
            $db=Database::connect($projectRoot);
            $productionColumns=self::columns($db,'productions');$channelColumns=self::columns($db,'channels');$postColumns=self::columns($db,'channel_posts');$formColumns=self::columns($db,'forms');$assignmentColumns=self::columns($db,'form_assignments');$submissionColumns=self::columns($db,'form_submissions');$playbillColumns=self::columns($db,'playbills');$scheduleColumns=self::columns($db,'schedule_items');$userColumns=self::columns($db,'users');$tables=self::tables($db);
            $missing006=[];foreach(['is_active','activated_at','deactivated_at'] as $c)if(!isset($productionColumns[$c]))$missing006[]='productions.'.$c;foreach(['read_audiences_json','post_audiences_json'] as $c)if(!isset($channelColumns[$c]))$missing006[]='channels.'.$c;
            $missing007=[];foreach(['production_id','created_by_user_id','created_at','updated_at'] as $c)if(!isset($formColumns[$c]))$missing007[]='forms.'.$c;foreach(['production_id','assigned_by_user_id','assigned_at'] as $c)if(!isset($assignmentColumns[$c]))$missing007[]='form_assignments.'.$c;
            $missing008=[];foreach(['display_title','subtitle','cover_note','created_by_user_id','published_at','created_at','updated_at'] as $c)if(!isset($playbillColumns[$c]))$missing008[]='playbills.'.$c;if(!isset($tables['playbill_sections']))$missing008[]='table playbill_sections';
            $missing009=[];if(!isset($tables['production_resources']))$missing009[]='table production_resources';
            $missing010=[];if(!isset($channelColumns['access_mode']))$missing010[]='channels.access_mode';foreach(['teams','team_members','channel_members','channel_teams'] as $t)if(!isset($tables[$t]))$missing010[]='table '.$t;
            $missing011=[];if(!isset($scheduleColumns['audience_mode']))$missing011[]='schedule_items.audience_mode';foreach(['production_groups','production_group_members','schedule_item_groups'] as $t)if(!isset($tables[$t]))$missing011[]='table '.$t;
            $missing012=[];foreach(['moderation_status','moderation_term_id','moderation_reason','moderated_by_user_id','moderated_at'] as $c)if(!isset($postColumns[$c]))$missing012[]='channel_posts.'.$c;if(!isset($tables['moderation_terms']))$missing012[]='table moderation_terms';
            $missing013=[];foreach(['attendance_records','attendance_absence_reports'] as $t)if(!isset($tables[$t]))$missing013[]='table '.$t;
            $missing014=[];if(!isset($formColumns['definition_version']))$missing014[]='forms.definition_version';foreach(['definition_version','definition_snapshot_json'] as $c)if(!isset($submissionColumns[$c]))$missing014[]='form_submissions.'.$c;foreach(['form_fields','form_submission_answers'] as $t)if(!isset($tables[$t]))$missing014[]='table '.$t;
            $missing015=[];foreach(['volunteer_hour_entries','volunteer_training_modules','volunteer_training_completions','form_requirement_mappings'] as $t)if(!isset($tables[$t]))$missing015[]='table '.$t;
            $missing016=[];foreach(['status','cancelled_at','cancelled_by_user_id','duplicate_of_id'] as $c)if(!isset($scheduleColumns[$c]))$missing016[]='schedule_items.'.$c;if(!isset($tables['calendar_subscriptions']))$missing016[]='table calendar_subscriptions';
            $missing017=[];foreach(['password_hash','account_status','last_login_at','password_changed_at'] as $c)if(!isset($userColumns[$c]))$missing017[]='users.'.$c;foreach(['auth_roles','auth_permissions','auth_role_permissions','auth_user_roles','auth_invitations','auth_password_resets'] as $t)if(!isset($tables[$t]))$missing017[]='table '.$t;
            if(!$missing006&&!$missing007&&!$missing008&&!$missing009&&!$missing010&&!$missing011&&!$missing012&&!$missing013&&!$missing014&&!$missing015&&!$missing016&&!$missing017)return;
            self::render([$missing006,$missing007,$missing008,$missing009,$missing010,$missing011,$missing012,$missing013,$missing014,$missing015,$missing016,$missing017]);
        }catch(PDOException $e){if(str_contains($e->getMessage(),"doesn't exist"))return;throw $e;}
    }
    public static function requireConcurrentProductionSchema(string $projectRoot,string $basePath):void{self::requireCurrentSchema($projectRoot,$basePath);}
    private static function columns(PDO $db,string $table):array{$s=$db->query('SHOW COLUMNS FROM `'.$table.'`');$out=[];foreach($s->fetchAll() as $row)$out[(string)$row['Field']]=true;return $out;}
    private static function tables(PDO $db):array{$out=[];foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row)$out[(string)$row[0]]=true;return $out;}
    private static function render(array $missingSets):never
    {
        $files=['006_concurrent_productions_and_audiences.sql','007_form_management_and_context.sql','008_playbill_management.sql','009_production_resources.sql','010_teams_and_private_channels.sql','011_production_groups_and_schedule_targeting.sql','012_community_moderation.sql','013_attendance.sql','014_dynamic_forms.sql','015_volunteer_hours_training_automation.sql','016_calendar_and_schedule_lifecycle.sql','017_authentication_and_rbac.sql'];$steps=[];foreach($missingSets as $i=>$missing)if($missing)$steps[]=['file'=>'database/migrations/'.$files[$i],'missing'=>$missing];$esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');http_response_code(503);header('Content-Type:text/html; charset=utf-8');?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Database update required · CTSMD Connect</title><style>body{font-family:system-ui;background:#f5f1ef;color:#241b1e;padding:32px}.card{max-width:780px;margin:8vh auto;background:#fff;border:1px solid #ded5d8;border-radius:18px;padding:32px}code{display:block;background:#191519;color:#fff;padding:14px;border-radius:10px;margin:10px 0}.missing{font-size:12px;color:#786a6f}</style></head><body><main class="card"><small>LOCAL DATABASE UPDATE REQUIRED</small><h1>CTSMD Connect needs a database migration.</h1><p>Run these against <b>ctsmd</b> in order, then refresh.</p><?php foreach($steps as $step):?><code><?= $esc($step['file']) ?></code><p class="missing">Missing: <?= $esc(implode(', ',$step['missing'])) ?></p><?php endforeach;?><p>No demo seed reset is required.</p></main></body></html><?php exit;
    }
}
