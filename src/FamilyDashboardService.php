<?php

declare(strict_types=1);

require_once __DIR__ . '/CalendarService.php';
require_once __DIR__ . '/Auth.php';

final class FamilyDashboardService
{
    public static function build(PDO $db, array $guardian, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $windowEnd = $now->modify('+45 days');
        $children = self::children($db, (int)$guardian['id']);
        $householdEvents = [];
        $openForms = [];
        $activeProductionIds = [];

        foreach ($children as &$child) {
            $childUser = self::childUser($db, (int)$child['id']);
            $events = $childUser ? CalendarService::visibleEvents($db, $childUser, $now->modify('-1 day'), $windowEnd) : [];
            $events = array_values(array_filter($events, static fn(array $event): bool => ($event['status'] ?? 'active') !== 'cancelled'));
            $conflicts = CalendarService::conflicts($events);
            $forms = self::formsForUser($db, (int)$child['id']);
            $productions = self::productionsForUser($db, (int)$child['id']);
            foreach ($productions as $production) $activeProductionIds[(int)$production['id']] = true;

            foreach ($events as &$event) {
                $event['child_id'] = (int)$child['id'];
                $event['child_name'] = $child['name'];
                $event['child_initials'] = $child['initials'];
                $event['has_conflict'] = isset($conflicts[(int)$event['id']]);
                $householdEvents[] = $event;
            }
            unset($event);

            foreach ($forms as $form) {
                $form['person_id'] = (int)$child['id'];
                $form['person_name'] = $child['name'];
                $form['person_initials'] = $child['initials'];
                $openForms[] = $form;
            }

            $child['events'] = $events;
            $child['forms'] = $forms;
            $child['productions'] = $productions;
            $child['conflict_count'] = count(array_unique(array_keys($conflicts)));
            $child['next_event'] = $events[0] ?? null;
            $child['open_form_count'] = count($forms);
        }
        unset($child);

        $guardianForms = self::formsForUser($db, (int)$guardian['id']);
        foreach ($guardianForms as $form) {
            $form['person_id'] = (int)$guardian['id'];
            $form['person_name'] = $guardian['name'];
            $form['person_initials'] = $guardian['initials'];
            $openForms[] = $form;
        }

        $volunteer = self::volunteerCommitments($db, (int)$guardian['id'], $now, $windowEnd);
        $notifications = self::notifications($db, (int)$guardian['id']);
        usort($householdEvents, static fn(array $a, array $b): int => strcmp((string)$a['starts_at'], (string)$b['starts_at']));
        usort($openForms, static function(array $a, array $b): int {
            if ($a['due_at'] === null && $b['due_at'] !== null) return 1;
            if ($a['due_at'] !== null && $b['due_at'] === null) return -1;
            return strcmp((string)$a['due_at'], (string)$b['due_at']);
        });

        $householdConflicts = self::householdLogisticsConflicts($householdEvents);
        $urgentForms = array_values(array_filter($openForms, static function(array $form) use ($now): bool {
            if (($form['status'] ?? '') === 'missing') return true;
            if (!$form['due_at']) return false;
            return new DateTimeImmutable((string)$form['due_at']) <= $now->modify('+3 days');
        }));

        return [
            'children' => $children,
            'events' => $householdEvents,
            'forms' => $openForms,
            'urgent_forms' => $urgentForms,
            'volunteer' => $volunteer,
            'notifications' => $notifications,
            'household_conflicts' => $householdConflicts,
            'summary' => [
                'children' => count($children),
                'active_productions' => count($activeProductionIds),
                'open_forms' => count($openForms),
                'urgent_forms' => count($urgentForms),
                'unread_notifications' => (int)$notifications['unread_count'],
                'volunteer_commitments' => count($volunteer),
                'conflicts' => count($householdConflicts),
            ],
        ];
    }

    private static function children(PDO $db, int $guardianId): array
    {
        $stmt = $db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.first_name,u.initials,u.display_role role,fr.relationship_type,fr.is_primary FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 WHERE fr.guardian_user_id=:guardian AND fr.status='active' ORDER BY fr.is_primary DESC,u.last_name,u.first_name");
        $stmt->execute(['guardian' => $guardianId]);
        return $stmt->fetchAll();
    }

    private static function childUser(PDO $db, int $userId): ?array
    {
        $stmt = $db->prepare("SELECT id,CONCAT(first_name,' ',last_name) name,first_name,last_name,email,initials,display_role role,active FROM users WHERE id=:id AND active=1 LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) return null;
        try {
            $user['roles'] = Auth::roles($db, $userId);
            $user['permissions'] = Auth::permissions($db, $userId);
        } catch (Throwable) {
            $user['roles'] = ['student'];
            $user['permissions'] = [];
        }
        return $user;
    }

    private static function productionsForUser(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT DISTINCT p.id,p.title,p.season,pm.participation_role,pm.audience_type FROM production_memberships pm JOIN productions p ON p.id=pm.production_id WHERE pm.user_id=:user AND pm.status='active' AND p.is_active=1 ORDER BY p.title");
        $stmt->execute(['user' => $userId]);
        return $stmt->fetchAll();
    }

    private static function formsForUser(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT fa.id assignment_id,fa.form_id,fa.production_id,fa.status,fa.due_at,f.title,f.form_type,p.title production_title FROM form_assignments fa JOIN forms f ON f.id=fa.form_id AND f.active=1 LEFT JOIN productions p ON p.id=fa.production_id WHERE fa.user_id=:user AND fa.status<>'completed' ORDER BY fa.due_at IS NULL,fa.due_at,f.title");
        $stmt->execute(['user' => $userId]);
        return $stmt->fetchAll();
    }

    private static function volunteerCommitments(PDO $db, int $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $db->prepare("SELECT vss.id signup_id,vss.status,vs.id shift_id,vs.title,vs.category,vs.starts_at,vs.ends_at,vs.location,p.title production_title FROM volunteer_shift_signups vss JOIN volunteer_shifts vs ON vs.id=vss.shift_id LEFT JOIN productions p ON p.id=vs.production_id WHERE vss.user_id=:user AND vss.status IN ('signed_up','checked_in','waitlisted') AND vs.starts_at>=:from_date AND vs.starts_at<:to_date ORDER BY vs.starts_at");
        $stmt->execute(['user' => $userId, 'from_date' => $from->format('Y-m-d H:i:s'), 'to_date' => $to->format('Y-m-d H:i:s')]);
        return $stmt->fetchAll();
    }

    private static function notifications(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT id,title,body,action_path,source_type,read_at,created_at FROM app_notifications WHERE recipient_user_id=:user ORDER BY created_at DESC LIMIT 12");
        $stmt->execute(['user' => $userId]);
        $items = $stmt->fetchAll();
        $unread = 0;
        foreach ($items as $item) if ($item['read_at'] === null) $unread++;
        return ['items' => $items, 'unread_count' => $unread];
    }

    private static function householdLogisticsConflicts(array $events): array
    {
        $conflicts = [];
        $count = count($events);
        for ($i = 0; $i < $count; $i++) {
            $a = $events[$i];
            if (($a['status'] ?? 'active') === 'cancelled') continue;
            $aStart = strtotime((string)$a['starts_at']);
            $aEnd = strtotime((string)($a['ends_at'] ?: $a['starts_at'] . ' +1 hour'));
            for ($j = $i + 1; $j < $count; $j++) {
                $b = $events[$j];
                $bStart = strtotime((string)$b['starts_at']);
                if ($bStart >= $aEnd) break;
                $bEnd = strtotime((string)($b['ends_at'] ?: $b['starts_at'] . ' +1 hour'));
                if ($aStart >= $bEnd || $bStart >= $aEnd) continue;
                if ((int)$a['child_id'] === (int)$b['child_id']) continue;
                $sameEvent = (int)$a['id'] === (int)$b['id'];
                $sameLocation = trim(strtolower((string)$a['location'])) !== '' && trim(strtolower((string)$a['location'])) === trim(strtolower((string)$b['location']));
                if ($sameEvent || $sameLocation) continue;
                $key = min((int)$a['id'], (int)$b['id']) . ':' . max((int)$a['id'], (int)$b['id']) . ':' . min((int)$a['child_id'], (int)$b['child_id']) . ':' . max((int)$a['child_id'], (int)$b['child_id']);
                $conflicts[$key] = ['a' => $a, 'b' => $b];
            }
        }
        return array_values($conflicts);
    }
}
