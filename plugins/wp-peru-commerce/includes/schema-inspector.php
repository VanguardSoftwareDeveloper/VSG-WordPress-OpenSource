<?php
/**
 * Lectura estricta de integridad física del esquema, sin crear ni modificar tablas.
 * IMP-05. No confundir comprobación de metadatos con prueba de concurrencia real.
 *
 * @package PeruCommerce
 */

declare(strict_types=1);

namespace PeruCommerce;

if (!\defined('ABSPATH')) {
    exit;
}

final class Schema_Inspector
{
    private \wpdb $db;
    private ?bool $isMariaDb = null;

    public function __construct(\wpdb $db)
    {
        $this->db = $db;
    }

    public function tableExists(string $suffix): bool
    {
        $name = $this->physicalName($suffix);
        $rows = $this->rows($this->db->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $name
        ));

        if ([] === $rows) {
            return false;
        }
        if (1 !== \count($rows) || 'innodb' !== \strtolower((string) $rows[0]['ENGINE'])) {
            throw new \RuntimeException('pc_schema_engine_mismatch');
        }
        return true;
    }

    public function verifyTable(string $suffix, string $ddl): void
    {
        if (!$this->tableExists($suffix)) {
            throw new \RuntimeException('pc_schema_table_missing');
        }

        $table = $this->physicalName($suffix);
        $columnRows = $this->rows($this->db->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_DEFAULT '
                . 'FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
            $table
        ));
        $expected = [];
        foreach (\explode("\n", $ddl) as $line) {
            if (!\preg_match('/^\s*\x60([a-z_]+)\x60\s+'
                . '(BIGINT UNSIGNED|BIGINT|INT UNSIGNED|SMALLINT UNSIGNED|TINYINT UNSIGNED|TINYINT\(1\)'
                . '|VARCHAR\(\d+\)|CHAR\(\d+\)|LONGTEXT|BINARY\(\d+\)|DATETIME\(6\)|DATE|TIME)'
                . '\s+(NOT NULL|NULL)\b(.*)$/i', $line, $part)) {
                continue;
            }
            $expected[$part[1]] = [
                'type' => $this->canonicalType($part[2]),
                'nullable' => 'NULL' === \strtoupper($part[3]),
                'auto' => false !== \stripos($part[4], 'AUTO_INCREMENT'),
                'default_one' => false !== \stripos($part[4], 'DEFAULT 1'),
            ];
        }

        if ([] === $expected || \count($columnRows) !== \count($expected)) {
            throw new \RuntimeException('pc_schema_columns_mismatch');
        }
        foreach ($columnRows as $row) {
            $name = (string) $row['COLUMN_NAME'];
            if (!isset($expected[$name])) {
                throw new \RuntimeException('pc_schema_columns_mismatch');
            }
            $type = $expected[$name];
            if ($type['type'] !== $this->canonicalType((string) $row['COLUMN_TYPE'])
                || $type['nullable'] !== ('YES' === $row['IS_NULLABLE'])
                || $type['auto'] !== (false !== \stripos((string) $row['EXTRA'], 'auto_increment'))) {
                throw new \RuntimeException('pc_schema_columns_mismatch');
            }

            if ($type['default_one']) {
                if ('1' !== (string) $row['COLUMN_DEFAULT']) {
                    throw new \RuntimeException('pc_schema_columns_mismatch');
                }
            } elseif (null !== $row['COLUMN_DEFAULT']) {
                throw new \RuntimeException('pc_schema_columns_mismatch');
            }

            if (\preg_match('/^(varchar|char|longtext)/', $type['type'])) {
                if ('utf8mb4' !== \strtolower((string) $row['CHARACTER_SET_NAME'])
                    || ('' !== $this->db->collate
                        && \strtolower((string) $row['COLLATION_NAME']) !== \strtolower($this->db->collate))) {
                    throw new \RuntimeException('pc_schema_charset_mismatch');
                }
            }
        }

        $this->verifyIndexes($table, $ddl);
        $this->verifyChecks($table, $ddl);
    }

    public function foreignKeyExists(string $statement): bool
    {
        $namePattern = '/^ALTER TABLE \x60([A-Za-z0-9_]+)\x60 ADD CONSTRAINT \x60(pcfk_[0-9a-f]+)\x60 '
            . 'FOREIGN KEY \(([^)]+)\) REFERENCES \x60([A-Za-z0-9_]+)\x60 \(([^)]+)\) '
            . 'ON DELETE RESTRICT ON UPDATE RESTRICT$/D';
        if (!\preg_match($namePattern, $statement, $matches)) {
            throw new \LogicException('pc_schema_fk_definition_invalid');
        }

        [, $table, $constraint, $childPart, $parent, $parentPart] = $matches;
        $childColumns = $this->identifierList($childPart);
        $parentColumns = $this->identifierList($parentPart);
        if ([] === $childColumns || \count($childColumns) !== \count($parentColumns)) {
            throw new \LogicException('pc_schema_fk_definition_invalid');
        }

        $rows = $this->rows($this->db->prepare(
            'SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, '
                . 'r.DELETE_RULE, r.UPDATE_RULE '
                . 'FROM information_schema.KEY_COLUMN_USAGE AS k '
                . 'JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r '
                . 'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA '
                . 'AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME '
                . 'WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = %s AND k.CONSTRAINT_NAME = %s '
                . 'ORDER BY k.ORDINAL_POSITION',
            $table, $constraint
        ));
        if ([] === $rows) {
            return false;
        }
        if (\count($rows) !== \count($childColumns)) {
            throw new \RuntimeException('pc_schema_fk_mismatch');
        }
        foreach ($rows as $index => $row) {
            if ((string) $row['COLUMN_NAME'] !== $childColumns[$index]
                || (string) $row['REFERENCED_TABLE_NAME'] !== $parent
                || (string) $row['REFERENCED_COLUMN_NAME'] !== $parentColumns[$index]
                || !\in_array((string) $row['DELETE_RULE'], ['RESTRICT', 'NO ACTION'], true)
                || !\in_array((string) $row['UPDATE_RULE'], ['RESTRICT', 'NO ACTION'], true)) {
                throw new \RuntimeException('pc_schema_fk_mismatch');
            }
        }
        return true;
    }

    public function verifyAll(): void
    {
        $statements = Schema::tableStatements($this->db);
        if (39 !== \count($statements)) {
            throw new \LogicException('pc_schema_inventory_invalid');
        }
        foreach ($statements as $suffix => $sql) {
            $this->verifyTable($suffix, $sql);
        }

        $foreignKeys = Schema::foreignKeyStatements($this->db);
        if (56 !== \count($foreignKeys)) {
            throw new \LogicException('pc_schema_inventory_invalid');
        }
        foreach ($foreignKeys as $statement) {
            if (!$this->foreignKeyExists($statement)) {
                throw new \RuntimeException('pc_schema_fk_missing');
            }
        }
        $this->verifyAnchor();
    }

    public function verifyAnchor(): void
    {
        $table = $this->physicalName('pc_schedule_revisions');
        $rows = $this->rows($this->db->prepare(
            'SELECT resource_id, row_version, active_revision_no, timezone '
                . 'FROM %i ORDER BY resource_id LIMIT 2',
            $table
        ));
        if (1 !== \count($rows) || '1' !== (string) $rows[0]['resource_id']
            || (int) $rows[0]['row_version'] < 1
            || (int) $rows[0]['active_revision_no'] < 0
            || !\is_string($rows[0]['timezone']) || '' === $rows[0]['timezone']) {
            throw new \RuntimeException('pc_schema_anchor_invalid');
        }
    }

    /** @return list<array<string,mixed>> */
    public function rows(string $sql): array
    {
        $suppress = $this->db->suppress_errors(true);
        $show = $this->db->hide_errors();
        try {
            $rows = $this->db->get_results($sql, ARRAY_A);
            if (!\is_array($rows) || '' !== $this->db->last_error) {
                throw new \RuntimeException('pc_schema_metadata_unavailable');
            }
            return $rows;
        } finally {
            $this->db->suppress_errors($suppress);
            if ($show) {
                $this->db->show_errors(true);
            }
        }
    }

    private function verifyIndexes(string $table, string $ddl): void
    {
        $expected = [];
        foreach (\explode("\n", $ddl) as $line) {
            if (!\preg_match('/^\s*(PRIMARY KEY|UNIQUE KEY \x60([a-z0-9_]+)\x60|KEY \x60([a-z0-9_]+)\x60)\s+\(([^)]+)\)/',
                $line, $parts)) {
                continue;
            }
            $key = 'PRIMARY KEY' === $parts[1] ? 'PRIMARY' : ($parts[2] ?: $parts[3]);
            $expected[$key] = [
                'unique' => 'PRIMARY KEY' === $parts[1] || 0 === \strpos($parts[1], 'UNIQUE'),
                'columns' => $this->identifierList($parts[4]),
            ];
        }
        if ([] === $expected) {
            throw new \LogicException('pc_schema_index_definition_invalid');
        }

        $rows = $this->rows($this->db->prepare(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE, SUB_PART, INDEX_TYPE '
                . 'FROM information_schema.STATISTICS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            $table
        ));
        $actual = [];
        foreach ($rows as $row) {
            $name = (string) $row['INDEX_NAME'];
            if (null === $row['SUB_PART'] && 'BTREE' === \strtoupper((string) $row['INDEX_TYPE'])) {
                $actual[$name]['unique'] = 0 === (int) $row['NON_UNIQUE'];
                $actual[$name]['columns'][] = (string) $row['COLUMN_NAME'];
            }
        }
        foreach ($expected as $name => $index) {
            if (!isset($actual[$name])
                || $actual[$name]['unique'] !== $index['unique']
                || $actual[$name]['columns'] !== $index['columns']) {
                throw new \RuntimeException('pc_schema_index_mismatch');
            }
        }
    }

    private function verifyChecks(string $table, string $ddl): void
    {
        $expected = [];
        foreach (\explode("\n", $ddl) as $line) {
            if (\preg_match('/^\s*CHECK \((.+)\),?$/', $line, $parts)) {
                $expected[] = $this->checkSignature(\rtrim($parts[1], ','));
            }
        }
        // MariaDB tiene CHECK_CONSTRAINTS.TABLE_NAME y permite nombres
        // automáticos repetidos por tabla; MySQL 8.4 no expone esa columna.
        // Un JOIN solo por constraint_name en MariaDB mezclaría otras tablas.
        if (null === $this->isMariaDb) {
            $server = $this->rows('SELECT VERSION() AS server_version');
            if (1 !== \count($server)) {
                throw new \RuntimeException('pc_schema_metadata_unavailable');
            }
            $this->isMariaDb = false !== \stripos((string) $server[0]['server_version'], 'mariadb');
        }
        if ($this->isMariaDb) {
            $rows = $this->rows($this->db->prepare(
                'SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS '
                    . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $table
            ));
        } else {
            $rows = $this->rows($this->db->prepare(
                'SELECT c.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS AS c '
                    . 'JOIN information_schema.TABLE_CONSTRAINTS AS t '
                    . 'ON t.CONSTRAINT_SCHEMA = c.CONSTRAINT_SCHEMA '
                    . 'AND t.CONSTRAINT_NAME = c.CONSTRAINT_NAME '
                    . 'WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = %s '
                    . 'AND t.CONSTRAINT_TYPE = %s',
                $table, 'CHECK'
            ));
        }
        if (\count($expected) !== \count($rows)) {
            throw new \RuntimeException('pc_schema_check_mismatch');
        }
        $actual = \array_map(fn (array $row): string =>
            $this->checkSignature((string) $row['CHECK_CLAUSE']), $rows);
        \sort($expected);
        \sort($actual);
        if ($expected !== $actual) {
            throw new \RuntimeException('pc_schema_check_mismatch');
        }

        if ([] !== $expected) {
            $definition = $this->rows($this->db->prepare('SHOW CREATE TABLE %i', $table));
            if (1 !== \count($definition)
                || !\is_string($definition[0]['Create Table'] ?? null)
                || false !== \stripos($definition[0]['Create Table'], 'NOT ENFORCED')) {
                throw new \RuntimeException('pc_schema_check_mismatch');
            }
        }
    }

    private function checkSignature(string $check): string
    {
        // MySQL/MariaDB agregan comillas, espacios y paréntesis exteriores.
        return \preg_replace('/[\x60"()\s]+/', '', \strtolower($check)) ?? '';
    }

    /** @return list<string> */
    private function identifierList(string $quoted): array
    {
        \preg_match_all('/\x60([a-z][a-z0-9_]*)\x60/', $quoted, $parts);
        return $parts[1];
    }

    private function canonicalType(string $type): string
    {
        $type = \strtolower(\preg_replace('/\s+/', ' ', \trim($type)) ?? '');
        return \preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type) ?? '';
    }

    private function physicalName(string $suffix): string
    {
        $names = Schema::tableNames($this->db);
        if (!isset($names[$suffix])) {
            throw new \LogicException('pc_schema_unknown_table');
        }
        return $names[$suffix];
    }
}
