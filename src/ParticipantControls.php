<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionParticipantEligibility.php';

/** Existing Operations primitives; owning screens supply their existing CSRF token. */
final class ParticipantControls
{
    public static function settings(PDO $db,array $production,string $token):void
    {
        $e=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$id=(int)$production['id'];
        $closed=in_array($production['lifecycle_stage'],['completed','archived'],true);
        ?><section class="pl-panel ops-panel" aria-labelledby="participation-title"><h3 id="participation-title">Participation eligibility</h3>
        <p>These rules apply to performers, not staff or family access. Existing cast memberships and historical credits are retained when rules change.</p>
        <form method="post" class="pl-detail-form ops-form"><input type="hidden" name="csrf_token" value="<?=$e($token)?>"><input type="hidden" name="production_id" value="<?=$id?>"><input type="hidden" name="action" value="participation_settings">
        <fieldset <?=$closed?'disabled':''?>><legend>Who can participate</legend><label>Participant categories<select name="participant_category"><?php foreach(['students'=>'Students only','mixed'=>'Adults and Students','adults'=>'Adults only'] as $value=>$label):?><option value="<?=$value?>"<?=($production['participant_category']??'students')===$value?' selected':''?>><?=$label?></option><?php endforeach;?></select></label>
        <label>Minimum Student age (optional)<input type="number" name="student_minimum_age" min="0" max="120" value="<?=$e($production['student_minimum_age']??'')?>"></label>
        <label>Maximum Student age (optional)<input type="number" name="student_maximum_age" min="0" max="120" value="<?=$e($production['student_maximum_age']??'')?>"></label>
        <label>Eligibility date<input type="date" name="participant_eligibility_date" value="<?=$e($production['participant_eligibility_date']??substr((string)($production['audition_starts_at']??''),0,10))?>" aria-describedby="eligibility-help"></label><p id="eligibility-help">Age limits include both endpoints. A date is required when either limit is set. February 29 birthdays complete on March 1 in non-leap years.</p><button type="submit" class="button">Save participation eligibility</button></fieldset></form>
        <details><summary>Student age exceptions</summary><p>Exceptions apply only to this production's Student age limits. They do not waive category or guardian safeguards.</p><form method="post" class="pl-detail-form ops-form"><input type="hidden" name="csrf_token" value="<?=$e($token)?>"><input type="hidden" name="production_id" value="<?=$id?>"><fieldset <?=$closed?'disabled':''?>><legend>Review an individual exception</legend><label>Student<select name="student_user_id" required><option value="">Choose Student</option><?php foreach($db->query("SELECT DISTINCT u.id,u.first_name,u.last_name FROM users u JOIN auth_user_roles ur ON ur.user_id=u.id JOIN auth_roles r ON r.id=ur.role_id AND r.code='student' AND r.active=1 WHERE u.active=1 AND u.account_status<>'disabled' ORDER BY u.last_name,u.first_name")->fetchAll() as $student):?><option value="<?=(int)$student['id']?>"><?=$e($student['first_name'].' '.$student['last_name'])?></option><?php endforeach;?></select></label><label>Review reason<textarea name="reason" maxlength="1000" required></textarea></label><button type="submit" name="action" value="age_exception_approve">Approve age exception</button><button type="submit" name="action" value="age_exception_revoke" class="secondary">Revoke age exception</button></fieldset></form>
        <?php $q=$db->prepare('SELECT x.*,u.first_name,u.last_name FROM production_age_exceptions x JOIN users u ON u.id=x.student_user_id WHERE production_id=? ORDER BY x.approved_at DESC,x.id DESC');$q->execute([$id]);?><ul><?php foreach($q->fetchAll() as $row):?><li><?=$e($row['first_name'].' '.$row['last_name'])?> — <?=$row['revoked_at']?'Revoked':'Approved'?> <?=$e($row['approved_at'])?> · <?=$e($row['reason'])?></li><?php endforeach;?></ul></details></section><?php
    }

    public static function account(array $account,string $token):void
    {
        $e=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        ?><section class="acct-panel ops-panel"><h3>Adult participation</h3><p>Reviewed classification allows independent participation. It grants no guardian, staff, pickup or confidential access. Student transitions require separate safeguarding review.</p><form method="post" class="ops-form"><input type="hidden" name="csrf_token" value="<?=$e($token)?>"><input type="hidden" name="user_id" value="<?=(int)$account['id']?>"><input type="hidden" name="action" value="adult_classification"><label>Classification<select name="adult_participant"><option value="0"<?=empty($account['adult_participant'])?' selected':''?>>Not classified Adult</option><option value="1"<?=!empty($account['adult_participant'])?' selected':''?>>Reviewed Adult participant</option></select></label><label>Review reason<textarea name="reason" maxlength="1000" required></textarea></label><button type="submit">Save reviewed classification</button></form></section><?php
    }
}
