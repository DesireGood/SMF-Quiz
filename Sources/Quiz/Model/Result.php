<?php

declare(strict_types=1);

namespace Quiz\Model;

/**
 * Result Model
 *
 * Represents a quiz result/attempt.
 *
 * @package Quiz\Model
 */
final class Result
{
    public function __construct(
        public readonly int $id,
        public int $quizId,
        public int $userId,
        public int $score,
        public int $totalQuestions,
        public int $correctAnswers,
        public int $incorrectAnswers,
        public int $timeouts,
        public int $totalSeconds,
        public int $totalResumes = 0,
        public int $createdAt = 0,
    ) {}

    /**
     * Get percentage score
     *
     * @return float Percentage (0-100)
     */
    public function getPercentage(): float
    {
        if ($this->totalQuestions === 0) {
            return 0.0;
        }

        return ($this->correctAnswers / $this->totalQuestions) * 100;
    }

    /**
     * Get average time per question
     *
     * @return int Seconds per question
     */
    public function getAverageTimePerQuestion(): int
    {
        if ($this->totalQuestions === 0) {
            return 0;
        }

        return (int)($this->totalSeconds / $this->totalQuestions);
    }
}
