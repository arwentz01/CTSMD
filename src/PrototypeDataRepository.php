<?php

declare(strict_types=1);

final class PrototypeDataRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $user = $this->db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 LIMIT 1")->fetch();
        if (!$user) {
            throw new RuntimeException('Demo seed data is missing. Import database/schema.sql and database/seeds/001_demo.sql.');
        }

        return [
            'user' => $user,
            'announcements' => $this->announcements(),
            'schedule' => $this->schedule(),
            'channels' => $this->channels(),
            'channel_posts' => $this->channelPosts(),
            'volunteer_stats' => $this->volunteerStats(),
            'shifts' => $this->shifts((int)$user['id']),
            'forms' => $this->forms((int)$user['id']),
            'playbills' => $this->playbills(),
            'people' => $this->people(),
            'safeguarding' => $this->safeguarding(),
        ];
    }

    private function announcements(): array
    {
        $rows = $this->db->query("SELECT title, body, context_label, tone, published_at FROM announcements ORDER BY pinned DESC, published_at DESC LIMIT 6")->fetchAll();
        return array_map(static function (array $row): array {
            return [
                'title' => $row['title'],
                'meta' => date('M j, g:i A', strtotime($row['published_at'])) . ' · ' . $row['context_label'],
                'body' => $row['body'],
                'tone' => $row['tone'],
            ];
        }, $rows);
    }

    private function schedule(): array
    {
        $rows = $this->db->query("SELECT title, starts_at, family_call_at, location FROM schedule_items ORDER BY starts_at ASC LIMIT 8")->fetchAll();
        return array_map(static function (array $row): array {
            $start = new DateTimeImmutable($row['starts_at']);
            $detail = $row['location'];
            if (!empty($row['family_call_at'])) {
                $detail .= ' · Family call ' . date('g:i A', strtotime($row['family_call_at']));
            }
            return [
                'time' => $start->format('g:i A'),
                'title' => $row['title'],
                'detail' => $detail,
                'tag' => strtoupper($start->format('D')),
            ];
        }, $rows);
    }

    private function channels(): array
    {
        return $this->db->query("SELECT name FROM channels WHERE archived_at IS NULL ORDER BY sort_order, name")->fetchAll(PDO::FETCH_COLUMN);
    }

    private function channelPosts(): array
    {
        $stmt = $this->db->query("SELECT CONCAT(u.first_name, ' · ', u.display_role) AS author, cp.created_at, cp.body, cp.pinned, cp.reactions_json FROM channel_posts cp JOIN users u ON u.id = cp.author_user_id JOIN channels c ON c.id = cp.channel_id WHERE c.name = 'Current Production' ORDER BY cp.created_at DESC LIMIT 12");
        $rows = $stmt->fetchAll();

        return array_map(static function (array $row): array {
            $reactions = json_decode($row['reactions_json'] ?: '{}', true) ?: [];
            $labels = [
                'thumbs_up' => '👍',
                'heart' => '❤️',
                'theatre' => '🎭',
                'clap' => '👏',
            ];
            $rendered = [];
            foreach ($reactions as $key => $count) {
                $rendered[] = ($labels[$key] ?? '•') . ' ' . $count;
            }

            return [
                'author' => $row['author'],
                'time' => date('g:i A', strtotime($row['created_at'])),
                'text' => $row['body'],
                'pinned' => (bool)$row['pinned'],
                'reactions' => implode('   ', $rendered),
            ];
        }, $rows);
    }

    private function volunteerStats(): array
    {
        $openShifts = (int)$this->db->query("SELECT COALESCE(SUM(GREATEST(vs.required_slots - COALESCE(s.signups, 0), 0)), 0) FROM volunteer_shifts vs LEFT JOIN (SELECT shift_id, COUNT(*) signups FROM volunteer_shift_signups WHERE status IN ('signed_up','checked_in','completed') GROUP BY shift_id) s ON s.shift_id = vs.id WHERE vs.starts_at >= '2026-08-01 00:00:00'")->fetchColumn();
        $pendingChecks = (int)$this->db->query("SELECT COUNT(*) FROM volunteer_credentials vc JOIN volunteer_requirements vr ON vr.id = vc.requirement_id WHERE vr.code = 'background_check' AND vc.status = 'pending'")->fetchColumn();
        $trainingIncomplete = (int)$this->db->query("SELECT COUNT(*) FROM volunteer_profiles vp LEFT JOIN volunteer_credentials vc ON vc.user_id = vp.user_id AND vc.requirement_id = (SELECT id FROM volunteer_requirements WHERE code = 'child_safety_training' LIMIT 1) WHERE vp.active = 1 AND (vc.id IS NULL OR vc.status <> 'approved')")->fetchColumn();
        $readyVolunteers = (int)$this->db->query("SELECT COUNT(DISTINCT vp.user_id) FROM volunteer_profiles vp JOIN volunteer_credentials bg ON bg.user_id = vp.user_id AND bg.requirement_id = (SELECT id FROM volunteer_requirements WHERE code = 'background_check' LIMIT 1) AND bg.status = 'approved' JOIN volunteer_credentials cs ON cs.user_id = vp.user_id AND cs.requirement_id = (SELECT id FROM volunteer_requirements WHERE code = 'child_safety_training' LIMIT 1) AND cs.status = 'approved' WHERE vp.active = 1")->fetchColumn();

        return [
            ['label' => 'Open shifts', 'value' => (string)$openShifts, 'note' => 'unfilled slots'],
            ['label' => 'Background checks', 'value' => (string)$pendingChecks, 'note' => 'pending review'],
            ['label' => 'Training incomplete', 'value' => (string)$trainingIncomplete, 'note' => 'active volunteers'],
            ['label' => 'Ready volunteers', 'value' => (string)$readyVolunteers, 'note' => 'eligible now'],
        ];
    }

    private function shifts(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT vs.id, vs.title, vs.starts_at, vs.ends_at, vs.location, vs.required_slots, COALESCE(s.signups,0) AS signups FROM volunteer_shifts vs LEFT JOIN (SELECT shift_id, COUNT(*) signups FROM volunteer_shift_signups WHERE status IN ('signed_up','checked_in','completed') GROUP BY shift_id) s ON s.shift_id = vs.id ORDER BY vs.starts_at ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $requirementStmt = $this->db->prepare("SELECT vr.name, COALESCE(vc.status, 'missing') AS credential_status FROM volunteer_shift_requirements vsr JOIN volunteer_requirements vr ON vr.id = vsr.requirement_id LEFT JOIN volunteer_credentials vc ON vc.requirement_id = vr.id AND vc.user_id = :user_id WHERE vsr.shift_id = :shift_id ORDER BY vr.id");

        return array_map(function (array $row) use ($requirementStmt, $userId): array {
            $requirementStmt->execute(['user_id' => $userId, 'shift_id' => $row['id']]);
            $requirements = $requirementStmt->fetchAll();
            $eligible = true;
            foreach ($requirements as $requirement) {
                if ($requirement['credential_status'] !== 'approved') {
                    $eligible = false;
                }
            }

            $start = new DateTimeImmutable($row['starts_at']);
            $end = new DateTimeImmutable($row['ends_at']);
            $open = max((int)$row['required_slots'] - (int)$row['signups'], 0);

            return [
                'title' => $row['title'],
                'when' => $start->format('D · g:i A') . '–' . $end->format('g:i A'),
                'location' => $row['location'],
                'slots' => $open . ' of ' . $row['required_slots'] . ' open',
                'status' => $eligible ? 'eligible' : 'locked',
                'requirements' => $requirements ? implode(' + ', array_column($requirements, 'name')) : 'No special requirements',
            ];
        }, $rows);
    }

    private function forms(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT f.title, fa.status, fa.due_at FROM form_assignments fa JOIN forms f ON f.id = fa.form_id WHERE fa.user_id = :user_id ORDER BY fa.due_at ASC");
        $stmt->execute(['user_id' => $userId]);
        $labels = [
            'completed' => 'Completed',
            'due_soon' => 'Due soon',
            'missing' => 'Missing',
            'requires_review' => 'Requires review',
        ];

        return array_map(static fn(array $row): array => [
            'title' => $row['title'],
            'status' => $labels[$row['status']] ?? $row['status'],
            'due' => $row['due_at'] ? date('M j', strtotime($row['due_at'])) : '—',
        ], $stmt->fetchAll());
    }

    private function playbills(): array
    {
        $rows = $this->db->query("SELECT p.title, p.season, pb.status FROM playbills pb JOIN productions p ON p.id = pb.production_id ORDER BY FIELD(pb.status,'current','draft','archived'), p.id DESC")->fetchAll();
        return array_map(static fn(array $row): array => [
            'title' => $row['title'],
            'season' => $row['season'],
            'status' => ucfirst($row['status']),
        ], $rows);
    }

    private function people(): array
    {
        $rows = $this->db->query("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.initials, u.display_role AS role, EXISTS(SELECT 1 FROM volunteer_profiles vp WHERE vp.user_id = u.id AND vp.active = 1) AS is_volunteer, EXISTS(SELECT 1 FROM volunteer_credentials vc JOIN volunteer_requirements vr ON vr.id = vc.requirement_id WHERE vc.user_id = u.id AND vr.code = 'background_check' AND vc.status = 'approved') AS background_ready, EXISTS(SELECT 1 FROM volunteer_credentials vc JOIN volunteer_requirements vr ON vr.id = vc.requirement_id WHERE vc.user_id = u.id AND vr.code = 'child_safety_training' AND vc.status = 'approved') AS training_ready FROM users u WHERE u.active = 1 ORDER BY u.last_name, u.first_name LIMIT 12")->fetchAll();

        return array_map(static function (array $row): array {
            $volunteerReady = (bool)$row['is_volunteer'] && (bool)$row['background_ready'] && (bool)$row['training_ready'];
            return [
                'name' => $row['name'],
                'initials' => $row['initials'],
                'role' => $row['role'],
                'status' => $volunteerReady ? 'Ready' : ((bool)$row['is_volunteer'] ? 'Review' : 'Active'),
                'context' => (bool)$row['is_volunteer'] ? ($volunteerReady ? 'Volunteer requirements current' : 'Volunteer requirements need attention') : 'Organization member',
            ];
        }, $rows);
    }

    private function safeguarding(): array
    {
        $safeguarded = (int)$this->db->query("SELECT COUNT(*) FROM conversations WHERE conversation_type = 'safeguarded'")->fetchColumn();
        $guardianRequired = (int)$this->db->query("SELECT COUNT(*) FROM conversation_participants WHERE guardian_required = 1")->fetchColumn();
        $pendingBackground = (int)$this->db->query("SELECT COUNT(*) FROM volunteer_credentials vc JOIN volunteer_requirements vr ON vr.id = vc.requirement_id WHERE vr.code = 'background_check' AND vc.status IN ('pending','review')")->fetchColumn();
        $credentialExceptions = (int)$this->db->query("SELECT COUNT(*) FROM volunteer_credentials WHERE status IN ('pending','review','missing','expired')")->fetchColumn();

        $queue = $this->db->query("SELECT CONCAT('Credential review · ', u.first_name, ' ', u.last_name) AS title, CONCAT(vr.name, ' is ', vc.status) AS detail, CASE WHEN vr.code = 'background_check' THEN 'High' ELSE 'Review' END AS severity FROM volunteer_credentials vc JOIN volunteer_requirements vr ON vr.id = vc.requirement_id JOIN users u ON u.id = vc.user_id WHERE vc.status IN ('pending','review','missing','expired') ORDER BY FIELD(vc.status,'pending','review','missing','expired'), u.last_name LIMIT 6")->fetchAll();

        return [
            'metrics' => [
                ['value' => (string)$credentialExceptions, 'label' => 'Items awaiting review'],
                ['value' => (string)$safeguarded, 'label' => 'Safeguarded conversations'],
                ['value' => (string)$guardianRequired, 'label' => 'Required guardian links'],
                ['value' => (string)$pendingBackground, 'label' => 'Background check reviews'],
            ],
            'queue' => $queue,
        ];
    }
}
