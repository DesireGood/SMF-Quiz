<?php

declare(strict_types=1);

namespace Quiz\Model;

/**
 * Question Model
 *
 * Represents a quiz question.
 *
 * @package Quiz\Model
 */
final class Question
{
    public const TYPE_MULTIPLE_CHOICE = 1;
    public const TYPE_FREE_TEXT = 2;
    public const TYPE_TRUE_FALSE = 3;

    /**
     * @var array<int, string> Question type labels
     */
    private static array $typeLabels = [
        self::TYPE_MULTIPLE_CHOICE => 'Multiple Choice',
        self::TYPE_FREE_TEXT => 'Free Text',
        self::TYPE_TRUE_FALSE => 'True/False',
    ];

    /**
     * @var array<Answer> Question answers
     */
    private array $answers = [];

    public function __construct(
        public readonly int $id,
        public string $text,
        public int $typeId = self::TYPE_MULTIPLE_CHOICE,
        public int $quizId = 0,
        public ?string $image = null,
        public ?string $answerText = null,
        public int $createdAt = 0,
        public int $updatedAt = 0,
    ) {}

    /**
     * Get question type label
     *
     * @return string Question type label
     */
    public function getTypeLabel(): string
    {
        return self::$typeLabels[$this->typeId] ?? 'Unknown';
    }

    /**
     * Add answer to question
     *
     * @param Answer $answer Answer object
     * @return void
     */
    public function addAnswer(Answer $answer): void
    {
        $this->answers[$answer->id] = $answer;
    }

    /**
     * Get all answers
     *
     * @return array<int, Answer>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /**
     * Get correct answers
     *
     * @return array<int, Answer>
     */
    public function getCorrectAnswers(): array
    {
        return array_filter(
            $this->answers,
            static fn(Answer $a) => $a->isCorrect
        );
    }
}
