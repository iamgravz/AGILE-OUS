<?php

declare(strict_types=1);

namespace App\Models;

use LogicException;

/** Audit logs are insert-only (section 9), so changing or removing a row is refused here too. */
final class AuditLogRepository extends BaseRepository
{
    protected string $table = 'audit_logs';
    protected string $primaryKey = 'log_id';

    /**
     * Everything logged against one application and its documents, newest first.
     *
     * @param list<int> $documentIds
     * @return list<array<string, mixed>>
     */
    public function forApplication(int $applicationId, array $documentIds, int $limit = 200): array
    {
        $documents = '';
        $params = [$applicationId];

        if ($documentIds !== []) {
            $documents = " OR (a.entity_type = 'application_document' AND a.entity_id IN (" . implode(',', array_fill(0, count($documentIds), '?')) . '))';
            array_push($params, ...$documentIds);
        }

        $statement = $this->pdo()->prepare(
            "SELECT a.action, a.details_json, a.created_at, CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             FROM audit_logs a
             LEFT JOIN users u ON u.user_id = a.user_id
             WHERE (a.entity_type = 'membership_application' AND a.entity_id = ?){$documents}
             ORDER BY a.log_id DESC
             LIMIT " . max(1, $limit)
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> Everything logged against one record, newest first. */
    public function forEntity(string $entityType, int $entityId, int $limit = 200): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT a.action, a.details_json, a.created_at, CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             FROM audit_logs a
             LEFT JOIN users u ON u.user_id = a.user_id
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.log_id DESC
             LIMIT " . max(1, $limit)
        );
        $statement->execute([$entityType, $entityId]);

        return $statement->fetchAll();
    }

    /** @return list<string> For filter lists. $column is one of two fixed names. */
    public function distinctValues(string $column): array
    {
        if (!in_array($column, ['action', 'entity_type'], true)) {
            throw new LogicException('Unknown column.');
        }

        return $this->pdo()->query("SELECT DISTINCT {$column} FROM audit_logs WHERE {$column} IS NOT NULL ORDER BY {$column}")->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Newest first.
     *
     * @param array<string, string> $filters user, action, entity, from, to
     * @return array{data: list<array<string, mixed>>, total: int, page: int, perPage: int, lastPage: int}
     */
    public function search(array $filters, int $page, int $perPage = 50): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (($filters['user'] ?? '') !== '') {
            $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            array_push($params, "%{$filters['user']}%", "%{$filters['user']}%");
        }

        if (($filters['action'] ?? '') !== '') {
            $where[] = 'a.action = ?';
            $params[] = $filters['action'];
        }

        if (($filters['entity'] ?? '') !== '') {
            $where[] = 'a.entity_type = ?';
            $params[] = $filters['entity'];
        }

        if (($filters['from'] ?? '') !== '') {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }

        if (($filters['to'] ?? '') !== '') {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $conditions = implode(' AND ', $where);
        $from = 'FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id';
        $count = $this->pdo()->prepare("SELECT COUNT(*) {$from} WHERE {$conditions}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $rows = $this->pdo()->prepare(
            "SELECT a.log_id, a.action, a.entity_type, a.entity_id, a.details_json, a.ip_address, a.created_at, CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             {$from} WHERE {$conditions} ORDER BY a.log_id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $rows->execute($params);

        return ['data' => $rows->fetchAll(), 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'lastPage' => max(1, (int) ceil($total / $perPage))];
    }

    public function update(int $id, array $data): int
    {
        throw new LogicException('Audit logs cannot be changed.');
    }

    public function delete(int $id): int
    {
        throw new LogicException('Audit logs cannot be deleted.');
    }
}
