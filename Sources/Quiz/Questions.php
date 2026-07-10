<?php
declare(strict_types=1);

if (!defined('SMF')) {
	die('Hacking attempt...');
}

/**
 * Returns quiz question XML for the current request.
 */
function quizQuestions(): void
{
	global $context;

	if (!allowedTo('quiz_play')) {
		$context['quiz_error'] = 'cannot_play';
		die();
	}

	$id_quiz_league = (int) ($_GET['id_quiz_league'] ?? 0);
	$id_quiz = (int) ($_GET['id_quiz'] ?? 0);
	$id_session = (string) ($_GET['id_session'] ?? '');
	$debugOn = isset($_GET['debugOn']);
	$questionNum = (int) ($_GET['questionNum'] ?? 0);
	$updateResumes = (bool) ($_GET['updateResumes'] ?? false);

	UpdateSession($id_session, $updateResumes);

	if ($id_quiz !== 0) {
		GetQuizQuestion($id_quiz, $questionNum, $debugOn);
		die();
	}

	GetQuizLeagueQuestion($id_quiz_league, $debugOn);
	die();
}

/**
 * Encodes special XML characters.
 *
 * @param string $txt Text to encode.
 * @return string
 */
function xmlencode(string $txt): string
{
	$txt = str_replace('&', '&amp;', $txt);
	$txt = str_replace('<', '&lt;', $txt);
	$txt = str_replace('>', '&gt;', $txt);
	$txt = str_replace("'", '&apos;', $txt);

	return str_replace('"', '&quot;', $txt);
}

/**
 * Outputs XML for a quiz question and answers.
 *
 * @param int $id_quiz Quiz ID.
 * @param int $questionNum Zero-based question offset.
 * @param bool $debugOn Debug flag.
 */
function GetQuizQuestion(int $id_quiz, int $questionNum, bool $debugOn): void
{
	global $smcFunc, $settings;

	$questionQuery = '';
	$questionResult = $smcFunc['db_query']('', '
		SELECT Q.id_question, Q.question_text, Q.id_question_type,
			Q.answer_text, Q.image
		FROM {db_prefix}quiz_question Q
		WHERE Q.id_quiz = {int:id_quiz}
		LIMIT {int:questionNum}, 1',
		[
			'id_quiz' => $id_quiz,
			'questionNum' => $questionNum,
		]
	);

	$xmlFragment = '
		<smfQuiz>
			<question>
	';

	$id_question = 0;
	while ($questionRow = $smcFunc['db_fetch_assoc']($questionResult)) {
		$id_question = (int) $questionRow['id_question'];
		$xmlFragment .= '<id_question>' . $id_question . '</id_question>';
		$xmlFragment .= '<question_text>' . xmlencode(format_string((string) $questionRow['question_text'])) . '</question_text>';
		$xmlFragment .= '<id_question_type>' . $questionRow['id_question_type'] . '</id_question_type>';
		$xmlFragment .= '<questionanswer_text>' . xmlencode(format_string((string) $questionRow['answer_text'])) . '</questionanswer_text>';
		$xmlFragment .= '<image>';
		if (!empty($questionRow['image'])) {
			$xmlFragment .= $settings['default_images_url'] . '/quiz_images/Questions/' . $questionRow['image'];
		}
		$xmlFragment .= '</image>';
	}
	$smcFunc['db_free_result']($questionResult);

	$xmlFragment .= '
			</question>
			<answers>
	';

	$answerResult = $smcFunc['db_query']('', '
		SELECT A.id_answer, A.answer_text, A.is_correct
		FROM {db_prefix}quiz_answer A
		WHERE id_question = {int:id_question}
		ORDER BY RAND()',
		[
			'id_question' => $id_question,
		]
	);

	while ($answerRow = $smcFunc['db_fetch_assoc']($answerResult)) {
		$xmlFragment .= '<answer>';
		$xmlFragment .= '<id_answer>' . $answerRow['id_answer'] . '</id_answer>';
		$xmlFragment .= '<answer_text>' . xmlencode(format_string((string) $answerRow['answer_text'])) . '</answer_text>';
		$xmlFragment .= '<is_correct>' . $answerRow['is_correct'] . '</is_correct>';
		$xmlFragment .= '</answer>';
	}
	$smcFunc['db_free_result']($answerResult);

	$xmlFragment .= '
			</answers>
		</smfQuiz>
	';

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}quiz_question
		SET plays = plays + 1
		WHERE id_question = {int:id_question}',
		[
			'id_question' => $id_question,
		]
	);

	if ($debugOn === true) {
		echo $questionQuery;
		return;
	}

	header('Content-Type: text/xml');
	echo $xmlFragment;
}

/**
 * Outputs XML for a quiz league question and answers.
 *
 * @param int $id_quiz_league Quiz league ID.
 * @param bool $debugOn Debug flag.
 */
function GetQuizLeagueQuestion(int $id_quiz_league, bool $debugOn): void
{
	global $smcFunc, $settings;

	$questionQuery = '';
	$result = $smcFunc['db_query']('', '
		SELECT categories
		FROM {db_prefix}quiz_league
		WHERE id_quiz_league = {int:id_quiz_league}',
		[
			'id_quiz_league' => $id_quiz_league,
		]
	);

	$categories = null;
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		if ($row['categories'] !== null) {
			$categories = $row['categories'];
		}
	}
	$smcFunc['db_free_result']($result);

	$whereCategories = $categories === null
		? ''
		: $smcFunc['db_quote']('WHERE Q.id_category IN ({string:categories})', ['categories' => $categories]);

	$questionResult = $smcFunc['db_query']('', '
		SELECT QQ.id_question, QQ.question_text, QQ.id_question_type,
			QQ.answer_text, QQ.image, QQ.id_quiz, Q.Title
		FROM {db_prefix}quiz_question QQ
		INNER JOIN {db_prefix}quiz Q
			ON QQ.id_quiz = Q.id_quiz
		{raw:where_categories}
		ORDER BY RAND()
		LIMIT 1',
		[
			'where_categories' => $whereCategories,
		]
	);

	$xmlFragment = '
		<smfQuiz>
			<question>
	';

	$id_question = 0;
	while ($questionRow = $smcFunc['db_fetch_assoc']($questionResult)) {
		$id_question = (int) $questionRow['id_question'];
		$xmlFragment .= '<id_question>' . $id_question . '</id_question>';
		$xmlFragment .= '<question_text>' . xmlencode(format_string((string) $questionRow['question_text'])) . '</question_text>';
		$xmlFragment .= '<id_question_type>' . $questionRow['id_question_type'] . '</id_question_type>';
		$xmlFragment .= '<questionanswer_text>' . xmlencode(format_string((string) $questionRow['answer_text'])) . '</questionanswer_text>';
		$xmlFragment .= '<image>';
		if (!empty($questionRow['image'])) {
			$xmlFragment .= $settings['default_images_url'] . '/quiz_images/Questions/' . $questionRow['image'];
		}
		$xmlFragment .= '</image>';
		$xmlFragment .= '<quizTitle>' . xmlencode(format_string((string) $questionRow['Title'])) . '</quizTitle>';
	}
	$smcFunc['db_free_result']($questionResult);

	$xmlFragment .= '
			</question>
			<answers>
	';

	$answerResult = $smcFunc['db_query']('', '
		SELECT A.id_answer, A.answer_text, A.is_correct
		FROM {db_prefix}quiz_answer A
		WHERE id_question = {int:id_question}',
		[
			'id_question' => $id_question,
		]
	);

	while ($answerRow = $smcFunc['db_fetch_assoc']($answerResult)) {
		$xmlFragment .= '<answer>';
		$xmlFragment .= '<id_answer>' . $answerRow['id_answer'] . '</id_answer>';
		$xmlFragment .= '<answer_text>' . xmlencode(format_string((string) $answerRow['answer_text'])) . '</answer_text>';
		$xmlFragment .= '<is_correct>' . $answerRow['is_correct'] . '</is_correct>';
		$xmlFragment .= '</answer>';
	}
	$smcFunc['db_free_result']($answerResult);

	$xmlFragment .= '
			</answers>
		</smfQuiz>
	';

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}quiz_question
		SET plays = plays + 1
		WHERE id_question = {int:id_question}',
		[
			'id_question' => $id_question,
		]
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}quiz_league
		SET question_plays = question_plays + 1
		WHERE id_quiz_league = {int:id_quiz_league}',
		[
			'id_quiz_league' => $id_quiz_league,
		]
	);

	if ($debugOn === true) {
		echo $questionQuery;
		return;
	}

	header('Content-Type: text/xml');
	echo $xmlFragment;
}

/**
 * Normalizes a string for XML output.
 *
 * @param string $stringToFormat Source string.
 * @return string
 */
function format_string(string $stringToFormat): string
{
	global $smcFunc;

	$returnString = str_replace('\\', '', $smcFunc['db_unescape_string']($stringToFormat));
	$returnString = str_replace(chr(10), '&lt;br/&gt;', $returnString);

	return html_entity_decode($returnString, ENT_QUOTES, 'UTF-8');
}

/**
 * Updates the quiz session start time and optionally increments resume count.
 *
 * @param string $id_session Session ID.
 * @param bool $updateResumes Whether to increment resume count.
 */
function UpdateSession(string $id_session, bool $updateResumes = false): void
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}quiz_session
		SET last_question_start = {int:last_question_start}
			{raw:resumeCount}
		WHERE id_quiz_session = {string:id_session}',
		[
			'last_question_start' => time(),
			'resumeCount' => $updateResumes ? ', total_resumes = total_resumes + 1 ' : '',
			'id_session' => $id_session,
		]
	);
}
