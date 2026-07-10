<?php

declare(strict_types=1);

namespace Quiz\Service;

use Quiz\Model\Quiz;
use Quiz\Repository\QuizRepository;
use Quiz\Repository\QuestionRepository;
use Quiz\Repository\ResultRepository;
use Quiz\Traits\HasErrorHandling;

/**
 * QuizService
 *
 * Business logic for quiz CRUD operations, access control,
 * and play-count / top-score management.
 *
 * @package Quiz\Service
 */
final class QuizService
{
    use HasErrorHandling;

    public function __construct(
        private readonly QuizRepository $quizRepository,
        private readonly QuestionRepository $questionRepository,
        private readonly ResultRepository $resultRepository,
    ) {}

    /**
     * Get a quiz by ID
     *
     * @param int $id Quiz ID
     * @return Quiz|null
     */
    public function getById(int $id): ?Quiz
    {
        try {
            $quiz = $this->quizRepository->findById($id);
            if ($quiz !== null) {
                $quiz->questionCount = $this->questionRepository->countByQuiz($id);
            }
            return $quiz;
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load quiz.');
            return null;
        }
    }

    /**
     * Get all quizzes (paginated)
     *
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @param string|null $search Search term
     * @return array{quizzes: array<int,Quiz>, total: int, pages: int}
     */
    public function getPaginated(int $page = 1, int $perPage = 20, ?string $search = null): array
    {
        try {
            $total = $this->quizRepository->count($search);
            $quizzes = $this->quizRepository->findAll($page, $perPage, $search);

            return [
                'quizzes' => $quizzes,
                'total'   => $total,
                'pages'   => (int)ceil($total / $perPage),
            ];
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load quizzes.');
            return ['quizzes' => [], 'total' => 0, 'pages' => 0];
        }
    }

    /**
     * Get quizzes for a category
     *
     * @param int $categoryId Category ID
     * @return array<int, Quiz>
     */
    public function getByCategory(int $categoryId): array
    {
        try {
            return $this->quizRepository->findByCategory($categoryId);
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load quizzes for category.');
            return [];
        }
    }

    /**
     * Get quizzes created by a user
     *
     * @param int $userId User ID
     * @return array<int, Quiz>
     */
    public function getByCreator(int $userId): array
    {
        try {
            return $this->quizRepository->findByCreator($userId);
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load user quizzes.');
            return [];
        }
    }

    /**
     * Create or update a quiz
     *
     * @param array<string, mixed> $data Quiz data from form input
     * @param int $creatorId User ID of the creator/editor
     * @return int|false Saved quiz ID on success, false on failure
     */
    public function save(array $data, int $creatorId): int|false
    {
        try {
            $quizId = (int)($data['id_quiz'] ?? 0);

            $quiz = new Quiz(
                id: $quizId,
                title: trim((string)($data['title'] ?? '')),
                description: trim((string)($data['description'] ?? '')) ?: null,
                playLimit: max(0, (int)($data['play_limit'] ?? 0)),
                secondsPerQuestion: max(0, (int)($data['seconds_per_question'] ?? 0)),
                showAnswers: (bool)($data['show_answers'] ?? false),
                enabled: (bool)($data['enabled'] ?? true),
                creatorId: $creatorId,
                categoryId: (int)($data['id_category'] ?? 0),
                image: trim((string)($data['image'] ?? '')) ?: null,
            );

            if ($quiz->title === '') {
                $this->addError('title', 'Quiz title cannot be empty.');
                return false;
            }

            return $this->quizRepository->save($quiz);
        } catch (\Throwable $e) {
            $this->handleException($e, 'save', 'Unable to save quiz.');
            return false;
        }
    }

    /**
     * Delete a quiz and all its associated data
     *
     * @param int $quizId Quiz ID
     * @return bool
     */
    public function delete(int $quizId): bool
    {
        try {
            $this->questionRepository->deleteByQuiz($quizId);
            $this->resultRepository->deleteByQuiz($quizId);
            $this->quizRepository->delete($quizId);
            return true;
        } catch (\Throwable $e) {
            $this->handleException($e, 'delete', 'Unable to delete quiz.');
            return false;
        }
    }

    /**
     * Check whether a user can play a quiz
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return bool True when the quiz is playable and limits not exceeded
     */
    public function canUserPlay(int $quizId, int $userId): bool
    {
        $quiz = $this->getById($quizId);
        if ($quiz === null || !$quiz->enabled) {
            return false;
        }

        if ($quiz->playLimit > 0) {
            $playCount = $this->resultRepository->countUserPlays($quizId, $userId);
            if ($quiz->hasExceededPlayLimit($playCount)) {
                return false;
            }
        }

        return true;
    }
}
