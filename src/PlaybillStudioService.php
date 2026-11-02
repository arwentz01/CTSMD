<?php
declare(strict_types=1);
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/ProductionLifecycleGuard.php';
require_once __DIR__.'/PlaybillEnhancementService.php';
require_once __DIR__.'/ProductionTeamPublicationService.php';

/** Shared editorial boundaries; public requests retain their independent publication gate. */
final class PlaybillStudioService
{
    public static function canEdit(PDO $db,array $user,int $production):bool
    {
        if(!AccessPolicy::canManagePlaybill($user))return false;
        if(Auth::hasRole($user,'administrator'))return true;
        $q=$db->prepare("SELECT 1 FROM production_memberships WHERE production_id=? AND user_id=? AND audience_type='staff' AND status='active'");$q->execute([$production,(int)$user['id']]);return(bool)$q->fetchColumn();
    }
    public static function assertEditable(PDO $db,array $user,array $production,?array $playbill):void
    {
        if(!self::canEdit($db,$user,(int)$production['id']))throw new RuntimeException('Current production Playbill editing permission is required.');
        ProductionLifecycleGuard::assertWritable($db,(int)$production['id'],true);
        if($playbill&&$playbill['editorial_status']==='archived')throw new RuntimeException('Archived programs are historical records and cannot be edited.');
        if($playbill&&!empty($playbill['public_enabled']))throw new RuntimeException('Unpublish this program before editing. Then save, preview and publish a new edition.');
    }
    public static function company(PDO $db,int $id,bool $preview=false):array
    {
        if(!$preview)return PlaybillEnhancementService::publicCastProfiles($db,$id);
        $rows=PlaybillEnhancementService::playbillCastProfiles($db,$id);
        $rows=array_values(array_filter($rows,static fn(array $p):bool=>(bool)$p['visible']));
        foreach($rows as &$p){$p['asset_kind']=$p['identity_type']==='manual'?'manual-headshot':'headshot';$p['identity_id']=$p['identity_type']==='manual'?abs((int)$p['user_id']):(int)$p['user_id'];if($p['identity_type']==='registered'){$q=$db->prepare("SELECT first_name,last_name FROM users WHERE id=? AND active=1 AND account_status<>'disabled' AND ".PerformerQuery::role('users','student'));$q->execute([(int)$p['user_id']]);if($s=$q->fetch())$p['display_name']=self::studentName($s['first_name'],$s['last_name']);}}
        unset($p);return$rows;
    }
    public static function studentName(string $first,string $last):string{return trim($first).(trim($last)!==''?' '.mb_strtoupper(mb_substr(trim($last),0,1)).'.':'');}
    public static function presentation(PDO $db,array $pb,bool $preview=false):array
    {
        $q=$db->prepare('SELECT * FROM playbill_sections WHERE playbill_id=? AND active=1 ORDER BY sort_order,id');$q->execute([(int)$pb['id']]);$sections=array_values(array_filter($q->fetchAll(),static fn(array $s):bool=>$s['section_type']!=='production_team_snapshot'));
        $company=self::company($db,(int)$pb['id'],$preview);$staff=ProductionTeamPublicationService::snapshot($db,(int)$pb['id'],true);
        foreach($company as $person)if(($person['participant_type']??'cast')!=='cast')$staff[]=['user_id'=>$person['identity_type']==='registered'?(int)$person['user_id']:0,'name'=>$person['display_name'],'participation_role'=>$person['role_title']?:ucfirst($person['participant_type'])];
        // Multiple reviewed functions become one person's combined credit, keyed by stable identity.
        $merged=[];foreach($staff as $i=>$credit){$key=!empty($credit['user_id'])?'user-'.$credit['user_id']:'row-'.$i;if(!isset($merged[$key]))$merged[$key]=$credit;elseif(!str_contains($merged[$key]['participation_role'],$credit['participation_role']))$merged[$key]['participation_role'].=' · '.$credit['participation_role'];}
        $sponsors=array_values(array_filter(PlaybillEnhancementService::sponsors($db,(int)$pb['id']),static fn(array $s):bool=>(bool)$s['attachment_active']&&$s['status']==='active'));
        return[$sections,$company,array_values($merged),$sponsors];
    }
    public static function previewAsset(PDO $db,array $pb,string $kind,int $id):?array
    {
        $file=null;$pid=(int)$pb['id'];
        if($kind==='artwork')$file=$pb['artwork_stored_file_id'];
        elseif($kind==='headshot'){$q=$db->prepare("SELECT p.headshot_stored_file_id FROM playbill_cast_profiles p JOIN users u ON u.id=p.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE p.playbill_id=? AND p.user_id=?");$q->execute([$pid,$id]);$file=$q->fetchColumn();}
        elseif($kind==='manual-headshot'){$q=$db->prepare("SELECT p.headshot_stored_file_id FROM playbill_manual_profiles p JOIN manual_participant_credits c ON c.id=p.manual_credit_id AND c.active=1 JOIN manual_participants m ON m.id=c.manual_participant_id AND m.status='active' WHERE p.playbill_id=? AND p.manual_credit_id=?");$q->execute([$pid,$id]);$file=$q->fetchColumn();}
        elseif(in_array($kind,['sponsor','sponsor-ad'],true)){$col=$kind==='sponsor-ad'?'advertisement_stored_file_id':'logo_stored_file_id';$q=$db->prepare("SELECT s.$col FROM sponsors s JOIN playbill_sponsors p ON p.sponsor_id=s.id AND p.active=1 WHERE p.playbill_id=? AND s.id=? AND s.status='active'");$q->execute([$pid,$id]);$file=$q->fetchColumn();}
        $v=$file?StorageService::currentVersion($db,(int)$file):null;return$v&&in_array($v['mime_type'],['image/jpeg','image/png','image/webp'],true)?$v:null;
    }
}
