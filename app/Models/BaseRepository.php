<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

abstract class BaseRepository
{
    protected string $table;
    protected string $primaryKey;

    /** @var list<string> Columns that paginate() may sort by. */
    protected array $sortable = [];

    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo()->prepare(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1"
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /**
     * Rows where every column equals the given value. A null value matches IS NULL.
     *
     * @param array<string, mixed> $criteria
     * @return list<array<string, mixed>>
     */
    public function findBy(array $criteria): array
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $column => $value) {
            $name = $this->column($column);

            if ($value === null) {
                $conditions[] = "{$name} IS NULL";
                continue;
            }

            $conditions[] = "{$name} = ?";
            $params[] = $value;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $statement = $this->pdo()->prepare("SELECT * FROM `{$this->table}`{$where}");
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     * @return int The new auto-increment id.
     */
    public function insert(array $data): int
    {
        $columns = implode(', ', array_map($this->column(...), array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $statement = $this->pdo()->prepare("INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})");
        $statement->execute(array_values($data));

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @return int The number of rows changed.
     */
    public function update(int $id, array $data): int
    {
        $assignments = implode(', ', array_map(fn (string $column): string => $this->column($column) . ' = ?', array_keys($data)));

        $statement = $this->pdo()->prepare(
            "UPDATE `{$this->table}` SET {$assignments} WHERE `{$this->primaryKey}` = ?"
        );
        $statement->execute([...array_values($data), $id]);

        return $statement->rowCount();
    }

    /** @return int The number of rows deleted. */
    public function delete(int $id): int
    {
        $statement = $this->pdo()->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?");
        $statement->execute([$id]);

        return $statement->rowCount();
    }

    /**
     * $where must be a fixed SQL fragment with placeholders; user input only ever goes in $params.
     * $orderBy is a column from $sortable, optionally followed by ASC or DESC.
     *
     * @param array<int|string, mixed> $params
     * @return array{data: list<array<string, mixed>>, total: int, page: int, perPage: int, lastPage: int}
     */
    public function paginate(int $page = 1, int $perPage = 20, string $where = '', array $params = [], ?string $orderBy = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $whereSql = $where === '' ? '' : " WHERE {$where}";

        $count = $this->pdo()->prepare("SELECT COUNT(*) FROM `{$this->table}`{$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        // $perPage and $offset are integers, so they are safe to place in the SQL text.
        $offset = ($page - 1) * $perPage;
        $orderSql = $this->orderClause($orderBy);
        $statement = $this->pdo()->prepare(
            "SELECT * FROM `{$this->table}`{$whereSql} ORDER BY {$orderSql} LIMIT {$perPage} OFFSET {$offset}"
        );
        $statement->execute($params);

        return [
            'data' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    protected function pdo(): PDO
    {
        return $this->db ??= Database::connection();
    }

    private function orderClause(?string $orderBy): string
    {
        if ($orderBy === null) {
            return "`{$this->primaryKey}` DESC";
        }

        $parts = preg_split('/\s+/', trim($orderBy));
        $column = $parts[0];
        $direction = strtoupper($parts[1] ?? 'ASC');

        if (count($parts) > 2 || !in_array($column, $this->sortable, true) || !in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Sorting by \"{$orderBy}\" is not allowed on {$this->table}.");
        }

        return "`{$column}` {$direction}";
    }

    private function column(string $name): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid column name: {$name}");
        }

        return "`{$name}`";
    }
}
