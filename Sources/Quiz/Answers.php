<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Updates the current quiz session counters and returns an XML response.
 */
function UpdateSession(): void
{
    global $smcFunc, $db_prefix, $context;

    if (!allowedTo('quiz_play')) {
        $context['quiz_error'] = 'cannot_play';
        die();
    }

    $idSession = (string) ($_GET['id_session'] ?? '');
    $isCorrectRaw = (string) ($_GET['is_correct'] ?? '');
    $timeSpent = (int) ($_GET['time'] ?? 0);

    if ($idSession !== '' && $isCorrectRaw !== '' && isset($_GET['time'])) {
        $answer = [-1 => 'timeouts', 0 => 'incorrect', 1 => 'correct'];
        $isCorrectKey = is_numeric($isCorrectRaw) ? (int) $isCorrectRaw : -1;

        $queryParam = [
            'id_session' => $idSession,
            'is_correct' => $answer[$isCorrectKey] ?? 'timeouts',
            'time' => $timeSpent,
            'last_question_start' => time(),
        ];

        $smcFunc['db_query']('', '
            UPDATE {db_prefix}quiz_session
            SET
                question_count = question_count + 1,
                last_question_start = {int:last_question_start},
                {raw:is_correct} = {raw:is_correct} + 1,
                total_seconds = {int:time}
            WHERE id_quiz_session = {string:id_session}',
            $queryParam
        );
    }

    header('Content-Type: text/xml');
    echo '<xml/>';
    die();
}
