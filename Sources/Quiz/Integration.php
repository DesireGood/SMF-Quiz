<?php

declare(strict_types=1);

namespace Quiz;

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Integration
 *
 * This class handles all SMF hook registration for the Quiz modification.
 * It serves as the entry point for the mod and manages the service container.
 *
 * @package Quiz
 */
final class Integration
{
    /**
     * Service instances
     *
     * @var array<string, object>
     */
    private static array $services = [];

    /**
     * Initialize the Quiz modification
     *
     * This method is called by SMF's integrate_pre_load hook and sets up:
     * - Version information
     * - Hook registrations
     * - Autoloader
     * - Permissions
     *
     * @return void
     */
    public static function init(): void
    {
        self::setVersion();
        self::registerHooks();
        self::registerAutoloader();
    }

    /**
     * Set default mod settings and version
     *
     * @return void
     */
    private static function setVersion(): void
    {
        global $modSettings;

        $defaults = [
            'SMFQuiz_version' => '2.1.0-Modernized',
            'SMFQuiz_ListPageSizes' => 20,
            'SMFQuiz_InfoBoardItemsToDisplay' => 20,
            'SMFQuiz_showUserRating' => 1,
            'SMFQuiz_AutoClean' => 'on',
            'SMFQuiz_SessionTimeLimit' => 30,
            'SMFQuiz_enabled' => 1,

            // Results messages
            'SMFQuiz_0to19' => 'Oh dear, you really were poor in that quiz.',
            'SMFQuiz_20to39' => 'That was not your best effort now was it?',
            'SMFQuiz_40to59' => 'Well - You could have done better. Mediocrity is not the end of the world!',
            'SMFQuiz_60to79' => 'That is a pretty good score, well done.',
            'SMFQuiz_80to99' => 'Good score, we like that!',
            'SMFQuiz_99to100' => 'WOW - You are simply amazing. That is a Perfect Score! Did you Google those answers?',
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($modSettings[$key])) {
                $modSettings[$key] = $value;
            }
        }
    }

    /**
     * Register SMF hooks
     *
     * @return void
     */
    private static function registerHooks(): void
    {
        add_integration_function('integrate_autoload', [self::class, 'autoload'], false);
        add_integration_function('integrate_admin_areas', [self::class, 'adminAreas'], false);
        add_integration_function('integrate_menu_buttons', [self::class, 'menuButtons'], false);
        add_integration_function('integrate_actions', [self::class, 'actions'], false);
        add_integration_function('integrate_load_permissions', [self::class, 'permissions'], false);
        add_integration_function('integrate_load_illegal_guest_permissions', [self::class, 'illegalGuestPermissions'], false);
        add_integration_function('integrate_pre_css_output', [self::class, 'preCSS'], false);
    }

    /**
     * Register PSR-4 autoloader
     *
     * @param array<string, string> $classMap Class map for autoloading
     * @return void
     */
    public static function autoload(array &$classMap): void
    {
        $classMap['Quiz\\'] = 'Quiz/';
    }

    /**
     * Register admin areas
     *
     * @param array<string, array> $adminAreas Admin areas array
     * @return void
     */
    public static function adminAreas(array &$adminAreas): void
    {
        global $txt, $modSettings, $scripturl;

        loadLanguage('Quiz/Admin');
        loadLanguage('Quiz/Quiz');
        loadLanguage('Quiz/Common');

        $adminAreas['quiz'] = [
            'title' => $txt['SMFQuiz'] ?? 'Quiz',
            'permission' => ['quiz_admin'],
            'areas' => [
                'quiz' => [
                    'label' => $txt['SMFQuiz'] ?? 'Quiz',
                    'file' => 'Quiz/Admin.php',
                    'function' => 'SMFQuizAdmin',
                    'icon' => 'icons/quiz.png',
                    'permission' => ['quiz_admin'],
                    'subsections' => [
                        'adminCenter' => [$txt['SMFQuizAdmin_Titles']['AdminCenter'] ?? 'Dashboard'],
                        'settings' => [$txt['SMFQuizAdmin_Titles']['Settings'] ?? 'Settings'],
                        'quizes' => [$txt['SMFQuizAdmin_Titles']['Quizes'] ?? 'Quizzes'],
                        'categories' => [$txt['SMFQuizAdmin_Titles']['Categories'] ?? 'Categories'],
                        'questions' => [$txt['SMFQuizAdmin_Titles']['Questions'] ?? 'Questions'],
                        'results' => [$txt['SMFQuizAdmin_Titles']['Results'] ?? 'Results'],
                        'disputes' => [$txt['SMFQuizAdmin_Titles']['Disputes'] ?? 'Disputes'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Register menu buttons
     *
     * @param array<string, array> $buttons Buttons array
     * @return void
     */
    public static function menuButtons(array &$buttons): void
    {
        global $txt, $modSettings, $scripturl;

        loadLanguage('Quiz/Quiz');

        if (empty($modSettings['SMFQuiz_enabled'])) {
            return;
        }

        $before = 'mlist';
        $temp_buttons = [];

        foreach ($buttons as $k => $v) {
            if ($k === $before) {
                $temp_buttons['SMFQuiz'] = [
                    'title' => $txt['SMFQuiz'] ?? 'Quiz',
                    'href' => $scripturl . '?action=SMFQuiz',
                    'icon' => 'quiz',
                    'show' => allowedTo('quiz_view'),
                    'sub_buttons' => [],
                ];
            }
            $temp_buttons[$k] = $v;
        }

        $buttons = $temp_buttons;
    }

    /**
     * Register actions
     *
     * @param array<string, array> $actionArray Action array
     * @return void
     */
    public static function actions(array &$actionArray): void
    {
        $actionArray['SMFQuiz'] = ['Quiz/Quiz.php', 'SMFQuiz'];
        $actionArray['SMFQuizAnswers'] = ['Quiz/Answers.php', 'UpdateSession'];
        $actionArray['SMFQuizStart'] = ['Quiz/Start.php', 'loadQuiz'];
        $actionArray['SMFQuizEnd'] = ['Quiz/End.php', 'endQuiz'];
        $actionArray['SMFQuizQuestions'] = ['Quiz/Questions.php', 'quizQuestions'];
        $actionArray['SMFQuizDispute'] = ['Quiz/Dispute.php', 'quizDispute'];
        $actionArray['SMFQuizAjax'] = ['Quiz/Ajax.php', 'quizImageUpload'];
    }

    /**
     * Register permissions
     *
     * @param array<string, array> $permissionGroups Permission groups
     * @param array<string, array> $permissionList Permission list
     * @return void
     */
    public static function permissions(array &$permissionGroups, array &$permissionList): void
    {
        $permissionGroups['membergroup'][] = 'quiz';
        $permissionList['membergroup']['quiz_view'] = [false, 'quiz'];
        $permissionList['membergroup']['quiz_play'] = [false, 'quiz'];
        $permissionList['membergroup']['quiz_submit'] = [false, 'quiz'];
        $permissionList['membergroup']['quiz_admin'] = [false, 'quiz'];
    }

    /**
     * Register illegal guest permissions
     *
     * @return void
     */
    public static function illegalGuestPermissions(): void
    {
        global $context;

        $context['non_guest_permissions'][] = 'quiz_play';
        $context['non_guest_permissions'][] = 'quiz_submit';
        $context['non_guest_permissions'][] = 'quiz_admin';
    }

    /**
     * Add CSS for quiz icon
     *
     * @return void
     */
    public static function preCSS(): void
    {
        global $settings;

        addInlineCss(
            '.main_icons.quiz::before {'
            . 'background-position: 0;'
            . 'background-image: url("' . $settings['default_images_url'] . '/icons/quiz.png");'
            . 'background-size: contain;'
            . '}'
        );
    }

    /**
     * Get or create a service instance
     *
     * @param string $serviceName Service name
     * @return object Service instance
     */
    public static function getService(string $serviceName): object
    {
        if (!isset(self::$services[$serviceName])) {
            self::$services[$serviceName] = self::createService($serviceName);
        }

        return self::$services[$serviceName];
    }

    /**
     * Create service instance
     *
     * @param string $serviceName Service name
     * @return object Service instance
     */
    private static function createService(string $serviceName): object
    {
        return match ($serviceName) {
            default => throw new \RuntimeException("Unknown service: {$serviceName}"),
        };
    }
}
