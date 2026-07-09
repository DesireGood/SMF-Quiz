<?php

declare(strict_types=1);

namespace Quiz\Model;

/**
 * Answer Model
 *
 * Represents a quiz answer choice.
 *
 * @package Quiz\Model
 */
final class Answer
{
    public function __construct(
        public readonly int $id,
        public string $text,
        public bool $isCorrect = false,
        public int $questionId = 0,
        public int $createdAt = 0,
    ) {}
}
