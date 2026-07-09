# SMF Quiz 2.1.0 - Architecture Guide

## Design Philosophy

The modernized SMF Quiz follows these principles:

1. **Single Responsibility Principle** - Each class does one thing well
2. **Dependency Injection** - Dependencies passed in, not created internally
3. **Type Safety** - Strict types everywhere, no implicit coercion
4. **Security First** - All inputs validated, all queries parameterized
5. **Testability** - Easy to unit test each component
6. **Performance** - Optimized queries, minimal memory footprint

## Directory Structure

```
Sources/Quiz/
├── Integration.php              # SMF Hook Entry Point
│                                # Registers all hooks and autoloader
│
├── Controller/                  # Request Handlers
│   ├── AdminController.php      # Handles admin panel requests
│   ├── QuizController.php       # Handles user quiz requests
│   └── AjaxController.php       # Handles AJAX requests
│
├── Service/                     # Business Logic
│   ├── QuizService.php          # Quiz operations (CRUD, scoring)
│   ├── CategoryService.php      # Category operations
│   ├── QuestionService.php      # Question operations
│   ├── ResultService.php        # Result tracking
│   └── ValidationService.php    # Input validation
│
├── Repository/                  # Data Access Layer
│   ├── BaseRepository.php       # Common repository logic
│   ├── QuizRepository.php       # Quiz data queries
│   ├── CategoryRepository.php   # Category data queries
│   ├── QuestionRepository.php   # Question data queries
│   └── ResultRepository.php     # Result data queries
│
├── Model/                       # Data Entities
│   ├── Quiz.php                 # Quiz entity with methods
│   ├── Category.php             # Category entity
│   ├── Question.php             # Question entity
│   ├── Answer.php               # Answer entity
│   └── Result.php               # Result entity
│
└── Traits/                      # Reusable Code
    ├── HasValidation.php        # Validation helper methods
    └── HasErrorHandling.php     # Error handling helper methods
```

## Data Flow

### User Playing a Quiz

```
User Browser
    ↓
SMF Routes to QuizController::playQuiz()
    ↓
QuizController validates input & calls QuizService::getQuiz()
    ↓
QuizService calls QuizRepository::findById()
    ↓
QuizRepository queries database, returns Quiz model
    ↓
QuizService enriches Quiz with Questions via QuestionRepository
    ↓
QuizController renders template with Quiz data
    ↓
User sees quiz
```

### User Submitting Answers

```
User submits form
    ↓
SMF routes to QuizController::submitAnswers()
    ↓
QuizController uses ValidationService to validate answers
    ↓
QuizController calls ResultService::scoreQuiz()
    ↓
ResultService uses QuestionRepository to get correct answers
    ↓
ResultService calculates score
    ↓
ResultService calls ResultRepository::save()
    ↓
ResultRepository stores result in database
    ↓
ResultService returns Result object
    ↓
QuizController renders results template
    ↓
User sees their score
```

## Layer Responsibilities

### Controllers (Request Handling)

**File:** `Controller/QuizController.php`

**Responsibilities:**
- Receive HTTP request
- Validate request parameters
- Call appropriate service
- Render response (template or JSON)

**Example:**
```php
public function playQuiz(): void
{
    $quizId = (int)($_GET['id'] ?? 0);
    
    // Validation
    if ($quizId <= 0) {
        throw new InvalidArgumentException('Invalid quiz ID');
    }
    
    // Fetch data
    $quiz = $this->quizService->getQuiz($quizId);
    
    // Check permissions
    if (!$this->auth->canViewQuiz($quiz)) {
        throw new PermissionDeniedException();
    }
    
    // Render
    return $this->renderTemplate('quiz/play', ['quiz' => $quiz]);
}
```

### Services (Business Logic)

**File:** `Service/QuizService.php`

**Responsibilities:**
- Implement business rules
- Coordinate with repositories
- Manage transactions
- Calculate results

**Example:**
```php
public function scoreQuiz(Quiz $quiz, array $userAnswers): int
{
    $score = 0;
    $totalQuestions = count($quiz->questions);
    
    foreach ($quiz->questions as $question) {
        $userAnswer = $userAnswers[$question->id] ?? null;
        $correctAnswer = $this->questionRepository->getCorrectAnswer($question->id);
        
        if ($userAnswer === $correctAnswer->id) {
            $score += (100 / $totalQuestions);
        }
    }
    
    return (int)$score;
}
```

### Repositories (Data Access)

**File:** `Repository/QuizRepository.php`

**Responsibilities:**
- Execute database queries
- Return model objects (never raw arrays)
- Handle query building
- Implement caching (optional)

**Example:**
```php
public function findById(int $id): ?Quiz
{
    $result = $this->query(
        'SELECT id, title, description FROM {db_prefix}quiz WHERE id = ?',
        [Quiz::TYPE_INT => $id]
    );
    
    if (!$row = $result->fetch_assoc()) {
        return null;
    }
    
    return new Quiz(
        id: (int)$row['id'],
        title: $row['title'],
        description: $row['description'],
        // ...
    );
}
```

### Models (Data Entities)

**File:** `Model/Quiz.php`

**Responsibilities:**
- Hold data
- Provide typed properties
- Implement business logic relevant to the entity

**Example:**
```php
final class Quiz
{
    public function __construct(
        public readonly int $id,
        public string $title,
        public ?string $description = null,
        public int $playLimit = 0,
        public int $secondsPerQuestion = 0,
        public bool $showAnswers = false,
        public bool $enabled = true,
        public int $creatorId,
    ) {}
    
    public function isAccessibleTo(int $userId): bool
    {
        return $this->creatorId === $userId || isAdmin();
    }
    
    public function canBePlayed(): bool
    {
        return $this->enabled && !empty($this->questions);
    }
}
```

## Dependency Injection

### How It Works

Instead of creating dependencies inside methods:

**❌ OLD (Tightly Coupled):**
```php
class QuizService
{
    public function getQuiz(int $id): ?Quiz
    {
        $repository = new QuizRepository(); // Creates new instance
        return $repository->findById($id);
    }
}
```

**✅ NEW (Loosely Coupled):**
```php
class QuizService
{
    public function __construct(
        private QuizRepository $repository,
    ) {}
    
    public function getQuiz(int $id): ?Quiz
    {
        return $this->repository->findById($id); // Uses injected instance
    }
}
```

**Benefits:**
- Easy to test (inject mock repository in tests)
- Easy to swap implementations
- Explicit about dependencies

### Service Container

The Integration class acts as a service container:

```php
class Integration
{
    private static array $services = [];
    
    public static function getService(string $name): object
    {
        if (!isset(self::$services[$name])) {
            self::$services[$name] = match ($name) {
                'quiz.repository' => new QuizRepository(),
                'quiz.service' => new QuizService(self::getService('quiz.repository')),
                'controller.quiz' => new QuizController(self::getService('quiz.service')),
            };
        }
        return self::$services[$name];
    }
}
```

## Type System

### Strict Types

Every file starts with:
```php
declare(strict_types=1);
```

This means:
- `1` will NOT be auto-converted to `true`
- `"123"` will NOT be auto-converted to `123`
- Functions enforce their type declarations

### Type Declarations

```php
// Parameter types
public function addQuestion(int $quizId, string $text, int $type): void

// Return type
public function getQuiz(int $id): ?Quiz

// Nullable types
public function getDescription(): ?string

// Union types (PHP 8.0+)
public function getValue(): int|string|null
```

## Error Handling

### Exception Hierarchy

```
Exception (PHP built-in)
├── RuntimeException
│   └── QuizException (base quiz exception)
│       ├── QuizNotFoundException
│       ├── InvalidQuizException
│       └── QuizAccessDeniedException
└── ...
```

### Usage

```php
try {
    $quiz = $this->quizService->getQuiz($id);
} catch (QuizNotFoundException $e) {
    // Handle not found
} catch (QuizAccessDeniedException $e) {
    // Handle permission denied
} catch (QuizException $e) {
    // Handle other quiz errors
}
```

## Database Security

### Parameterized Queries

**❌ VULNERABLE:**
```php
$query = "SELECT * FROM quizzes WHERE id = $id";
```

**✅ SAFE:**
```php
$query = "SELECT * FROM quizzes WHERE id = ?";
$db->query($query, ['int' => $id]);
```

All queries use placeholders (`?`) with type information.

## Performance Considerations

### N+1 Query Problem

**❌ INEFFICIENT:**
```php
$quizzes = $repository->getAllQuizzes();
foreach ($quizzes as $quiz) {
    $quiz->questions = $repository->getQuestions($quiz->id); // N queries!
}
```

**✅ EFFICIENT:**
```php
$quizzes = $repository->getAllQuizzesWithQuestions(); // 1 query with JOIN
```

### Query Optimization

Use eager loading:
```php
$quiz = $repository->findByIdWithQuestions($id); // 1 query
```

## Testing

### Unit Test Example

```php
class QuizServiceTest extends TestCase
{
    private QuizService $service;
    private MockRepository $mockRepository;
    
    protected function setUp(): void
    {
        $this->mockRepository = new MockRepository();
        $this->service = new QuizService($this->mockRepository);
    }
    
    public function testScoreQuiz(): void
    {
        $quiz = new Quiz(...); // Test data
        $answers = [1 => 5, 2 => 10]; // User answers
        
        $score = $this->service->scoreQuiz($quiz, $answers);
        
        $this->assertEquals(100, $score);
    }
}
```

## Integration with SMF

### Hook System

The mod uses SMF's hook system (no modifications needed):

```php
// Integration.php
add_integration_function('integrate_pre_load', [Integration::class, 'init']);
add_integration_function('integrate_admin_areas', [Integration::class, 'adminAreas']);
```

### SMF Globals

Legacy globals are wrapped in a context object:

```php
class SMFContext
{
    public function __construct(
        public array &$context,
        public array &$modSettings,
        public string $scriptUrl,
    ) {}
}
```

Instead of:
```php
global $context, $modSettings, $scripturl;
```

## Security Model

### Authorization

Every action checks permissions:

```php
if (!isAllowedTo('quiz_admin')) {
    throw new PermissionDeniedException();
}
```

### Input Validation

All inputs validated before use:

```php
$validated = $this->validation->validate($_POST, [
    'title' => 'required|string|max:255',
    'description' => 'string|nullable|max:1000',
]);
```

## Future Enhancements

The architecture supports:
- API endpoints (return JSON instead of HTML)
- Event system (hooks for plugins)
- Caching layer (Redis support)
- Admin dashboard widgets
- REST API

## Questions?

Refer to specific service/repository files for implementation details.
