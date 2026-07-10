<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Exports selected quizzes as an XML package.
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
 * Builds and outputs the quiz XML package.
 */
function PackageQuiz(): void
{
    global $context, $modSettings;

    $quizIds = (string) ($_GET['quizIds'] ?? '');
    if ($quizIds === '') {
        return;
    }

    $quizKeys = array_values(array_unique(array_filter(
        array_map(
            static function (string $id): int {
                return (int) trim($id);
            },
            explode(',', $quizIds)
        ),
        static function (int $id): bool {
            return $id > 0;
        }
    )));

    if (count($quizKeys) === 0) {
        return;
    }

    $packageNameValue = (string) ($_GET['packageName'] ?? 'NoNameEntered');
    $packageDescription = (string) ($_GET['packageDescription'] ?? '');
    $packageAuthor = (string) ($_GET['packageAuthor'] ?? '');
    $packageSiteAddress = (string) ($_GET['packageSiteAddress'] ?? '');

    $packageName = ($packageNameValue !== '' ? $packageNameValue : 'NoNameEntered') . '.xml';
    $packageDescription = $packageDescription !== '' ? $packageDescription : 'No description entered';
    $packageAuthor = $packageAuthor !== '' ? $packageAuthor : 'No author entered';
    $packageSiteAddress = $packageSiteAddress !== '' ? $packageSiteAddress : 'No site entered';

    $quizRows = ExportQuizes($quizKeys);

    header('Content-Disposition: attachment; filename="' . $packageName . '"');
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    echo '<?xml version="1.0" encoding="ISO-8859-1"?>';
    echo '<quizes>
            <description>', $packageDescription, '</description>
            <author>', $packageAuthor, '</author>
            <siteAddress>', $packageSiteAddress, '</siteAddress>
            <packageDate>', date('F j, Y, g:i a'), '</packageDate>
            <smfQuizVersion>', (string) ($modSettings['SMFQuiz_version'] ?? ''), '</smfQuizVersion>
            <smfVersion>', (string) ($modSettings['smfVersion'] ?? ''), '</smfVersion>
    ';

    foreach ($quizRows as $row) {
        echo "
            <quiz>
                <title><![CDATA[{$row['title']}]]></title>
                <categoryName><![CDATA[{$row['category_name']}]]></categoryName>
                <description><![CDATA[{$row['description']}]]></description>
                <playLimit>{$row['play_limit']}</playLimit>
                <secondsPerQuestion>{$row['seconds_per_question']}</secondsPerQuestion>
                <showAnswers>{$row['show_answers']}</showAnswers>
                <image><![CDATA[{$row['image']}]]></image>
                <imageData><![CDATA[{$row['image_data']}]]></imageData>
                <questions>
        ";

        $quizQuestionRows = ExportQuizQuestions((int) $row['id_quiz']);

        foreach ($quizQuestionRows as $questionRow) {
            echo "
                    <question>
                        <questionText><![CDATA[{$questionRow['question_text']}]]></questionText>
                        <questionTypeId>{$questionRow['id_question_type']}</questionTypeId>
                        <image>{$questionRow['image']}</image>
                        <imageData>{$questionRow['image_data']}</imageData>
                        <answerText><![CDATA[{$questionRow['answer_text']}]]></answerText>
                        <answers>
            ";

            $quizAnswerRows = ExportQuizAnswers((int) $questionRow['id_question']);

            foreach ($quizAnswerRows as $answerRow) {
                echo "
                            <answer>
                                <answerText><![CDATA[{$answerRow['answer_text']}]]></answerText>
                                <isCorrect>{$answerRow['is_correct']}</isCorrect>
                            </answer>
                ";
            }

            echo "
                        </answers>
                    </question>
            ";
        }

        echo "
                </questions>
            </quiz>
        ";
    }

    echo '</quizes>';
}
