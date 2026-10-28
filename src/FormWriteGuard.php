<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionLifecycleGuard.php';

/** Lifecycle boundary only: callers must independently authorize the actor. */
final class FormWriteGuard
{
    public static function production(PDO $db, ?int $productionId): void
    {
        if ($productionId === null) return;
        // Hold the same production row that completion/archive locks until commit.
        $lock=$db->prepare('SELECT id FROM productions WHERE id=:id FOR UPDATE');
        $lock->execute(['id'=>$productionId]);
        if (!$lock->fetchColumn()) throw new RuntimeException('Production not found.');
        ProductionLifecycleGuard::assertWritable($db,$productionId);
    }

    public static function form(PDO $db,int $formId): void
    {
        $q=$db->prepare('SELECT production_id FROM forms WHERE id=:id FOR UPDATE');
        $q->execute(['id'=>$formId]);$id=$q->fetchColumn();
        if ($id === false) throw new RuntimeException('Form not found.');
        self::production($db,$id===null?null:(int)$id);
    }

    public static function assignment(PDO $db,int $assignmentId): void
    {
        $q=$db->prepare('SELECT form_id,production_id FROM form_assignments WHERE id=:id FOR UPDATE');
        $q->execute(['id'=>$assignmentId]);$assignment=$q->fetch();
        if (!$assignment) throw new RuntimeException('Form assignment not found.');
        self::form($db,(int)$assignment['form_id']);
        self::production($db,$assignment['production_id']===null?null:(int)$assignment['production_id']);
    }
}
