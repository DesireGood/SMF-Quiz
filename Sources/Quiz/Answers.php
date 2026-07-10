<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Update a quiz play session after each answered question.
 *
 * Called by the SMFQuizAnswers action during quiz play.
 * Writes the answer outcome to the session row and returns a minimal XML response.
 *
 * @return void
 */
function UpdateSession(): void
{
    global $smcFunc;

    if (!allowedTo('quiz_play')) {
        header('Content-Type: text/xml');
        echo '<xml/>';
        die();
    }

    if (!isset($_GET['id_session'], $_GET['is_correct'], $_GET['time'])) {
        header('Content-Type: text/xml');
        echo '<xml/>';
        die();
    }

    $answerMap = [-1 => 'timeouts', 0 => 'incorrect', 1 => 'correct'];
    $isCorrectRaw = (int)$_GET['is_correct'];
    $outcomeField = $answerMap[$isCorrectRaw] ?? 'timeouts';

    $idSession = preg_replace('/[^0-9a-f]/i', '', (string)$_GET['id_session']);
    $time      = max(0, (int)$_GET['time']);

    $smcFunc['db_query']('', '
        UPDATE {db_prefix}quiz_session
        SET
            question_count = question_count + 1,
            last_question_start = {int:last_question_start},
            {raw:outcome} = {raw:outcome} + 1,
            total_seconds = {int:time}
        WHERE id_quiz_session = {string:id_session}',
        [
            'id_session'          => $idSession,
            'outcome'             => $outcomeField,
            'time'                => $time,
            'last_question_start' => time(),
        ]
    );

    header('Content-Type: text/xml');
    echo '<xml/>';
    die();
}