<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionParticipantEligibility.php';

/** Descriptive team responsibilities. Never creates authorization or memberships. */
final class ProductionParticipantFunctionService
{
    public const CATEGORIES=['creative'=>'Creative Team','crew'=>'Technical Crew','operations'=>'Production Operations'];
    public const LABELS=[
        'creative'=>['director'=>'Director','assistant_director'=>'Assistant Director','choreographer'=>'Choreographer','music_director'=>'Music Director','custom'=>'Custom creative title'],
        'crew'=>['lighting_designer'=>'Lighting Designer','sound_designer'=>'Sound Designer','costume_designer'=>'Costume Designer','set_designer'=>'Set Designer','stagehand'=>'Stagehand','custom'=>'Custom technical title'],
        'operations'=>['stage_manager'=>'Stage Manager','assistant_stage_manager'=>'Assistant Stage Manager','production_manager'=>'Production Manager','custom'=>'Custom operations title'],
    ];
    public static function candidates(PDO $db):array
    {
        return $db->query("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,COALESCE((SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 WHERE ur.user_id=u.id),'No authorization roles') roles FROM users u WHERE u.active=1 AND u.account_status IN ('active','managed') AND u.organization_membership_status='approved' ORDER BY u.last_name,u.first_name,u.id")->fetchAll();
    }
    public static function assignments(PDO $db,int $production,bool $includeInactive=false):array
    {
        $q=$db->prepare("SELECT f.*,CONCAT(u.first_name,' ',u.last_name) name,u.active account_active,u.account_status FROM production_participant_functions f JOIN users u ON u.id=f.user_id WHERE f.production_id=? AND f.function_type<>'cast'".($includeInactive?'':" AND f.status='active'")." ORDER BY f.display_order,u.last_name,u.first_name,f.title,f.id");$q->execute([$production]);return $q->fetchAll();
    }
    public static function saveAssignment(PDO $db,int $production,int $actor,array $input):int
    {
        $id=(int)($input['assignment_id']??0);$person=(int)($input['user_id']??0);$type=(string)($input['function_type']??'');$code=(string)($input['function_code']??'');
        if(!isset(self::LABELS[$type][$code]))throw new RuntimeException('Choose a Production Team category and function. Cast assignments belong in Casting.');
        $title=$code==='custom'?trim((string)($input['custom_title']??'')):self::LABELS[$type][$code];$notes=trim((string)($input['staff_notes']??''));$order=filter_var($input['display_order']??0,FILTER_VALIDATE_INT);
        if($title===''||mb_strlen($title)>190||mb_strlen($notes)>2000||$order===false||abs($order)>10000)throw new RuntimeException('Enter a title up to 190 characters, notes up to 2000 and order between -10000 and 10000.');
        $db->beginTransaction();
        try{
            ProductionLifecycleGuard::assertWritable($db,$production,true);ProductionParticipantEligibility::assertManager($db,$actor,$production);
            $old=null;
            if($id){$q=$db->prepare("SELECT * FROM production_participant_functions WHERE id=? AND production_id=? AND function_type<>'cast' FOR UPDATE");$q->execute([$id,$production]);$old=$q->fetch();if(!$old||$old['status']!=='active'||(int)$old['revision']!==(int)($input['revision']??0))throw new RuntimeException('This team assignment changed. Reload People before editing.');if((int)$old['user_id']!==$person)throw new RuntimeException('A team assignment cannot be moved to another person. Remove it and add a new assignment.');}
            $q=$db->prepare("SELECT id,active,account_status,organization_membership_status FROM users WHERE id=? FOR UPDATE");$q->execute([$person]);$user=$q->fetch();
            if(!$user||!(int)$user['active']||!in_array($user['account_status'],['active','managed'],true)||$user['organization_membership_status']!=='approved')throw new RuntimeException('Choose an available, approved existing account. Manage account approval in Account & Access.');
            $eligible=!empty($input['playbill_eligible'])?1:0;
            if($old){
                self::closeCredit($db,$old);
                $db->prepare('UPDATE production_participant_functions SET function_type=?,function_code=?,title=?,display_order=?,staff_notes=?,playbill_eligible=?,assigned_by_user_id=?,revision=revision+1 WHERE id=?')->execute([$type,$code,$title,$order,$notes?:null,$eligible,$actor,$id]);
            }else{
                // Reactivation is explicit; no hidden duplicate identities or rows.
                $q=$db->prepare('SELECT id,status FROM production_participant_functions WHERE production_id=? AND user_id=? AND function_type=? AND title=? FOR UPDATE');$q->execute([$production,$person,$type,$title]);$previous=$q->fetch();
                if($previous&&$previous['status']==='active')throw new RuntimeException('This person already has that team assignment. Edit the existing assignment.');
                if($previous){$id=(int)$previous['id'];$db->prepare("UPDATE production_participant_functions SET status='active',function_code=?,display_order=?,staff_notes=?,playbill_eligible=?,assigned_by_user_id=?,revision=revision+1 WHERE id=?")->execute([$code,$order,$notes?:null,$eligible,$actor,$id]);}
                else{$db->prepare('INSERT INTO production_participant_functions(production_id,user_id,function_type,title,function_code,display_order,staff_notes,playbill_eligible,assigned_by_user_id) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$production,$person,$type,$title,$code,$order,$notes?:null,$eligible,$actor]);$id=(int)$db->lastInsertId();}
            }
            $q=$db->prepare('SELECT * FROM production_participant_functions WHERE id=?');$q->execute([$id]);$after=$q->fetch();
            $db->prepare("INSERT INTO theatre_history_credits(user_id,production_id,credit_kind,production_title,season_label,role_title,participation_track,verification_status,verified_by_user_id,participation_started_at) SELECT ?,?, ?,p.title,p.season,?,?,'verified',?,NOW() FROM productions p WHERE p.id=?")->execute([$person,$production,self::creditKind($after),$title,$type,$actor,$production]);
            ProductionParticipantEligibility::audit($db,$actor,$person,'team_assignment_saved',['production_id'=>$production,'assignment_id'=>$id,'before'=>$old,'after'=>$after]);
            $db->commit();return $id;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')throw new RuntimeException('That team function already exists for this person. Reload People and edit it.');throw $e;}
    }
    public static function remove(PDO $db,int $production,int $actor,int $id,int $revision):void
    {
        $db->beginTransaction();try{
            ProductionLifecycleGuard::assertWritable($db,$production,true);ProductionParticipantEligibility::assertManager($db,$actor,$production);
            $q=$db->prepare("SELECT * FROM production_participant_functions WHERE id=? AND production_id=? AND function_type<>'cast' FOR UPDATE");$q->execute([$id,$production]);$row=$q->fetch();
            if(!$row||$row['status']!=='active'||(int)$row['revision']!==$revision)throw new RuntimeException('This assignment changed. Reload People before removing it.');
            self::closeCredit($db,$row);$db->prepare("UPDATE production_participant_functions SET status='inactive',revision=revision+1,assigned_by_user_id=? WHERE id=?")->execute([$actor,$id]);
            ProductionParticipantEligibility::audit($db,$actor,(int)$row['user_id'],'team_assignment_removed',['production_id'=>$production,'assignment'=>$row]);$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    private static function creditKind(array $row):string{return 'team-'.$row['id'].'-'.$row['revision'];}
    private static function closeCredit(PDO $db,array $row):void
    {
        $legacy=$row['function_type'].'-'.substr(hash('sha256',(string)$row['title']),0,16);
        $db->prepare('UPDATE theatre_history_credits SET participation_ended_at=COALESCE(participation_ended_at,NOW()) WHERE production_id=? AND user_id=? AND credit_kind IN (?,?)')->execute([$row['production_id'],$row['user_id'],self::creditKind($row),$legacy]);
    }
    /** Compatibility for internal callers; cast credit creation belongs only to Casting. */
    public static function save(PDO $db,int $production,int $person,int $actor,string $type,string $title,bool $active):void
    {
        if(!isset(self::CATEGORIES[$type]))throw new RuntimeException('Cast functions are managed in Casting.');
        $q=$db->prepare('SELECT id,revision FROM production_participant_functions WHERE production_id=? AND user_id=? AND function_type=? AND title=?');$q->execute([$production,$person,$type,$title]);$row=$q->fetch();
        if(!$active){if(!$row)throw new RuntimeException('Team assignment not found.');self::remove($db,$production,$actor,(int)$row['id'],(int)$row['revision']);return;}
        self::saveAssignment($db,$production,$actor,['assignment_id'=>$row['id']??0,'revision'=>$row['revision']??0,'user_id'=>$person,'function_type'=>$type,'function_code'=>'custom','custom_title'=>$title]);
    }
}
