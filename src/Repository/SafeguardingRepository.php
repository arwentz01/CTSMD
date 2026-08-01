<?php

declare(strict_types=1);

namespace App\Repository;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class SafeguardingRepository
{
    public function __construct(private PDO $pdo, private AdminRepository $admin)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function activeStudents(): array
    {
        return $this->peopleByStudentFlag(true);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeAdults(): array
    {
        return $this->peopleByStudentFlag(false);
    }

    /** @return array<int, array<string, mixed>> */
    public function guardianLinks(): array
    {
        $statement = $this->pdo->query(
            'SELECT gsr.id, gsr.status, gsr.relationship_label,
                guardian.first_name AS guardian_first_name, guardian.last_name AS guardian_last_name, guardian.email AS guardian_email,
                student.first_name AS student_first_name, student.last_name AS student_last_name, student.email AS student_email
             FROM guardian_student_relationships gsr
             INNER JOIN users guardian ON guardian.id = gsr.guardian_user_id
             INNER JOIN users student ON student.id = gsr.student_user_id
             ORDER BY gsr.created_at DESC'
        );

        return $statement->fetchAll();
    }

    public function linkGuardian(int $guardianUserId, int $studentUserId, string $label, int $actorId): void
    {
        if ($guardianUserId === $studentUserId) {
            throw new InvalidArgumentException('A student cannot be their own guardian.');
        }

        $guardian = $this->user($guardianUserId);
        $student = $this->user($studentUserId);

        if ($guardian === null || $student === null || (int) $guardian['is_student'] === 1 || (int) $student['is_student'] !== 1) {
            throw new InvalidArgumentException('Choose one active adult guardian and one active student.');
        }

        $organizationId = $this->admin->organizationId();
        $statement = $this->pdo->prepare(
            'INSERT INTO guardian_student_relationships
                (organization_id, guardian_user_id, student_user_id, relationship_label, status, approved_by_user_id, approved_at)
             VALUES (?, ?, ?, ?, "approved", ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE relationship_label = VALUES(relationship_label), status = "approved",
                approved_by_user_id = VALUES(approved_by_user_id), approved_at = CURRENT_TIMESTAMP, revoked_at = NULL'
        );
        $statement->execute([$organizationId, $guardianUserId, $studentUserId, trim($label) ?: null, $actorId]);
        $this->audit($organizationId, $actorId, 'guardian_student.approved', 'user', $studentUserId);
    }

    public function createSafeguardedConversation(int $adultUserId, int $studentUserId, int $actorId): int
    {
        if ($adultUserId === $studentUserId) {
            throw new InvalidArgumentException('Choose two different people.');
        }

        $adult = $this->user($adultUserId);
        $student = $this->user($studentUserId);

        if ($adult === null || $student === null || (int) $adult['is_student'] === 1 || (int) $student['is_student'] !== 1) {
            throw new InvalidArgumentException('Safeguarded conversations require one active adult and one active student.');
        }

        $organizationId = $this->admin->organizationId();
        $guardians = $this->approvedGuardians($organizationId, $studentUserId);
        if ($guardians === []) {
            throw new RuntimeException('This student needs at least one approved guardian before messaging can start.');
        }

        $this->pdo->beginTransaction();
        try {
            $conversation = $this->pdo->prepare(
                'INSERT INTO conversations (organization_id, type, created_by_user_id) VALUES (?, "safeguarded", ?)'
            );
            $conversation->execute([$organizationId, $actorId]);
            $conversationId = (int) $this->pdo->lastInsertId();

            $this->participant($conversationId, $adultUserId, 'adult', false, $actorId);
            $this->participant($conversationId, $studentUserId, 'student', false, $actorId);

            foreach ($guardians as $guardianId) {
                $this->participant($conversationId, $guardianId, 'guardian', true, $actorId);
            }

            $this->audit($organizationId, $actorId, 'conversation.safeguarded_created', 'conversation', $conversationId);
            $this->pdo->commit();

            return $conversationId;
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function conversations(): array
    {
        $statement = $this->pdo->query(
            'SELECT c.id, c.type, c.created_at,
                GROUP_CONCAT(CONCAT(u.first_name, " ", u.last_name, " (", cp.participant_kind, IF(cp.is_required = 1, ", required", ""), ")") ORDER BY cp.participant_kind SEPARATOR "; ") AS participants
             FROM conversations c
             INNER JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.left_at IS NULL
             INNER JOIN users u ON u.id = cp.user_id
             GROUP BY c.id
             ORDER BY c.created_at DESC'
        );

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function conversationsForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.type, c.created_at,
                GROUP_CONCAT(CONCAT(u.first_name, " ", u.last_name, " (", cp.participant_kind, IF(cp.is_required = 1, ", required", ""), ")") ORDER BY cp.participant_kind SEPARATOR "; ") AS participants
             FROM conversations c
             INNER JOIN conversation_participants mine ON mine.conversation_id = c.id AND mine.user_id = ? AND mine.left_at IS NULL
             INNER JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.left_at IS NULL
             INNER JOIN users u ON u.id = cp.user_id
             GROUP BY c.id
             ORDER BY c.updated_at DESC'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function conversationForUser(int $conversationId, int $userId): ?array
    {
        if (!$this->isParticipant($conversationId, $userId)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT c.id, c.type, c.created_at,
                GROUP_CONCAT(CONCAT(u.first_name, " ", u.last_name, " (", cp.participant_kind, IF(cp.is_required = 1, ", required", ""), ")") ORDER BY cp.participant_kind SEPARATOR "; ") AS participants
             FROM conversations c
             INNER JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.left_at IS NULL
             INNER JOIN users u ON u.id = cp.user_id
             WHERE c.id = ?
             GROUP BY c.id
             LIMIT 1'
        );
        $statement->execute([$conversationId]);
        $conversation = $statement->fetch();

        return is_array($conversation) ? $conversation : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function messagesForUser(int $conversationId, int $userId): array
    {
        if (!$this->isParticipant($conversationId, $userId)) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT m.id, m.body, m.created_at, m.deleted_at, u.first_name, u.last_name, u.email
             FROM messages m
             INNER JOIN users u ON u.id = m.sender_user_id
             WHERE m.conversation_id = ?
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $statement->execute([$conversationId]);

        return $statement->fetchAll();
    }

    public function postMessage(int $conversationId, int $senderUserId, string $body): void
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Message body is required.');
        }

        if (!$this->isParticipant($conversationId, $senderUserId)) {
            throw new RuntimeException('Only active conversation participants can post messages.');
        }

        $this->pdo->beginTransaction();
        try {
            $message = $this->pdo->prepare(
                'INSERT INTO messages (conversation_id, sender_user_id, body) VALUES (?, ?, ?)'
            );
            $message->execute([$conversationId, $senderUserId, $body]);
            $messageId = (int) $this->pdo->lastInsertId();

            $organizationId = (int) $this->pdo
                ->query('SELECT organization_id FROM conversations WHERE id = ' . $conversationId . ' LIMIT 1')
                ->fetchColumn();
            $this->audit($organizationId, $senderUserId, 'message.created', 'message', $messageId);

            $this->pdo->commit();
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function peopleByStudentFlag(bool $isStudent): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, first_name, last_name, email FROM users WHERE status = "active" AND deleted_at IS NULL AND is_student = ? ORDER BY last_name, first_name'
        );
        $statement->execute([$isStudent ? 1 : 0]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    private function user(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, is_student FROM users WHERE id = ? AND status = "active" AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute([$userId]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /** @return list<int> */
    private function approvedGuardians(int $organizationId, int $studentUserId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT guardian_user_id FROM guardian_student_relationships
             WHERE organization_id = ? AND student_user_id = ? AND status = "approved" AND revoked_at IS NULL'
        );
        $statement->execute([$organizationId, $studentUserId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function participant(int $conversationId, int $userId, string $kind, bool $isRequired, int $actorId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO conversation_participants (conversation_id, user_id, participant_kind, is_required, added_by_user_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$conversationId, $userId, $kind, $isRequired ? 1 : 0, $actorId]);
    }

    private function isParticipant(int $conversationId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL'
        );
        $statement->execute([$conversationId, $userId]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function audit(int $organizationId, ?int $actorId, string $action, string $subjectType, int $subjectId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (organization_id, actor_user_id, action, subject_type, subject_id) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$organizationId, $actorId, $action, $subjectType, $subjectId]);
    }
}
