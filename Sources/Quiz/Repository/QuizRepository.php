<?php

declare(strict_types=1);

namespace Quiz\Repository;

use Quiz\Model\Quiz;

/**
 * Quiz Repository
 *
 * Handles all database operations for quizzes.
 *
 * @package Quiz\Repository
 */
final class QuizRepository extends BaseRepository
{
    /**
     * Find quiz by ID
     *
     * @param int $id Quiz ID
     * @return Quiz|null Quiz object or null if not found
     */
    public function findById(int $id): ?Quiz
    {
        $result = $this->query(
            'SELECT id_quiz, title, description, play_limit, seconds_per_question, '
            . 'show_answers, enabled, creator_id, id_category, image, created, updated '
            . 'FROM {db_prefix}quiz WHERE id_quiz = ?',
            ['int' => $id]
        );

        if (!$row = $this->fetchRow($result)) {
            return null;
        }

        $this->freeResult($result);

        return $this->rowToQuiz($row);
    }

    /**
     * Find all quizzes (paginated)
     *
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @param string|null $search Search term
     * @return array<int, Quiz> List of quizzes
     */
    public function findAll(int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        $where = '';
        $params = ['int' => []];

        if ($search !== null && $search !== '') {
            $where = 'WHERE title LIKE ?';
            $params['string'] = ['%' . $search . '%'];
        }

        $offset = ($page - 1) * $perPage;

        $result = $this->query(
            'SELECT id_quiz, title, description, play_limit, seconds_per_question, '
            . 'show_answers, enabled, creator_id, id_category, image, created, updated '
            . "FROM {db_prefix}quiz $where "
            . 'ORDER BY updated DESC LIMIT ?, ?',
            array_merge($params, ['int' => [$offset, $perPage]])
        );

        $quizzes = [];
        foreach ($this->fetchAll($result) as $row) {
            $quizzes[] = $this->rowToQuiz($row);
        }
        $this->freeResult($result);

        return $quizzes;
    }

    /**
     * Count total quizzes
     *
     * @param string|null $search Search term
     * @return int Total count
     */
    public function count(?string $search = null): int
    {
        $where = '';
        $params = [];

        if ($search !== null && $search !== '') {
            $where = 'WHERE title LIKE ?';
            $params['string'] = ['%' . $search . '%'];
        }

        $result = $this->query(
            "SELECT COUNT(*) as cnt FROM {db_prefix}quiz $where",
            $params
        );

        $row = $this->fetchRow($result);
        $this->freeResult($result);

        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Find quizzes by category
     *
     * @param int $categoryId Category ID
     * @return array<int, Quiz>
     */
    public function findByCategory(int $categoryId): array
    {
        $result = $this->query(
            'SELECT id_quiz, title, description, play_limit, seconds_per_question, '
            . 'show_answers, enabled, creator_id, id_category, image, created, updated '
            . 'FROM {db_prefix}quiz WHERE id_category = ? AND enabled = 1 '
            . 'ORDER BY title ASC',
            ['int' => $categoryId]
        );

        $quizzes = [];
        foreach ($this->fetchAll($result) as $row) {
            $quizzes[] = $this->rowToQuiz($row);
        }
        $this->freeResult($result);

        return $quizzes;
    }

    /**
     * Find quizzes by creator
     *
     * @param int $creatorId Creator user ID
     * @return array<int, Quiz>
     */
    public function findByCreator(int $creatorId): array
    {
        $result = $this->query(
            'SELECT id_quiz, title, description, play_limit, seconds_per_question, '
            . 'show_answers, enabled, creator_id, id_category, image, created, updated '
            . 'FROM {db_prefix}quiz WHERE creator_id = ? '
            . 'ORDER BY updated DESC',
            ['int' => $creatorId]
        );

        $quizzes = [];
        foreach ($this->fetchAll($result) as $row) {
            $quizzes[] = $this->rowToQuiz($row);
        }
        $this->freeResult($result);

        return $quizzes;
    }

    /**
     * Save quiz (create or update)
     *
     * @param Quiz $quiz Quiz object
     * @return int Quiz ID (inserted or updated)
     */
    public function save(Quiz $quiz): int
    {
        if ($quiz->id === 0) {
            return $this->create($quiz);
        }
        return $this->update($quiz);
    }

    /**
     * Create new quiz
     *
     * @param Quiz $quiz Quiz object
     * @return int Inserted ID
     */
    private function create(Quiz $quiz): int
    {
        $this->query(
            'INSERT INTO {db_prefix}quiz '
            . '(title, description, play_limit, seconds_per_question, show_answers, '
            . 'enabled, creator_id, id_category, image, created, updated) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'string' => [$quiz->title, $quiz->description ?? ''],
                'int' => [
                    $quiz->playLimit,
                    $quiz->secondsPerQuestion,
                    (int)$quiz->showAnswers,
                    (int)$quiz->enabled,
                    $quiz->creatorId,
                    $quiz->categoryId,
                    (int)time(),
                    (int)time(),
                ],
            ]
        );

        return $this->getInsertId();
    }

    /**
     * Update existing quiz
     *
     * @param Quiz $quiz Quiz object
     * @return int Quiz ID
     */
    private function update(Quiz $quiz): int
    {
        $this->query(
            'UPDATE {db_prefix}quiz SET '
            . 'title = ?, description = ?, play_limit = ?, seconds_per_question = ?, '
            . 'show_answers = ?, enabled = ?, id_category = ?, image = ?, updated = ? '
            . 'WHERE id_quiz = ?',
            [
                'string' => [$quiz->title, $quiz->description ?? ''],
                'int' => [
                    $quiz->playLimit,
                    $quiz->secondsPerQuestion,
                    (int)$quiz->showAnswers,
                    (int)$quiz->enabled,
                    $quiz->categoryId,
                    (int)time(),
                    $quiz->id,
                ],
            ]
        );

        return $quiz->id;
    }

    /**
     * Delete quiz
     *
     * @param int $quizId Quiz ID
     * @return bool True if deleted
     */
    public function delete(int $quizId): bool
    {
        $this->query(
            'DELETE FROM {db_prefix}quiz WHERE id_quiz = ?',
            ['int' => $quizId]
        );

        return true;
    }

    /**
     * Convert database row to Quiz model
     *
     * @param array<string, mixed> $row Database row
     * @return Quiz
     */
    private function rowToQuiz(array $row): Quiz
    {
        return new Quiz(
            id: (int)$row['id_quiz'],
            title: $row['title'],
            description: $row['description'] ?: null,
            playLimit: (int)$row['play_limit'],
            secondsPerQuestion: (int)$row['seconds_per_question'],
            showAnswers: (bool)$row['show_answers'],
            enabled: (bool)$row['enabled'],
            creatorId: (int)$row['creator_id'],
            categoryId: (int)$row['id_category'],
            image: $row['image'] ?: null,
            createdAt: (int)$row['created'],
            updatedAt: (int)$row['updated'],
        );
    }
}
