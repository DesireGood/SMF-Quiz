<?php

declare(strict_types=1);

namespace Quiz\Repository;

use Quiz\Model\Result;

/**
 * Result Repository
 *
 * Handles all database operations for quiz results and play sessions.
 *
 * @package Quiz\Repository
 */
final class ResultRepository extends BaseRepository
{
    /**
     * Find result by ID
     *
     * @param int $id Result ID
     * @return Result|null
     */
    public function findById(int $id): ?Result
    {
        $result = $this->query(
            'SELECT id_quiz_result, id_quiz, id_user, result_date, questions, correct, incorrect, timeouts, total_seconds, total_resumes '
            . 'FROM {db_prefix}quiz_result WHERE id_quiz_result = ?',
            ['int' => $id]
        );

        if (!$row = $this->fetchRow($result)) {
            return null;
        }
        $this->freeResult($result);

        return $this->rowToResult($row);
    }

    /**
     * Find all results for a quiz (paginated)
     *
     * @param int $quizId Quiz ID
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array<int, Result>
     */
    public function findByQuiz(int $quizId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $result = $this->query(
            'SELECT id_quiz_result, id_quiz, id_user, result_date, questions, correct, incorrect, timeouts, total_seconds, total_resumes '
            . 'FROM {db_prefix}quiz_result WHERE id_quiz = ? ORDER BY result_date DESC LIMIT ?, ?',
            ['int' => [$quizId, $offset, $perPage]]
        );

        $results = [];
        foreach ($this->fetchAll($result) as $row) {
            $results[] = $this->rowToResult($row);
        }
        $this->freeResult($result);

        return $results;
    }

    /**
     * Find all results for a user (paginated)
     *
     * @param int $userId User ID
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array<int, Result>
     */
    public function findByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $result = $this->query(
            'SELECT id_quiz_result, id_quiz, id_user, result_date, questions, correct, incorrect, timeouts, total_seconds, total_resumes '
            . 'FROM {db_prefix}quiz_result WHERE id_user = ? ORDER BY result_date DESC LIMIT ?, ?',
            ['int' => [$userId, $offset, $perPage]]
        );

        $results = [];
        foreach ($this->fetchAll($result) as $row) {
            $results[] = $this->rowToResult($row);
        }
        $this->freeResult($result);

        return $results;
    }

    /**
     * Find result for a specific user and quiz
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return Result|null
     */
    public function findByQuizAndUser(int $quizId, int $userId): ?Result
    {
        $result = $this->query(
            'SELECT id_quiz_result, id_quiz, id_user, result_date, questions, correct, incorrect, timeouts, total_seconds, total_resumes '
            . 'FROM {db_prefix}quiz_result WHERE id_quiz = ? AND id_user = ? ORDER BY result_date DESC LIMIT 1',
            ['int' => [$quizId, $userId]]
        );

        if (!$row = $this->fetchRow($result)) {
            return null;
        }
        $this->freeResult($result);

        return $this->rowToResult($row);
    }

    /**
     * Count how many times a user has played a quiz
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return int Play count
     */
    public function countUserPlays(int $quizId, int $userId): int
    {
        $result = $this->query(
            'SELECT COUNT(*) AS cnt FROM {db_prefix}quiz_result WHERE id_quiz = ? AND id_user = ?',
            ['int' => [$quizId, $userId]]
        );

        $row = $this->fetchRow($result);
        $this->freeResult($result);

        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Check whether a result already exists (also checks if quiz is enabled)
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return bool True if result exists OR quiz is disabled
     */
    public function resultOrDisabledExists(int $quizId, int $userId): bool
    {
        $result = $this->query(
            'SELECT id_quiz_result FROM {db_prefix}quiz_result QR '
            . 'RIGHT JOIN {db_prefix}quiz Q ON QR.id_quiz = Q.id_quiz '
            . 'WHERE (QR.id_quiz = ? AND QR.id_user = ?) OR (Q.id_quiz = ? AND Q.enabled = 0)',
            ['int' => [$quizId, $userId, $quizId]]
        );

        $count = 0;
        while ($this->fetchRow($result)) {
            $count++;
        }
        $this->freeResult($result);

        return $count > 0;
    }

    /**
     * Count total results for a quiz
     *
     * @param int $quizId Quiz ID
     * @return int Total count
     */
    public function countByQuiz(int $quizId): int
    {
        $result = $this->query(
            'SELECT COUNT(*) AS cnt FROM {db_prefix}quiz_result WHERE id_quiz = ?',
            ['int' => $quizId]
        );

        $row = $this->fetchRow($result);
        $this->freeResult($result);

        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Save a result
     *
     * @param Result $result Result model
     * @return int Result ID
     */
    public function save(Result $result): int
    {
        if ($result->id === 0) {
            return $this->create($result);
        }
        return $this->update($result);
    }

    /**
     * Create a new result
     *
     * @param Result $result Result model
     * @return int Inserted ID
     */
    private function create(Result $result): int
    {
        $this->query(
            'INSERT INTO {db_prefix}quiz_result '
            . '(id_quiz, id_user, result_date, questions, correct, incorrect, timeouts, total_seconds, total_resumes) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'int' => [
                    $result->quizId,
                    $result->userId,
                    $result->createdAt !== 0 ? $result->createdAt : (int)time(),
                    $result->totalQuestions,
                    $result->correctAnswers,
                    $result->incorrectAnswers,
                    $result->timeouts,
                    $result->totalSeconds,
                    $result->totalResumes,
                ],
            ]
        );

        return $this->getInsertId();
    }

    /**
     * Update an existing result
     *
     * @param Result $result Result model
     * @return int Result ID
     */
    private function update(Result $result): int
    {
        $this->query(
            'UPDATE {db_prefix}quiz_result SET '
            . 'questions = ?, correct = ?, incorrect = ?, timeouts = ?, total_seconds = ?, total_resumes = ? '
            . 'WHERE id_quiz_result = ?',
            [
                'int' => [
                    $result->totalQuestions,
                    $result->correctAnswers,
                    $result->incorrectAnswers,
                    $result->timeouts,
                    $result->totalSeconds,
                    $result->totalResumes,
                    $result->id,
                ],
            ]
        );

        return $result->id;
    }

    /**
     * Delete result by ID
     *
     * @param int $id Result ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        $this->query(
            'DELETE FROM {db_prefix}quiz_result WHERE id_quiz_result = ?',
            ['int' => $id]
        );

        return true;
    }

    /**
     * Delete all results for a quiz
     *
     * @param int $quizId Quiz ID
     * @return bool
     */
    public function deleteByQuiz(int $quizId): bool
    {
        $this->query(
            'DELETE FROM {db_prefix}quiz_result WHERE id_quiz = ?',
            ['int' => $quizId]
        );

        return true;
    }

    /**
     * Get aggregated statistics for a user on a quiz
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return array<string, int> Aggregated stats
     */
    public function getAggregateStats(int $quizId, int $userId): array
    {
        $result = $this->query(
            'SELECT IFNULL(SUM(questions),0) AS total_questions, '
            . 'IFNULL(SUM(correct),0) AS total_correct, '
            . 'IFNULL(SUM(incorrect),0) AS total_incorrect, '
            . 'IFNULL(SUM(timeouts),0) AS total_timeouts, '
            . 'IFNULL(SUM(total_seconds),0) AS total_seconds '
            . 'FROM {db_prefix}quiz_result WHERE id_user = ? AND id_quiz = ?',
            ['int' => [$userId, $quizId]]
        );

        $row = $this->fetchRow($result);
        $this->freeResult($result);

        return [
            'total_questions' => (int)($row['total_questions'] ?? 0),
            'total_correct'   => (int)($row['total_correct'] ?? 0),
            'total_incorrect' => (int)($row['total_incorrect'] ?? 0),
            'total_timeouts'  => (int)($row['total_timeouts'] ?? 0),
            'total_seconds'   => (int)($row['total_seconds'] ?? 0),
        ];
    }

    /**
     * Convert database row to Result model
     *
     * @param array<string, mixed> $row Database row
     * @return Result
     */
    private function rowToResult(array $row): Result
    {
        return new Result(
            id: (int)$row['id_quiz_result'],
            quizId: (int)$row['id_quiz'],
            userId: (int)$row['id_user'],
            score: (int)($row['correct'] ?? 0),
            totalQuestions: (int)($row['questions'] ?? 0),
            correctAnswers: (int)($row['correct'] ?? 0),
            incorrectAnswers: (int)($row['incorrect'] ?? 0),
            timeouts: (int)($row['timeouts'] ?? 0),
            totalSeconds: (int)($row['total_seconds'] ?? 0),
            totalResumes: (int)($row['total_resumes'] ?? 0),
            createdAt: (int)($row['result_date'] ?? 0),
        );
    }
}
