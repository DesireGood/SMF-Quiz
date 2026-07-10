<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Initialise a quiz play session and return quiz/session data as XML.
 *
 * Called by the SMFQuizStart action.
 *
 * @return void
 */
function loadQuiz(): void
{
    global $context, $txt;

    loadTemplate('Quiz/Admin');
    loadLanguage('Quiz/Quiz');

    if (!allowedTo('quiz_play')) {
        header('Content-Type: text/xml');
        echo '<xml/>';
        die();
    }

    $idQuizLeague = max(0, (int)($_GET['id_quiz_league'] ?? 0));
    $idQuiz       = max(0, (int)($_GET['id_quiz'] ?? 0));
    $idUser       = (int)$context['user']['id'];
    $idSession    = md5(uniqid((string)mt_rand(), true));

    $xmlReturn = '<smfQuiz>';

    if ($idQuiz !== 0) {
        $sessions = QuizSessionExists($idUser, $idQuiz);
        if (count($sessions) > 0) {
            $xmlReturn .= GetQuizSessionXml($sessions);
            $xmlReturn .= GetQuizDetails($idQuiz, $idUser, $idSession, false);
        } else {
            $xmlReturn .= GetQuizDetails($idQuiz, $idUser, $idSession, false);
            if (str_contains($xmlReturn, 'title')) {
                InsertQuizSession($idSession, $idUser, $idQuiz, null);
            }
        }
    } elseif ($idQuizLeague !== 0) {
        $sessions = QuizLeagueSessionExists($idUser, $idQuizLeague);
        if (count($sessions) > 0) {
            $xmlReturn .= GetQuizSessionXml($sessions);
            $xmlReturn .= GetQuizLeagueDetails($idQuizLeague, $idUser, $idSession, false);
        } else {
            $xmlReturn .= GetQuizLeagueDetails($idQuizLeague, $idUser, $idSession, false);
            InsertQuizSession($idSession, $idUser, null, $idQuizLeague);
        }
    } else {
        $xmlReturn .= '<Error>' . ($txt['quiz_xml_error_no_id'] ?? 'No quiz ID provided') . '</Error>';
    }

    $xmlReturn .= '</smfQuiz>';

    header('Content-Type: text/xml');
    echo $xmlReturn;
    die();
}

/**
 * Encode a string for safe inclusion in XML.
 *
 * @param string $txt Raw string
 * @return string XML-safe string
 */
function xmlencode(string $txt): string
{
    return strtr($txt, [
        '&'  => '&amp;',
        '<'  => '&lt;',
        '>'  => '&gt;',
        "'"  => '&apos;',
        '"'  => '&quot;',
    ]);
}

/**
 * Build an XML fragment for one or more existing quiz sessions.
 *
 * @param array<int, array<string, mixed>> $sessions Session rows
 * @return string XML fragment
 */
function GetQuizSessionXml(array $sessions): string
{
    $xmlFragment = '';
    foreach ($sessions as $session) {
        $xmlFragment .= '<session>'
            . '<id_quiz_session>' . $session['id_quiz_session'] . '</id_quiz_session>'
            . '<session_start>' . (int)$session['session_start'] . '</session_start>'
            . '<last_question_start>' . (int)$session['last_question_start'] . '</last_question_start>'
            . '<question_count>' . (int)$session['question_count'] . '</question_count>'
            . '<session_correct>' . (int)$session['session_correct'] . '</session_correct>'
            . '<session_incorrect>' . (int)$session['session_incorrect'] . '</session_incorrect>'
            . '<session_timeouts>' . (int)$session['session_timeouts'] . '</session_timeouts>'
            . '<session_time>' . (int)$session['session_time'] . '</session_time>'
            . '<total_resumes>' . (int)$session['total_resumes'] . '</total_resumes>'
            . '</session>';
    }
    return $xmlFragment;
}

/**
 * Look up any active sessions for a user on a given quiz.
 *
 * @param int $idUser User ID
 * @param int $idQuiz Quiz ID
 * @return array<int, array<string, mixed>> Session rows
 */
function QuizSessionExists(int $idUser, int $idQuiz): array
{
    global $smcFunc;

    $result = $smcFunc['db_query']('', '
        SELECT id_quiz_session, session_start, last_question_start,
            question_count, id_quiz, id_quiz_league,
            correct AS session_correct, incorrect AS session_incorrect,
            timeouts AS session_timeouts, total_seconds AS session_time, total_resumes
        FROM {db_prefix}quiz_session
        WHERE id_user = {int:id_user}
            AND id_quiz = {int:id_quiz}',
        ['id_user' => $idUser, 'id_quiz' => $idQuiz]
    );

    $rows = [];
    while ($row = $smcFunc['db_fetch_assoc']($result)) {
        $rows[] = $row;
    }
    $smcFunc['db_free_result']($result);

    return $rows;
}

/**
 * Look up any active sessions for a user on a quiz league.
 *
 * Increments the timeout/question count for each resumed session
 * to penalise window-close cheating.
 *
 * @param int $idUser User ID
 * @param int $idQuizLeague Quiz league ID
 * @return array<int, array<string, mixed>> Session rows
 */
function QuizLeagueSessionExists(int $idUser, int $idQuizLeague): array
{
    global $smcFunc;

    $result = $smcFunc['db_query']('', '
        SELECT id_quiz_session, session_start, last_question_start,
            (question_count + 1) AS question_count, id_quiz, id_quiz_league,
            correct AS session_correct, incorrect AS session_incorrect,
            timeouts AS session_timeouts, total_seconds AS session_time, total_resumes
        FROM {db_prefix}quiz_session
        WHERE id_user = {int:id_user}
            AND id_quiz_league = {int:id_quiz_league}',
        ['id_user' => $idUser, 'id_quiz_league' => $idQuizLeague]
    );

    $rows = [];
    while ($row = $smcFunc['db_fetch_assoc']($result)) {
        $rows[] = $row;
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}quiz_session
            SET timeouts = timeouts + 1, question_count = question_count + 1
            WHERE id_quiz_session = {string:id_quiz_session}',
            ['id_quiz_session' => $row['id_quiz_session']]
        );
    }
    $smcFunc['db_free_result']($result);

    return $rows;
}

/**
 * Build an XML fragment containing quiz details and aggregated results.
 *
 * @param int $idQuiz Quiz ID
 * @param int $idUser User ID
 * @param string $idSession New session token
 * @param bool $debugOn Unused; kept for legacy compatibility
 * @return string XML fragment
 */
function GetQuizDetails(int $idQuiz, int $idUser, string $idSession, bool $debugOn): string
{
    global $smcFunc;

    $result = $smcFunc['db_query']('', '
        SELECT Q.title, Q.description, Q.play_limit, Q.seconds_per_question,
            Q.show_answers, Q.image, Q.creator_id
        FROM {db_prefix}quiz Q
        WHERE Q.id_quiz = {int:id_quiz}',
        ['id_user' => $idUser, 'id_quiz' => $idQuiz]
    );

    $rows          = $smcFunc['db_num_rows']($result);
    $quizRow       = $rows > 0 ? $smcFunc['db_fetch_assoc']($result) : null;
    $questionsData = 0;
    $timesPlayed   = 0;

    if ($quizRow !== null) {
        $qResult = $smcFunc['db_query']('', '
            SELECT COUNT(*) AS questions_per_session
            FROM {db_prefix}quiz_question WHERE id_quiz = {int:id_quiz}',
            ['id_quiz' => $idQuiz]
        );
        [$questionsData] = $smcFunc['db_fetch_row']($qResult);
        $smcFunc['db_free_result']($qResult);

        $pResult = $smcFunc['db_query']('', '
            SELECT COUNT(*) AS user_plays FROM {db_prefix}quiz_result
            WHERE id_quiz = {int:id_quiz} AND id_user = {int:id_user}',
            ['id_user' => $idUser, 'id_quiz' => $idQuiz]
        );
        $pRow = $smcFunc['db_fetch_row']($pResult);
        $timesPlayed = $pRow !== false ? (int)$pRow[0] : 0;
        $smcFunc['db_free_result']($pResult);
    }
    $smcFunc['db_free_result']($result);

    $xmlFragment = '<quizDetail>';
    if ($quizRow !== null) {
        $xmlFragment .= '<title>' . xmlencode(ajax_format_string((string)$quizRow['title'])) . '</title>';
        $xmlFragment .= '<id_session>' . $idSession . '</id_session>';
        $xmlFragment .= '<creator_id>' . (int)$quizRow['creator_id'] . '</creator_id>';
        $xmlFragment .= '<description>' . xmlencode(ajax_format_string((string)$quizRow['description'])) . '</description>';
        $xmlFragment .= '<play_limit>' . (int)$quizRow['play_limit'] . '</play_limit>';
        $xmlFragment .= '<questions_per_session>' . (int)$questionsData . '</questions_per_session>';
        $xmlFragment .= '<seconds_per_question>' . (int)$quizRow['seconds_per_question'] . '</seconds_per_question>';
        $xmlFragment .= '<show_answers>' . (int)$quizRow['show_answers'] . '</show_answers>';
        $xmlFragment .= '<image>' . xmlencode((string)$quizRow['image']) . '</image>';
    }
    $xmlFragment .= '</quizDetail>';

    if ($quizRow !== null && (empty($timesPlayed) || (int)$quizRow['play_limit'] > (int)$timesPlayed)) {
        $xmlFragment .= '<quizResults>';
        $statsResult = $smcFunc['db_query']('', '
            SELECT IFNULL(SUM(QR.questions),0) AS total_questions,
                IFNULL(SUM(QR.correct),0) AS total_correct,
                IFNULL(SUM(QR.incorrect),0) AS total_incorrect,
                IFNULL(SUM(QR.timeouts),0) AS total_timeouts,
                IFNULL(SUM(QR.total_seconds),0) AS total_seconds
            FROM {db_prefix}quiz_result QR
            WHERE QR.id_user = {int:id_user} AND QR.id_quiz = {int:id_quiz}',
            ['id_user' => $idUser, 'id_quiz' => $idQuiz]
        );
        while ($statsRow = $smcFunc['db_fetch_assoc']($statsResult)) {
            $xmlFragment .= '<total_questions>' . (int)$statsRow['total_questions'] . '</total_questions>';
            $xmlFragment .= '<total_correct>' . (int)$statsRow['total_correct'] . '</total_correct>';
            $xmlFragment .= '<total_incorrect>' . (int)$statsRow['total_incorrect'] . '</total_incorrect>';
            $xmlFragment .= '<total_timeouts>' . (int)$statsRow['total_timeouts'] . '</total_timeouts>';
            $xmlFragment .= '<total_seconds>' . (int)$statsRow['total_seconds'] . '</total_seconds>';
        }
        $smcFunc['db_free_result']($statsResult);
        $xmlFragment .= '</quizResults>';
    }

    return $xmlFragment;
}

/**
 * Build an XML fragment containing quiz league details.
 *
 * @param int $idQuizLeague Quiz league ID
 * @param int $idUser User ID
 * @param string $idSession New session token
 * @param bool $debugOn Unused; kept for legacy compatibility
 * @return string XML fragment
 */
function GetQuizLeagueDetails(int $idQuizLeague, int $idUser, string $idSession, bool $debugOn): string
{
    global $smcFunc;

    $leagueResult = $smcFunc['db_query']('', '
        SELECT title, description, day_interval, question_plays, questions_per_session,
            seconds_per_question, points_for_correct, show_answers, current_round
        FROM {db_prefix}quiz_league QL
        WHERE id_quiz_league = {int:id_quiz_league} AND state = 1',
        ['id_user' => $idUser, 'id_quiz_league' => $idQuizLeague]
    );
    $leagueRow = $smcFunc['db_fetch_assoc']($leagueResult);

    $timesPlayed = 0;
    if ($leagueRow !== null) {
        $playsResult = $smcFunc['db_query']('', '
            SELECT COUNT(*) AS user_plays FROM {db_prefix}quiz_league_result
            WHERE id_quiz_league = {int:id_quiz_league}
                AND id_user = {int:id_user}
                AND round = {int:current_round}',
            ['id_user' => $idUser, 'id_quiz_league' => $idQuizLeague, 'current_round' => (int)$leagueRow['current_round']]
        );
        $playsRow = $smcFunc['db_fetch_row']($playsResult);
        $timesPlayed = $playsRow !== false ? (int)$playsRow[0] : 0;
        $smcFunc['db_free_result']($playsResult);
    }
    $smcFunc['db_free_result']($leagueResult);

    $xmlFragment = '<leagueDetail>';
    if ($leagueRow !== null && empty($timesPlayed)) {
        $xmlFragment .= '<title>' . xmlencode(ajax_format_string((string)$leagueRow['title'])) . '</title>';
        $xmlFragment .= '<id_session>' . $idSession . '</id_session>';
        $xmlFragment .= '<description>' . xmlencode(ajax_format_string((string)$leagueRow['description'])) . '</description>';
        $xmlFragment .= '<day_interval>' . (int)$leagueRow['day_interval'] . '</day_interval>';
        $xmlFragment .= '<question_plays>' . (int)$leagueRow['question_plays'] . '</question_plays>';
        $xmlFragment .= '<questions_per_session>' . (int)$leagueRow['questions_per_session'] . '</questions_per_session>';
        $xmlFragment .= '<seconds_per_question>' . (int)$leagueRow['seconds_per_question'] . '</seconds_per_question>';
        $xmlFragment .= '<points_for_correct>' . (int)$leagueRow['points_for_correct'] . '</points_for_correct>';
        $xmlFragment .= '<show_answers>' . (int)$leagueRow['show_answers'] . '</show_answers>';
        $xmlFragment .= '<current_round>' . (int)$leagueRow['current_round'] . '</current_round>';
        $xmlFragment .= '<image></image>';
    }
    $xmlFragment .= '</leagueDetail><leagueResults></leagueResults>';

    return $xmlFragment;
}

/**
 * Create a new quiz session row in the database.
 *
 * @param string $idSession Session token
 * @param int $idUser User ID
 * @param int|null $idQuiz Quiz ID (null for leagues)
 * @param int|null $idQuizLeague League ID (null for standard quizzes)
 * @return void
 */
function InsertQuizSession(string $idSession, int $idUser, ?int $idQuiz, ?int $idQuizLeague): void
{
    global $smcFunc;

    $idQuizLeague = $idQuizLeague ?? 0;
    $idQuiz       = $idQuiz ?? 0;

    if ($idQuizLeague === 0 && $idQuiz === 0) {
        return;
    }

    $smcFunc['db_insert']('',
        '{db_prefix}quiz_session',
        [
            'id_quiz_session'     => 'string-38',
            'id_user'             => 'int',
            'session_start'       => 'int',
            'last_question_start' => 'int',
            'id_quiz_league'      => 'int',
            'question_count'      => 'int',
            'id_quiz'             => 'int',
            'correct'             => 'int',
            'incorrect'           => 'int',
            'timeouts'            => 'int',
        ],
        [$idSession, $idUser, time(), time(), $idQuizLeague, 0, $idQuiz, 0, 0, 0],
        ['id_quiz_session']
    );
}

/**
 * Format a raw database string for AJAX/XML responses.
 *
 * @param string $stringToFormat Raw string from the database
 * @return string Decoded UTF-8 string
 */
function ajax_format_string(string $stringToFormat): string
{
    global $smcFunc;

    $returnString = str_replace('\\', '', $smcFunc['db_unescape_string']($stringToFormat));

    return html_entity_decode($returnString, ENT_QUOTES, 'UTF-8');
}