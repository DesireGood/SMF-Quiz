<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

require_once(__DIR__ . '/Utils.php');

/**
 * Finalise a completed quiz play session.
 *
 * Persists the result, updates quiz statistics, fires integration hooks,
 * and removes the active session row.
 *
 * @return void
 */
function endQuiz(): void
{
    global $context;

    if (!allowedTo('quiz_play')) {
        header('Content-Type: text/xml');
        echo '<xml/>';
        die();
    }

    loadLanguage('Quiz/Quiz');

    $idQuizLeague = max(0, (int)($_GET['id_quiz_league'] ?? 0));
    $idQuiz       = max(0, (int)($_GET['id_quiz'] ?? 0));
    $idUser       = (int)$context['user']['id'];
    $name         = (string)$context['user']['name'];
    $idSession    = quiz_sanitize_session_token((string)($_GET['id_session'] ?? ''));
    $questions    = max(0, (int)($_GET['questions'] ?? 0));
    $correct      = max(0, (int)($_GET['correct'] ?? 0));
    $incorrect    = max(0, (int)($_GET['incorrect'] ?? 0));
    $timeouts     = max(0, (int)($_GET['timeouts'] ?? 0));
    $totalSeconds = max(0, (int)($_GET['total_seconds'] ?? 0));
    $creatorId    = max(0, (int)($_GET['creator_id'] ?? 0));
    $points       = max(0, (int)($_GET['points'] ?? 0));
    $round        = max(0, (int)($_GET['round'] ?? 0));
    $totalResumes = max(0, (int)($_GET['totalResumes'] ?? 0));

    if (!empty($idQuiz)) {
        // Don't record results when the creator is playing their own quiz
        if ($creatorId !== $idUser) {
            if (CheckResultExists($idQuiz, $idUser) === false) {
                InsertQuizEnd($idQuiz, $idUser, $questions, $correct, $incorrect, $timeouts, $totalSeconds, $totalResumes);
                UpdateQuiz($idQuiz, $questions, $correct, $totalSeconds, $idUser, $name);
                call_integration_hook(
                    'integrate_quiz_result',
                    [$idQuiz, $idUser, $questions, $correct, $incorrect, $timeouts, $totalSeconds, $totalResumes]
                );
            }
        }
    } elseif (!empty($idQuizLeague)) {
        InsertQuizLeagueEnd($idQuizLeague, $idUser, $questions, $correct, $incorrect, $timeouts, $totalSeconds, $points, $round, $totalSeconds, $name);
        call_integration_hook(
            'integrate_quiz_league_result',
            [$idQuizLeague, $idUser, $questions, $correct, $incorrect, $timeouts, $totalSeconds, $points, $round, $totalSeconds, $name]
        );
    }

    EndSession($idSession);

    header('Content-Type: text/xml');
    echo '<xml/>';
    die();
}

/**
 * Check whether a quiz result already exists, or if the quiz is disabled.
 *
 * @param int $idQuiz Quiz ID
 * @param int $idUser User ID
 * @return bool True when a result exists or the quiz is disabled
 */
function CheckResultExists(int $idQuiz, int $idUser): bool
{
    global $smcFunc;

    $result = $smcFunc['db_query']('', '
        SELECT id_quiz_result
        FROM {db_prefix}quiz_result QR
        RIGHT JOIN {db_prefix}quiz Q ON QR.id_quiz = Q.id_quiz
        WHERE (QR.id_quiz = {int:id_quiz} AND QR.id_user = {int:id_user})
            OR (Q.id_quiz = {int:id_quiz} AND Q.enabled = {int:quiz_disabled})',
        [
            'id_quiz'       => $idQuiz,
            'id_user'       => $idUser,
            'quiz_disabled' => 0,
        ]
    );

    $count = $smcFunc['db_num_rows']($result);
    $smcFunc['db_free_result']($result);

    return $count > 0;
}

/**
 * Insert a new quiz result row.
 *
 * @param int $idQuiz Quiz ID
 * @param int $idUser User ID
 * @param int $questions Total questions
 * @param int $correct Correct answers
 * @param int $incorrect Incorrect answers
 * @param int $timeouts Timed-out answers
 * @param int $totalSeconds Total time in seconds
 * @param int $totalResumes Session resume count
 * @return void
 */
function InsertQuizEnd(int $idQuiz, int $idUser, int $questions, int $correct, int $incorrect, int $timeouts, int $totalSeconds, int $totalResumes): void
{
    global $smcFunc;

    $smcFunc['db_insert']('',
        '{db_prefix}quiz_result',
        [
            'id_quiz'       => 'int',
            'id_user'       => 'int',
            'result_date'   => 'int',
            'questions'     => 'int',
            'correct'       => 'int',
            'incorrect'     => 'int',
            'timeouts'      => 'int',
            'total_seconds' => 'int',
            'total_resumes' => 'int',
        ],
        [$idQuiz, $idUser, time(), $questions, $correct, $incorrect, $timeouts, $totalSeconds, $totalResumes],
        ['id_quiz_result']
    );
}

/**
 * Remove the active quiz session row.
 *
 * @param string $idSession Session token
 * @return void
 */
function EndSession(string $idSession): void
{
    global $smcFunc;

    $smcFunc['db_query']('', '
        DELETE FROM {db_prefix}quiz_session
        WHERE id_quiz_session = {string:id_session}',
        ['id_session' => $idSession]
    );
}

/**
 * Update quiz-level statistics and top-score after a completed attempt.
 *
 * @param int $idQuiz Quiz ID
 * @param int $questions Questions answered
 * @param int $correct Correct answers
 * @param int $totalSeconds Total time
 * @param int $idUser User ID
 * @param string $name User display name
 * @return void
 */
function UpdateQuiz(int $idQuiz, int $questions, int $correct, int $totalSeconds, int $idUser, string $name): void
{
    global $smcFunc, $scripturl, $sourcedir, $modSettings, $settings, $user_settings;

    $quizTopResult = $smcFunc['db_query']('', '
        SELECT Q.top_correct, Q.top_time, Q.top_user_id, Q.title, Q.image, M.real_name
        FROM {db_prefix}quiz Q
        LEFT JOIN {db_prefix}members M ON M.id_member = Q.top_user_id
        WHERE id_quiz = {int:id_quiz}',
        ['id_quiz' => $idQuiz]
    );

    $quizImage = $settings['default_images_url'] . '/quiz_images/Quizes/Default-64.png';
    $quizTitle = '';
    $topScore  = false;
    $topCorrect = 0;
    $topIdUser  = 0;
    $topTime    = 0;
    $topUserName = '';

    if ($smcFunc['db_num_rows']($quizTopResult) > 0) {
        $row = $smcFunc['db_fetch_assoc']($quizTopResult);
        $topCorrect  = (int)$row['top_correct'];
        $topIdUser   = (int)$row['top_user_id'];
        $topUserName = (string)$row['real_name'];
        $topTime     = (int)$row['top_time'];
        $quizTitle   = (string)$row['title'];
        if (!empty($row['image'])) {
            $quizImage = $settings['default_images_url'] . '/quiz_images/Quizes/' . $row['image'];
        }
        if ($correct > $topCorrect || ($correct === $topCorrect && $totalSeconds < $topTime)) {
            $topScore = true;
        }
    } else {
        $topScore = true;
    }
    $smcFunc['db_free_result']($quizTopResult);

    if (!$topScore) {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}quiz
            SET
                quiz_plays = quiz_plays + 1,
                question_plays = question_plays + {int:questions},
                total_correct = total_correct + {int:correct}
            WHERE id_quiz = {int:id_quiz}',
            ['questions' => $questions, 'correct' => $correct, 'id_quiz' => $idQuiz]
        );
        AddInfoBoardentry($idUser, $name, $idQuiz, $correct, $totalSeconds, false, $quizTitle, $quizImage);
    } else {
        if (!empty($modSettings['SMFQuiz_SendPMOnBrokenTopScore'])) {
            require_once($sourcedir . '/Subs-Post.php');

            $pmto = ['to' => [], 'bcc' => [$topIdUser]];
            $subject = ParseMessage($modSettings['SMFQuiz_PMBrokenTopScoreSubject'], $quizTitle, $totalSeconds, $correct, $topTime, $topCorrect, $quizImage, $scripturl, $idQuiz, $topUserName);
            $message = ParseMessage($modSettings['SMFQuiz_PMBrokenTopScoreMsg'], $quizTitle, $totalSeconds, $correct, $topTime, $topCorrect, $quizImage, $scripturl, $idQuiz, $topUserName);
            $pmfrom  = [
                'id'       => (int)$user_settings['id_member'],
                'name'     => $user_settings['real_name'],
                'username' => $user_settings['member_name'],
            ];
            sendpm($pmto, $subject, $message, 0, $pmfrom);
        }

        $smcFunc['db_query']('', '
            UPDATE {db_prefix}quiz
            SET
                quiz_plays = quiz_plays + 1,
                question_plays = question_plays + {int:questions},
                total_correct = total_correct + {int:correct},
                top_user_id = {int:id_user},
                top_correct = {int:correct},
                top_time = {int:total_seconds}
            WHERE id_quiz = {int:id_quiz}',
            [
                'questions'     => $questions,
                'correct'       => $correct,
                'id_user'       => $idUser,
                'total_seconds' => $totalSeconds,
                'id_quiz'       => $idQuiz,
            ]
        );
        AddInfoBoardentry($idUser, $name, $idQuiz, $correct, $totalSeconds, true, $quizTitle, $quizImage);
    }
}

/**
 * Add an infoboard entry after a completed quiz attempt.
 *
 * @param int $idUser User ID
 * @param string $name User display name
 * @param int $idQuiz Quiz ID
 * @param int $correct Correct answers
 * @param int $totalSeconds Total seconds
 * @param bool $topScore Whether this is a new top score
 * @param string $quizTitle Quiz title
 * @param string $quizImage Quiz image URL
 * @return void
 */
function AddInfoBoardentry(int $idUser, string $name, int $idQuiz, int $correct, int $totalSeconds, bool $topScore, string $quizTitle, string $quizImage): void
{
    global $smcFunc, $boardurl, $settings, $txt;

    $safeName  = addslashes($name);
    $safeTitle = addslashes($quizTitle);
    $userLink  = '<a href="' . $boardurl . '/index.php?action=SMFQuiz;sa=userdetails;id_user=' . $idUser . '"><b>' . $safeName . '</b></a>';
    $quizLink  = '<b><a href="' . $boardurl . '/index.php?action=SMFQuiz;sa=categories;id_quiz=' . $idQuiz . '">' . $safeTitle . '</a></b>';
    $imgTag    = '<img width="17" height="17" src="' . $quizImage . '"/>';

    if ($topScore) {
        $entry = '<img src="' . $settings['default_images_url'] . '/quiz_images/cup_g.gif"/> '
            . $userLink . ' ' . ($txt['SMFQuiz_QuizEnd_Page']['JustAnswered'] ?? 'just answered') . ' <b>' . $correct . '</b> '
            . ($txt['SMFQuiz_QuizEnd_Page']['QuestionsCorrectlyInThe'] ?? 'questions correctly in the') . ' ' . $imgTag . $quizLink . ' '
            . ($txt['SMFQuiz_QuizEnd_Page']['QuizInATimeOf'] ?? 'quiz in a time of') . ' <b>' . $totalSeconds . '</b> '
            . ($txt['SMFQuiz_QuizEnd_Page']['SecondsThisIsANewTopScore'] ?? 'seconds. This is a new top score!');
    } else {
        $entry = $userLink . ' ' . ($txt['SMFQuiz_QuizEnd_Page']['JustAnswered'] ?? 'just answered') . ' <b>' . $correct . '</b> '
            . ($txt['SMFQuiz_QuizEnd_Page']['QuestionsCorrectlyInThe'] ?? 'questions correctly in the') . ' ' . $imgTag . $quizLink . ' '
            . ($txt['SMFQuiz_QuizEnd_Page']['QuizInATimeOf'] ?? 'quiz in a time of') . ' <b>' . $totalSeconds . '</b> '
            . ($txt['SMFQuiz_Common']['seconds'] ?? 'seconds');
    }

    $entry = $smcFunc['db_escape_string'](html_entity_decode($entry, ENT_QUOTES, 'UTF-8'));

    $smcFunc['db_insert']('',
        '{db_prefix}quiz_infoboard',
        ['entry_date' => 'int', 'entry' => 'string'],
        [time(), $entry],
        ['id_infoboard']
    );
}

/**
 * Add an infoboard entry after a completed quiz league attempt.
 *
 * @param int $idUser User ID
 * @param string $name User display name
 * @param int $idQuizLeague Quiz league ID
 * @param int $correct Correct answers
 * @param int $totalSeconds Total seconds
 * @return void
 */
function AddQuizLeagueInfoBoardentry(int $idUser, string $name, int $idQuizLeague, int $correct, int $totalSeconds): void
{
    global $smcFunc, $boardurl;

    $titleResult = $smcFunc['db_query']('', '
        SELECT QL.title FROM {db_prefix}quiz_league QL
        WHERE QL.id_quiz_league = {int:id_quiz_league}',
        ['id_quiz_league' => $idQuizLeague]
    );

    $quizTitle = '';
    if ($smcFunc['db_num_rows']($titleResult) > 0) {
        [$quizTitle] = $smcFunc['db_fetch_row']($titleResult);
    }
    $smcFunc['db_free_result']($titleResult);

    $entry = '<a href="' . $boardurl . '/index.php?action=SMFQuiz;sa=userdetails;id_user=' . $idUser . '"><b>'
        . addslashes($name) . '</b></a> just answered <b>' . $correct . '</b> questions correctly in the <b>'
        . addslashes($quizTitle) . '</b> quiz league in a time of <b>' . $totalSeconds . '</b> seconds.';

    $entry = $smcFunc['db_escape_string'](html_entity_decode($entry, ENT_QUOTES, 'UTF-8'));

    $smcFunc['db_insert']('',
        '{db_prefix}quiz_infoboard',
        ['entry_date' => 'int', 'entry' => 'string'],
        [time(), $entry],
        ['id_infoboard']
    );
}

/**
 * Insert a quiz league result row and update the league totals.
 *
 * @param int $idQuizLeague League ID
 * @param int $idUser User ID
 * @param int $questions Questions answered
 * @param int $correct Correct answers
 * @param int $incorrect Incorrect answers
 * @param int $timeouts Timed-out answers
 * @param int $totalSeconds Total time
 * @param int $points Points earned
 * @param int $round League round
 * @param int $seconds Seconds (alias of totalSeconds for DB)
 * @param string $name User display name
 * @return void
 */
function InsertQuizLeagueEnd(int $idQuizLeague, int $idUser, int $questions, int $correct, int $incorrect, int $timeouts, int $totalSeconds, int $points, int $round, int $seconds, string $name): void
{
    global $smcFunc;

    $smcFunc['db_insert']('',
        '{db_prefix}quiz_league_result',
        [
            'id_quiz_league' => 'int',
            'id_user'        => 'int',
            'round'          => 'int',
            'correct'        => 'int',
            'points'         => 'int',
            'result_date'    => 'int',
            'incorrect'      => 'int',
            'timeouts'       => 'int',
            'seconds'        => 'int',
        ],
        [$idQuizLeague, $idUser, $round, $correct, $points, time(), $incorrect, $timeouts, $seconds],
        ['id_quiz_league_result']
    );

    $smcFunc['db_query']('', '
        UPDATE {db_prefix}quiz_league
        SET
            total_plays = total_plays + 1,
            total_correct = total_correct + {int:correct},
            total_incorrect = total_incorrect + {int:incorrect},
            total_timeouts = total_timeouts + {int:timeouts}',
        ['correct' => $correct, 'incorrect' => $incorrect, 'timeouts' => $timeouts]
    );

    AddQuizLeagueInfoBoardentry($idUser, $name, $idQuizLeague, $correct, $totalSeconds);
}

/**
 * Replace template placeholders in a PM message string.
 *
 * @param string $message Message template
 * @param string $quizTitle Quiz title
 * @param int $totalSeconds New score time
 * @param int $totalPoints New score points
 * @param int $topTime Previous top-score time
 * @param int $topPoints Previous top-score points
 * @param string $quizImage Quiz image URL
 * @param string $scripturl SMF script URL
 * @param int $idQuiz Quiz ID
 * @param string $oldMemberName Previous top-scorer name
 * @return string Processed message
 */
function ParseMessage(string $message, string $quizTitle, int $totalSeconds, int $totalPoints, int $topTime, int $topPoints, string $quizImage, string $scripturl, int $idQuiz, string $oldMemberName): string
{
    global $user_settings;

    return strtr($message, [
        '{quiz_name}'        => $quizTitle,
        '{new_score_seconds}'=> (string)$totalSeconds,
        '{new_score}'        => (string)$totalPoints,
        '{old_score_seconds}'=> (string)$topTime,
        '{old_score}'        => (string)$topPoints,
        '{member_name}'      => $user_settings['real_name'] ?? '',
        '{old_member_name}'  => $oldMemberName,
        '{quiz_image}'       => '[img]' . $quizImage . '[/img]',
        '{quiz_link}'        => $scripturl . '?action=SMFQuiz;sa=categories;id_quiz=' . $idQuiz,
    ]);
}
