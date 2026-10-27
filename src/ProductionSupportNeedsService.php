<?php

declare(strict_types=1);

require_once __DIR__.'/Auth.php';

/**
 * Sensitive participant support details: explicit two-permission gate.
 * Never call this service from public, generic cast, or messaging surfaces.
 */
final class ProductionSupportNeedsService
{
    public static function assertAuthorized(PDO $db,array $actor):void
    {
        $actorId=(int)($actor['id']??0);
        if($actorId<1||Auth::userId()!==$actorId)throw new RuntimeException('Sign in to view this information.');
        $permissions=Auth::permissions($db,$actorId);
        if(!in_array('production.manage',$permissions,true)||!in_array('forms.manage',$permissions,true)){
            throw new RuntimeException('Production management and registration review permissions are required.');
        }
    }

    public static function list(PDO $db,array $actor,int $productionId):array
    {
        self::assertAuthorized($db,$actor);
        if($productionId<1)throw new RuntimeException('Choose a production.');
        $p=$db->prepare('SELECT id,title FROM productions WHERE id=:id LIMIT 1');
        $p->execute(['id'=>$productionId]);
        $production=$p->fetch(PDO::FETCH_ASSOC);
        if(!$production)throw new RuntimeException('Production not found.');

        // Audit before loading sensitive free text; avoid logging the actual responses.
        $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'production.support_needs_viewed','production',:production,'Viewed production participant support needs.',NULL)");
        $audit->execute(['actor'=>(int)$actor['id'],'production'=>$productionId]);

        $s=$db->prepare("SELECT rs.id,rs.participant_first_name,rs.participant_last_name,rs.allergy_information,rs.accommodation_information,rs.status,ro.title opportunity_title,rsl.participant_user_id FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id LEFT JOIN registration_submission_links rsl ON rsl.submission_id=rs.id WHERE ro.production_id=:production AND rs.status IN ('submitted','waitlisted','accepted') AND (NULLIF(TRIM(rs.allergy_information),'') IS NOT NULL OR NULLIF(TRIM(rs.accommodation_information),'') IS NOT NULL) ORDER BY rs.participant_last_name,rs.participant_first_name,rs.submitted_at DESC,rs.id DESC");
        $s->execute(['production'=>$productionId]);
        return ['production'=>$production,'entries'=>$s->fetchAll(PDO::FETCH_ASSOC)];
    }
}
