<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionLifecycleGuard.php';

final class ProductionChecklistAssignmentService {
 public static function candidates(PDO $db,int $production):array {
  $s=$db->prepare("SELECT DISTINCT u.id,CONCAT(u.first_name,' ',u.last_name) name,pm.audience_type FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status='active' WHERE pm.production_id=:p AND pm.status='active' AND pm.audience_type IN ('student','guardian','staff') ORDER BY pm.audience_type,name");
  $s->execute(['p'=>$production]);return $s->fetchAll();
 }
 public static function forItem(PDO $db,int $production,int $itemId):array {
  $s=$db->prepare("SELECT a.*,CONCAT(u.first_name,' ',u.last_name) name,pm.audience_type FROM production_checklist_assignments a JOIN production_checklist_items i ON i.id=a.checklist_item_id AND i.production_id=:p JOIN users u ON u.id=a.assigned_user_id LEFT JOIN production_memberships pm ON pm.production_id=i.production_id AND pm.user_id=u.id AND pm.status='active' WHERE a.checklist_item_id=:item ORDER BY name");
  $s->execute(['p'=>$production,'item'=>$itemId]);return $s->fetchAll();
 }
 public static function assign(PDO $db,int $production,int $itemId,array $userIds,bool $required):void {
  ProductionLifecycleGuard::assertWritable($db,$production);
  $ids=array_values(array_unique(array_map('intval',$userIds)));
  if(count($ids)>100||array_filter($ids,static fn(int $id):bool=>$id<1))throw new RuntimeException('Invalid task assignees.');
  $item=$db->prepare('SELECT id FROM production_checklist_items WHERE id=:item AND production_id=:p');$item->execute(['item'=>$itemId,'p'=>$production]);if(!$item->fetchColumn())throw new RuntimeException('Checklist task not found.');
  $available=array_column(self::candidates($db,$production),'id');foreach($ids as $id)if(!in_array($id,array_map('intval',$available),true))throw new RuntimeException('A selected assignee is not an active production participant.');
  $own=!$db->inTransaction();if($own)$db->beginTransaction();
  try {
   $old=self::forItem($db,$production,$itemId);$byId=[];foreach($old as $o)$byId[(int)$o['assigned_user_id']]=$o;
   $del=$db->prepare('DELETE FROM production_checklist_assignments WHERE checklist_item_id=:item AND assigned_user_id=:user');
   foreach($old as $o)if(!in_array((int)$o['assigned_user_id'],$ids,true))$del->execute(['item'=>$itemId,'user'=>(int)$o['assigned_user_id']]);
   $ins=$db->prepare("INSERT INTO production_checklist_assignments (checklist_item_id,assigned_user_id,required) VALUES (:item,:user,:required) ON DUPLICATE KEY UPDATE required=VALUES(required)");
   foreach($ids as $id)$ins->execute(['item'=>$itemId,'user'=>$id,'required'=>$required?1:0]);
   if($own)$db->commit();
  }catch(Throwable $e){if($own&&$db->inTransaction())$db->rollBack();throw $e;}
 }
 public static function actionItems(PDO $db,int $viewer):array {
  $s=$db->prepare("SELECT a.id assignment_id,a.assigned_user_id,a.required,a.completed_at,i.title,i.notes,i.due_at,p.title production_title,u.first_name student_first,u.last_name student_last,CASE WHEN a.assigned_user_id=:self THEN 1 ELSE 0 END is_own FROM production_checklist_assignments a JOIN production_checklist_items i ON i.id=a.checklist_item_id JOIN productions p ON p.id=i.production_id AND p.is_active=1 JOIN users u ON u.id=a.assigned_user_id AND u.active=1 AND u.account_status='active' JOIN production_memberships pm ON pm.production_id=i.production_id AND pm.user_id=a.assigned_user_id AND pm.status='active' WHERE a.completed_at IS NULL AND (a.assigned_user_id=:viewer OR (pm.audience_type='student' AND EXISTS(SELECT 1 FROM family_relationships fr WHERE fr.student_user_id=a.assigned_user_id AND fr.guardian_user_id=:guardian AND fr.status='active'))) ORDER BY i.due_at IS NULL,i.due_at,a.id");
  $s->execute(['self'=>$viewer,'viewer'=>$viewer,'guardian'=>$viewer]);return $s->fetchAll();
 }
 public static function complete(PDO $db,int $viewer,int $assignmentId):void {
  $db->beginTransaction();
  try{
   $s=$db->prepare("SELECT a.*,i.production_id FROM production_checklist_assignments a JOIN production_checklist_items i ON i.id=a.checklist_item_id WHERE a.id=:id FOR UPDATE");$s->execute(['id'=>$assignmentId]);$a=$s->fetch();
   if(!$a||$a['completed_at']!==null)throw new RuntimeException('This task is not open.');
   $target=(int)$a['assigned_user_id'];$production=(int)$a['production_id'];
   $u=$db->prepare("SELECT active,account_status FROM users WHERE id=:id");$u->execute(['id'=>$target]);$person=$u->fetch();if(!$person||(int)$person['active']!==1||$person['account_status']!=='active')throw new RuntimeException('The assignee is unavailable.');
   $membership=$db->prepare("SELECT audience_type FROM production_memberships WHERE production_id=:p AND user_id=:user AND status='active' LIMIT 1");$membership->execute(['p'=>$production,'user'=>$target]);$role=$membership->fetchColumn();
   if(!$role)throw new RuntimeException('This task is no longer assigned to an active production member.');
   if($viewer!==$target){
    if($role!=='student')throw new RuntimeException('Only the assigned person can complete this task.');
    $g=$db->prepare("SELECT 1 FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 AND u.account_status='active' WHERE fr.student_user_id=:student AND fr.guardian_user_id=:viewer AND fr.status='active'");
    $g->execute(['student'=>$target,'viewer'=>$viewer]);if(!$g->fetchColumn())throw new RuntimeException('You cannot complete this student task.');
   }
   $db->prepare("UPDATE production_checklist_assignments SET completed_at=NOW(),completed_by_user_id=:viewer WHERE id=:id AND completed_at IS NULL")->execute(['viewer'=>$viewer,'id'=>$assignmentId]);
   $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'production_checklist.assignment_completed','production_checklist_item',:item,'Completed assigned production task.',:metadata)");
   $audit->execute(['actor'=>$viewer,'item'=>(int)$a['checklist_item_id'],'metadata'=>json_encode(['assignment_id'=>$assignmentId,'assigned_user_id'=>$target,'production_id'=>$production],JSON_THROW_ON_ERROR)]);
   $db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
