<?php

declare(strict_types=1);

namespace App\Repository;

use InvalidArgumentException;
use PDO;

final class ModerationRepository
{
    public function __construct(
        private PDO $pdo,
        private AdminRepository $admin,
        private NotificationRepository $notifications
    ) {
    }

    public function report(int $reporterId, string $subjectType, int $subjectId, string $reason, string $details): void
    {
        if (!in_array($subjectType, ['channel_post', 'message', 'user'], true)) {
            throw new InvalidArgumentException('Choose a valid report type.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Report reason is required.');
        }

        $organizationId = $this->admin->organizationId();
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO content_reports (organization_id, reporter_user_id, subject_type, subject_id, reason, details)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([$organizationId, $reporterId, $subjectType, $subjectId, $reason, trim($details) ?: null]);
            $reportId = (int) $this->pdo->lastInsertId();

            $this->audit($organizationId, $reporterId, 'content_report.created', 'content_report', $reportId);
            foreach ($this->safeguardingAdmins() as $adminId) {
                $this->notifications->create($adminId, 'content_report.created', [
                    'report_id' => $reportId,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'reason' => $reason,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function reports(): array
    {
        $statement = $this->pdo->query(
            'SELECT cr.id, cr.subject_type, cr.subject_id, cr.reason, cr.details, cr.status, cr.created_at,
                reporter.first_name, reporter.last_name, reporter.email,
                reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name
             FROM content_reports cr
             INNER JOIN users reporter ON reporter.id = cr.reporter_user_id
             LEFT JOIN users reviewer ON reviewer.id = cr.reviewed_by_user_id
             ORDER BY FIELD(cr.status, "open", "reviewing", "resolved", "dismissed"), cr.created_at DESC
             LIMIT 50'
        );

        return $statement->fetchAll();
    }

    public function updateStatus(int $reportId, string $status, int $reviewerId): void
    {
        if (!in_array($status, ['open', 'reviewing', 'resolved', 'dismissed'], true)) {
            throw new InvalidArgumentException('Choose a valid report status.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE content_reports SET status = ?, reviewed_by_user_id = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $statement->execute([$status, $reviewerId, $reportId]);
        $this->audit($this->admin->organizationId(), $reviewerId, 'content_report.status_updated', 'content_report', $reportId);
    }

    /** @return list<int> */
    private function safeguardingAdmins(): array
    {
        $statement = $this->pdo->query(
            'SELECT DISTINCT om.user_id
             FROM organization_memberships om
             INNER JOIN membership_roles mr ON mr.membership_id = om.id
             INNER JOIN roles r ON r.id = mr.role_id
             WHERE om.status = "active" AND r.code IN ("owner", "administrator", "safeguarding_administrator")'
        );

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function audit(int $organizationId, ?int $actorId, string $action, string $subjectType, int $subjectId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (organization_id, actor_user_id, action, subject_type, subject_id) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$organizationId, $actorId, $action, $subjectType, $subjectId]);
    }
}
