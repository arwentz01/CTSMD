<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/CalendarService.php';
require_once __DIR__ . '/FamilyDashboardService.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/AccessPolicy.php';

final class HomeDashboardService
{
    public static function build(PDO $db, array $user, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $windowEnd = $now->modify('+45 days');
        $approved = Auth::isApprovedMember($user);
        $staff = AccessPolicy::isStaff($user);

        $ownEvents = CalendarService::visibleEvents($db, $user, $now->modify('-1 day'), $windowEnd);
        $ownEvents = array_values(array_filter($ownEvents, static fn(array $e): bool => ($e['status'] ?? 'active') !== 'cancelled'));
        $ownConflicts = CalendarService::conflicts($ownEvents);
        foreach ($ownEvents as &$event) $event['has_conflict'] = isset($ownConflicts[(int)$event['id']]);
        unset($event);

        $family = FamilyDashboardService::build($db, $user, $now);
        $ownForms = self::formsForUser($db, (int)$user['id']);
        $volunteer = self::volunteerCommitments($db, (int)$user['id'], $now, $windowEnd);
        $notifications = self::notifications($db, (int)$user['id']);
        $productions = ProductionContext::activeProductions($db, $user);
        $selectedProduction = $staff ? ProductionContext::selected($db, $user) : null;

        // Guardians may see the same call through their own guardian audience and a linked child.
        // Prefer the child-labeled family event so the account-wide timeline does not duplicate it.
        $familyEventIds = [];
        foreach ($family['events'] as $event) $familyEventIds[(int)$event['id']] = true;
        if ($familyEventIds) $ownEvents = array_values(array_filter($ownEvents, static fn(array $event): bool => !isset($familyEventIds[(int)$event['id']])));

        $timeline = [];
        foreach ($ownEvents as $event) {
            $event['subject_name'] = $user['name'];
            $event['subject_initials'] = $user['initials'];
            $event['subject_type'] = 'self';
            $timeline[] = $event;
        }
        foreach ($family['events'] as $event) {
            $event['subject_name'] = $event['child_name'];
            $event['subject_initials'] = $event['child_initials'];
            $event['subject_type'] = 'child';
            $timeline[] = $event;
        }
        usort($timeline, static fn(array $a, array $b): int => strcmp((string)$a['starts_at'], (string)$b['starts_at']));

        $forms = [];
        foreach ($ownForms as $form) {
            $form['person_name'] = $user['name'];
            $form['person_initials'] = $user['initials'];
            $forms[] = $form;
        }
        foreach ($family['forms'] as $form) {
            if ((int)$form['person_id'] === (int)$user['id']) continue;
            $forms[] = $form;
        }
        usort($forms, static function(array $a,array $b):int{
            if($a['due_at']===null&&$b['due_at']!==null)return 1;
            if($a['due_at']!==null&&$b['due_at']===null)return -1;
            return strcmp((string)$a['due_at'],(string)$b['due_at']);
        });

        $attention = [];
        foreach ($forms as $form) {
            $urgent = ($form['status'] ?? '') === 'missing';
            if (!$urgent && !empty($form['due_at'])) $urgent = new DateTimeImmutable((string)$form['due_at']) <= $now->modify('+3 days');
            $attention[] = [
                'kind'=>'form','urgent'=>$urgent,'title'=>$form['title'],'context'=>$form['person_name'].($form['production_title']?' · '.$form['production_title']:''),
                'detail'=>$form['due_at']?'Due '.date('M j',strtotime((string)$form['due_at'])):'No due date','href'=>'/forms'
            ];
        }
        foreach ($family['household_conflicts'] as $conflict) {
            $a=$conflict['a'];$b=$conflict['b'];
            $attention[]=['kind'=>'conflict','urgent'=>true,'title'=>'Household schedule conflict','context'=>$a['child_name'].' + '.$b['child_name'],'detail'=>date('M j · g:i A',strtotime((string)$a['starts_at'])).' · '.$a['location'].' / '.$b['location'],'href'=>'/calendar'];
        }
        foreach ($volunteer as $shift) {
            if (($shift['status'] ?? '') === 'waitlisted') continue;
            $starts = new DateTimeImmutable((string)$shift['starts_at']);
            if ($starts <= $now->modify('+2 days')) {
                $attention[]=['kind'=>'volunteer','urgent'=>false,'title'=>$shift['title'],'context'=>'Volunteer commitment'.($shift['production_title']?' · '.$shift['production_title']:''),'detail'=>date('M j · g:i A',strtotime((string)$shift['starts_at'])),'href'=>'/volunteer-shifts'];
            }
        }

        return [
            'membership'=>[
                'status'=>(string)($user['organization_membership_status'] ?? 'pending'),
                'approved'=>$approved,
                'staff'=>$staff,
            ],
            'timeline'=>$timeline,
            'next_event'=>$timeline[0] ?? null,
            'forms'=>$forms,
            'attention'=>$attention,
            'volunteer'=>$volunteer,
            'notifications'=>$notifications,
            'children'=>$family['children'],
            'household_conflicts'=>$family['household_conflicts'],
            'productions'=>$productions,
            'selected_production'=>$selectedProduction,
            'summary'=>[
                'active_productions'=>count($productions),
                'linked_children'=>count($family['children']),
                'open_forms'=>count($forms),
                'attention'=>count($attention),
                'unread_notifications'=>(int)$notifications['unread_count'],
                'volunteer_commitments'=>count($volunteer),
            ],
        ];
    }

    private static function formsForUser(PDO $db,int $userId):array
    {
        $stmt=$db->prepare("SELECT fa.id assignment_id,fa.form_id,fa.production_id,fa.status,fa.due_at,f.title,f.form_type,p.title production_title FROM form_assignments fa JOIN forms f ON f.id=fa.form_id AND f.active=1 LEFT JOIN productions p ON p.id=fa.production_id WHERE COALESCE(fa.subject_user_id,fa.user_id)=:user AND fa.status<>'completed' ORDER BY fa.due_at IS NULL,fa.due_at,f.title");
        $stmt->execute(['user'=>$userId]);return $stmt->fetchAll();
    }

    private static function volunteerCommitments(PDO $db,int $userId,DateTimeImmutable $from,DateTimeImmutable $to):array
    {
        $stmt=$db->prepare("SELECT vss.id signup_id,vss.status,vs.id shift_id,vs.title,vs.category,vs.starts_at,vs.ends_at,vs.location,p.title production_title FROM volunteer_shift_signups vss JOIN volunteer_shifts vs ON vs.id=vss.shift_id LEFT JOIN productions p ON p.id=vs.production_id WHERE vss.user_id=:user AND vss.status IN ('signed_up','checked_in','waitlisted') AND vs.starts_at>=:from_date AND vs.starts_at<:to_date ORDER BY vs.starts_at");
        $stmt->execute(['user'=>$userId,'from_date'=>$from->format('Y-m-d H:i:s'),'to_date'=>$to->format('Y-m-d H:i:s')]);return $stmt->fetchAll();
    }

    private static function notifications(PDO $db,int $userId):array
    {
        $stmt=$db->prepare("SELECT id,title,body,action_path,source_type,read_at,created_at FROM app_notifications WHERE recipient_user_id=:user ORDER BY created_at DESC LIMIT 12");
        $stmt->execute(['user'=>$userId]);$items=$stmt->fetchAll();
        $count=$db->prepare('SELECT COUNT(*) FROM app_notifications WHERE recipient_user_id=:user AND read_at IS NULL');
        $count->execute(['user'=>$userId]);
        return ['items'=>$items,'unread_count'=>(int)$count->fetchColumn()];
    }
}
