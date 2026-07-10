<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Entry point for the quiz export action.
 *
 * Requires quiz_admin permission and streams an XML file for download.
 *
 * @return void
 */
function quizExport(): void
{
    global $sourcedir;

    isAllowedTo('quiz_admin');
    require_once($sourcedir . '/Quiz/Db.php');

    PackageQuiz();
    die();
}

/**
 * Build and stream an XML export of the selected quizzes.
 *
 * Quiz IDs are supplied as a comma-separated list in $_GET['quizIds'].
 *
 * @return void
 */
function PackageQuiz(): void
{
    global $modSettings;

    if (empty($_GET['quizIds'])) {
        return;
    }

    $quizKeys = array_unique(array_filter(
        array_map(
            static fn(string $id): int => (int)$id,
            explode(',', (string)$_GET['quizIds'])
        ),
        static fn(int $id): bool => $id > 0
    ));

    if (empty($quizKeys)) {
        return;
    }

    $packageName        = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)($_GET['packageName'] ?? 'NoNameEntered')) . '.xml';
    $packageDescription = trim((string)($_GET['packageDescription'] ?? '')) ?: 'No description entered';
    $packageAuthor      = trim((string)($_GET['packageAuthor'] ?? '')) ?: 'No author entered';
    $packageSiteAddress = trim((string)($_GET['packageSiteAddress'] ?? '')) ?: 'No site entered';

    $quizRows = ExportQuizes($quizKeys);

    header('Content-Disposition: attachment; filename="' . $packageName . '"');
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<quizes>' . "\n";
    echo '  <description><![CDATA[' . $packageDescription . ']]></description>' . "\n";
    echo '  <author><![CDATA[' . $packageAuthor . ']]></author>' . "\n";
    echo '  <siteAddress><![CDATA[' . $packageSiteAddress . ']]></siteAddress>' . "\n";
    echo '  <packageDate>' . date('Y-m-d H:i:s') . '</packageDate>' . "\n";
    echo '  <smfQuizVersion>' . htmlspecialchars((string)($modSettings['SMFQuiz_version'] ?? ''), ENT_XML1, 'UTF-8') . '</smfQuizVersion>' . "\n";
    echo '  <smfVersion>' . htmlspecialchars((string)($modSettings['smfVersion'] ?? ''), ENT_XML1, 'UTF-8') . '</smfVersion>' . "\n";

    foreach ($quizRows as $row) {
        echo '  <quiz>' . "\n";
        echo '    <title><![CDATA[' . $row['title'] . ']]></title>' . "\n";
        echo '    <categoryName><![CDATA[' . $row['category_name'] . ']]></categoryName>' . "\n";
        echo '    <description><![CDATA[' . $row['description'] . ']]></description>' . "\n";
        echo '    <playLimit>' . (int)$row['play_limit'] . '</playLimit>' . "\n";
        echo '    <secondsPerQuestion>' . (int)$row['seconds_per_question'] . '</secondsPerQuestion>' . "\n";
        echo '    <showAnswers>' . (int)$row['show_answers'] . '</showAnswers>' . "\n";
        echo '    <image><![CDATA[' . $row['image'] . ']]></image>' . "\n";
        echo '    <imageData><![CDATA[' . $row['image_data'] . ']]></imageData>' . "\n";
        echo '    <questions>' . "\n";

        foreach (ExportQuizQuestions((int)$row['id_quiz']) as $questionRow) {
            echo '      <question>' . "\n";
            echo '        <questionText><![CDATA[' . $questionRow['question_text'] . ']]></questionText>' . "\n";
            echo '        <questionTypeId>' . (int)$questionRow['id_question_type'] . '</questionTypeId>' . "\n";
            echo '        <image>' . htmlspecialchars((string)$questionRow['image'], ENT_XML1, 'UTF-8') . '</image>' . "\n";
            echo '        <imageData>' . htmlspecialchars((string)$questionRow['image_data'], ENT_XML1, 'UTF-8') . '</imageData>' . "\n";
            echo '        <answerText><![CDATA[' . $questionRow['answer_text'] . ']]></answerText>' . "\n";
            echo '        <answers>' . "\n";

            foreach (ExportQuizAnswers((int)$questionRow['id_question']) as $answerRow) {
                echo '          <answer>' . "\n";
                echo '            <answerText><![CDATA[' . $answerRow['answer_text'] . ']]></answerText>' . "\n";
                echo '            <isCorrect>' . (int)$answerRow['is_correct'] . '</isCorrect>' . "\n";
                echo '          </answer>' . "\n";
            }

            echo '        </answers>' . "\n";
            echo '      </question>' . "\n";
        }

        echo '    </questions>' . "\n";
        echo '  </quiz>' . "\n";
    }

    echo '</quizes>' . "\n";
}