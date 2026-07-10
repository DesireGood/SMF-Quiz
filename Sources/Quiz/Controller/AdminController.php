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
 * AdminController
 *
 * Handles all admin-panel actions for the Quiz modification.
 * Delegates data persistence to the appropriate service layer.
 *
 * @package Quiz\Controller
 */
final class AdminController
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
     * Admin dashboard — show summary counts
     *
     * @return array<string, mixed> Template context data
     */
    public function dashboard(): array
    {
        global $context, $txt;

        $data = $this->quizService->getPaginated(1, 1);
        $categories = $this->categoryService->getAll();

        return [
            'quiz_count'     => $data['total'],
            'category_count' => count($categories),
        ];
    }

    /**
     * List quizzes (paginated)
     *
     * @param int $page Page number (1-indexed)
     * @param string|null $search Optional search term
     * @return array<string, mixed> Template context data
     */
    public function listQuizzes(int $page = 1, ?string $search = null): array
    {
        return $this->quizService->getPaginated($page, 20, $search);
    }

    /**
     * Show the add/edit quiz form
     *
     * @param int $quizId 0 for new, positive for edit
     * @return array<string, mixed> Template context data
     */
    public function editQuiz(int $quizId = 0): array
    {
        $quiz = $quizId > 0 ? $this->quizService->getById($quizId) : null;
        $categories = $this->categoryService->getSelectList();

        return [
            'quiz'       => $quiz,
            'categories' => $categories,
        ];
    }

    /**
     * Save a quiz (create or update)
     *
     * @param array<string, mixed> $data POST/GET data
     * @param int $creatorId Current user ID
     * @return int|false Saved quiz ID on success, false on failure
     */
    public function saveQuiz(array $data, int $creatorId): int|false
    {
        $result = $this->quizService->save($data, $creatorId);
        if ($result === false) {
            foreach ($this->quizService->getErrors() as $key => $msg) {
                $this->addError($key, $msg);
            }
        }
        return $result;
    }

    /**
     * Delete a quiz
     *
     * @param int $quizId Quiz ID
     * @return bool
     */
    public function deleteQuiz(int $quizId): bool
    {
        return $this->quizService->delete($quizId);
    }

    /**
     * List categories
     *
     * @return array<int, \Quiz\Model\Category>
     */
    public function listCategories(): array
    {
        return $this->categoryService->getAll();
    }

    /**
     * Save a category
     *
     * @param array<string, mixed> $data Form data
     * @return int|false
     */
    public function saveCategory(array $data): int|false
    {
        $result = $this->categoryService->save($data);
        if ($result === false) {
            foreach ($this->categoryService->getErrors() as $key => $msg) {
                $this->addError($key, $msg);
            }
        }
        return $result;
    }

    /**
     * Delete a category
     *
     * @param int $categoryId Category ID
     * @return bool
     */
    public function deleteCategory(int $categoryId): bool
    {
        return $this->categoryService->delete($categoryId);
    }

    /**
     * List questions for a quiz
     *
     * @param int $quizId Quiz ID
     * @return array<int, \Quiz\Model\Question>
     */
    public function listQuestions(int $quizId): array
    {
        return $this->questionService->getByQuiz($quizId);
    }

    /**
     * Save a question
     *
     * @param array<string, mixed> $data Form data
     * @param int $quizId Quiz ID
     * @return int|false
     */
    public function saveQuestion(array $data, int $quizId): int|false
    {
        $result = $this->questionService->save($data, $quizId);
        if ($result === false) {
            foreach ($this->questionService->getErrors() as $key => $msg) {
                $this->addError($key, $msg);
            }
        }
        return $result;
    }

    /**
     * Delete a question
     *
     * @param int $questionId Question ID
     * @return bool
     */
    public function deleteQuestion(int $questionId): bool
    {
        return $this->questionService->delete($questionId);
    }

    /**
     * List paginated results for a quiz
     *
     * @param int $quizId Quiz ID
     * @param int $page Page number
     * @return array<string, mixed>
     */
    public function listResults(int $quizId, int $page = 1): array
    {
        return $this->resultService->getByQuiz($quizId, $page);
    }
}
