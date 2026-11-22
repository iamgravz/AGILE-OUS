<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;
use RuntimeException;

/**
 * Removes the demo data before the system goes live: every account and applicant with an @agile.test email, and every
 * record that belongs to them. The foreign keys are read from the database, so tables added by later migrations are
 * covered too.
 *
 * Real records are never deleted. Where a real record only names a demo account as the person who acted on it (who
 * approved, decided or verified it), that column is cleared if it may be empty. If it may not be empty, such as a demo
 * reviewer's note on a real welfare case, nothing is removed and the record is listed as a problem to sort out first.
 *
 * Files are never deleted. A file that only removed rows pointed to is moved to storage/removed-demo/<time>/.
 */
final class DemoDataRemover
{
    public const DEMO_DOMAIN = '@agile.test';

    /** Never removed. Links to these tables are ignored; who-did-it columns on them are cleared. */
    private const REFERENCE_TABLES = ['roles', 'permissions', 'role_permissions', 'committees', 'positions',
        'welfare_categories', 'application_questions', 'email_templates', 'academic_terms', 'settings',
        'schema_migrations', 'job_runs', 'rate_limits'];

    /** A row that belongs to its parent: it goes when the parent goes. */
    private const OWNER_COLUMNS = ['user_permissions.user_id', 'user_tokens.user_id', 'applicants.user_id',
        'members.applicant_id', 'members.user_id', 'membership_status_history.member_id', 'digital_credentials.member_id',
        'membership_applications.applicant_id', 'membership_applications.user_id', 'application_access_links.application_id',
        'application_status_history.application_id', 'application_answers.application_id',
        'application_documents.application_id', 'evaluations.application_id', 'interviews.application_id',
        'appointments.application_id', 'appointments.member_id', 'welfare_cases.user_id', 'case_notes.case_id',
        'referrals.case_id', 'case_followups.case_id', 'case_resolutions.case_id', 'welfare_case_history.case_id',
        'welfare_attachments.case_id', 'notifications.user_id', 'eligibility_screenings.member_id',
        'eligibility_documents.screening_id', 'eligibility_reviews.screening_id', 'vacancies.request_id', 'officer_terms.member_id'];

    /** Columns that record who did something. Any other link not listed here must not point to a removed row. */
    private const ACTOR_COLUMNS = ['approved_by', 'decided_by', 'changed_by', 'verified_by', 'evaluator_id',
        'interviewer_id', 'checklist_by', 'prepared_by', 'appointed_by', 'assigned_to', 'closed_by', 'author_id',
        'referred_by', 'handler_id', 'resolved_by', 'actor_id', 'disposition_by', 'updated_by', 'created_by',
        'requested_by', 'head_user_id', 'recorded_by'];

    /** Actor columns named only by their table, because the column name also means an owner elsewhere. */
    private const ACTOR_TABLE_COLUMNS = ['audit_logs.user_id'];

    /** Optional links between rows of the same kind, cleared when the other row goes. */
    private const CLEARABLE_LINKS = ['welfare_cases.related_case_id', 'digital_credentials.replaces_credential_id'];

    /** Columns holding a stored file, and the storage folder it is relative to. */
    private const FILE_COLUMNS = [
        'users.photo_path' => 'uploads',
        'digital_credentials.file_path' => 'credentials',
        'application_documents.stored_path' => 'uploads',
        'welfare_attachments.stored_path' => 'uploads',
        'eligibility_documents.stored_path' => 'uploads',
        'content_posts.cover_image' => 'uploads',
    ];

    private const CHUNK = 500;

    /** @var list<array{table: string, column: string, parent: string, parentColumn: string, nullable: bool}> */
    private array $foreignKeys = [];

    /** @var array<string, ?string> table => its single-column primary key, or null */
    private array $primaryKeys = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Works out what would be removed, without changing anything.
     *
     * @return array{
     *     delete: array<string, list<int>>,
     *     deleteWhere: list<array{table: string, column: string, ids: list<int>, count: int}>,
     *     clear: list<array{table: string, column: string, ids: list<int>}>,
     *     madeByDemo: array<string, int>,
     *     files: list<array{root: string, path: string, table: string, column: string}>,
     *     problems: list<string>
     * }
     */
    public function plan(bool $allowNoAdmin = false): array
    {
        $this->loadSchema();

        /** @var array<string, array<int, true>> $delete */
        $delete = [];
        $madeByDemo = [];
        /** @var array<string, array{table: string, column: string, ids: array<int, true>}> $clear */
        $clear = [];
        /** @var array<string, array{table: string, column: string, ids: list<int>}> $deleteWhere */
        $deleteWhere = [];
        /** @var array<string, array<int, string>> $strictLinks child "table.column" => [child id => parent table] */
        $strictLinks = [];
        $problems = [];

        foreach (['users' => 'user_id', 'applicants' => 'applicant_id'] as $table => $id) {
            foreach ($this->column("SELECT {$id} FROM {$table} WHERE email LIKE ?", ['%' . self::DEMO_DOMAIN]) as $value) {
                $delete[$table][(int) $value] = true;
            }
        }

        // Follow every link to a removed row until nothing new is added.
        do {
            $added = false;

            foreach ($this->foreignKeys as $fk) {
                $parentIds = array_keys($delete[$fk['parent']] ?? []);

                if ($parentIds === [] || in_array($fk['parent'], self::REFERENCE_TABLES, true)) {
                    continue;
                }

                $key = "{$fk['table']}.{$fk['column']}";
                $kind = $this->kind($fk);
                $pk = $this->primaryKeys[$fk['table']] ?? null;

                if ($pk === null) {
                    // A link table with no single id (only owners are expected here): removed by its column.
                    if ($kind === 'owner') {
                        $deleteWhere[$key] = ['table' => $fk['table'], 'column' => $fk['column'], 'ids' => $parentIds];
                    } else {
                        $problems[] = "{$key} points to removed {$fk['parent']} rows, and the script does not know how to handle that table.";
                    }

                    continue;
                }

                foreach ($this->rowsReferencing($fk, $pk, $parentIds) as $childId) {
                    if (isset($delete[$fk['table']][$childId])) {
                        continue;
                    }

                    if ($kind === 'owner' || ($kind === 'actor' && !$fk['nullable'])) {
                        if (in_array($fk['table'], self::REFERENCE_TABLES, true)) {
                            $problems[] = "{$fk['table']} #{$childId} needs a demo account in {$fk['column']}, and that table is kept.";
                            continue;
                        }

                        $delete[$fk['table']][$childId] = true;
                        $added = true;

                        if ($kind === 'actor') {
                            $madeByDemo[$fk['table']] = ($madeByDemo[$fk['table']] ?? 0) + 1;
                        }
                    } elseif ($kind === 'actor' || $kind === 'clearable') {
                        $clear[$key] ??= ['table' => $fk['table'], 'column' => $fk['column'], 'ids' => []];
                        $clear[$key]['ids'][$childId] = true;
                    } else {
                        $strictLinks[$key][$childId] = $fk['parent'];
                    }
                }
            }

            // AI suggestions point to their document or screening by type and id, with no foreign key.
            foreach (['application_document' => 'application_documents', 'eligibility_screening' => 'eligibility_screenings'] as $type => $source) {
                $ids = array_keys($delete[$source] ?? []);

                if ($ids === [] || !isset($this->primaryKeys['ai_extractions'])) {
                    continue;
                }

                foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                    $marks = implode(',', array_fill(0, count($chunk), '?'));

                    foreach ($this->column("SELECT extraction_id FROM ai_extractions WHERE source_type = ? AND source_id IN ({$marks})", [$type, ...$chunk]) as $value) {
                        if (!isset($delete['ai_extractions'][(int) $value])) {
                            $delete['ai_extractions'][(int) $value] = true;
                            $added = true;
                        }
                    }
                }
            }
        } while ($added);

        // A row being removed must not belong to a record that stays.
        foreach ($this->foreignKeys as $fk) {
            if ($this->kind($fk) !== 'owner' || empty($delete[$fk['table']]) || in_array($fk['parent'], self::REFERENCE_TABLES, true)) {
                continue;
            }

            $pk = $this->primaryKeys[$fk['table']] ?? null;

            if ($pk === null) {
                continue;
            }

            foreach ($this->parentsOf($fk, $pk, array_keys($delete[$fk['table']])) as $childId => $parentId) {
                if (!isset($delete[$fk['parent']][$parentId])) {
                    $problems[] = sprintf('%s #%d was made by a demo account but belongs to a real %s record (#%d). Remove or reassign it in the app first.',
                        $fk['table'], $childId, $fk['parent'], $parentId);
                }
            }
        }

        // A real record must not point to a removed one through a link the script cannot clear.
        foreach ($strictLinks as $key => $children) {
            foreach ($children as $childId => $parent) {
                [$table] = explode('.', $key);

                if (!isset($delete[$table][$childId])) {
                    $problems[] = "{$table} #{$childId} is a real record that points to a removed {$parent} row ({$key}).";
                }
            }
        }

        // Only demo people are removed.
        foreach (['users' => 'user_id', 'applicants' => 'applicant_id'] as $table => $id) {
            foreach (array_chunk(array_keys($delete[$table] ?? []), self::CHUNK) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));

                foreach ($this->column("SELECT {$id} FROM {$table} WHERE {$id} IN ({$marks}) AND email NOT LIKE ?", [...$chunk, '%' . self::DEMO_DOMAIN]) as $value) {
                    $problems[] = "{$table} #{$value} has a real email address but is linked to demo data.";
                }
            }
        }

        // Someone must still be able to run the system.
        $demoUsers = array_keys($delete['users'] ?? []);
        $admins = $this->column(
            "SELECT u.user_id FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.slug = 'msw_head' AND u.status = 'active' AND u.email NOT LIKE ?",
            ['%' . self::DEMO_DOMAIN]
        );

        if (!$allowNoAdmin && array_diff(array_map('intval', $admins), $demoUsers) === []) {
            $problems[] = 'No real MSW Committee Head account would be left. Create one first: php jobs/create_user.php email first last msw_head';
        }

        $deleteIds = array_map(static fn (array $ids): array => array_keys($ids), $delete);
        $clearList = [];

        foreach ($clear as $entry) {
            // A row that is removed anyway needs no clearing.
            $ids = array_values(array_filter(array_keys($entry['ids']), static fn (int $id): bool => !isset($delete[$entry['table']][$id])));

            if ($ids !== []) {
                $clearList[] = ['table' => $entry['table'], 'column' => $entry['column'], 'ids' => $ids];
            }
        }

        $whereList = [];

        foreach ($deleteWhere as $entry) {
            $count = 0;

            foreach (array_chunk($entry['ids'], self::CHUNK) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                $count += (int) ($this->column("SELECT COUNT(*) FROM `{$entry['table']}` WHERE `{$entry['column']}` IN ({$marks})", $chunk)[0] ?? 0);
            }

            if ($count > 0) {
                $whereList[] = $entry + ['count' => $count];
            }
        }

        ksort($deleteIds);

        return [
            'delete' => $deleteIds,
            'deleteWhere' => $whereList,
            'clear' => $clearList,
            'madeByDemo' => $madeByDemo,
            'files' => $this->filesOf($deleteIds),
            'problems' => array_values(array_unique($problems)),
        ];
    }

    /**
     * Removes what plan() found, in one transaction, and then moves the files that nothing points to any more.
     * With $keep false the transaction is rolled back after every check has passed, and no file is moved.
     *
     * @param array<string, mixed> $plan
     * @return array{removed: array<string, int>, cleared: int, moved: list<string>}
     */
    public function apply(array $plan, bool $keep = true): array
    {
        if ($plan['problems'] !== []) {
            throw new RuntimeException('The plan has problems, so nothing was removed.');
        }

        $this->loadSchema();
        $removed = [];
        $cleared = 0;

        $this->db->beginTransaction();
        // The removed rows point to each other, so they are removed together and checked afterwards.
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($plan['clear'] as $entry) {
                $pk = (string) $this->primaryKeys[$entry['table']];

                foreach (array_chunk($entry['ids'], self::CHUNK) as $chunk) {
                    $marks = implode(',', array_fill(0, count($chunk), '?'));
                    $statement = $this->db->prepare("UPDATE `{$entry['table']}` SET `{$entry['column']}` = NULL WHERE `{$pk}` IN ({$marks})");
                    $statement->execute($chunk);
                    $cleared += $statement->rowCount();
                }
            }

            foreach ($plan['deleteWhere'] as $entry) {
                foreach (array_chunk($entry['ids'], self::CHUNK) as $chunk) {
                    $marks = implode(',', array_fill(0, count($chunk), '?'));
                    $statement = $this->db->prepare("DELETE FROM `{$entry['table']}` WHERE `{$entry['column']}` IN ({$marks})");
                    $statement->execute($chunk);
                    $removed[$entry['table']] = ($removed[$entry['table']] ?? 0) + $statement->rowCount();
                }
            }

            foreach ($plan['delete'] as $table => $ids) {
                $pk = (string) $this->primaryKeys[$table];

                foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                    $marks = implode(',', array_fill(0, count($chunk), '?'));
                    $statement = $this->db->prepare("DELETE FROM `{$table}` WHERE `{$pk}` IN ({$marks})");
                    $statement->execute($chunk);
                    $removed[$table] = ($removed[$table] ?? 0) + $statement->rowCount();
                }
            }

            $orphans = $this->orphans();

            if ($orphans !== []) {
                throw new RuntimeException('Removing would leave broken links (' . implode(', ', $orphans) . '), so nothing was removed.');
            }

            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

            if ($keep) {
                $this->db->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details_json, ip_address, user_agent) VALUES (NULL, ?, NULL, NULL, ?, ?, ?)')
                    ->execute(['demo.removed', json_encode(['removed' => $removed, 'cleared' => $cleared], JSON_THROW_ON_ERROR), 'cli', 'database/remove_demo.php']);
                $this->db->commit();
            } else {
                $this->db->rollBack();
            }
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

            throw $exception;
        }

        return ['removed' => $removed, 'cleared' => $cleared, 'moved' => $keep ? $this->moveFiles($plan['files']) : []];
    }

    private function loadSchema(): void
    {
        if ($this->foreignKeys !== []) {
            return;
        }

        $rows = $this->db->query(
            "SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, c.IS_NULLABLE
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = k.TABLE_SCHEMA AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME
             WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY k.TABLE_NAME, k.COLUMN_NAME"
        )->fetchAll(PDO::FETCH_NUM);

        foreach ($rows as [$table, $column, $parent, $parentColumn, $nullable]) {
            $this->foreignKeys[] = ['table' => $table, 'column' => $column, 'parent' => $parent, 'parentColumn' => $parentColumn, 'nullable' => $nullable === 'YES'];
        }

        $keys = $this->db->query(
            "SELECT TABLE_NAME, GROUP_CONCAT(COLUMN_NAME) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'PRIMARY' GROUP BY TABLE_NAME"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($keys as $table => $columns) {
            $this->primaryKeys[(string) $table] = str_contains((string) $columns, ',') ? null : (string) $columns;
        }
    }

    /** @param array{table: string, column: string, nullable: bool} $fk */
    private function kind(array $fk): string
    {
        $key = "{$fk['table']}.{$fk['column']}";

        return match (true) {
            in_array($key, self::OWNER_COLUMNS, true) => 'owner',
            in_array($key, self::ACTOR_TABLE_COLUMNS, true), in_array($fk['column'], self::ACTOR_COLUMNS, true) => 'actor',
            in_array($key, self::CLEARABLE_LINKS, true) && $fk['nullable'] => 'clearable',
            default => 'strict',
        };
    }

    /**
     * @param array{table: string, column: string} $fk
     * @param list<int> $parentIds
     * @return list<int>
     */
    private function rowsReferencing(array $fk, string $pk, array $parentIds): array
    {
        $ids = [];

        foreach (array_chunk($parentIds, self::CHUNK) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));

            foreach ($this->column("SELECT `{$pk}` FROM `{$fk['table']}` WHERE `{$fk['column']}` IN ({$marks})", $chunk) as $value) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    /**
     * @param array{table: string, column: string} $fk
     * @param list<int> $childIds
     * @return array<int, int> child id => parent id
     */
    private function parentsOf(array $fk, string $pk, array $childIds): array
    {
        $parents = [];

        foreach (array_chunk($childIds, self::CHUNK) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $statement = $this->db->prepare("SELECT `{$pk}`, `{$fk['column']}` FROM `{$fk['table']}` WHERE `{$pk}` IN ({$marks}) AND `{$fk['column']}` IS NOT NULL");
            $statement->execute($chunk);

            foreach ($statement->fetchAll(PDO::FETCH_NUM) as [$child, $parent]) {
                $parents[(int) $child] = (int) $parent;
            }
        }

        return $parents;
    }

    /**
     * @param array<string, list<int>> $delete
     * @return list<array{root: string, path: string, table: string, column: string}>
     */
    private function filesOf(array $delete): array
    {
        $files = [];

        foreach (self::FILE_COLUMNS as $key => $root) {
            [$table, $column] = explode('.', $key);
            $pk = $this->primaryKeys[$table] ?? null;

            if ($pk === null || empty($delete[$table]) || !$this->hasColumn($table, $column)) {
                continue;
            }

            foreach (array_chunk($delete[$table], self::CHUNK) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));

                foreach ($this->column("SELECT DISTINCT `{$column}` FROM `{$table}` WHERE `{$pk}` IN ({$marks}) AND `{$column}` IS NOT NULL AND `{$column}` <> ''", $chunk) as $path) {
                    $files["{$root}/{$path}"] = ['root' => $root, 'path' => (string) $path, 'table' => $table, 'column' => $column];
                }
            }
        }

        return array_values($files);
    }

    /**
     * Moves files no remaining row points to. Never deletes, and never touches a file outside its storage folder.
     *
     * @param list<array{root: string, path: string}> $files
     * @return list<string>
     */
    private function moveFiles(array $files): array
    {
        $moved = [];
        $holding = Config::basePath('storage/removed-demo/' . date('Ymd_His'));

        foreach ($files as $file) {
            if ($this->stillReferenced($file['path']) || !preg_match('#^[A-Za-z0-9_\-/]+\.[A-Za-z0-9]+$#', $file['path']) || str_contains($file['path'], '..')) {
                continue;
            }

            $source = Config::basePath("storage/{$file['root']}/{$file['path']}");

            if (!is_file($source)) {
                continue;
            }

            $target = "{$holding}/{$file['root']}/{$file['path']}";

            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0775, true);
            }

            if (rename($source, $target)) {
                $moved[] = "{$file['root']}/{$file['path']}";
            }
        }

        return $moved;
    }

    private function stillReferenced(string $path): bool
    {
        foreach (array_keys(self::FILE_COLUMNS) as $key) {
            [$table, $column] = explode('.', $key);

            if ($this->hasColumn($table, $column) && $this->column("SELECT 1 FROM `{$table}` WHERE `{$column}` = ? LIMIT 1", [$path]) !== []) {
                return true;
            }
        }

        return false;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->column(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) !== [];
    }

    /** @return list<string> "table.column (n)" for every foreign key that points to a missing row. */
    private function orphans(): array
    {
        $broken = [];

        foreach ($this->foreignKeys as $fk) {
            $count = (int) $this->db->query(
                "SELECT COUNT(*) FROM `{$fk['table']}` c LEFT JOIN `{$fk['parent']}` p ON p.`{$fk['parentColumn']}` = c.`{$fk['column']}`
                 WHERE c.`{$fk['column']}` IS NOT NULL AND p.`{$fk['parentColumn']}` IS NULL"
            )->fetchColumn();

            if ($count > 0) {
                $broken[] = "{$fk['table']}.{$fk['column']} ({$count})";
            }
        }

        return $broken;
    }

    /**
     * @param list<mixed> $params
     * @return list<mixed>
     */
    private function column(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }
}
