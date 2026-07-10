<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

require_once(__DIR__ . '/Utils.php');

/**
 * Return the next question and its answers during quiz play.
 *
 * Called by the SMFQuizQuestions action.
 *
 * @return void
 */
function quizQuestions(): void
{
    global $context;

    if (!allowedTo('quiz_play')) {
        header('Content-Type: text/xml');
        echo '<xml/>';
        die();
    }

    $idQuizLeague  = max(0, (int)($_GET['id_quiz_league'] ?? 0));
    $idQuiz        = max(0, (int)($_GET['id_quiz'] ?? 0));
    $idSession     = quiz_sanitize_session_token((string)($_GET['id_session'] ?? ''));
    $questionNum   = max(0, (int)($_GET['questionNum'] ?? 0));
    $updateResumes = !empty($_GET['updateResumes']);

    UpdateSession($idSession, $updateResumes);

    if ($idQuiz !== 0) {
        GetQuizQuestion($idQuiz, $questionNum, false);
    } else {
        GetQuizLeagueQuestion($idQuizLeague, false);
    }

    die();
}

/**
 * Encode a string for safe inclusion in XML output.
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
 * Fetch and stream a single quiz question with its answers as XML.
 *
 * @param int $idQuiz Quiz ID
 * @param int $questionNum Zero-based question index (LIMIT offset)
 * @param bool $debugOn When true, suppresses output (kept for legacy compatibility)
 * @return void
 */
function GetQuizQuestion(int $idQuiz, int $questionNum, bool $debugOn): void
{
    global $smcFunc, $settings;

    $questionResult = $smcFunc['db_query']('', '
        SELECT Q.id_question, Q.question_text, Q.id_question_type,
            Q.answer_text, Q.image
        FROM {db_prefix}quiz_question Q
        WHERE Q.id_quiz = {int:id_quiz}
        LIMIT {int:questionNum}, 1',
        ['id_quiz' => $idQuiz, 'questionNum' => $questionNum]
    );

    $xmlFragment  = '<smfQuiz><question>';
    $idQuestion   = 0;

    if ($questionRow = $smcFunc['db_fetch_assoc']($questionResult)) {
        $idQuestion    = (int)$questionRow['id_question'];
        $imageUrl      = '';
        if (!empty($questionRow['image'])) {
            $imageUrl = $settings['default_images_url'] . '/quiz_images/Questions/' . $questionRow['image'];
        }

        $xmlFragment .= '<id_question>' . $idQuestion . '</id_question>';
        $xmlFragment .= '<question_text>' . xmlencode(format_string((string)$questionRow['question_text'])) . '</question_text>';
        $xmlFragment .= '<id_question_type>' . (int)$questionRow['id_question_type'] . '</id_question_type>';
        $xmlFragment .= '<questionanswer_text>' . xmlencode(format_string((string)$questionRow['answer_text'])) . '</questionanswer_text>';
        $xmlFragment .= '<image>' . xmlencode($imageUrl) . '</image>';
    }
    $smcFunc['db_free_result']($questionResult);

    $xmlFragment .= '</question><answers>';

    if ($idQuestion > 0) {
        $answerResult = $smcFunc['db_query']('', '
            SELECT A.id_answer, A.answer_text, A.is_correct
            FROM {db_prefix}quiz_answer A
            WHERE id_question = {int:id_question}
            ORDER BY RAND()',
            ['id_question' => $idQuestion]
        );

        while ($answerRow = $smcFunc['db_fetch_assoc']($answerResult)) {
            $xmlFragment .= '<answer>'
                . '<id_answer>' . (int)$answerRow['id_answer'] . '</id_answer>'
                . '<answer_text>' . xmlencode(format_string((string)$answerRow['answer_text'])) . '</answer_text>'
                . '<is_correct>' . (int)$answerRow['is_correct'] . '</is_correct>'
                . '</answer>';
        }
        $smcFunc['db_free_result']($answerResult);

        $smcFunc['db_query']('', '
            UPDATE {db_prefix}quiz_question SET plays = plays + 1 WHERE id_question = {int:id_question}',
            ['id_question' => $idQuestion]
        );
    }

    $xmlFragment .= '</answers></smfQuiz>';

    if (!$debugOn) {
        header('Content-Type: text/xml');
        echo $xmlFragment;
    }
}

/**
 * Fetch and stream a single quiz league question with its answers as XML.
 *
 * @param int $idQuizLeague Quiz league ID
 * @param bool $debugOn When true, suppresses output (kept for legacy compatibility)
 * @return void
 */
function GetQuizLeagueQuestion(int $idQuizLeague, bool $debugOn): void
{
    global $smcFunc, $settings;

    $result = $smcFunc['db_query']('', '
        SELECT categories FROM {db_prefix}quiz_league WHERE id_quiz_league = {int:id_quiz_league}',
        ['id_quiz_league' => $idQuizLeague]
    );

    $categories = null;
    if ($row = $smcFunc['db_fetch_assoc']($result)) {
        $categories = $row['categories'] !== null ? (string)$row['categories'] : null;
    }
    $smcFunc['db_free_result']($result);

    $whereCategories = '';
    if ($categories !== null) {
        $whereCategories = $smcFunc['db_quote'](
            'WHERE Q.id_category IN ({string:categories})',
            ['categories' => $categories]
        );
    }

    $questionResult = $smcFunc['db_query']('', '
        SELECT QQ.id_question, QQ.question_text, QQ.id_question_type,
            QQ.answer_text, QQ.image, QQ.id_quiz, Q.Title
        FROM {db_prefix}quiz_question QQ
        INNER JOIN {db_prefix}quiz Q ON QQ.id_quiz = Q.id_quiz
        {raw:where_categories}
        ORDER BY RAND()
        LIMIT 1',
        ['where_categories' => $whereCategories]
    );

    $xmlFragment = '<smfQuiz><question>';
    $idQuestion  = 0;

    if ($questionRow = $smcFunc['db_fetch_assoc']($questionResult)) {
        $idQuestion = (int)$questionRow['id_question'];
        $imageUrl   = '';
        if (!empty($questionRow['image'])) {
            $imageUrl = $settings['default_images_url'] . '/quiz_images/Questions/' . $questionRow['image'];
        }

        $xmlFragment .= '<id_question>' . $idQuestion . '</id_question>';
        $xmlFragment .= '<question_text>' . xmlencode(format_string((string)$questionRow['question_text'])) . '</question_text>';
        $xmlFragment .= '<id_question_type>' . (int)$questionRow['id_question_type'] . '</id_question_type>';
        $xmlFragment .= '<questionanswer_text>' . xmlencode(format_string((string)$questionRow['answer_text'])) . '</questionanswer_text>';
        $xmlFragment .= '<image>' . xmlencode($imageUrl) . '</image>';
        $xmlFragment .= '<quizTitle>' . xmlencode(format_string((string)$questionRow['Title'])) . '</quizTitle>';
    }
    $smcFunc['db_free_result']($questionResult);

    $xmlFragment .= '</question><answers>';

    if ($idQuestion > 0) {
        $answerResult = $smcFunc['db_query']('', '
            SELECT A.id_answer, A.answer_text, A.is_correct
            FROM {db_prefix}quiz_answer A
            WHERE id_question = {int:id_question}',
            ['id_question' => $idQuestion]
        );

        while ($answerRow = $smcFunc['db_fetch_assoc']($answerResult)) {
            $xmlFragment .= '<answer>'
                . '<id_answer>' . (int)$answerRow['id_answer'] . '</id_answer>'
                . '<answer_text>' . xmlencode(format_string((string)$answerRow['answer_text'])) . '</answer_text>'
                . '<is_correct>' . (int)$answerRow['is_correct'] . '</is_correct>'
                . '</answer>';
        }
        $smcFunc['db_free_result']($answerResult);

        $smcFunc['db_query']('', '
            UPDATE {db_prefix}quiz_question SET plays = plays + 1 WHERE id_question = {int:id_question}',
            ['id_question' => $idQuestion]
        );

        $smcFunc['db_query']('', '
            UPDATE {db_prefix}quiz_league SET question_plays = question_plays + 1 WHERE id_quiz_league = {int:id_quiz_league}',
            ['id_quiz_league' => $idQuizLeague]
        );
    }

    $xmlFragment .= '</answers></smfQuiz>';

    if (!$debugOn) {
        header('Content-Type: text/xml');
        echo $xmlFragment;
    }
}

/**
 * Format a raw database string for XML output.
 *
 * Strips escape slashes, converts newlines to XML line-break entities,
 * and decodes HTML entities to UTF-8.
 *
 * @param string $stringToFormat Raw string from DB
 * @return string Formatted string
 */
function format_string(string $stringToFormat): string
{
    global $smcFunc;

    $returnString = str_replace('\\', '', $smcFunc['db_unescape_string']($stringToFormat));
    $returnString = str_replace(chr(10), '&lt;br/&gt;', $returnString);

    return html_entity_decode($returnString, ENT_QUOTES, 'UTF-8');
}

/**
 * Update the quiz session timestamp (and optionally the resume count).
 *
 * @param string $idSession Session token
 * @param bool $updateResumes Whether to increment total_resumes
 * @return void
 */
function UpdateSession(string $idSession, bool $updateResumes = false): void
{
    global $smcFunc;

    $resumePart = $updateResumes ? ', total_resumes = total_resumes + 1' : '';

    $smcFunc['db_query']('', '
        UPDATE {db_prefix}quiz_session
        SET last_question_start = {int:last_question_start}' . $resumePart . '
        WHERE id_quiz_session = {string:id_session}',
        [
            'last_question_start' => time(),
            'id_session'          => $idSession,
        ]
    );
}
