<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Creates or responds to a quiz dispute request and returns XML.
 */
function quizDispute(): void
{
    global $smcFunc, $context, $user_settings, $sourcedir;

    $idQuizQuestion = (int) ($_GET['id_quiz_question'] ?? 0);
    $idQuiz = (int) ($_GET['id_quiz'] ?? 0);
    $rawReason = (string) ($_GET['reason'] ?? '');
    $reason = $rawReason !== '' ? $smcFunc['htmlspecialchars']($rawReason, ENT_QUOTES) : '';
    $idUser = (int) ($context['user']['id'] ?? 0);
    $idDispute = (int) ($_GET['id_dispute'] ?? 0);

    if ($idDispute !== 0) {
        require_once($sourcedir . '/Subs-Post.php');

        $remove = (int) ($_GET['remove'] ?? 0);
        $result = $smcFunc['db_query']('', '
            SELECT QD.id_user, Q.title, M.real_name,
                QQ.question_text, QD.reason, QD.updated
            FROM {db_prefix}quiz_dispute QD
            INNER JOIN {db_prefix}quiz Q
                ON QD.id_quiz = Q.id_quiz
            INNER JOIN {db_prefix}members M
                ON QD.id_user = M.id_member
            INNER JOIN {db_prefix}quiz_question QQ
                ON QD.id_quiz_question = QQ.id_question
            WHERE id_quiz_dispute = {int:id_quiz_dispute}',
            [
                'id_quiz_dispute' => $idDispute,
            ]
        );

        while (($row = $smcFunc['db_fetch_assoc']($result)) !== false) {
            $pmto = [
                'to' => [],
                'bcc' => [$row['id_user']],
            ];

            $subject = 'Quiz Dispute Response #' . $idDispute;
            $message = 'Your dispute [b]'
                . html_entity_decode((string) $row['reason'], ENT_QUOTES, 'UTF-8')
                . '[/b] against the question [b]'
                . (string) $row['question_text']
                . '[/b] in the quiz [b]'
                . (string) $row['title']
                . '[/b] has had the following response from the Quiz Administrator:'
                . "\n\n[i]"
                . html_entity_decode($reason, ENT_QUOTES, 'UTF-8')
                . '[/i]';

            if ($remove === 1) {
                $message .= "\n\nThis dispute has now been removed";
            }

            $pmfrom = [
                'id' => (int) ($user_settings['id_member'] ?? 0),
                'name' => (string) ($user_settings['real_name'] ?? ''),
                'username' => (string) ($user_settings['member_name'] ?? ''),
            ];

            sendpm($pmto, $subject, $message, 0, $pmfrom);
        }
        $smcFunc['db_free_result']($result);

        if ($remove !== 0) {
            $smcFunc['db_query']('', '
                DELETE
                FROM {db_prefix}quiz_dispute
                WHERE id_quiz_dispute = {int:id_quiz_dispute}',
                [
                    'id_quiz_dispute' => $idDispute,
                ]
            );
        }
    } elseif ($reason !== '' && $idQuizQuestion !== 0) {
        $smcFunc['db_insert']('insert',
            '{db_prefix}quiz_dispute',
            [
                'id_quiz_question' => 'int',
                'id_quiz' => 'int',
                'id_user' => 'int',
                'reason' => 'string',
                'updated' => 'int',
            ],
            [
                $idQuizQuestion,
                $idQuiz,
                $idUser,
                $reason,
                time(),
            ],
            ['id_quiz_dispute']
        );
    }

    header('Content-Type: text/xml');
    echo '<xml/>';
    die();
}
