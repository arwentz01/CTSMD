<?php

declare(strict_types=1);

require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/SafeguardingCaseService.php';
require_once __DIR__.'/VolunteerCoverageService.php';

final class StaffDashboardService
{
    public static function build(PDO $db,array $user,?DateTimeImmutable $now=null):array
    {
        $now??=new DateTimeImmutable('now');
        $to=$now->modify('+21 days');
        $productions=ProductionContext::activeProductions($db,$user);
        $productionIds=array_map(static fn(array $p):int=>(int)$p['id'],$productions);
        $upcoming=self::upcomingCalls($db,$productionIds,$now,$to);
        $cards=[];

        if(AccessPolicy::canManageSafeguarding($user)){
            $cards[]=['key'=>'safeguarding','label'=>'Safeguarding reviews','count'=>SafeguardingCaseService::openCount($db),'detail'=>'Restricted cases requiring safeguarding follow-through','href'=>'/safeguarding/cases','tone'=>'attention'];
        }
        if(AccessPolicy::canManageAccounts($user)){
            $cards[]=['key'=>'membership','label'=>'Pending memberships','count'=>self::pendingMemberships($db),'detail'=>'Verified accounts waiting for CTSMD approval','href'=>'/admin/accounts','tone'=>'attention'];
        }
        if(AccessPolicy::canManageForms($user)){
            $cards[]=['key'=>'forms','label'=>'Missing / review forms','count'=>self::formsNeedingAttention($db,$productionIds),'detail'=>'Open assignments across active productions','href'=>'/admin/forms/manage','tone'=>'attention'];
            $cards[]=['key'=>'registration','label'=>'Registration intake','count'=>self::registrationIntake($db),'detail'=>'Submitted or accepted registrations not yet linked to People','href'=>'/admin/registrations','tone'=>'attention'];
        }
        if(AccessPolicy::canManageVolunteers($user)){
            $cards[]=['key'=>'volunteer','label'=>'Uncovered shifts','count'=>self::uncoveredShiftCount($db,$productionIds,$now,$to),'detail'=>'Upcoming shifts below required eligible staffing','href'=>'/admin/volunteer-shifts','tone'=>'attention'];
            $cards[]=['key'=>'volunteer_approval','label'=>'Volunteer approvals','count'=>self::pendingVolunteerApprovals($db),'detail'=>'Upcoming shift approval requests awaiting review','href'=>'/admin/volunteer-approvals','tone'=>'neutral'];
        }
        if(AccessPolicy::canModerateCommunity($user)){
            $cards[]=['key'=>'moderation','label'=>'Moderation queue','count'=>self::moderationQueue($db),'detail'=>'Community posts waiting for review','href'=>'/admin/moderation/queue','tone'=>'attention'];
        }

        $uncovered=AccessPolicy::canManageVolunteers($user)?self::uncoveredShifts($db,$productionIds,$now,$to):[];
        $registrations=AccessPolicy::canManageForms($user)?self::registrationRows($db):[];
        $memberships=AccessPolicy::canManageAccounts($user)?self::membershipRows($db):[];
        $selected=ProductionContext::selected($db,$user);

        return [
            'productions'=>$productions,
            'selected_production'=>$selected,
            'upcoming'=>$upcoming,
            'cards'=>$cards,
            'uncovered_shifts'=>$uncovered,
            'registration_intake'=>$registrations,
            'pending_memberships'=>$memberships,
            'summary'=>[
                'active_productions'=>count($productions),
                'upcoming_calls'=>count($upcoming),
                'attention'=>array_sum(array_map(static fn(array $c):int=>(int)$c['count'],$cards)),
            ],
        ];
    }

    private static function placeholders(array $ids):string{return implode(',',array_fill(0,count($ids),'?'));}

    private static function upcomingCalls(PDO $db,array $ids,DateTimeImmutable $from,DateTimeImmutable $to):array
    {
        if(!$ids)return [];$ph=self::placeholders($ids);$sql="SELECT si.id,si.production_id,si.title,si.starts_at,si.ends_at,si.location,si.item_type,p.title production_title,p.season FROM schedule_items si JOIN productions p ON p.id=si.production_id WHERE si.production_id IN ($ph) AND p.is_active=1 AND si.status='active' AND si.starts_at>=? AND si.starts_at<? ORDER BY si.starts_at LIMIT 18";$s=$db->prepare($sql);$s->execute([...$ids,$from->format('Y-m-d H:i:s'),$to->format('Y-m-d H:i:s')]);return $s->fetchAll();
    }

    private static function pendingMemberships(PDO $db):int{return (int)$db->query("SELECT COUNT(*) FROM users WHERE active=1 AND account_status='active' AND organization_membership_status='pending'")->fetchColumn();}

    private static function formsNeedingAttention(PDO $db,array $ids):int
    {
        if(!$ids)return 0;$ph=self::placeholders($ids);$s=$db->prepare("SELECT COUNT(*) FROM form_assignments fa JOIN forms f ON f.id=fa.form_id AND f.active=1 JOIN users subject ON subject.id=COALESCE(fa.subject_user_id,fa.user_id) AND subject.active=1 AND subject.account_status<>'disabled' WHERE fa.production_id IN ($ph) AND fa.status IN ('missing','requires_review','due_soon')");$s->execute($ids);return (int)$s->fetchColumn();
    }

    private static function registrationIntake(PDO $db):int
    {
        return (int)$db->query("SELECT COUNT(*) FROM registration_submissions rs LEFT JOIN registration_submission_links l ON l.submission_id=rs.id WHERE rs.status IN ('submitted','accepted') AND l.submission_id IS NULL")->fetchColumn();
    }

    private static function uncoveredShiftCount(PDO $db,array $ids,DateTimeImmutable $from,DateTimeImmutable $to):int{return count(self::uncoveredShifts($db,$ids,$from,$to));}

    private static function uncoveredShifts(PDO $db,array $ids,DateTimeImmutable $from,DateTimeImmutable $to):array
    {
        if(!$ids)return [];
        $ph=self::placeholders($ids);
        $sql="SELECT vs.id,vs.title,vs.category,vs.starts_at,vs.location,vs.required_slots,p.title production_title FROM volunteer_shifts vs JOIN productions p ON p.id=vs.production_id WHERE vs.production_id IN ($ph) AND p.is_active=1 AND vs.starts_at>=? AND vs.starts_at<? ORDER BY vs.starts_at";
        $s=$db->prepare($sql);
        $s->execute([...$ids,$from->format('Y-m-d H:i:s'),$to->format('Y-m-d H:i:s')]);
        $uncovered=[];
        foreach($s->fetchAll() as $row){
            $row['filled_slots']=VolunteerCoverageService::eligibleLiveSignupCount($db,(int)$row['id']);
            if((int)$row['filled_slots']<(int)$row['required_slots']){
                $uncovered[]=$row;
                if(count($uncovered)>=12)break;
            }
        }
        return $uncovered;
    }

    private static function pendingVolunteerApprovals(PDO $db):int{return (int)$db->query("SELECT COUNT(*) FROM volunteer_shift_approval_requests r JOIN volunteer_shifts vs ON vs.id=r.shift_id JOIN users u ON u.id=r.user_id AND u.active=1 AND u.account_status='active' WHERE r.status='pending' AND vs.starts_at>NOW()")->fetchColumn();}
    private static function moderationQueue(PDO $db):int{return (int)$db->query("SELECT COUNT(*) FROM channel_posts WHERE moderation_status='pending' AND hidden_at IS NULL AND deleted_at IS NULL")->fetchColumn();}

    private static function registrationRows(PDO $db):array
    {
        return $db->query("SELECT rs.id,rs.participant_first_name,rs.participant_last_name,rs.participant_age_group,rs.status,rs.submitted_at,ro.id opportunity_id,ro.title opportunity_title,p.title production_title FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id LEFT JOIN productions p ON p.id=ro.production_id LEFT JOIN registration_submission_links l ON l.submission_id=rs.id WHERE rs.status IN ('submitted','accepted') AND l.submission_id IS NULL ORDER BY rs.submitted_at LIMIT 8")->fetchAll();
    }

    private static function membershipRows(PDO $db):array
    {
        return $db->query("SELECT id,first_name,last_name,email,self_registered_at,email_verified_at FROM users WHERE active=1 AND account_status='active' AND organization_membership_status='pending' ORDER BY COALESCE(email_verified_at,self_registered_at,created_at) LIMIT 8")->fetchAll();
    }
}
