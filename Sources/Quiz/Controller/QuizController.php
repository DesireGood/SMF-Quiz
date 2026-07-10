<?php

declare(strict_types=1);

namespace Quiz\Controller;

use Quiz\Service\QuizService;
use Quiz\Service\CategoryService;
use Quiz\Service\QuestionService;
use Quiz\Service\ResultService;
use Quiz\Service\ValidationService;
use Quiz\Traits\HasErrorHandling;

/**
 * QuizController
 *
 * Handles all public-facing quiz actions (list, play, results).
 * Delegates data access and business logic to the service layer.
 *
 * @package Quiz\Controller
 */
final class QuizController
{
    use HasErrorHandling;

    public function __construct(
        private readonly QuizService $quizService,
        private readonly CategoryService $categoryService,
        private readonly QuestionService $questionService,
        private readonly ResultService $resultService,
        private readonly ValidationService $validationService,
    ) {}

    /**
     * Public home page — list all enabled quizzes
     *
     * @param int $page Current page
     * @param string|null $search Optional search term
     * @return array<string, mixed> Context data for the template
     */
    public function home(int $page = 1, ?string $search = null): array
    {
        $data = $this->quizService->getPaginated($page, 20, $search);
        $categories = $this->categoryService->getAll();

        return array_merge($data, ['categories' => $categories]);
    }

    /**
     * Category listing page
     *
     * @param int $categoryId 0 for all root categories
     * @return array<string, mixed>
     */
    public function categories(int $categoryId = 0): array
    {
        if ($categoryId > 0) {
            $category = $this->categoryService->getById($categoryId);
            $quizzes  = $this->quizService->getByCategory($categoryId);
            return ['category' => $category, 'quizzes' => $quizzes];
        }

        return ['categories' => $this->categoryService->getAll()];
    }

    /**
     * Quiz detail / scores page
     *
     * @param int $quizId Quiz ID
     * @param int $page Result page
     * @return array<string, mixed>
     */
    public function quizDetail(int $quizId, int $page = 1): array
    {
        $quiz    = $this->quizService->getById($quizId);
        $results = $this->resultService->getByQuiz($quizId, $page);

        return ['quiz' => $quiz, 'results' => $results];
    }

    /**
     * User-specific quiz listing
     *
     * @param int $userId User ID
     * @return array<string, mixed>
     */
    public function userQuizzes(int $userId): array
    {
        $quizzes = $this->quizService->getByCreator($userId);
        return ['quizzes' => $quizzes];
    }

    /**
     * User result history
     *
     * @param int $userId User ID
     * @param int $page Page number
     * @return array<string, mixed>
     */
    public function userResults(int $userId, int $page = 1): array
    {
        return $this->resultService->getByUser($userId, $page);
    }

    /**
     * Check whether a user can play a quiz, and return pre-play context
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return array<string, mixed> ['canPlay' => bool, 'quiz' => Quiz|null, 'stats' => array]
     */
    public function getPlayContext(int $quizId, int $userId): array
    {
        $quiz    = $this->quizService->getById($quizId);
        $canPlay = $this->quizService->canUserPlay($quizId, $userId);
        $stats   = $canPlay ? $this->resultService->getAggregateStats($quizId, $userId) : [];

        return [
            'canPlay' => $canPlay,
            'quiz'    => $quiz,
            'stats'   => $stats,
        ];
    }

    /**
     * Add a new quiz via the public interface (user-submitted)
     *
     * @param array<string, mixed> $data Form data
     * @param int $userId Current user ID
     * @return int|false Saved quiz ID on success, false on failure
     */
    public function addQuiz(array $data, int $userId): int|false
    {
        $result = $this->quizService->save($data, $userId);
        if ($result === false) {
            foreach ($this->quizService->getErrors() as $key => $msg) {
                $this->addError($key, $msg);
            }
        }
        return $result;
    }

    /**
     * Get statistics for the statistics page
     *
     * @param int $userId User ID (0 = global stats)
     * @return array<string, mixed>
     */
    public function statistics(int $userId = 0): array
    {
        if ($userId > 0) {
            return $this->resultService->getByUser($userId);
        }

        return [];
    }
}
