<?php
declare(strict_types=1);

if (!defined('SMF')) {
	die('Hacking attempt...');
}

/**
 * Loads quiz or league XML data for the current user.
 */
function loadQuiz(): void
{
	global $context, $txt;

	loadTemplate('Quiz/Admin');
	loadLanguage('Quiz/Quiz');

	if (!allowedTo('quiz_play')) {
		$context['quiz_error'] = 'cannot_play';
		die();
	}

	$id_quiz_league = (int) ($_GET['id_quiz_league'] ?? 0);
	$id_quiz = (int) ($_GET['id_quiz'] ?? 0);
	$id_user = (int) ($context['user']['id'] ?? 0);
	$debugOn = isset($_GET['debugOn']);
	$id_session = md5(uniqid((string) mt_rand(), true));

	$xmlReturn = '<smfQuiz>';

	if ($id_quiz !== 0) {
		$sessions = QuizSessionExists($id_user, $id_quiz);
		if (count($sessions) > 0) {
			$xmlReturn .= GetQuizSessionXml($sessions);
			$xmlReturn .= GetQuizDetails($id_quiz, $id_user, $id_session, $debugOn);
		} else {
			$xmlReturn .= GetQuizDetails($id_quiz, $id_user, $id_session, $debugOn);

			if (strpos($xmlReturn, 'title') !== false) {
				InsertQuizSession($id_session, $id_user, $id_quiz, null);
			}
		}
	} elseif ($id_quiz_league !== 0) {
		$sessions = QuizLeagueSessionExists($id_user, $id_quiz_league);
		if (count($sessions) > 0) {
			$xmlReturn .= GetQuizSessionXml($sessions);
			$xmlReturn .= GetQuizLeagueDetails($id_quiz_league, $id_user, $id_session, $debugOn);
		} else {
			$xmlReturn .= GetQuizLeagueDetails($id_quiz_league, $id_user, $id_session, $debugOn);
			InsertQuizSession($id_session, $id_user, null, $id_quiz_league);
		}
	} else {
		$xmlReturn .= '<Error>' . $txt['quiz_xml_error_no_id'] . '</Error>';
	}

	$xmlReturn .= '</smfQuiz>';

	header('Content-Type: text/xml');
	echo $xmlReturn;
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
 * Builds XML for one or more existing quiz sessions.
 *
 * @param array $sessions Session rows.
 * @return string
 */
function GetQuizSessionXml(array $sessions): string
{
	$xmlFragment = '';
	foreach ($sessions as $session) {
		$xmlFragment .= '<session>';
		$xmlFragment .= '<id_quiz_session>' . $session['id_quiz_session'] . '</id_quiz_session>';
		$xmlFragment .= '<session_start>' . $session['session_start'] . '</session_start>';
		$xmlFragment .= '<last_question_start>' . $session['last_question_start'] . '</last_question_start>';
		$xmlFragment .= '<question_count>' . $session['question_count'] . '</question_count>';
		$xmlFragment .= '<session_correct>' . $session['session_correct'] . '</session_correct>';
		$xmlFragment .= '<session_incorrect>' . $session['session_incorrect'] . '</session_incorrect>';
		$xmlFragment .= '<session_timeouts>' . $session['session_timeouts'] . '</session_timeouts>';
		$xmlFragment .= '<session_time>' . $session['session_time'] . '</session_time>';
		$xmlFragment .= '<total_resumes>' . $session['total_resumes'] . '</total_resumes>';
		$xmlFragment .= '</session>';
	}

	return $xmlFragment;
}

/**
 * Returns prior quiz sessions for a user and quiz.
 *
 * @param int $id_user Member ID.
 * @param int $id_quiz Quiz ID.
 * @return array
 */
function QuizSessionExists(int $id_user, int $id_quiz): array
{
	global $smcFunc;

	$sessionResult = $smcFunc['db_query']('', '
		SELECT id_quiz_session, session_start, last_question_start, question_count AS question_count,
			id_quiz, id_quiz_league, correct AS session_correct, incorrect AS session_incorrect,
			timeouts AS session_timeouts, total_seconds AS session_time, total_resumes
		FROM {db_prefix}quiz_session
		WHERE id_user = {int:id_user}
			AND id_quiz = {int:id_quiz}',
		[
			'id_user' => $id_user,
			'id_quiz' => $id_quiz,
		]
	);

	$returnRow = [];
	if ($smcFunc['db_num_rows']($sessionResult) > 0) {
		while ($sessionRow = $smcFunc['db_fetch_assoc']($sessionResult)) {
			$returnRow[] = $sessionRow;
		}
	}

	$smcFunc['db_free_result']($sessionResult);

	return $returnRow;
}

/**
 * Returns prior quiz league sessions for a user and league.
 *
 * @param int $id_user Member ID.
 * @param int $id_quiz_league Quiz league ID.
 * @return array
 */
function QuizLeagueSessionExists(int $id_user, int $id_quiz_league): array
{
	global $smcFunc;

	$sessionResult = $smcFunc['db_query']('', '
		SELECT id_quiz_session, session_start, last_question_start, (question_count + 1) AS question_count,
			id_quiz, id_quiz_league, correct AS session_correct, incorrect AS session_incorrect,
			timeouts AS session_timeouts, total_seconds AS session_time, total_resumes
		FROM {db_prefix}quiz_session
		WHERE id_user = {int:id_user}
			AND id_quiz_league = {int:id_quiz_league}',
		[
			'id_user' => $id_user,
			'id_quiz_league' => $id_quiz_league,
		]
	);

	$returnRow = [];
	if ($smcFunc['db_num_rows']($sessionResult) > 0) {
		while ($sessionRow = $smcFunc['db_fetch_assoc']($sessionResult)) {
			$returnRow[] = $sessionRow;
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}quiz_session
				SET
					timeouts = timeouts + 1,
					question_count = question_count + 1
				WHERE id_quiz_session = {string:id_quiz_session}',
				[
					'id_quiz_session' => (string) $sessionRow['id_quiz_session'],
				]
			);
		}
	}

	$smcFunc['db_free_result']($sessionResult);

	return $returnRow;
}

/**
 * Builds quiz league XML details.
 *
 * @param int $id_quiz_league Quiz league ID.
 * @param int $id_user Member ID.
 * @param string $id_session Session ID.
 * @param bool $debugOn Debug flag.
 * @return string
 */
function GetQuizLeagueDetails(int $id_quiz_league, int $id_user, string $id_session, bool $debugOn): string
{
	global $smcFunc;

	$leagueResult = $smcFunc['db_query']('', '
		SELECT title, description, day_interval, question_plays, questions_per_session,
			seconds_per_question, points_for_correct, show_answers,
			current_round
		FROM {db_prefix}quiz_league QL
		WHERE id_quiz_league = {int:id_quiz_league}
			AND state = 1',
		[
			'id_user' => $id_user,
			'id_quiz_league' => $id_quiz_league,
		]
	);
	$leagueRow = $smcFunc['db_fetch_assoc']($leagueResult) ?: [];

	if ($leagueRow === []) {
		$smcFunc['db_free_result']($leagueResult);

		return '<leagueDetail></leagueDetail><leagueResults></leagueResults>';
	}

	$leaguePlays = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS user_plays
		FROM {db_prefix}quiz_league_result
		WHERE id_quiz_league = {int:id_quiz_league}
			AND id_user = {int:id_user}
			AND round = {int:current_round}',
		[
			'id_user' => $id_user,
			'id_quiz_league' => $id_quiz_league,
			'current_round' => (int) $leagueRow['current_round'],
		]
	);
	[$timesPlayed] = $smcFunc['db_fetch_row']($leaguePlays);
	$smcFunc['db_free_result']($leaguePlays);
	$smcFunc['db_free_result']($leagueResult);

	$xmlFragment = '<leagueDetail>';
	if (empty($timesPlayed)) {
		$xmlFragment .= '<title>' . xmlencode(ajax_format_string((string) $leagueRow['title'])) . '</title>';
		$xmlFragment .= '<id_session>' . $id_session . '</id_session>';
		$xmlFragment .= '<description>' . xmlencode(ajax_format_string((string) $leagueRow['description'])) . '</description>';
		$xmlFragment .= '<day_interval>' . $leagueRow['day_interval'] . '</day_interval>';
		$xmlFragment .= '<question_plays>' . $leagueRow['question_plays'] . '</question_plays>';
		$xmlFragment .= '<questions_per_session>' . $leagueRow['questions_per_session'] . '</questions_per_session>';
		$xmlFragment .= '<seconds_per_question>' . $leagueRow['seconds_per_question'] . '</seconds_per_question>';
		$xmlFragment .= '<points_for_correct>' . $leagueRow['points_for_correct'] . '</points_for_correct>';
		$xmlFragment .= '<show_answers>' . $leagueRow['show_answers'] . '</show_answers>';
		$xmlFragment .= '<current_round>' . $leagueRow['current_round'] . '</current_round>';
		$xmlFragment .= '<image></image>';
	}

	$xmlFragment .= '</leagueDetail><leagueResults></leagueResults>';

	return $xmlFragment;
}

/**
 * Builds quiz XML details.
 *
 * @param int $id_quiz Quiz ID.
 * @param int $id_user Member ID.
 * @param string $id_session Session ID.
 * @param bool $debugOn Debug flag.
 * @return string
 */
function GetQuizDetails(int $id_quiz, int $id_user, string $id_session, bool $debugOn): string
{
	global $smcFunc;

	$leagueResult = $smcFunc['db_query']('', '
		SELECT Q.title, Q.description, Q.play_limit, Q.seconds_per_question, Q.show_answers, Q.image,
			Q.creator_id
		FROM {db_prefix}quiz Q
		WHERE Q.id_quiz = {int:id_quiz}',
		[
			'id_user' => $id_user,
			'id_quiz' => $id_quiz,
		]
	);
	$rows = $smcFunc['db_num_rows']($leagueResult);
	$leagueRow = [];
	$questions_per_session = 0;
	$timesPlayed = 0;

	if ($rows > 0) {
		$leagueRow = $smcFunc['db_fetch_assoc']($leagueResult) ?: [];
		$questionsData = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS questions_per_session
			FROM {db_prefix}quiz_question
			WHERE id_quiz = {int:id_quiz}',
			[
				'id_quiz' => $id_quiz,
			]
		);
		[$questions_per_session] = $smcFunc['db_fetch_row']($questionsData);
		$smcFunc['db_free_result']($questionsData);

		$quizPlays = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS user_plays
			FROM {db_prefix}quiz_result
			WHERE id_quiz = {int:id_quiz}
				AND id_user = {int:id_user}',
			[
				'id_user' => $id_user,
				'id_quiz' => $id_quiz,
			]
		);
		[$timesPlayed] = $smcFunc['db_fetch_row']($quizPlays);
		$smcFunc['db_free_result']($quizPlays);
	}

	$smcFunc['db_free_result']($leagueResult);

	$xmlFragment = '<quizDetail>';
	if ($rows > 0) {
		$xmlFragment .= '<title>' . xmlencode(ajax_format_string((string) $leagueRow['title'])) . '</title>';
		$xmlFragment .= '<id_session>' . $id_session . '</id_session>';
		$xmlFragment .= '<creator_id>' . $leagueRow['creator_id'] . '</creator_id>';
		$xmlFragment .= '<description>' . xmlencode(ajax_format_string((string) $leagueRow['description'])) . '</description>';
		$xmlFragment .= '<play_limit>' . $leagueRow['play_limit'] . '</play_limit>';
		$xmlFragment .= '<questions_per_session>' . $questions_per_session . '</questions_per_session>';
		$xmlFragment .= '<seconds_per_question>' . $leagueRow['seconds_per_question'] . '</seconds_per_question>';
		$xmlFragment .= '<show_answers>' . $leagueRow['show_answers'] . '</show_answers>';
		$xmlFragment .= '<image>' . $leagueRow['image'] . '</image>';
	}
	$xmlFragment .= '</quizDetail>';

	if ($rows > 0 && (empty($timesPlayed) || ((int) $leagueRow['play_limit'] > (int) $timesPlayed))) {
		$xmlFragment .= '<quizResults>';
		$resultsResult = $smcFunc['db_query']('', '
			SELECT IFNULL(SUM(QR.questions),0) AS total_questions,
				IFNULL(SUM(QR.correct),0) AS total_correct,
				IFNULL(SUM(QR.incorrect),0) AS total_incorrect,
				IFNULL(SUM(QR.timeouts),0) AS total_timeouts,
				IFNULL(SUM(QR.total_seconds),0) AS total_seconds
			FROM {db_prefix}quiz_result QR
			WHERE QR.id_user = {int:id_user}
				AND QR.id_quiz = {int:id_quiz}',
			[
				'id_user' => $id_user,
				'id_quiz' => $id_quiz,
			]
		);

		while ($resultsRow = $smcFunc['db_fetch_assoc']($resultsResult)) {
			$xmlFragment .= '<total_questions>' . $resultsRow['total_questions'] . '</total_questions>';
			$xmlFragment .= '<total_correct>' . $resultsRow['total_correct'] . '</total_correct>';
			$xmlFragment .= '<total_incorrect>' . $resultsRow['total_incorrect'] . '</total_incorrect>';
			$xmlFragment .= '<total_timeouts>' . $resultsRow['total_timeouts'] . '</total_timeouts>';
			$xmlFragment .= '<total_seconds>' . $resultsRow['total_seconds'] . '</total_seconds>';
		}
		$smcFunc['db_free_result']($resultsResult);
		$xmlFragment .= '</quizResults>';
	}

	return $xmlFragment;
}

/**
 * Creates a quiz session row.
 *
 * @param string $id_session Session ID.
 * @param int $id_user Member ID.
 * @param int|null $id_quiz Quiz ID.
 * @param int|null $id_quiz_league Quiz league ID.
 */
function InsertQuizSession(string $id_session, int $id_user, ?int $id_quiz, ?int $id_quiz_league): void
{
	global $smcFunc;

	$id_quiz_league = (int) $id_quiz_league;
	$id_quiz = (int) $id_quiz;
	if ($id_quiz_league === 0 && $id_quiz === 0) {
		return;
	}

	$smcFunc['db_insert']('',
		'{db_prefix}quiz_session',
		[
			'id_quiz_session' => 'string-38',
			'id_user' => 'int',
			'session_start' => 'int',
			'last_question_start' => 'int',
			'id_quiz_league' => 'int',
			'question_count' => 'int',
			'id_quiz' => 'int',
			'correct' => 'int',
			'incorrect' => 'int',
			'timeouts' => 'int',
		],
		[
			$id_session,
			$id_user,
			time(),
			time(),
			$id_quiz_league,
			0,
			$id_quiz,
			0,
			0,
			0,
		],
		[
			'id_quiz_session',
		]
	);
}

/**
 * Normalizes a string for AJAX/XML output.
 *
 * @param string $stringToFormat Source string.
 * @return string
 */
function ajax_format_string(string $stringToFormat): string
{
	global $smcFunc;

	$returnString = str_replace('\\', '', $smcFunc['db_unescape_string']($stringToFormat));

	return html_entity_decode($returnString, ENT_QUOTES, 'UTF-8');
}
