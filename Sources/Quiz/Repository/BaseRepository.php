<?php

declare(strict_types=1);

namespace Quiz\Repository;

use Quiz\Model\Quiz;
use Quiz\Model\Category;
use Quiz\Model\Question;
use Quiz\Model\Answer;

/**
 * Base Repository
 *
 * Abstract base class for all repositories.
 * Provides common database query functionality and type safety.
 *
 * @package Quiz\Repository
 */
abstract class BaseRepository
{
    /**
     * Database function reference
     *
     * @var callable
     */
    protected $db;

    /**
     * Database prefix
     *
     * @var string
     */
    protected string $dbPrefix = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        global $smcFunc, $db_prefix;

        $this->db = $smcFunc['db_query'] ?? null;
        $this->dbPrefix = $db_prefix ?? '';

        if ($this->db === null) {
            throw new \RuntimeException('Database not available');
        }
    }

    /**
     * Execute a parameterized query
     *
     * All queries use parameterized statements to prevent SQL injection.
     *
     * @param string $query SQL query with ? placeholders
     * @param array<string, mixed> $params Parameters indexed by type (int, string, etc.)
     * @param string $queryType Query type ('', 'console', etc.)
     * @return mixed Query result
     */
    protected function query(string $query, array $params = [], string $queryType = '')
    {
        $query = str_replace('{db_prefix}', $this->dbPrefix, $query);
        return call_user_func($this->db, $queryType, $query, $params);
    }

    /**
     * Fetch single row as associative array
     *
     * @param mixed $result Query result
     * @return array<string, mixed>|null Single row or null
     */
    protected function fetchRow($result): ?array
    {
        global $smcFunc;

        return $smcFunc['db_fetch_assoc']($result) ?: null;
    }

    /**
     * Fetch all rows
     *
     * @param mixed $result Query result
     * @return array<int, array<string, mixed>> All rows
     */
    protected function fetchAll($result): array
    {
        global $smcFunc;

        $rows = [];
        while ($row = $smcFunc['db_fetch_assoc']($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Free query result
     *
     * @param mixed $result Query result
     * @return void
     */
    protected function freeResult($result): void
    {
        global $smcFunc;

        $smcFunc['db_free_result']($result);
    }

    /**
     * Get insert ID from last insert
     *
     * @return int Last insert ID
     */
    protected function getInsertId(): int
    {
        global $smcFunc;

        return (int)$smcFunc['db_insert_id']();
    }

    /**
     * Escape string for database
     *
     * @param string $str String to escape
     * @return string Escaped string
     */
    protected function escape(string $str): string
    {
        global $smcFunc;

        return $smcFunc['db_escape_string']($str) ?? '';
    }
}
