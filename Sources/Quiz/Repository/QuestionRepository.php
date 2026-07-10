<?php

declare(strict_types=1);

namespace Quiz\Repository;

use Quiz\Model\Question;
use Quiz\Model\Answer;

/**
 * Question Repository
 *
 * Handles all database operations for quiz questions and their answers.
 *
 * @package Quiz\Repository
 */
final class QuestionRepository extends BaseRepository
{
    /**
     * Find question by ID (with answers loaded)
     *
     * @param int $id Question ID
     * @return Question|null Question object or null if not found
     */
    public function findById(int $id): ?Question
    {
        $result = $this->query(
            'SELECT id_question, question_text, id_question_type, id_quiz, image, answer_text, created, updated '
            . 'FROM {db_prefix}quiz_question WHERE id_question = ?',
            ['int' => $id]
        );

        if (!$row = $this->fetchRow($result)) {
            return null;
        }
        $this->freeResult($result);

        $question = $this->rowToQuestion($row);
        $this->loadAnswers($question);

        return $question;
    }

    /**
     * Find all questions for a quiz (with answers loaded)
     *
     * @param int $quizId Quiz ID
     * @return array<int, Question>
     */
    public function findByQuiz(int $quizId): array
    {
        $result = $this->query(
            'SELECT id_question, question_text, id_question_type, id_quiz, image, answer_text, created, updated '
            . 'FROM {db_prefix}quiz_question WHERE id_quiz = ? ORDER BY id_question ASC',
            ['int' => $quizId]
        );

        $questions = [];
        foreach ($this->fetchAll($result) as $row) {
            $question = $this->rowToQuestion($row);
            $this->loadAnswers($question);
            $questions[$question->id] = $question;
        }
        $this->freeResult($result);

        return $questions;
    }

    /**
     * Find a single random question for a quiz (used during play)
     *
     * @param int $quizId Quiz ID
     * @param array<int> $excludeIds Question IDs already answered
     * @return Question|null
     */
    public function findRandomForQuiz(int $quizId, array $excludeIds = []): ?Question
    {
        $exclude = '';
        $params = ['int' => $quizId];

        if (!empty($excludeIds)) {
            $placeholders = implode(', ', array_fill(0, count($excludeIds), '?'));
            $exclude = "AND id_question NOT IN ($placeholders)";
            $params = ['int' => array_merge([$quizId], $excludeIds)];
        }

        $result = $this->query(
            "SELECT id_question, question_text, id_question_type, id_quiz, image, answer_text, created, updated "
            . "FROM {db_prefix}quiz_question WHERE id_quiz = ? $exclude ORDER BY RAND() LIMIT 1",
            $params
        );

        if (!$row = $this->fetchRow($result)) {
            return null;
        }
        $this->freeResult($result);

        $question = $this->rowToQuestion($row);
        $this->loadAnswers($question);

        return $question;
    }

    /**
     * Count questions for a quiz
     *
     * @param int $quizId Quiz ID
     * @return int
     */
    public function countByQuiz(int $quizId): int
    {
        $result = $this->query(
            'SELECT COUNT(*) AS cnt FROM {db_prefix}quiz_question WHERE id_quiz = ?',
            ['int' => $quizId]
        );

        $row = $this->fetchRow($result);
        $this->freeResult($result);

        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Save question (create or update)
     *
     * @param Question $question Question object
     * @return int Question ID
     */
    public function save(Question $question): int
    {
        if ($question->id === 0) {
            return $this->create($question);
        }
        return $this->update($question);
    }

    /**
     * Create new question
     *
     * @param Question $question Question object
     * @return int Inserted ID
     */
    private function create(Question $question): int
    {
        $this->query(
            'INSERT INTO {db_prefix}quiz_question '
            . '(question_text, id_question_type, id_quiz, image, answer_text, created, updated) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                'string' => [$question->text, $question->image ?? '', $question->answerText ?? ''],
                'int' => [
                    $question->typeId,
                    $question->quizId,
                    (int)time(),
                    (int)time(),
                ],
            ]
        );

        return $this->getInsertId();
    }

    /**
     * Update existing question
     *
     * @param Question $question Question object
     * @return int Question ID
     */
    private function update(Question $question): int
    {
        $this->query(
            'UPDATE {db_prefix}quiz_question SET '
            . 'question_text = ?, id_question_type = ?, image = ?, answer_text = ?, updated = ? '
            . 'WHERE id_question = ?',
            [
                'string' => [$question->text, $question->image ?? '', $question->answerText ?? ''],
                'int' => [$question->typeId, (int)time(), $question->id],
            ]
        );

        return $question->id;
    }

    /**
     * Delete question and its answers
     *
     * @param int $questionId Question ID
     * @return bool
     */
    public function delete(int $questionId): bool
    {
        $this->query(
            'DELETE FROM {db_prefix}quiz_answer WHERE id_question = ?',
            ['int' => $questionId]
        );
        $this->query(
            'DELETE FROM {db_prefix}quiz_question WHERE id_question = ?',
            ['int' => $questionId]
        );

        return true;
    }

    /**
     * Delete all questions for a quiz
     *
     * @param int $quizId Quiz ID
     * @return bool
     */
    public function deleteByQuiz(int $quizId): bool
    {
        // Delete answers for all questions in this quiz
        $this->query(
            'DELETE QA FROM {db_prefix}quiz_answer QA '
            . 'INNER JOIN {db_prefix}quiz_question QQ ON QA.id_question = QQ.id_question '
            . 'WHERE QQ.id_quiz = ?',
            ['int' => $quizId]
        );
        $this->query(
            'DELETE FROM {db_prefix}quiz_question WHERE id_quiz = ?',
            ['int' => $quizId]
        );

        return true;
    }

    /**
     * Save answer for a question
     *
     * @param Answer $answer Answer object
     * @param int $questionId Question ID (used when answer->id is 0)
     * @return int Answer ID
     */
    public function saveAnswer(Answer $answer, int $questionId = 0): int
    {
        $qId = $answer->questionId !== 0 ? $answer->questionId : $questionId;

        if ($answer->id === 0) {
            return $this->createAnswer($answer, $qId);
        }
        return $this->updateAnswer($answer);
    }

    /**
     * Create a new answer
     *
     * @param Answer $answer Answer object
     * @param int $questionId Question ID
     * @return int Inserted ID
     */
    private function createAnswer(Answer $answer, int $questionId): int
    {
        $this->query(
            'INSERT INTO {db_prefix}quiz_answer (answer_text, is_correct, id_question, created) VALUES (?, ?, ?, ?)',
            [
                'string' => [$answer->text],
                'int' => [(int)$answer->isCorrect, $questionId, (int)time()],
            ]
        );

        return $this->getInsertId();
    }

    /**
     * Update an existing answer
     *
     * @param Answer $answer Answer object
     * @return int Answer ID
     */
    private function updateAnswer(Answer $answer): int
    {
        $this->query(
            'UPDATE {db_prefix}quiz_answer SET answer_text = ?, is_correct = ? WHERE id_answer = ?',
            [
                'string' => [$answer->text],
                'int' => [(int)$answer->isCorrect, $answer->id],
            ]
        );

        return $answer->id;
    }

    /**
     * Delete an answer
     *
     * @param int $answerId Answer ID
     * @return bool
     */
    public function deleteAnswer(int $answerId): bool
    {
        $this->query(
            'DELETE FROM {db_prefix}quiz_answer WHERE id_answer = ?',
            ['int' => $answerId]
        );

        return true;
    }

    /**
     * Load answers into a question object
     *
     * @param Question $question Question object (mutated in place)
     * @return void
     */
    private function loadAnswers(Question $question): void
    {
        $result = $this->query(
            'SELECT id_answer, answer_text, is_correct, id_question, created '
            . 'FROM {db_prefix}quiz_answer WHERE id_question = ? ORDER BY id_answer ASC',
            ['int' => $question->id]
        );

        foreach ($this->fetchAll($result) as $row) {
            $question->addAnswer(new Answer(
                id: (int)$row['id_answer'],
                text: $row['answer_text'],
                isCorrect: (bool)$row['is_correct'],
                questionId: (int)$row['id_question'],
                createdAt: (int)$row['created'],
            ));
        }
        $this->freeResult($result);
    }

    /**
     * Convert database row to Question model
     *
     * @param array<string, mixed> $row Database row
     * @return Question
     */
    private function rowToQuestion(array $row): Question
    {
        return new Question(
            id: (int)$row['id_question'],
            text: $row['question_text'],
            typeId: (int)$row['id_question_type'],
            quizId: (int)$row['id_quiz'],
            image: $row['image'] ?: null,
            answerText: $row['answer_text'] ?: null,
            createdAt: (int)$row['created'],
            updatedAt: (int)$row['updated'],
        );
    }
}
