<?php

declare(strict_types=1);

namespace Quiz\Service;

use Quiz\Model\Answer;
use Quiz\Model\Question;
use Quiz\Repository\QuestionRepository;
use Quiz\Traits\HasErrorHandling;

/**
 * QuestionService
 *
 * Business logic for quiz question and answer management.
 *
 * @package Quiz\Service
 */
final class QuestionService
{
    use HasErrorHandling;

    public function __construct(
        private readonly QuestionRepository $questionRepository,
    ) {}

    /**
     * Get a question by ID (with answers)
     *
     * @param int $id Question ID
     * @return Question|null
     */
    public function getById(int $id): ?Question
    {
        try {
            return $this->questionRepository->findById($id);
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load question.');
            return null;
        }
    }

    /**
     * Get all questions for a quiz
     *
     * @param int $quizId Quiz ID
     * @return array<int, Question>
     */
    public function getByQuiz(int $quizId): array
    {
        try {
            return $this->questionRepository->findByQuiz($quizId);
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load questions.');
            return [];
        }
    }

    /**
     * Get a random next question during quiz play
     *
     * @param int $quizId Quiz ID
     * @param array<int> $answeredIds IDs already answered in this session
     * @return Question|null null when all questions exhausted
     */
    public function getNextQuestion(int $quizId, array $answeredIds = []): ?Question
    {
        try {
            return $this->questionRepository->findRandomForQuiz($quizId, $answeredIds);
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to retrieve next question.');
            return null;
        }
    }

    /**
     * Save a question (create or update) along with its answers
     *
     * @param array<string, mixed> $data Question data from form input
     * @param int $quizId Quiz ID
     * @return int|false Saved question ID on success, false on failure
     */
    public function save(array $data, int $quizId): int|false
    {
        try {
            $text = trim((string)($data['question_text'] ?? ''));
            if ($text === '') {
                $this->addError('question_text', 'Question text cannot be empty.');
                return false;
            }

            $question = new Question(
                id: (int)($data['id_question'] ?? 0),
                text: $text,
                typeId: (int)($data['id_question_type'] ?? Question::TYPE_MULTIPLE_CHOICE),
                quizId: $quizId,
                image: trim((string)($data['image'] ?? '')) ?: null,
                answerText: trim((string)($data['answer_text'] ?? '')) ?: null,
            );

            $questionId = $this->questionRepository->save($question);

            // Save answers if provided
            if (isset($data['answers']) && is_array($data['answers'])) {
                $this->saveAnswers($questionId, $data['answers']);
            }

            return $questionId;
        } catch (\Throwable $e) {
            $this->handleException($e, 'save', 'Unable to save question.');
            return false;
        }
    }

    /**
     * Save answers for a question
     *
     * @param int $questionId Question ID
     * @param array<int, array<string, mixed>> $answers Answer data arrays
     * @return void
     */
    private function saveAnswers(int $questionId, array $answers): void
    {
        foreach ($answers as $answerData) {
            $text = trim((string)($answerData['answer_text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $answer = new Answer(
                id: (int)($answerData['id_answer'] ?? 0),
                text: $text,
                isCorrect: (bool)($answerData['is_correct'] ?? false),
                questionId: $questionId,
            );

            $this->questionRepository->saveAnswer($answer, $questionId);
        }
    }

    /**
     * Delete a question and all its answers
     *
     * @param int $questionId Question ID
     * @return bool
     */
    public function delete(int $questionId): bool
    {
        try {
            $this->questionRepository->delete($questionId);
            return true;
        } catch (\Throwable $e) {
            $this->handleException($e, 'delete', 'Unable to delete question.');
            return false;
        }
    }

    /**
     * Delete an individual answer
     *
     * @param int $answerId Answer ID
     * @return bool
     */
    public function deleteAnswer(int $answerId): bool
    {
        try {
            $this->questionRepository->deleteAnswer($answerId);
            return true;
        } catch (\Throwable $e) {
            $this->handleException($e, 'delete', 'Unable to delete answer.');
            return false;
        }
    }

    /**
     * Count questions for a quiz
     *
     * @param int $quizId Quiz ID
     * @return int
     */
    public function countByQuiz(int $quizId): int
    {
        try {
            return $this->questionRepository->countByQuiz($quizId);
        } catch (\Throwable $e) {
            $this->handleException($e, 'count', 'Unable to count questions.');
            return 0;
        }
    }
}
