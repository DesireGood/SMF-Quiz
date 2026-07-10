<?php

declare(strict_types=1);

namespace Quiz\Service;

use Quiz\Model\Result;
use Quiz\Repository\ResultRepository;
use Quiz\Repository\QuizRepository;
use Quiz\Traits\HasErrorHandling;

/**
 * ResultService
 *
 * Business logic for quiz results, scoring, and leaderboard management.
 *
 * @package Quiz\Service
 */
final class ResultService
{
    use HasErrorHandling;

    public function __construct(
        private readonly ResultRepository $resultRepository,
        private readonly QuizRepository $quizRepository,
    ) {}

    /**
     * Get paginated results for a quiz
     *
     * @param int $quizId Quiz ID
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array{results: array<int,Result>, total: int, pages: int}
     */
    public function getByQuiz(int $quizId, int $page = 1, int $perPage = 20): array
    {
        try {
            $total = $this->resultRepository->countByQuiz($quizId);
            $results = $this->resultRepository->findByQuiz($quizId, $page, $perPage);

            return [
                'results' => $results,
                'total'   => $total,
                'pages'   => (int)ceil($total / $perPage),
            ];
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load results.');
            return ['results' => [], 'total' => 0, 'pages' => 0];
        }
    }

    /**
     * Get paginated results for a user
     *
     * @param int $userId User ID
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array{results: array<int,Result>, total: int}
     */
    public function getByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        try {
            $results = $this->resultRepository->findByUser($userId, $page, $perPage);
            return ['results' => $results, 'total' => count($results)];
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load user results.');
            return ['results' => [], 'total' => 0];
        }
    }

    /**
     * Record a completed quiz attempt
     *
     * Skips recording when a result already exists or the quiz is disabled.
     * Also updates the quiz-level top score if applicable.
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @param int $creatorId Quiz creator user ID
     * @param int $questions Total questions answered
     * @param int $correct Correct answers
     * @param int $incorrect Incorrect answers
     * @param int $timeouts Timed-out answers
     * @param int $totalSeconds Total time taken
     * @param int $totalResumes Times the session was resumed
     * @return int|false Inserted result ID, or false when skipped/failed
     */
    public function recordResult(
        int $quizId,
        int $userId,
        int $creatorId,
        int $questions,
        int $correct,
        int $incorrect,
        int $timeouts,
        int $totalSeconds,
        int $totalResumes = 0,
    ): int|false {
        // Don't record when the player is the creator
        if ($creatorId === $userId) {
            return false;
        }

        try {
            if ($this->resultRepository->resultOrDisabledExists($quizId, $userId)) {
                return false;
            }

            $result = new Result(
                id: 0,
                quizId: $quizId,
                userId: $userId,
                score: $correct,
                totalQuestions: $questions,
                correctAnswers: $correct,
                incorrectAnswers: $incorrect,
                timeouts: $timeouts,
                totalSeconds: $totalSeconds,
                totalResumes: $totalResumes,
                createdAt: (int)time(),
            );

            $resultId = $this->resultRepository->save($result);

            // Fire integration hook for third-party extensions
            call_integration_hook(
                'integrate_quiz_result',
                [$quizId, $userId, $questions, $correct, $incorrect, $timeouts, $totalSeconds, $totalResumes]
            );

            return $resultId;
        } catch (\Throwable $e) {
            $this->handleException($e, 'save', 'Unable to record quiz result.');
            return false;
        }
    }

    /**
     * Check whether a user has exceeded the play limit
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @param int $playLimit Quiz play limit (0 = unlimited)
     * @return bool True when limit is exceeded
     */
    public function hasExceededPlayLimit(int $quizId, int $userId, int $playLimit): bool
    {
        if ($playLimit === 0) {
            return false;
        }

        try {
            $plays = $this->resultRepository->countUserPlays($quizId, $userId);
            return $plays >= $playLimit;
        } catch (\Throwable $e) {
            $this->handleException($e);
            return false;
        }
    }

    /**
     * Get aggregate statistics for a user on a specific quiz
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return array<string, int>
     */
    public function getAggregateStats(int $quizId, int $userId): array
    {
        try {
            return $this->resultRepository->getAggregateStats($quizId, $userId);
        } catch (\Throwable $e) {
            $this->handleException($e, 'stats', 'Unable to load statistics.');
            return [
                'total_questions' => 0,
                'total_correct'   => 0,
                'total_incorrect' => 0,
                'total_timeouts'  => 0,
                'total_seconds'   => 0,
            ];
        }
    }

    /**
     * Delete all results for a quiz
     *
     * @param int $quizId Quiz ID
     * @return bool
     */
    public function deleteByQuiz(int $quizId): bool
    {
        try {
            $this->resultRepository->deleteByQuiz($quizId);
            return true;
        } catch (\Throwable $e) {
            $this->handleException($e, 'delete', 'Unable to delete results.');
            return false;
        }
    }

    /**
     * Resolve a percentage score to an SMF mod-setting message key
     *
     * @param float $percentage Score percentage (0–100)
     * @return string ModSettings key (e.g. 'SMFQuiz_0to19')
     */
    public function getScoreMessageKey(float $percentage): string
    {
        return match (true) {
            $percentage >= 100  => 'SMFQuiz_99to100',
            $percentage >= 80   => 'SMFQuiz_80to99',
            $percentage >= 60   => 'SMFQuiz_60to79',
            $percentage >= 40   => 'SMFQuiz_40to59',
            $percentage >= 20   => 'SMFQuiz_20to39',
            default             => 'SMFQuiz_0to19',
        };
    }
}
