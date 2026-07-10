<?php
declare(strict_types=1);

if (!defined('SMF')) {
	die('Hacking attempt...');
}

/**
 * Finalizes a quiz or quiz league session.
 */
function endQuiz(): void
{
	global $boardurl, $context;

	if (!allowedTo('quiz_play')) {
		$context['quiz_error'] = 'cannot_play';
		die();
	}

	$id_quiz_league = (int) ($_GET['id_quiz_league'] ?? 0);
	$id_quiz = (int) ($_GET['id_quiz'] ?? 0);
	$id_user = (int) ($context['user']['id'] ?? 0);
	$name = (string) ($context['user']['name'] ?? '');
	$id_session = (string) ($_GET['id_session'] ?? '');
	$questions = (int) ($_GET['questions'] ?? 0);
	$correct = (int) ($_GET['correct'] ?? 0);
	$incorrect = (int) ($_GET['incorrect'] ?? 0);
	$timeouts = (int) ($_GET['timeouts'] ?? 0);
	$total_seconds = (int) ($_GET['total_seconds'] ?? 0);
	$creatorId = (int) ($_GET['creator_id'] ?? 0);
	$points = (int) ($_GET['points'] ?? 0);
	$round = (int) ($_GET['round'] ?? 0);
	$totalResumes = (int) ($_GET['totalResumes'] ?? 0);

	loadLanguage('Quiz/Quiz');

	if ($id_quiz !== 0) {
		if ($creatorId !== $id_user && CheckResultExists($id_quiz, $id_user) === false) {
			InsertQuizEnd($id_quiz, $id_user, $questions, $correct, $incorrect, $timeouts, $total_seconds, $totalResumes);
			UpdateQuiz($id_quiz, $questions, $correct, $total_seconds, $id_user, $name);
			call_integration_hook('integrate_quiz_result', [$id_quiz, $id_user, $questions, $correct, $incorrect, $timeouts, $total_seconds, $totalResumes]);
		}
	} elseif ($id_quiz_league !== 0) {
		InsertQuizLeagueEnd($id_quiz_league, $id_user, $questions, $correct, $incorrect, $timeouts, $total_seconds, $points, $round, $total_seconds, $name);
		call_integration_hook('integrate_quiz_league_result', [$id_quiz_league, $id_user, $questions, $correct, $incorrect, $timeouts, $total_seconds, $points, $round, $total_seconds, $name]);
	}

	EndSession($id_session);

	header('Content-Type: text/xml');
	echo '<xml/>';
	die();
}

/**
 * Checks whether a quiz result already exists or the quiz is disabled.
 *
 * @param int $id_quiz Quiz ID.
 * @param int $id_user Member ID.
 * @return bool
 */
function CheckResultExists(int $id_quiz, int $id_user): bool
{
	global $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT id_quiz_result
		FROM {db_prefix}quiz_result QR
		RIGHT JOIN {db_prefix}quiz Q
			ON QR.id_quiz = Q.id_quiz
		WHERE (QR.id_quiz = {int:id_quiz} AND QR.id_user = {int:id_user})
			OR (Q.id_quiz = {int:id_quiz} AND Q.enabled = {int:quiz_disabled})',
		[
			'id_quiz' => $id_quiz,
			'id_user' => $id_user,
			'quiz_disabled' => 0,
		]
	);

	$count = $smcFunc['db_num_rows']($result);
	$smcFunc['db_free_result']($result);

	return $count > 0;
}

/**
 * Inserts a finished quiz result.
 *
 * @param int $id_quiz Quiz ID.
 * @param int $id_user Member ID.
 * @param int $questions Questions answered.
 * @param int $correct Correct answers.
 * @param int $incorrect Incorrect answers.
 * @param int $timeouts Timeout count.
 * @param int $total_seconds Total elapsed seconds.
 * @param int $totalResumes Resume count.
 */
function InsertQuizEnd(int $id_quiz, int $id_user, int $questions, int $correct, int $incorrect, int $timeouts, int $total_seconds, int $totalResumes): void
{
	global $smcFunc, $db_prefix;

	$result_date = time();
	$smcFunc['db_insert']('',
		'{db_prefix}quiz_result',
		[
			'id_quiz' => 'int',
			'id_user' => 'int',
			'result_date' => 'int',
			'questions' => 'int',
			'correct' => 'int',
			'incorrect' => 'int',
			'timeouts' => 'int',
			'total_seconds' => 'int',
			'total_resumes' => 'int',
		],
		[
			$id_quiz,
			$id_user,
			$result_date,
			$questions,
			$correct,
			$incorrect,
			$timeouts,
			$total_seconds,
			$totalResumes,
		],
		[
			'id_quiz_result',
		]
	);
}

/**
 * Deletes an active quiz session.
 *
 * @param string $id_session Session ID.
 */
function EndSession(string $id_session): void
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}quiz_session
		WHERE id_quiz_session = {string:id_session}',
		[
			'id_session' => $id_session,
		]
	);
}

/**
 * Updates aggregate quiz statistics and top score state.
 *
 * @param int $id_quiz Quiz ID.
 * @param int $questions Questions answered.
 * @param int $correct Correct answers.
 * @param int $total_seconds Total elapsed seconds.
 * @param int $id_user Member ID.
 * @param string $name Member display name.
 */
function UpdateQuiz(int $id_quiz, int $questions, int $correct, int $total_seconds, int $id_user, string $name): void
{
	global $smcFunc, $db_prefix, $scripturl, $sourcedir, $modSettings, $settings, $user_settings;

	$quizTopResult = $smcFunc['db_query']('', '
		SELECT Q.top_correct, Q.top_time, Q.top_user_id,
			Q.title, Q.image, M.real_name
		FROM {db_prefix}quiz Q
		LEFT JOIN {db_prefix}members M
			ON M.id_member = Q.top_user_id
		WHERE id_quiz = {int:id_quiz}',
		[
			'id_quiz' => $id_quiz,
		]
	);

	$total_points = 0;
	$top_points = 0;
	$quizTitle = '';
	$quizImage = $settings['default_images_url'] . '/quiz_images/Quizes/Default-64.png';
	$topScore = false;
	$top_correct = 0;
	$top_id_user = 0;
	$top_user_name = '';
	$top_time = 0;

	$rows = $smcFunc['db_num_rows']($quizTopResult);
	if ($rows > 0) {
		while ($quiztitleRow = $smcFunc['db_fetch_assoc']($quizTopResult)) {
			$top_correct = (int) $quiztitleRow['top_correct'];
			$top_id_user = (int) $quiztitleRow['top_user_id'];
			$top_user_name = (string) ($quiztitleRow['real_name'] ?? '');
			$top_time = (int) $quiztitleRow['top_time'];
			$quizTitle = (string) $quiztitleRow['title'];
			if (!empty($quiztitleRow['image'])) {
				$quizImage = $settings['default_images_url'] . '/quiz_images/Quizes/' . $quiztitleRow['image'];
			}
		}

		if ($correct > $top_correct || ($correct === $top_correct && $total_seconds < $top_time)) {
			$topScore = true;
		}
	} else {
		$topScore = true;
	}

	$smcFunc['db_free_result']($quizTopResult);

	if ($topScore === false) {
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}quiz
			SET
				quiz_plays = quiz_plays + 1,
				question_plays = question_plays + {int:questions},
				total_correct = total_correct + {int:correct}
			WHERE id_quiz = {int:id_quiz}',
			[
				'questions' => $questions,
				'correct' => $correct,
				'id_quiz' => $id_quiz,
			]
		);

		AddInfoBoardentry($id_user, $name, $id_quiz, $correct, $total_seconds, false, $quizTitle, $quizImage);

		return;
	}

	if (!empty($modSettings['SMFQuiz_SendPMOnBrokenTopScore']) && $top_id_user !== 0) {
		require_once($sourcedir . '/Subs-Post.php');

		$pmto = [
			'to' => [],
			'bcc' => [$top_id_user],
		];

		$subject = ParseMessage($modSettings['SMFQuiz_PMBrokenTopScoreSubject'], $quizTitle, $total_seconds, $correct, $top_time, $top_correct, $quizImage, $scripturl, $id_quiz, $top_user_name);
		$message = ParseMessage($modSettings['SMFQuiz_PMBrokenTopScoreMsg'], $quizTitle, $total_seconds, $correct, $top_time, $top_correct, $quizImage, $scripturl, $id_quiz, $top_user_name);
		$pmfrom = [
			'id' => $user_settings['id_member'],
			'name' => $user_settings['real_name'],
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
			'questions' => $questions,
			'correct' => $correct,
			'id_user' => $id_user,
			'total_seconds' => $total_seconds,
			'id_quiz' => $id_quiz,
		]
	);

	AddInfoBoardentry($id_user, $name, $id_quiz, $correct, $total_seconds, true, $quizTitle, $quizImage);
}

/**
 * Adds a quiz result entry to the infoboard.
 *
 * @param int $id_user Member ID.
 * @param string $name Member display name.
 * @param int $id_quiz Quiz ID.
 * @param int $correct Correct answers.
 * @param int $total_seconds Elapsed seconds.
 * @param bool $topScore Whether the result is a new top score.
 * @param string $quizTitle Quiz title.
 * @param string $quizImage Quiz image URL.
 */
function AddInfoBoardentry(int $id_user, string $name, int $id_quiz, int $correct, int $total_seconds, bool $topScore, string $quizTitle, string $quizImage): void
{
	global $smcFunc, $db_prefix, $boardurl, $settings, $txt;

	if ($topScore === true) {
		$entry = '<img src="' . $settings['default_images_url'] . '/quiz_images/cup_g.gif"/> <a href="' . $boardurl . '/index.php?action=SMFQuiz;sa=userdetails;id_user=' . $id_user . '"><b>' . addslashes($name) . '</b></a> ' . $txt['SMFQuiz_QuizEnd_Page']['JustAnswered'] . ' <b>' . $correct . '</b> ' . $txt['SMFQuiz_QuizEnd_Page']['QuestionsCorrectlyInThe'] . ' <img width="17" height="17" src="' . $quizImage . '"/><b> <a href="' . $boardurl . '/index.php?action=SMFQuiz;sa=categories;id_quiz=' . $id_quiz . '">' . addslashes($quizTitle) . '</a></b> ' . $txt['SMFQuiz_QuizEnd_Page']['QuizInATimeOf'] . ' <b>' . $total_seconds . '</b> ' . $txt['SMFQuiz_QuizEnd_Page']['SecondsThisIsANewTopScore'];
	} else {
		$entry = '<a href="' . $boardurl . '/index.php?action=SMFQuiz;sa=userdetails;id_user=' . $id_user . '"><b>' . addslashes($name) . '</b></a> ' . $txt['SMFQuiz_QuizEnd_Page']['JustAnswered'] . ' <b>' . $correct . '</b> ' . $txt['SMFQuiz_QuizEnd_Page']['QuestionsCorrectlyInThe'] . ' <img width="17" height="17" src="' . $quizImage . '"/><b> <a href="' . $boardurl . '/index.php?action=SMFQuiz;sa=categories;id_quiz=' . $id_quiz . '">' . addslashes($quizTitle) . '</a></b> ' . $txt['SMFQuiz_QuizEnd_Page']['QuizInATimeOf'] . ' <b>' . $total_seconds . '</b> ' . $txt['SMFQuiz_Common']['seconds'];
	}

	$time = time();
	$entry = $smcFunc['db_escape_string'](html_entity_decode($entry, ENT_QUOTES, 'UTF-8'));

	$smcFunc['db_insert']('',
		'{db_prefix}quiz_infoboard',
		[
			'entry_date' => 'int',
			'entry' => 'string',
		],
		[
			$time,
			$entry,
		],
		[
			'id_infoboard',
		]
	);
}

/**
 * Adds a quiz league result entry to the infoboard.
 *
 * @param int $id_user Member ID.
 * @param string $name Member display name.
 * @param int $id_quiz_league Quiz league ID.
 * @param int $correct Correct answers.
 * @param int $total_seconds Elapsed seconds.
 */
function AddQuizLeagueInfoBoardentry(int $id_user, string $name, int $id_quiz_league, int $correct, int $total_seconds): void
{
	global $smcFunc, $db_prefix, $boardurl, $settings, $txt;

	$quiztitleResult = $smcFunc['db_query']('', '
		SELECT QL.title
		FROM {db_prefix}quiz_league QL
		WHERE QL.id_quiz_league = {int:id_quiz_league}',
		[
			'id_quiz_league' => $id_quiz_league,
		]
	);

	$quiztitle = '';
	if ($smcFunc['db_num_rows']($quiztitleResult) > 0) {
		[$quiztitle] = $smcFunc['db_fetch_row']($quiztitleResult);
	}
	$smcFunc['db_free_result']($quiztitleResult);

	$entry = '<a href="' . $boardurl . '/index.php?action=SMFQuiz;sa=userdetails;id_user=' . $id_user . '"><b>' . addslashes($name) . '</b></a> just answered <b>' . $correct . '</b> questions correctly in the <b>' . addslashes($quiztitle) . '</b> quiz league in a time of <b>' . $total_seconds . '</b> seconds.';
	$entry = $smcFunc['db_escape_string'](html_entity_decode($entry, ENT_QUOTES, 'UTF-8'));
	$time = time();

	$smcFunc['db_insert']('',
		'{db_prefix}quiz_infoboard',
		[
			'entry_date' => 'int',
			'entry' => 'string',
		],
		[
			$time,
			$entry,
		],
		[
			'id_infoboard',
		]
	);
}

/**
 * Inserts a quiz league result and updates league totals.
 *
 * @param int $id_quiz_league Quiz league ID.
 * @param int $id_user Member ID.
 * @param int $questions Questions answered.
 * @param int $correct Correct answers.
 * @param int $incorrect Incorrect answers.
 * @param int $timeouts Timeout count.
 * @param int $total_seconds Elapsed seconds.
 * @param int $points Points earned.
 * @param int $round League round.
 * @param int $seconds Seconds stored for the result row.
 * @param string $name Member display name.
 */
function InsertQuizLeagueEnd(int $id_quiz_league, int $id_user, int $questions, int $correct, int $incorrect, int $timeouts, int $total_seconds, int $points, int $round, int $seconds, string $name): void
{
	global $smcFunc, $db_prefix;

	$result_date = time();
	$smcFunc['db_insert']('',
		'{db_prefix}quiz_league_result',
		[
			'id_quiz_league' => 'int',
			'id_user' => 'int',
			'round' => 'int',
			'correct' => 'int',
			'points' => 'int',
			'result_date' => 'int',
			'incorrect' => 'int',
			'timeouts' => 'int',
			'seconds' => 'int',
		],
		[
			$id_quiz_league,
			$id_user,
			$round,
			$correct,
			$points,
			$result_date,
			$incorrect,
			$timeouts,
			$seconds,
		],
		[
			'id_quiz_league_result',
		]
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}quiz_league
		SET
			total_plays = total_plays + 1,
			total_correct = total_correct + {int:correct},
			total_incorrect = total_incorrect + {int:incorrect},
			total_timeouts = total_timeouts + {int:timeouts}
		WHERE id_quiz_league = {int:id_quiz_league}',
		[
			'correct' => $correct,
			'incorrect' => $incorrect,
			'timeouts' => $timeouts,
			'id_quiz_league' => $id_quiz_league,
		]
	);

	AddQuizLeagueInfoBoardentry($id_user, $name, $id_quiz_league, $correct, $total_seconds);
}

/**
 * Replaces PM template tokens with quiz data.
 *
 * @param string $message Template message.
 * @param string $quiztitle Quiz title.
 * @param int $total_seconds New score seconds.
 * @param int $total_points New score points.
 * @param int $top_time Previous top time.
 * @param int $top_points Previous top points.
 * @param string $quizImage Quiz image URL.
 * @param string $scripturl Forum script URL.
 * @param int $id_quiz Quiz ID.
 * @param string $old_member_name Previous top scorer name.
 * @return string
 */
function ParseMessage(string $message, string $quiztitle, int $total_seconds, int $total_points, int $top_time, int $top_points, string $quizImage, string $scripturl, int $id_quiz, string $old_member_name): string
{
	global $user_settings;

	$message = str_replace('{quiz_name}', $quiztitle, $message);
	$message = str_replace('{new_score_seconds}', (string) $total_seconds, $message);
	$message = str_replace('{new_score}', (string) $total_points, $message);
	$message = str_replace('{old_score_seconds}', (string) $top_time, $message);
	$message = str_replace('{old_score}', (string) $top_points, $message);
	$message = str_replace('{member_name}', (string) $user_settings['real_name'], $message);
	$message = str_replace('{old_member_name}', $old_member_name, $message);
	$message = str_replace('{quiz_image}', '[img]' . $quizImage . '[/img]', $message);

	return str_replace('{quiz_link}', $scripturl . '?action=SMFQuiz;sa=categories;id_quiz=' . $id_quiz, $message);
}
