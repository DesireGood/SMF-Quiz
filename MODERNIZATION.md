# SMF Quiz 2.1.0 - Modernization Guide

This document outlines all changes made to modernize the SMF Quiz modification for PHP 8.0+.

## Overview

The SMF Quiz modification has been comprehensively refactored from a decade-old codebase with procedural patterns to a modern, object-oriented architecture with strict types, security hardening, and clean separation of concerns.

## Key Changes

### 1. PHP 8.0+ Strict Typing
- Added `declare(strict_types=1);` to all files
- All functions now have parameter and return type declarations
- Uses nullable types (`?Type`), union types where applicable
- No implicit type coercion

**Example (Before):**
```php
function GetQuizData()
{
    global $context;
    // ...
}
```

**Example (After):**
```php
public function getQuizData(int $quizId, int $page = 1): array
{
    // ...
    return $quizzes;
}
```

### 2. Architecture Refactoring

The monolithic 91KB `Admin.php` and 70KB `Quiz.php` files have been split into focused classes:

**New Directory Structure:**
```
Sources/Quiz/
├── Integration.php              # SMF hook registration (entry point)
├── Controller/
│   ├── AdminController.php      # Admin panel request handling
│   ├── QuizController.php       # User quiz request handling
│   └── AjaxController.php       # AJAX endpoint handling
├── Service/
│   ├── QuizService.php          # Quiz business logic
│   ├── CategoryService.php      # Category management logic
│   ├── QuestionService.php      # Question management logic
│   ├── ResultService.php        # Result tracking logic
│   └── ValidationService.php    # Centralized validation
├── Repository/
│   ├── QuizRepository.php       # Quiz data access
│   ├── CategoryRepository.php   # Category data access
│   ├── QuestionRepository.php   # Question data access
│   ├── ResultRepository.php     # Result data access
│   └── BaseRepository.php       # Common repository logic
├── Model/
│   ├── Quiz.php                 # Quiz entity
│   ├── Category.php             # Category entity
│   ├── Question.php             # Question entity
│   ├── Answer.php               # Answer entity
│   └── Result.php               # Result entity
└── Traits/
    ├── HasValidation.php        # Reusable validation methods
    └── HasErrorHandling.php     # Reusable error handling
```

**Benefits:**
- Each class has a single responsibility
- Easy to test individual components
- Easy to understand and modify specific features
- Reusable logic across controllers

### 3. Security Hardening

#### SQL Injection Prevention
**Before:**
```php
$result = $smcFunc['db_query']('', "
    DELETE FROM {$db_prefix}quiz_answer
    WHERE id_question = {$questionId}
");
```

**After:**
```php
$this->db->query('DELETE FROM {db_prefix}quiz_answer WHERE id_question = ?', [
    'int' => $questionId,
]);
```

#### Input Validation Framework
**New ValidationService ensures all inputs are validated before use:**
```php
$validated = $validationService->validate($_POST, [
    'title' => 'required|string|max:255',
    'description' => 'string|max:1000',
    'play_limit' => 'integer|min:1|max:100',
]);
```

### 4. Removed Global Dependencies

**Before:**
```php
function GetQuizesData()
{
    global $context, $scripturl, $smcFunc, $txt, $modSettings, $settings;
    // Uses globals throughout
}
```

**After:**
```php
public function __construct(
    private SMFContext $context,
    private QuizRepository $repository,
    private ValidationService $validation,
) {}

public function getQuizzes(int $page = 1): array
{
    // Dependencies injected, no globals needed
}
```

### 5. Modern JavaScript

**Removed:**
- jQuery 1.3.2 (from 2009)
- Inline JavaScript mixed with PHP
- Old CDATA sections

**Added:**
- Vanilla JavaScript modules (modern, no dependency)
- Proper event handling
- Separated into `Themes/Quiz/scripts/` with clear purpose

**Example:**
```javascript
// Old (mixed in PHP):
$context['html_headers'] .= '<script>function checkAll() { ... }</script>';

// New (separate file):
// Themes/Quiz/scripts/quiz-admin.js
export class QuizAdmin {
    static checkAll(form, checked) {
        form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = checked;
        });
    }
}
```

### 6. Error Handling & Logging

**Before:**
```php
if (!$myxml = simplexml_load_string($quizString))
    fatal_lang_error('quiz_mod_quiz_already_exists', false);
```

**After:**
```php
try {
    $quiz = $this->quizImporter->import($xmlString);
} catch (InvalidQuizException $e) {
    $this->logger->error('Quiz import failed', ['error' => $e->getMessage()]);
    throw new QuizException('Failed to import quiz', 0, $e);
}
```

### 7. Database Layer Modernization

**New QueryBuilder Pattern:**
```php
$quizzes = $this->quizRepository
    ->where('enabled', '=', 1)
    ->where('creator_id', '=', $userId)
    ->orderBy('updated', 'DESC')
    ->paginate(20);
```

Instead of:
```php
$result = $smcFunc['db_query']('', 'SELECT ...');
while ($row = $smcFunc['db_fetch_assoc']($result)) { ... }
```

### 8. Type Safety Throughout

**All functions now declare types:**
```php
// Quiz model
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
    return $this->creatorId === $userId || currentUserIsAdmin();
}
```

## Migration Path

See `MIGRATION.md` for upgrade instructions from 2.0.2.

## Performance Improvements

1. **Reduced database queries** through better query design
2. **Lazy loading** of related entities
3. **Query caching** for frequently accessed data
4. **Reduced memory usage** through generator-based result iteration

## Backward Compatibility

**SMF Integration:** ✓ Fully compatible with SMF 2.1.x hook system
**Database:** ✓ Existing data structure maintained (with migration script)
**Admin Panel:** ✓ Identical user experience
**User Features:** ✓ Identical quiz playing experience

**Code Level:** ✗ NOT backward compatible (internal API changed)
- Extensions that depend on old Quiz class methods will need updates
- This is intentional—modernization requires these changes

## Testing Checklist

- [ ] Create a new quiz
- [ ] Edit existing quiz
- [ ] Delete a quiz
- [ ] Add questions to quiz
- [ ] Play a quiz
- [ ] Submit answers
- [ ] View quiz results
- [ ] Check admin statistics
- [ ] Export quizzes
- [ ] Import quizzes
- [ ] Test with PHP 8.0, 8.1, 8.2
- [ ] Verify no SQL errors in SMF error logs

## Troubleshooting

### "Class not found" errors
- Ensure PSR-4 autoloader is registered (done in Integration.php)
- Clear SMF cache: Admin → Maintenance → Empty the cache

### Database errors after upgrade
- Run database migration: Database migration runs automatically on first load
- Check `database.php` for any issues

### Permission denied
- Ensure `Sources/Quiz/` directory is writable
- Verify file permissions are 0644 (files) and 0755 (directories)

## Questions or Issues?

See `ARCHITECTURE.md` for deep dive into design decisions.
