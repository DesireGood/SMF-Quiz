<?php

declare(strict_types=1);

namespace Quiz\Model;

/**
 * Quiz Model
 *
 * Represents a single quiz entity with typed properties and business logic.
 *
 * @package Quiz\Model
 */
final class Quiz
{
    /**
     * Quiz constructor
     *
     * @param int $id Quiz ID
     * @param string $title Quiz title
     * @param string|null $description Quiz description
     * @param int $playLimit Maximum number of times a user can play (0 = unlimited)
     * @param int $secondsPerQuestion Seconds allowed per question
     * @param bool $showAnswers Whether to show correct answers after quiz
     * @param bool $enabled Whether quiz is enabled
     * @param int $creatorId User ID of quiz creator
     * @param int $categoryId Category ID
     * @param string|null $image Quiz image filename
     * @param int $createdAt Unix timestamp
     * @param int $updatedAt Unix timestamp
     */
    public function __construct(
        public readonly int $id,
        public string $title,
        public ?string $description = null,
        public int $playLimit = 0,
        public int $secondsPerQuestion = 0,
        public bool $showAnswers = false,
        public bool $enabled = true,
        public int $creatorId = 0,
        public int $categoryId = 0,
        public ?string $image = null,
        public int $createdAt = 0,
        public int $updatedAt = 0,
        public ?int $questionCount = null,
    ) {}

    /**
     * Check if quiz is accessible to user
     *
     * @param int $userId User ID
     * @return bool True if user can access quiz
     */
    public function isAccessibleTo(int $userId): bool
    {
        return $this->creatorId === $userId || isAdmin();
    }

    /**
     * Check if quiz can be played
     *
     * @return bool True if quiz can be played
     */
    public function canBePlayed(): bool
    {
        return $this->enabled && (!empty($this->questionCount) || $this->questionCount > 0);
    }

    /**
     * Check if user has exceeded play limit
     *
     * @param int $userPlayCount Number of times user has played
     * @return bool True if limit exceeded
     */
    public function hasExceededPlayLimit(int $userPlayCount): bool
    {
        return $this->playLimit > 0 && $userPlayCount >= $this->playLimit;
    }
}
