<?php
declare(strict_types=1);


if (!defined('SMF'))
	die('Hacking attempt...');


/**
 * Validates an ORDER BY clause against an allow list.
 *
 * @param string $orderBy The requested order-by value.
 * @param array $allowed The allowed order-by values.
 * @param string $default The default order-by value.
 * @return string
 */
function SMFQuizNormalizeOrderBy(string $orderBy, array $allowed, string $default): string
{
	if ($orderBy === '' || !in_array($orderBy, $allowed, true)) {
		return $default;
	}

	return $orderBy;
}

/**
 * Normalizes the requested order direction.
 *
 * @param string $orderDir The requested order direction.
 * @return string
 */
function SMFQuizNormalizeOrderDirection(string $orderDir): string
{
	return $orderDir === 'up' ? 'ASC' : 'DESC';
}

/**
 * Parses a comma-separated list of IDs into integers.
 *
 * @param string $ids The comma-separated ID list.
 * @return array
 */
function SMFQuizParseIntList(string $ids): array
{
	$parsedIds = [];

	foreach (explode(',', $ids) as $id) {
		$id = (int) trim($id);
		if ($id > 0) {
			$parsedIds[] = $id;
		}
	}

	return array_values(array_unique($parsedIds));
}

/* Retrieves the count of quizes and stores this in the context */
/**
 * Get Quiz Count.
 *
 * @return int
 */
function GetQuizCount(): int
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS quiz_count
		FROM {db_prefix}quiz'
	);

	$count = 0;
	$context['SMFQuiz']['quizCount'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['quizCount'][] = $row;
		$count = (int) $row['quiz_count'];
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

/* Retrieves the count of categories and stores this in the context */
/**
 * Get Category Count.
 *
 * @param ?int $id_category The id category value.
 * @return int
 */
function GetCategoryCount(?int $id_category): int
{
	global $context, $smcFunc;

	if ($id_category === null || $id_category === 0) {
		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS CategoryCount
			FROM {db_prefix}quiz_category'
		);
	} else {
		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS CategoryCount
			FROM {db_prefix}quiz_category
			WHERE id_category = {int:id_category}',
			[
				'id_category' => $id_category,
			]
		);
	}

	$count = 0;
	$context['SMFQuiz']['categoryCount'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['categoryCount'][] = $row;
		$count = (int) $row['CategoryCount'];
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

// Retrieves the question count for the user and populates the context with this
/**
 * Get User Question Count.
 *
 * @param ?int $id_quiz The id quiz value.
 * @param int $id_user The id user value.
 * @return int
 */
function GetUserQuestionCount(?int $id_quiz, int $id_user): int
{
	global $context, $smcFunc;

	if ($id_quiz !== null) {
		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS question_count
			FROM {db_prefix}quiz_question QQ
			INNER JOIN {db_prefix}quiz Q
				ON QQ.id_quiz = Q.id_quiz
			WHERE Q.id_quiz = {int:id_quiz}
				AND Q.creator_id = {int:id_user}',
			[
				'id_quiz' => $id_quiz,
				'id_user' => $id_user,
			]
		);
	} else {
		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS question_count
			FROM {db_prefix}quiz_question QQ
			INNER JOIN {db_prefix}quiz Q
				ON QQ.id_quiz = Q.id_quiz
			WHERE Q.creator_id = {int:id_user}',
			[
				'id_user' => $id_user,
			]
		);
	}

	$count = 0;
	$context['SMFQuiz']['questionCount'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['questionCount'][] = $row;
		$count = (int) $row['question_count'];
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

// Retrieves the quiz question count and populates the context with this
/**
 * Get Quiz Question Count.
 *
 * @param ?int $id_quiz The id quiz value.
 * @return int
 */
function GetQuizQuestionCount(?int $id_quiz): int
{
	global $context, $smcFunc;

	if ($id_quiz !== null && $id_quiz !== 0) {
		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS question_count
			FROM {db_prefix}quiz_question
			WHERE id_quiz = {int:id_quiz}',
			[
				'id_quiz' => $id_quiz,
			]
		);
	} else {
		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS question_count
			FROM {db_prefix}quiz_question'
		);
	}

	$count = 0;
	$context['SMFQuiz']['questionCount'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['questionCount'][] = $row;
		$count = (int) $row['question_count'];
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

// Data class for question details	
/**
 * Get All Question Details.
 *
 * @param int $page The page value.
 * @param string $orderBy The orderBy value.
 * @param string $orderDir The orderDir value.
 * @param int $id_quiz The id quiz value.
 * @return array
 */
function GetAllQuestionDetails(int $page = 1, string $orderBy = 'quiz_title', string $orderDir = 'up', int $id_quiz = 0): array
{
	global $context, $smcFunc, $modSettings;

	$perPage = (int) $modSettings['SMFQuiz_ListPageSizes'];
	$startPage = ($page - 1) * $perPage;
	$orderDir = SMFQuizNormalizeOrderDirection($orderDir);
	$orderBy = SMFQuizNormalizeOrderBy($orderBy, ['Q.id_question', 'Q.question_text', 'QT.description', 'quiz_title', 'QI.title'], 'quiz_title');

	if ($id_quiz !== 0) {
		$result = $smcFunc['db_query']('', '
			SELECT
				Q.id_question,
				Q.question_text,
				QT.description AS question_type,
				IFNULL(QI.title, \'None Assigned\') AS quiz_title
			FROM {db_prefix}quiz_question Q
			LEFT JOIN {db_prefix}quiz QI
				ON Q.id_quiz = QI.id_quiz
			INNER JOIN {db_prefix}quiz_question_type QT
				ON Q.id_question_type = QT.id_question_type
			WHERE Q.id_quiz = {int:id_quiz}
			ORDER BY {raw:order_by} {raw:order_dir}
			LIMIT {int:start_page}, {int:per_page}',
			[
				'id_quiz' => $id_quiz,
				'order_by' => $orderBy,
				'order_dir' => $orderDir,
				'start_page' => $startPage,
				'per_page' => $perPage,
			]
		);
	} else {
		$result = $smcFunc['db_query']('', '
			SELECT
				Q.id_question,
				Q.question_text,
				QT.description AS question_type,
				IFNULL(QI.title, \'None Assigned\') AS quiz_title
			FROM {db_prefix}quiz_question Q
			LEFT JOIN {db_prefix}quiz QI
				ON Q.id_quiz = QI.id_quiz
			INNER JOIN {db_prefix}quiz_question_type QT
				ON Q.id_question_type = QT.id_question_type
			ORDER BY {raw:order_by} {raw:order_dir}
			LIMIT {int:start_page}, {int:per_page}',
			[
				'order_by' => $orderBy,
				'order_dir' => $orderDir,
				'start_page' => $startPage,
				'per_page' => $perPage,
			]
		);
	}

	$context['SMFQuiz']['questions'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['questions'][] = $row;
	}

	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['questions'];
}

/**
 * Get User Question Details.
 *
 * @param int $page The page value.
 * @param string $orderBy The orderBy value.
 * @param string $orderDir The orderDir value.
 * @param int $id_quiz The id quiz value.
 * @param int $id_user The id user value.
 * @return array
 */
function GetUserQuestionDetails(int $page = 1, string $orderBy = 'quiz_title', string $orderDir = 'down', int $id_quiz = 0, int $id_user = 0): array
{
	global $context, $smcFunc;

	$startPage = ($page - 1) * 20;
	$orderDir = SMFQuizNormalizeOrderDirection($orderDir);
	$orderBy = SMFQuizNormalizeOrderBy($orderBy, ['Q.id_question', 'Q.question_text', 'QT.description', 'quiz_title', 'QI.title'], 'quiz_title');

	if ($id_quiz !== 0) {
		$result = $smcFunc['db_query']('', '
			SELECT 		Q.id_question,
						Q.question_text,
						QT.description AS question_type,
						IFNULL(QI.title, \'None Assigned\') AS quiz_title
			FROM 		{db_prefix}quiz_question Q
			LEFT JOIN 	{db_prefix}quiz QI
			ON 			Q.id_quiz = QI.id_quiz
			INNER JOIN 	{db_prefix}quiz_question_type QT
			ON 			Q.id_question_type = QT.id_question_type
			WHERE 		Q.id_quiz = {int:id_quiz} AND QI.creator_id = {int:id_user}
			ORDER BY 	{raw:orderBy} {raw:orderDir}
			LIMIT		{int:startPage}, 20',
			[
				'id_quiz' => $id_quiz,
				'id_user' => $id_user,
				'orderBy' => $orderBy,
				'orderDir' => $orderDir,
				'startPage' => $startPage,
			]
		);
	} else {
		$result = $smcFunc['db_query']('', '
			SELECT 		Q.id_question,
						Q.question_text,
						QT.description AS question_type,
						IFNULL(QI.title, \'None Assigned\') AS quiz_title
			FROM 		{db_prefix}quiz_question Q
			LEFT JOIN 	{db_prefix}quiz QI
			ON 			Q.id_quiz = QI.id_quiz
			INNER JOIN 	{db_prefix}quiz_question_type QT
			ON 			Q.id_question_type = QT.id_question_type
			WHERE 		QI.creator_id = {int:id_user}
			ORDER BY 	{raw:orderBy} {raw:orderDir}
			LIMIT		{int:startPage}, 20',
			[
				'id_user' => $id_user,
				'orderBy' => $orderBy,
				'orderDir' => $orderDir,
				'startPage' => $startPage,
			]
		);
	}

	$context['SMFQuiz']['questions'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['questions'][] = $row;
	}

	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['questions'];
}

// Retrieve all quiz details and populate results in the context
/**
 * Get All Quiz Details.
 *
 * @param int $page The page value.
 * @param string $orderBy The orderBy value.
 * @param string $orderDir The orderDir value.
 * @return array
 */
function GetAllQuizDetails(int $page = 0, string $orderBy = 'Q.Title', string $orderDir = 'up'): array
{
	global $context, $smcFunc;

	$startPage = ($page - 1) * 20;
	$orderDir = SMFQuizNormalizeOrderDirection($orderDir);
	$orderBy = SMFQuizNormalizeOrderBy($orderBy, ['Q.id_quiz', 'Q.title', 'Q.creator_id', 'M.real_name', 'Q.description', 'Q.play_limit', 'Q.seconds_per_question', 'Q.show_answers', 'Q.enabled', 'QC.id_category', 'category_name', 'questions_per_session'], 'Q.title');

	if ($page !== 0) {
		$result = $smcFunc['db_query']('', '
			SELECT		Q.id_quiz,
					Q.title,
					Q.creator_id,
					M.real_name,
					Q.description,
					Q.play_limit,
					Q.seconds_per_question,
					Q.show_answers,
					Q.enabled,
					QC.id_category,
					(CASE WHEN Q.id_category = 0 THEN \'Top Level\' ELSE QC.name END) AS category_name,
					COUNT(U.id_quiz) AS questions_per_session
			FROM 		{db_prefix}quiz Q
			LEFT JOIN	{db_prefix}quiz_category QC
			ON 			Q.id_category = QC.id_category
			LEFT JOIN	{db_prefix}quiz_question U
			ON			Q.id_quiz = U.id_quiz
			LEFT JOIN	{db_prefix}members M
			ON			Q.creator_id = M.id_member
			GROUP BY	Q.id_quiz,
					    Q.title,
						Q.creator_id,
						M.real_name,
						Q.description,
						Q.play_limit,
						Q.seconds_per_question,
						Q.show_answers,
						Q.enabled,
						QC.id_category,
						Q.id_category,
						QC.name,
						U.id_quiz
			ORDER BY 	{raw:orderBy} {raw:orderDir}
			LIMIT {int:startPage}, 20',
			[
				'orderBy' => $orderBy,
				'orderDir' => $orderDir,
				'startPage' => $startPage,
			]
		);
	} else {
		$result = $smcFunc['db_query']('', '
			SELECT		Q.id_quiz,
					Q.title,
					Q.creator_id,
					M.real_name,
					Q.description,
					Q.play_limit,
					Q.seconds_per_question,
					Q.show_answers,
					Q.enabled,
					QC.id_category,
					(CASE WHEN Q.id_category = 0 THEN \'Top Level\' ELSE QC.name END) AS category_name,
					COUNT(U.id_quiz) AS questions_per_session
			FROM 		{db_prefix}quiz Q
			LEFT JOIN	{db_prefix}quiz_category QC
			ON 			Q.id_category = QC.id_category
			LEFT JOIN	{db_prefix}quiz_question U
			ON			Q.id_quiz = U.id_quiz
			LEFT JOIN	{db_prefix}members M
			ON			Q.creator_id = M.id_member
			GROUP BY	Q.id_quiz,
					    Q.title,
						Q.creator_id,
						M.real_name,
						Q.description,
						Q.play_limit,
						Q.seconds_per_question,
						Q.show_answers,
						Q.enabled,
						QC.id_category,
						Q.id_category,
						QC.name,
						U.id_quiz
			ORDER BY 	{raw:orderBy} {raw:orderDir}',
			[
				'orderBy' => $orderBy,
				'orderDir' => $orderDir,
			]
		);
	}

	$context['SMFQuiz']['quizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['quizes'][] = $row;
	}

	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizes'];
}

/**
 * Get Category.
 *
 * @param int $categoryId The categoryId value.
 * @return array
 */
function GetCategory(int $categoryId): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		QC.id_parent,
					QC2.name AS parent_name,
					QC.name,
					QC.description,
					QC.image,
					QC.updated
		FROM 		{db_prefix}quiz_category QC
		LEFT JOIN	{db_prefix}quiz_category QC2
		ON 			QC.id_parent = QC2.id_category
		WHERE		QC.id_category = {int:id_category}',
		[
			'id_category' => $categoryId,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['category'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['category'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['category'];

}

// Data class for the Question and Answers details
/**
 * Get Question And Answers.
 *
 * @param int $id_question The id question value.
 * @return array
 */
function GetQuestionAndAnswers(int $id_question = 0): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		Q.question_text,
					Q.image,
					Q.answer_text,
					Q.id_question_type,
					Q.updated,
					QT.description AS question_type,
					QI.id_quiz,
					QI.title AS quiz_title,
					QI.creator_id
		FROM 		{db_prefix}quiz_question Q
		INNER JOIN 	{db_prefix}quiz_question_type QT
		ON 			Q.id_question_type = QT.id_question_type
		INNER JOIN	{db_prefix}quiz QI
		ON 			Q.id_quiz = QI.id_quiz
		WHERE		Q.id_question = {int:id_question}
		LIMIT		0, 1',
		[
			'id_question' => $id_question,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['questions'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['questions'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 	 	A.id_answer,
					A.answer_text,
					A.is_correct
		FROM 		{db_prefix}quiz_answer A
		WHERE		A.id_question = {int:id_question}
		ORDER BY	A.answer_text',
		[
			'id_question' => $id_question,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['answers'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['answers'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return ['questions' => $context['SMFQuiz']['questions'], 'answers' => $context['SMFQuiz']['answers']];

}

/**
 * Get Random Quizzes.
 *
 * @param int $limit The limit value.
 * @param int $id_user The id user value.
 * @return array
 */
function GetRandomQuizzes(int $limit, int $id_user): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 			Q.id_quiz, 
						Q.title, 
						Q.image
		FROM 			{db_prefix}quiz Q
		LEFT JOIN 		{db_prefix}quiz_result QR
		ON 				Q.id_quiz = QR.id_quiz
		AND 			QR.id_user = {int:id_user}
		WHERE 			id_quiz_result IS NULL
		ORDER BY		RAND()
		LIMIT			0, {int:limit}',
		[
			'limit' => $limit,
			'id_user' => $id_user
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['randomQuizzes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['randomQuizzes'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['randomQuizzes'];

}

/**
 * Get Quiz Correct.
 *
 * @param int $id_quiz The id quiz value.
 * @return array
 */
function GetQuizCorrect(int $id_quiz): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 			correct, 
						COUNT(*) AS count_correct
		FROM 			{db_prefix}quiz_result
		WHERE			id_quiz = {int:id_quiz}
		GROUP BY 		correct',
		[
			'id_quiz' => $id_quiz,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quizCorrect'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizCorrect'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizCorrect'];

}

/**
 * Get Quiz Results.
 *
 * @param int $id_quiz The id quiz value.
 * @return array
 */
function GetQuizResults(int $id_quiz): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 			M.real_name,
						QR.id_user,
						QR.result_date,
						QR.questions,
						QR.correct,
						QR.incorrect,
						QR.timeouts,
						QR.total_seconds,
						QR.auto_completed
		FROM 			{db_prefix}quiz_result QR
		INNER JOIN 		{db_prefix}members M
		ON 				QR.id_user = M.id_member
		WHERE			QR.id_quiz = {int:id_quiz}
		ORDER BY		QR.correct DESC,
						QR.total_seconds ASC
		LIMIT			0, 10',
		[
			'id_quiz' => $id_quiz,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quizResults'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizResults'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizResults'];

}

// Data class for the Quiz details
/**
 * Get Quiz.
 *
 * @param int $quizId The quizId value.
 * @return array
 */
function GetQuiz(int $quizId): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		Q.id_quiz,
					Q.title,
					Q.description,
					Q.image,
					Q.play_limit,
					Q.seconds_per_question,
					Q.show_answers,
					Q.quiz_plays,
					Q.updated,
					Q.question_plays,
					Q.total_correct,
					Q.id_category,
					Q.enabled,
					Q.creator_id,
					IFNULL(QC.id_category,0) AS id_category, 
					QC.name,
					M.real_name AS creator_name,
					round((Q.total_correct / Q.question_plays) * 100) AS percentage,
					COUNT(U.id_quiz) AS questions_per_session
		FROM 		{db_prefix}quiz Q
		LEFT JOIN	{db_prefix}quiz_question U
		ON			Q.id_quiz = U.id_quiz
		INNER JOIN	{db_prefix}members M
		ON			Q.creator_id = M.id_member
		LEFT JOIN	{db_prefix}quiz_category QC
		ON			Q.id_category = QC.id_category
		WHERE		Q.id_quiz = {int:id_quiz}
		GROUP BY	Q.id_quiz,
					Q.title,
					Q.description,
					Q.image,
					Q.play_limit,
					Q.seconds_per_question,
					Q.show_answers,
					Q.quiz_plays,
					Q.updated,
					Q.question_plays,
					Q.total_correct,
					Q.id_category,
					Q.enabled,
					Q.creator_id,
					QC.id_category,
					QC.name,
					M.real_name,
					U.id_quiz,
					percentage',
		[
			'id_quiz' => $quizId,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quiz'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quiz'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quiz'];

}

// Data class for Quiz League details	
/**
 * Get All Quiz League Details.
 *
 * @return array
 */
function GetAllQuizLeagueDetails(): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		QL.id_quiz_league,
					QL.title,
					QL.description,
					QL.day_interval,
					QL.questions_per_session,
					QL.seconds_per_question,
					QL.points_for_correct,
					QL.show_answers,
					QL.updated,
					QL.current_round,
					QL.total_plays,
					QL.total_correct,
					QL.total_timeouts,
					QL.state,
					QL.id_leader,
					QL.total_rounds,
					QL.state
		FROM 		{db_prefix}quiz_league QL
		LEFT JOIN	{db_prefix}members M
		ON			QL.id_leader = M.id_member
		ORDER BY 	QL.title ASC'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quizLeagues'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizLeagues'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizLeagues'];

}

// Data class for single Quiz League details	
/**
 * Get Quiz League Details.
 *
 * @param int $id_quiz_league The id quiz league value.
 * @return array
 */
function GetQuizLeagueDetails(int $id_quiz_league): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		QL.id_quiz_league,
					QL.title,
					QL.description,
					QL.day_interval,
					QL.question_plays,
					QL.questions_per_session,
					QL.seconds_per_question,
					QL.points_for_correct,
					QL.show_answers,
					QL.updated,
					QL.current_round,
					QL.total_plays,
					QL.total_correct,
					QL.total_timeouts,
					QL.state,
					QL.id_leader,
					M.real_name,
					QL.current_round,
					QL.total_rounds,
					QL.categories
		FROM 		{db_prefix}quiz_league QL
		LEFT JOIN	{db_prefix}members M
		ON			QL.id_leader = M.id_member
		WHERE		QL.id_quiz_league = {int:id_quiz_league}
		LIMIT		0, 1',
		[
			'id_quiz_league' => $id_quiz_league,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quizLeague'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizLeague'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizLeague'];

}

// Data class for Quiz League results
/**
 * Get Quiz League Results.
 *
 * @param int $id_quiz_league The id quiz league value.
 * @return array
 */
function GetQuizLeagueResults(int $id_quiz_league): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT		QLR.id_user,
					M.real_name,
					QLR.correct,
					QLR.incorrect,
					QLR.timeouts,
					QLR.points,
					QLR.result_date,
					QLR.round,
					QLR.seconds
		FROM		{db_prefix}quiz_league_result QLR
		INNER JOIN	{db_prefix}members M
		ON			QLR.id_user = M.id_member
		WHERE		QLR.id_quiz_league = {int:id_quiz_league}
		ORDER BY	QLR.result_date DESC
		LIMIT		0, 10',
		[
			'id_quiz_league' => $id_quiz_league,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quizLeagueResults'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizLeagueResults'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizLeagueResults'];

}

// Data class for single Quiz League table	
/**
 * Get Quiz League Table.
 *
 * @param int $id_quiz_league The id quiz league value.
 * @param int $round The round value.
 * @return array
 */
function GetQuizLeagueTable(int $id_quiz_league, int $round): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT		QLT.id_quiz_league_table,
					QLT.current_position,
					QLT.id_user,
					M.real_name,
					QLT.last_position,
					QLT.plays,
					QLT.correct,
					QLT.incorrect,
					QLT.timeouts,
					QLT.seconds,
					QLT.points
		FROM		{db_prefix}quiz_league_table QLT
		INNER JOIN	{db_prefix}members M
		ON			QLT.id_user = M.id_member
		WHERE		QLT.round = {int:current_round}
		AND			QLT.id_quiz_league = {int:id_quiz_league}
		ORDER BY	QLT.current_position ASC,
					QLT.seconds
		LIMIT		0, 10
		',
		[
			'current_round' => $round,
			'id_quiz_league' => $id_quiz_league,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quizTable'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizTable'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizTable'];

}

// Data class for user Quiz League details	
/**
 * Get User Quiz League Details.
 *
 * @param int $id_user The id user value.
 * @return array
 */
function GetUserQuizLeagueDetails(int $id_user): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		QL.id_quiz_league,
					QL.title,
					QL.updated,
					QL.current_round,
					QL.state,
					QL.day_interval,
					IFNULL(QL.id_leader,0) AS id_leader, 
					IFNULL(M.real_name,\'None\') AS leader_name, 
					IFNULL(QLT.points,0) AS user_points, 
					IFNULL(QLT.current_position,0) AS user_position
		FROM 		{db_prefix}quiz_league QL
		LEFT JOIN	{db_prefix}members M
		ON			QL.id_leader = M.id_member
		LEFT JOIN	{db_prefix}quiz_league_table QLT
		ON			QLT.round = QL.current_round
		AND			QLT.id_quiz_league = QL.id_quiz_league
		AND			QLT.id_user = {int:id_user}
		WHERE		QL.state = 1 OR QL.state = 2
		ORDER BY 	QL.state ASC,
					QL.title ASC',
		[
			'id_user' => $id_user
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quizLeagues'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizLeagues'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizLeagues'];

}

/**
 * Get All Question Types.
 *
 * @return array
 */
function GetAllQuestionTypes(): array
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT id_question_type, description
		FROM {db_prefix}quiz_question_type
		ORDER BY description',
		[]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['questionTypes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['questionTypes'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['questionTypes'];

}

// Data class for the category details
/**
 * Get All Category Details.
 *
 * @param int $page The page value.
 * @param string $orderBy The orderBy value.
 * @param string $orderDir The orderDir value.
 * @param int $pageSize The pageSize value.
 * @return array
 */
function GetAllCategoryDetails(int $page = 1, string $orderBy = 'C.name', string $orderDir = 'up', int $pageSize = 5000): array
{
	global $context, $smcFunc;

	$startPage = ($page - 1) * $pageSize;
	$orderDir = SMFQuizNormalizeOrderDirection($orderDir);
	$orderBy = SMFQuizNormalizeOrderBy($orderBy, ['C.id_category', 'C.name', 'C.description', 'C.id_parent', 'C.image', 'C.quiz_count', 'parent_name'], 'C.name');

	$result = $smcFunc['db_query']('', '
		SELECT 		C.id_category,
					C.name,
					C.description,
					C.id_parent,
					C.image,
					C.quiz_count,
					IFNULL(C2.name, \'Top Level\') AS parent_name
		FROM 		{db_prefix}quiz_category C
		LEFT JOIN 	{db_prefix}quiz_category C2
		ON 			C.id_parent = C2.id_category
		ORDER BY 	{raw:orderBy} {raw:orderDir}
		LIMIT		{int:startPage}, {int:pageSize}',
		[
			'orderBy' => $orderBy,
			'orderDir' => $orderDir,
			'startPage' => $startPage,
			'pageSize' => $pageSize,
		]
	);

	$context['SMFQuiz']['categories'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['categories'][] = $row;
	}

	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['categories'];
}

/**
 * Get Category Children.
 *
 * @param int $page The page value.
 * @param string $orderBy The orderBy value.
 * @param string $orderDir The orderDir value.
 * @param int $pageSize The pageSize value.
 * @param int $id_category The id category value.
 * @return array
 */
function GetCategoryChildren(int $page = 1, string $orderBy = 'C.name', string $orderDir = 'up', int $pageSize = 5000, int $id_category = 0): array
{
	global $context, $smcFunc;

	$startPage = ($page - 1) * $pageSize;
	$orderDir = SMFQuizNormalizeOrderDirection($orderDir);
	$orderBy = SMFQuizNormalizeOrderBy($orderBy, ['C.id_category', 'C.name', 'C.description', 'C.id_parent', 'C.image', 'C.quiz_count', 'parent_name'], 'C.name');

	$result = $smcFunc['db_query']('', '
		SELECT 		C.id_category,
					C.name,
					C.description,
					C.id_parent,
					C.image,
					C.quiz_count,
					IFNULL(C2.name, \'Top Level\') AS parent_name
		FROM 		{db_prefix}quiz_category C
		LEFT JOIN 	{db_prefix}quiz_category C2
		ON 			C.id_parent = C2.id_category
		WHERE		C.id_parent = {int:id_category}
		ORDER BY 	{raw:orderBy} {raw:orderDir}
		LIMIT		{int:startPage}, {int:pageSize}',
		[
			'id_category' => $id_category,
			'startPage' => $startPage,
			'pageSize' => $pageSize,
			'orderBy' => $orderBy,
			'orderDir' => $orderDir,
		]
	);

	$context['SMFQuiz']['categories'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['categories'][] = $row;
	}

	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['categories'];
}

/**
 * Get Category Parent.
 *
 * @param int $page The page value.
 * @param string $orderBy The orderBy value.
 * @param string $orderDir The orderDir value.
 * @param int $pageSize The pageSize value.
 * @param int $id_category The id category value.
 * @return array
 */
function GetCategoryParent(int $page = 1, string $orderBy = 'C.name', string $orderDir = 'up', int $pageSize = 5000, int $id_category = 0): array
{
	global $context, $smcFunc;

	$startPage = ($page - 1) * $pageSize;
	$orderDir = SMFQuizNormalizeOrderDirection($orderDir);
	$orderBy = SMFQuizNormalizeOrderBy($orderBy, ['C.id_category', 'C.name', 'C.description', 'C.id_parent', 'C.image', 'C.quiz_count', 'parent_name'], 'C.name');

	$result = $smcFunc['db_query']('', '
		SELECT 		C.id_category,
					C.name,
					C.description,
					C.id_parent,
					C.image,
					C.quiz_count,
					IFNULL(C2.name, \'Top Level\') AS parent_name
		FROM 		{db_prefix}quiz_category C
		LEFT JOIN 	{db_prefix}quiz_category C2
		ON 			C.id_parent = C2.id_category
		WHERE		C.id_parent IN (
					SELECT 		QC.id_parent 
					FROM		{db_prefix}quiz_category QC
					WHERE 		QC.id_category = {int:id_category}
				)
		ORDER BY 	{raw:orderBy} {raw:orderDir}
		LIMIT		{int:startPage}, {int:pageSize}',
		[
			'id_category' => $id_category,
			'orderBy' => $orderBy,
			'orderDir' => $orderDir,
			'startPage' => $startPage,
			'pageSize' => $pageSize,
		]
	);

	$context['SMFQuiz']['categories'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['categories'][] = $row;
	}

	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['categories'];
}

// Data class for the category details
/**
 * Get Parent Category Details.
 *
 * @param int $parentId The parentId value.
 * @return array
 */
function GetParentCategoryDetails(int $parentId = 0): array
{
	global $context, $smcFunc;

		// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		C.id_category,
					C.name,
					C.description,
					C.id_parent,
					C.image,
					C.quiz_count,
					IFNULL(C2.name, \'Top Level\') AS parent_name
		FROM 		{db_prefix}quiz_category C
		LEFT JOIN 	{db_prefix}quiz_category C2
		ON 			C.id_parent = C2.id_category
		WHERE		C.id_parent = {int:id_parent}
		ORDER BY	C.name',
		[
			'id_parent' => $parentId,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['categories'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['categories'][] = $row;

	// We want to return the number of rows in case some logic depends on it
	$rows = $smcFunc['db_num_rows']($result);

	// Free the database
	$smcFunc['db_free_result']($result);	
}

// Updates the answer with the specified data
/**
 * Update Answer.
 *
 * @param int $id_answer The id answer value.
 * @param string $answer_text The answer text value.
 * @param int $is_correct The is correct value.
 * @return void
 */
function UpdateAnswer(int $id_answer, string $answer_text, int $is_correct): void
{
	global $smcFunc;

	$updated = time();

	// Execute the query
		// @TODO query
	$smcFunc['db_query']('', '
		UPDATE		{db_prefix}quiz_answer
		SET			answer_text = {string:answer_text},
					is_correct = {int:is_correct},
					updated = {int:updated}
		WHERE		id_answer = {int:id_answer}',
		[
			'id_answer' => $id_answer,
			'answer_text' => $smcFunc['db_escape_string'] (htmlspecialchars($answer_text, ENT_QUOTES, 'utf-8')),
			'is_correct' => $is_correct,
			'updated' => $updated
	]);	
}

/**
 * Save Answer.
 *
 * @param int $id_question The id question value.
 * @param string $answer_text The answer text value.
 * @param int $is_correct The is correct value.
 * @return void
 */
function SaveAnswer(int $id_question, string $answer_text, int $is_correct): void
{
	global $smcFunc;

	$updated = time();

		// @TODO utf8
	// Execute the query
	$smcFunc['db_insert']('insert', 
		'{db_prefix}quiz_answer',
		[
			'id_question' => 'int',
			'answer_text' => 'string',
			'is_correct' => 'int',
			'updated' => 'int'
		],
		[
			$id_question,
			$smcFunc['db_escape_string'] (htmlspecialchars($answer_text, ENT_QUOTES, 'utf-8')),
			$is_correct,
			$updated
		],
		['id_answer']
	);
}

// Data class for updating quizes
/**
 * Update Quiz.
 *
 * @param int $id_quiz The id quiz value.
 * @param string $title The title value.
 * @param string $description The description value.
 * @param int $play_limit The play limit value.
 * @param int $seconds The seconds value.
 * @param int $show_answers The show answers value.
 * @param string $image The image value.
 * @param int $id_category The id category value.
 * @param int $oldCategoryId The oldCategoryId value.
 * @param int $enabled The enabled value.
 * @param int $for_review The for review value.
 * @return void
 */
function UpdateQuiz(int $id_quiz, string $title, string $description, int $play_limit, int $seconds, int $show_answers, string $image, int $id_category, int $oldCategoryId, int $enabled, int $for_review): void
{
	global $smcFunc;

	// Removing this, as it would cause the quiz to be regarded as new again
	// May look into adding another datetime for this purpose, but not worth it at the moment
	//$updated = time();
		// @TODO utf8
		// @TODO query
	$result = $smcFunc['db_query']('', '
		UPDATE		{db_prefix}quiz
		SET 		title = {string:title},
					description = {string:description},
					play_limit = {int:play_limit},
					seconds_per_question = {int:seconds},
					show_answers = {int:show_answers},
					image = {string:image},
					id_category = {int:id_category},
					enabled = {int:enabled},
					for_review = {int:for_review}
		WHERE		id_quiz = {int:id_quiz}',
		[
			'title' =>  $smcFunc['db_escape_string'] (htmlspecialchars($title, ENT_QUOTES, 'utf-8')),
			'description' =>  $smcFunc['db_escape_string'] (htmlspecialchars($description, ENT_QUOTES, 'utf-8')),
			'play_limit' => $play_limit,
			'seconds' => $seconds,
			'show_answers' => $show_answers,
			'image' =>  $smcFunc['db_escape_string'] ($image),
			'id_category' => $id_category,
			'enabled' => $enabled,
			'id_quiz' => $id_quiz,
			'for_review' => $for_review
		]
	);

	// If the category has changed we need to update the quiz counts on the associated trees
	if ($id_category !== $oldCategoryId)
	{
		IncrementCategoryTree($id_category);
		DecrementCategoryTree($oldCategoryId);
	}
}

// Data class for saving quizes
/**
 * Save Quiz.
 *
 * @param string $title The title value.
 * @param string $description The description value.
 * @param int $play_limit The play limit value.
 * @param int $seconds_per_question The seconds per question value.
 * @param int $show_answers The show answers value.
 * @param string $image The image value.
 * @param int $id_category The id category value.
 * @param int $enabled The enabled value.
 * @param int $creator_id The creator id value.
 * @param int $for_review The for review value.
 * @return int
 */
function SaveQuiz(string $title, string $description, int $play_limit, int $seconds_per_question, int $show_answers, string $image, int $id_category, int $enabled, int $creator_id, int $for_review): int
{
	global $smcFunc;

	$updated = time();
	$returnVal = 0;

	if ($title === '') {
		return $returnVal;
	}

	$smcFunc['db_insert']('insert', 
		'{db_prefix}quiz',
		[
			'title' => 'string',
			'description' => 'string',
			'play_limit' => 'int',
			'seconds_per_question' => 'int',
			'show_answers' => 'int',
			'image' => 'string',
			'id_category' => 'int',
			'enabled' => 'int',
			'creator_id' => 'int',
			'for_review' => 'int',
			'updated' => 'int'
		],
		[
			$smcFunc['db_escape_string'](htmlspecialchars($title, ENT_QUOTES, 'utf-8')),
			$smcFunc['db_escape_string'](htmlspecialchars($description, ENT_QUOTES, 'utf-8')),
			$play_limit,
			$seconds_per_question,
			$show_answers,
			$smcFunc['db_escape_string']($image),
			$id_category,
			$enabled,
			$creator_id,
			$for_review,
			$updated
		],
		['id_quiz']
	);

	IncrementCategoryTree($id_category);

	$result = $smcFunc['db_query']('', '
		SELECT 		id_quiz
		FROM 		{db_prefix}quiz
		ORDER BY 	id_quiz DESC
		LIMIT 0, 1'
	);

	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$returnVal = (int) $row['id_quiz'];
	}

	$smcFunc['db_free_result']($result);

	return $returnVal;
}

// Updates the question with the specified data
/**
 * Update Question.
 *
 * @param int $id_question The id question value.
 * @param string $question_text The question text value.
 * @param string $image The image value.
 * @param string $answer_text The answer text value.
 * @return void
 */
function UpdateQuestion(int $id_question, string $question_text, string $image, string $answer_text): void
{
	global $smcFunc;

	$updated = time();

	// Execute this query
		// @TODO utf8
	$smcFunc['db_query']('', '
		UPDATE 		{db_prefix}quiz_question 
		SET			question_text = {string:question_text},
					image = {string:image},
					answer_text = {text:answer_text},
					updated = {int:updated}
		WHERE		id_question = {int:id_question}',
		[
			'question_text' => $smcFunc['db_escape_string'] (htmlspecialchars($question_text, ENT_QUOTES, 'utf-8')),
			'image' => $smcFunc['db_escape_string'] ($image),
			'answer_text' => $smcFunc['db_escape_string'] (htmlspecialchars($answer_text, ENT_QUOTES, 'utf-8')),
			'updated' => $updated,
			'id_question' => $id_question
	]);
}

// Saves a new question to the database and returns the ID of this inserted record
/**
 * Save Question.
 *
 * @param string $question_text The question text value.
 * @param int $id_question_type The id question type value.
 * @param int $id_quiz The id quiz value.
 * @param string $image The image value.
 * @param string $answer_text The answer text value.
 * @return int
 */
function SaveQuestion(string $question_text, int $id_question_type, int $id_quiz, string $image, string $answer_text): int
{
	global $smcFunc;

	$updated = time();

	// Execute the query
	$smcFunc['db_insert']('insert', 
		'{db_prefix}quiz_question',
		[
			'question_text' => 'string',
			'id_question_type' => 'int',
			'id_quiz' => 'int',
			'image' => 'string',
			'answer_text' => 'string',
			'updated' => 'int'
		],
		[
// @TODO utf8
			$smcFunc['db_escape_string'] (htmlspecialchars($question_text, ENT_QUOTES, 'utf-8')),
			$id_question_type,
			$id_quiz,
			$smcFunc['db_escape_string'] ($image),
			$smcFunc['db_escape_string'] (htmlspecialchars($answer_text, ENT_QUOTES, 'utf-8')),
			$updated
		],
		['id_question']
	);

	// Get the ID of this insert
	$quiz_question['id_question'] = $smcFunc['db_insert_id']('{db_prefix}quiz_question', 'id_question');

	return (int) $quiz_question['id_question'];
}


/**
 * Update Quiz League.
 *
 * @param int $id_quiz_league The id quiz league value.
 * @param string $title The title value.
 * @param string $description The description value.
 * @param int $day_interval The day interval value.
 * @param int $questions_per_session The questions per session value.
 * @param int $seconds_per_question The seconds per question value.
 * @param int $points_for_correct The points for correct value.
 * @param int $show_answers The show answers value.
 * @param int $total_rounds The total rounds value.
 * @param int $state The state value.
 * @param string $categories The categories value.
 * @return void
 */
function UpdateQuizLeague(int $id_quiz_league, string $title, string $description, int $day_interval, int $questions_per_session, int $seconds_per_question, int $points_for_correct, int $show_answers, int $total_rounds, int $state, string $categories): void
{
	global $smcFunc;

// @TODO query + utf8
	$result = $smcFunc['db_query']('', '
		UPDATE		{db_prefix}quiz_league
		SET 		title = {string:title},
					description = {string:description},
					questions_per_session = {int:questions_per_session},
					day_interval = {int:day_interval},
					seconds_per_question = {int:seconds_per_question},
					points_for_correct = {int:points_for_correct},
					show_answers = {int:show_answers},
					total_rounds = {int:total_rounds},
					state = {int:state},
					categories = {string:categories}
		WHERE		id_quiz_league = {int:id_quiz_league}',
		[
			'title' =>  $smcFunc['db_escape_string'] (htmlspecialchars($title, ENT_QUOTES, 'utf-8')),
			'description' =>  $smcFunc['db_escape_string'] (htmlspecialchars($description, ENT_QUOTES, 'utf-8')),
			'questions_per_session' => $questions_per_session,
			'day_interval' => $day_interval,
			'seconds_per_question' => $seconds_per_question,
			'points_for_correct' => $points_for_correct,
			'show_answers' => $show_answers,
			'total_rounds' => $total_rounds,
			'state' => $state,
			'categories' => $categories,
			'id_quiz_league' => $id_quiz_league
		]
	);
}

// Data class for saving quiz leagues
/**
 * Save Quiz League.
 *
 * @param string $title The title value.
 * @param string $description The description value.
 * @param int $day_interval The day interval value.
 * @param int $questions_per_session The questions per session value.
 * @param int $seconds_per_question The seconds per question value.
 * @param int $points_for_correct The points for correct value.
 * @param int $show_answers The show answers value.
 * @param int $total_rounds The total rounds value.
 * @param int $state The state value.
 * @param string $categories The categories value.
 * @return void
 */
function SaveQuizLeague(string $title, string $description, int $day_interval, int $questions_per_session, int $seconds_per_question, int $points_for_correct, int $show_answers, int $total_rounds, int $state, string $categories): void
{
	global $smcFunc;

	// Execute the query
	$smcFunc['db_insert']('insert', 
		'{db_prefix}quiz_league',
		[
			'title' => 'string',
			'description' => 'string',
			'day_interval' => 'int',
			'questions_per_session' => 'string',
			'seconds_per_question' => 'string',
			'points_for_correct' => 'int',
			'show_answers' => 'int',
			'total_rounds' => 'int',
			'state' => 'int',
			'updated' => 'int',
			'categories' => 'string'
		],
		[
			$title,
			$description,
			$day_interval,
			$questions_per_session,
			$seconds_per_question,
			$points_for_correct,
			$show_answers,
			$total_rounds,
			$state,
			time(),
			$categories
		],
		['id_quiz_league_id']
	);
}

/**
 * Update Category.
 *
 * @param int $id_category The id category value.
 * @param string $name The name value.
 * @param string $description The description value.
 * @param int $parent The parent value.
 * @param string $image The image value.
 * @return void
 */
function UpdateCategory(int $id_category, string $name, string $description, int $parent, string $image): void
{
	global $smcFunc;

	$updated = time();

	// Execute the query
// @TODO query
	$smcFunc['db_query']('', '
		UPDATE		{db_prefix}quiz_category
		SET			id_parent = {int:parent},
					name = {string:name},
					description = {string:description},
					image = {string:image},
					updated = {int:updated}
		WHERE		id_category = {int:id_category}',
		[
			'parent' => $parent,
			'name' => $smcFunc['db_escape_string'] ($name),
			'description' => $smcFunc['db_escape_string'] ($description),
			'image' => $smcFunc['db_escape_string'] ($image),
			'updated' => $updated,
			'id_category' => $id_category,
		]
	);
}

// Data class for saving category details
/**
 * Save Category.
 *
 * @param string $name The name value.
 * @param string $description The description value.
 * @param int $id_parent The id parent value.
 * @param string $image The image value.
 * @return void
 */
function SaveCategory(string $name, string $description, int $id_parent, string $image): void
{
	global $smcFunc;

	// Execute the query
	$smcFunc['db_insert']('insert', 
		'{db_prefix}quiz_category',
		[
			'name' => 'string',
			'description' => 'string',
			'id_parent' => 'int',
			'image' => 'string'
		],
		[
			$smcFunc['db_escape_string'] ($name),
			$smcFunc['db_escape_string'] ($description),
			$id_parent,
			$smcFunc['db_escape_string'] ($image)
		],
		['id_category']
	);
}

// Data class for deleting questions
/**
 * Delete Questions.
 *
 * @param string $questionInIds The questionInIds value.
 * @return void
 */
function DeleteQuestions(string $questionInIds): void
{
	global $db_prefix, $smcFunc;

	$questionIds = SMFQuizParseIntList($questionInIds);
	if ($questionIds === []) {
		return;
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}quiz_question
		WHERE		id_question IN ({array_int:question_ids})',
		[
			'question_ids' => $questionIds,
		]
	);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}quiz_answer
		WHERE		id_question IN ({array_int:question_ids})',
		[
			'question_ids' => $questionIds,
		]
	);
}

// Data class for deleting quiz leagues
/**
 * Delete Quiz Leagues.
 *
 * @param string $quizLeagueInIds The quizLeagueInIds value.
 * @return void
 */
function DeleteQuizLeagues(string $quizLeagueInIds): void
{
	global $smcFunc, $db_prefix;

	$quizLeagueIds = SMFQuizParseIntList($quizLeagueInIds);
	if ($quizLeagueIds === []) {
		return;
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}quiz_league
		WHERE		id_quiz_league IN ({array_int:quiz_league_ids})',
		[
			'quiz_league_ids' => $quizLeagueIds,
		]
	);
}

// Data class for deleting quiz leagues
/**
 * Delete Quizes.
 *
 * @param string $quizInIds The quizInIds value.
 * @return void
 */
function DeleteQuizes(string $quizInIds): void
{
	global $smcFunc, $db_prefix;

	$quizIds = SMFQuizParseIntList($quizInIds);
	if ($quizIds === []) {
		return;
	}

	foreach ($quizIds as $quizId) {
		$result = $smcFunc['db_query']('', '
			SELECT 		id_category
			FROM 		{db_prefix}quiz Q
			WHERE		Q.id_quiz = {int:id_quiz}',
			[
				'id_quiz' => $quizId,
			]
		);

		while ($row = $smcFunc['db_fetch_assoc']($result)) {
			$categoryId = (int) $row['id_category'];
			DecrementCategoryTree($categoryId);
		}
		$smcFunc['db_free_result']($result);

		$smcFunc['db_query']('', '
			DELETE
			FROM		{db_prefix}quiz_question
			WHERE		id_quiz = {int:id_quiz}',
			[
				'id_quiz' => $quizId,
			]
		);
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}quiz
		WHERE		id_quiz IN ({array_int:quiz_ids})',
		[
			'quiz_ids' => $quizIds,
		]
	);
}

// Data class for deleting quiz disputes
/**
 * Delete Quiz Disputes.
 *
 * @param string $quizDisputeInIds The quizDisputeInIds value.
 * @return void
 */
function DeleteQuizDisputes(string $quizDisputeInIds): void
{
	global $smcFunc, $db_prefix;

	$quizDisputeIds = SMFQuizParseIntList($quizDisputeInIds);
	if ($quizDisputeIds === []) {
		return;
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}quiz_dispute
		WHERE		id_quiz_dispute IN ({array_int:quiz_dispute_ids})',
		[
			'quiz_dispute_ids' => $quizDisputeIds,
		]
	);
}

// Data class for deleting quiz results
/**
 * Delete Quiz Results.
 *
 * @param string $quizResultInIds The quizResultInIds value.
 * @return void
 */
function DeleteQuizResults(string $quizResultInIds): void
{
	global $smcFunc, $db_prefix;

	$quizResultIds = SMFQuizParseIntList($quizResultInIds);
	if ($quizResultIds === []) {
		return;
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}quiz_result
		WHERE		id_quiz_result IN ({array_int:quiz_result_ids})',
		[
			'quiz_result_ids' => $quizResultIds,
		]
	);
}

/**
 * Get Latest Quizes.
 *
 * @return array
 */
function GetLatestQuizes(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		Q.id_quiz,
					Q.title,
					Q.image,
					Q.updated
		FROM 		{db_prefix}quiz Q
		WHERE		Q.enabled = 1
		ORDER BY	Q.updated DESC
		LIMIT		0, 8'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['latestQuizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['latestQuizes'][] = $row;
	}

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['latestQuizes'];

}

/**
 * Get Popular Quizes.
 *
 * @param int $limit The limit value.
 * @return array
 */
function GetPopularQuizes(int $limit): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		Q.id_quiz,
					Q.title,
					Q.image,
					Q.quiz_plays,
					Q.updated
		FROM 		{db_prefix}quiz Q
		WHERE		Q.enabled = 1
		ORDER BY	Q.quiz_plays DESC
		LIMIT		0, {int:limit}',
		[
			'limit' => $limit
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['popularQuizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['popularQuizes'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['popularQuizes'];

}

/**
 * Get Quiz League Leaders.
 *
 * @param int $limit The limit value.
 * @return array
 */
function GetQuizLeagueLeaders(int $limit): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		QL.id_leader,
					QL.id_quiz_league,
					QL.title,
					M.real_name,
					QL.updated
		FROM 		{db_prefix}quiz_league QL
		LEFT JOIN 	{db_prefix}members M
		ON 			QL.id_leader = M.id_member
		ORDER BY 	QL.updated DESC
		LIMIT		0, {int:limit}',
		[
			'limit' => $limit,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quizLeagueLeaders'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizLeagueLeaders'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizLeagueLeaders'];

}

/**
 * Get Quiz Masters.
 *
 * @param int $limit The limit value.
 * @return array
 */
function GetQuizMasters(int $limit): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		Q.top_user_id AS id_user,
					M.real_name,
					COUNT(*) AS total_wins
		FROM 		{db_prefix}quiz Q
		INNER JOIN 	{db_prefix}members M
		ON 			Q.top_user_id = M.id_member
		WHERE		Q.top_user_id <> 0
		GROUP BY 	Q.top_user_id, M.real_name 
		ORDER BY 	total_wins DESC
		LIMIT		0, {int:limit}',
		[
			'limit' => $limit,
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['quizMasters'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizMasters'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizMasters'];

}

/**
 * Get Latest Info Board.
 *
 * @param int $limit The limit value.
 * @return array
 */
function GetLatestInfoBoard(int $limit = 20): array
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '	
		SELECT 		I.entry_date,
					I.Entry
		FROM 		{db_prefix}quiz_infoboard I
		ORDER BY	I.entry_date DESC
		LIMIT		0, {int:limit}',
		[
			'limit' => $limit,
		]
	);

	$context['SMFQuiz']['infoBoard'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['infoBoard'][] = $row;
	}

	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['infoBoard'];
}

// Data class for deleting categories
/**
 * Delete Categories.
 *
 * @param string $categoryInIds The categoryInIds value.
 * @return void
 */
function DeleteCategories(string $categoryInIds): void
{
	global $smcFunc, $db_prefix;

	$categoryIds = SMFQuizParseIntList($categoryInIds);
	if ($categoryIds === []) {
		return;
	}

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}quiz_category
		WHERE		id_category IN ({array_int:category_ids})
		OR			id_parent IN ({array_int:category_ids})',
		[
			'category_ids' => $categoryIds,
		]
	);
}

/**
 * Increment Category Tree.
 *
 * @param int $id_category The id category value.
 * @return void
 */
function IncrementCategoryTree(int $id_category): void
{
	global $smcFunc;

	// Execute the query
// @TODO query
	$smcFunc['db_query']('', '
		UPDATE		{db_prefix}quiz_category
		SET			quiz_count = quiz_count + 1
		WHERE		id_category = {int:id_category}',
		[
			'id_category' => $id_category
		]
	);

	// Now walk up the tree and increment any parent category quiz counts
	$parentId = -1;
	while ($parentId !== 0)
	{
// @TODO query
		$parentIdResult = $smcFunc['db_query']('', '
			SELECT		id_parent
			FROM		{db_prefix}quiz_category
			WHERE		id_category = {int:id_category}',
			[
				'id_category' => $id_category,
			]
		);
		$rows = $smcFunc['db_num_rows']($parentIdResult);
		if ($rows > 0)
		{
			while ($parentIdRow = $smcFunc['db_fetch_assoc']($parentIdResult))
				$parentId = $parentIdRow['id_parent'];

			// Free the database
			$smcFunc['db_free_result']($parentIdResult);		
		}
		else
			$parentId = 0;

		if ($parentId !== 0)
		{
// @TODO query
			$smcFunc['db_query']('', '
				UPDATE		{db_prefix}quiz_category
				SET			quiz_count = quiz_count + 1
				WHERE		id_category = {int:id_category}',
				[
					'id_category' => $parentId,
				]				
			);
			$id_category = $parentId;
		}
	}
}

/**
 * Decrement Category Tree.
 *
 * @param int $id_category The id category value.
 * @return void
 */
function DecrementCategoryTree(int $id_category): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		UPDATE 		{db_prefix}quiz_category QC
		SET 		QC.quiz_count = QC.quiz_count - 1
		WHERE 		QC.id_category = {int:id_category}
		AND			QC.quiz_count > 0',
		[
			'id_category' => $id_category,
		]
	);

	// Now walk up the tree and decrement any parent category quiz counts
	$parentId = -1;
	while ($parentId !== 0)
	{
// @TODO query
		$parentIdResult = $smcFunc['db_query']('', '
			SELECT 		C.id_parent
			FROM 		{db_prefix}quiz_category C
			WHERE		C.id_category = {int:id_category}',
			[
                    'id_category' => $id_category,
            ]
		);
		$rows = $smcFunc['db_num_rows']($parentIdResult);
		if ($rows > 0)
		{
			while ($parentIdRow = $smcFunc['db_fetch_assoc']($parentIdResult))
				$parentId = $parentIdRow['id_parent'];

		}
		else
			$parentId = 0;

		$smcFunc['db_free_result']($parentIdResult);

		if ($parentId !== 0)
		{
// @TODO query
			$smcFunc['db_query']('', '
				UPDATE 		{db_prefix}quiz_category QC
				SET 		QC.quiz_count = QC.quiz_count - 1
				WHERE 		QC.id_category = {int:id_category}
				AND			QC.quiz_count > 0',
				[
	                    'id_category' => $parentId,
	            ]
			);
			$id_category = $parentId;
		}
	}
}

/**
 * Get Total Quizes.
 *
 * @return int
 */
function GetTotalQuizes(): int
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT 		COUNT(*) AS total_quiz_count
		FROM		{db_prefix}quiz
		LIMIT		0, 1'
	);

	$count = 0;
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$count = (int) $row['total_quiz_count'];
		$context['SMFQuiz']['totalQuizes'] = [$count];
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

/**
 * Get Total Disputes Count.
 *
 * @return int
 */
function GetTotalDisputesCount(): int
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '	
		SELECT 		COUNT(*) AS total_disputes_count
		FROM 		{db_prefix}quiz_dispute'
	);

	$count = 0;
	$context['SMFQuiz_totalDisputes'] = 0;
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$count = (int) $row['total_disputes_count'];
		$context['SMFQuiz_totalDisputes'] = $count;
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

/**
 * Get Total Review Count.
 *
 * @return int
 */
function GetTotalReviewCount(): int
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '	
		SELECT 		COUNT(*) AS total_review_count
		FROM 		{db_prefix}quiz
		WHERE		for_review = 1'
	);

	$count = 0;
	$context['SMFQuiz_totalQuizesWaitingReview'] = 0;
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$count = (int) $row['total_review_count'];
		$context['SMFQuiz_totalQuizesWaitingReview'] = $count;
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

/**
 * Get Disabled Quiz Count.
 *
 * @return int
 */
function GetDisabledQuizCount(): int
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '	
		SELECT 		COUNT(*) AS total_diabled_quizes_count
		FROM 		{db_prefix}quiz
		WHERE		enabled = 0'
	);

	$count = 0;
	$context['SMFQuiz_totalDisabledQuizes'] = 0;
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$count = (int) $row['total_diabled_quizes_count'];
		$context['SMFQuiz_totalDisabledQuizes'] = $count;
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

/**
 * Get Total Quiz Stats.
 *
 * @return array
 */
function GetTotalQuizStats(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		COUNT(*) AS total_quiz_count,
					SUM(quiz_plays) AS total_quiz_plays,
					SUM(question_plays) AS total_question_plays,
					SUM(total_correct) AS total_correct,
					round((SUM(total_correct) / SUM(question_plays)) * 100) AS total_percentage_correct
		FROM 		{db_prefix}quiz'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['totalQuizStats'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['totalQuizStats'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['totalQuizStats'];

}

/**
 * Get Best Quiz Result.
 *
 * @return array
 */
function GetBestQuizResult(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		M.real_name, 
					QR.total_seconds, 
					QR.questions, 
					QR.result_date, 
					QR.correct,
					round((QR.correct / QR.questions) * 100) AS percentage_correct
		FROM 		{db_prefix}quiz_result QR
		INNER JOIN 	{db_prefix}members M
		ON 			QR.id_user = M.id_member
		ORDER BY 	percentage_correct DESC,
					questions DESC,
					total_seconds ASC,
					result_date ASC
		LIMIT 		0, 1'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['bestQuizResult'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['bestQuizResult'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['bestQuizResult'];

}

/**
 * Get Worst Quiz Result.
 *
 * @return array
 */
function GetWorstQuizResult(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		M.real_name, 
					QR.total_seconds, 
					QR.questions, 
					QR.result_date, 
					QR.correct,
					round((QR.correct / QR.questions) * 100) AS percentage_correct
		FROM 		{db_prefix}quiz_result QR
		INNER JOIN 	{db_prefix}members M
		ON 			QR.id_user = M.id_member
		ORDER BY 	percentage_correct ASC,
					questions ASC,
					total_seconds DESC,
					result_date ASC
		LIMIT 		0,1'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['worstQuizResult'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['worstQuizResult'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['worstQuizResult'];

}

/**
 * Get Hardest Quizes.
 *
 * @return array
 */
function GetHardestQuizes(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		Q.id_quiz, 
					Q.title,
					round((Q.question_plays - Q.total_correct) / Q.question_plays * 100) AS percentage_incorrect
		FROM 		{db_prefix}quiz Q
		WHERE		question_plays > 0
		AND			Q.enabled = 1
		ORDER BY 	percentage_incorrect DESC
		LIMIT 		0, 10'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['hardestQuizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['hardestQuizes'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['hardestQuizes'];

}

/**
 * Get Easiest Quizes.
 *
 * @return array
 */
function GetEasiestQuizes(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		Q.id_quiz, 
					Q.title,
					round(Q.total_correct / Q.question_plays * 100) AS percentage_correct
		FROM 		{db_prefix}quiz Q
		WHERE		question_plays > 0
		AND			Q.enabled = 1
		ORDER BY 	percentage_correct DESC
		LIMIT 		0, 10'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['easiestQuizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['easiestQuizes'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['easiestQuizes'];

}

/**
 * Get Newest Quiz.
 *
 * @return array
 */
function GetNewestQuiz(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		Q.id_quiz, 
					Q.title,
					Q.updated
		FROM 		{db_prefix}quiz Q
		WHERE		Q.enabled = 1
		ORDER BY 	Q.updated ASC
		LIMIT		0, 1'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['oldestQuiz'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['oldestQuiz'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['oldestQuiz'];

}

/**
 * Get Oldest Quiz.
 *
 * @return array
 */
function GetOldestQuiz(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		Q.id_quiz, 
					Q.title,
					Q.updated
		FROM 		{db_prefix}quiz Q
		WHERE		Q.enabled = 1
		ORDER BY 	Q.updated DESC
		LIMIT		0, 1'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['newestQuiz'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['newestQuiz'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['newestQuiz'];

}

/**
 * Most Quiz Wins.
 *
 * @return array
 */
function MostQuizWins(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		Q.top_user_id,
					M.real_name,
					COUNT(Q.top_user_id) AS TopScores
		FROM 		{db_prefix}quiz Q
		INNER JOIN	{db_prefix}members M
		ON			Q.top_user_id = M.id_member
		WHERE 		top_user_id != 0
		GROUP BY 	top_user_id,
					M.real_name
		ORDER BY	TopScores DESC
		LIMIT		0, 1'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['mostQuizWins'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['mostQuizWins'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['mostQuizWins'];

}

/**
 * Get Total Questions.
 *
 * @return int
 */
function GetTotalQuestions(): int
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '	
		SELECT 		COUNT(*) AS total_question_count
		FROM		{db_prefix}quiz_question
		LIMIT		0, 1'
	);

	$count = 0;
	$context['SMFQuiz']['totalQuestions'] = 0;
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$count = (int) $row['total_question_count'];
		$context['SMFQuiz']['totalQuestions'] = $count;
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

/**
 * Get Total Answers.
 *
 * @return int
 */
function GetTotalAnswers(): int
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT 		COUNT(*) AS total_answers
		FROM		{db_prefix}quiz_answer
		LIMIT		0, 1'
	);

	$count = 0;
	$context['SMFQuiz']['totalAnswers'] = 0;
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$count = (int) $row['total_answers'];
		$context['SMFQuiz']['totalAnswers'] = $count;
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

/**
 * Get Total Categories.
 *
 * @return int
 */
function GetTotalCategories(): int
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT 		COUNT(*) AS total_category_count
		FROM		{db_prefix}quiz_category
		LIMIT		0, 1'
	);

	$count = 0;
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$count = (int) $row['total_category_count'];
		$context['SMFQuiz']['totalCategories'] = $count;
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

/**
 * Get Member Statistics.
 *
 * @param int $id_user The id user value.
 * @return array
 */
function GetMemberStatistics(int $id_user): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		SUM(QR.questions) AS total_questions,
					SUM(QR.correct) AS total_correct,
					SUM(QR.incorrect) AS total_incorrect,
					SUM(QR.timeouts) AS total_timeouts,
					SUM(QR.total_seconds) AS total_seconds,
					COUNT(*) AS total_played,
					round((SUM(QR.correct) / SUM(QR.questions)) * 100) AS percentage_correct
		FROM 		{db_prefix}quiz_result QR
		WHERE 		QR.id_user = {int:id_user}',
		[
			'id_user' => $id_user
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['memberStatistics'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['memberStatistics'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['memberStatistics'];

}

/**
 * Get User Quiz Scores.
 *
 * @param int $id_user The id user value.
 * @return array
 */
function GetUserQuizScores(int $id_user): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT		Q.title,
					Q.id_quiz,
					QR.result_date,
					QR.questions,
					QR.correct,
					QR.incorrect,
					QR.timeouts,
					QR.total_seconds,
					IFNULL(round((QR.correct / QR.questions) * 100),0) AS percentage_correct,
					QR.auto_completed
		FROM		{db_prefix}quiz_result QR
		INNER JOIN	{db_prefix}quiz Q
		ON 			QR.id_quiz = Q.id_quiz
		WHERE 		QR.id_user = {int:id_user}
		ORDER BY	QR.result_date DESC
		LIMIT		0, 10',
		[
			'id_user' => $id_user
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['userQuizScores'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['userQuizScores'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['userQuizScores'];

}

/**
 * Get User Correct Scores.
 *
 * @param int $id_user The id user value.
 * @return array
 */
function GetUserCorrectScores(int $id_user): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		QR.correct, 
					COUNT(QR.correct) AS count_correct
		FROM 		{db_prefix}quiz_result QR
		WHERE 		QR.id_user = {int:id_user}
		GROUP BY 	QR.correct',
		[
			'id_user' => $id_user
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['userCorrectScores'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['userCorrectScores'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['userCorrectScores'];

}

/**
 * Get User Category Plays.
 *
 * @param int $id_user The id user value.
 * @return array
 */
function GetUserCategoryPlays(int $id_user): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		QC.id_category,
					QC.name,
					COUNT(*) AS category_plays
		FROM 		{db_prefix}quiz_result QR
		INNER JOIN 	{db_prefix}quiz Q
		ON 			QR.id_quiz = Q.id_quiz
		INNER JOIN 	{db_prefix}quiz_category QC
		ON 			Q.id_category = QC.id_category
		WHERE 		QR.id_user = {int:id_user}
		GROUP 		BY QC.id_category,
					QC.name
		ORDER BY 	category_plays DESC',
		[
			'id_user' => $id_user
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['userCategoryPlays'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['userCategoryPlays'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['userCategoryPlays'];

}

/**
 * Get Quiz Sessions.
 *
 * @param int $id_user The id user value.
 * @return array
 */
function GetQuizSessions(int $id_user): array
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT 		QS.question_count,
					QS.last_question_start,
					Q.id_quiz,
					Q.title
		FROM 		{db_prefix}quiz_session QS
		INNER JOIN 	{db_prefix}quiz Q
		ON 			QS.id_quiz = Q.id_quiz
		WHERE 		id_user = {int:id_user}',
		[
			'id_user' => $id_user,
		]
	);

	$context['SMFQuiz']['quizSessions'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$context['SMFQuiz']['quizSessions'][] = $row;
	}

	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['quizSessions'];
}

/**
 * Get User Quizes.
 *
 * @param int $id_user The id user value.
 * @return array
 */
function GetUserQuizes(int $id_user): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '
		SELECT 		Q.id_quiz,
					Q.title,
					Q.updated,
					Q.enabled,
					Q.for_review,
					QC.name AS category_name,
					IFNULL(COUNT(QQ.id_quiz),0) AS questions_per_session
		FROM		{db_prefix}quiz Q
		INNER JOIN	{db_prefix}quiz_category QC
		ON			Q.id_category = QC.id_category
		LEFT JOIN	{db_prefix}quiz_question QQ
		ON			Q.id_quiz = QQ.id_quiz
		WHERE 		Q.creator_id = {int:id_user}
		GROUP BY	Q.id_quiz,
					Q.title,
					Q.updated,
					Q.enabled,
					Q.for_review,
					QQ.id_quiz,
					category_name
		ORDER BY	Q.title',
		[
			'id_user' => $id_user
		]
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['userQuizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['userQuizes'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['userQuizes'];

}

/**
 * Set Quiz For Review.
 *
 * @param int $id_quiz The id quiz value.
 * @return void
 */
function SetQuizForReview(int $id_quiz): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		UPDATE		{db_prefix}quiz Q
		SET			Q.for_review = 1
		WHERE 		Q.id_quiz = {int:id_quiz}',
		[
			'id_quiz' => $id_quiz
		]
	);
}

/**
 * Get Total User Wins.
 *
 * @param int $id_user The id user value.
 * @return int
 */
function GetTotalUserWins(int $id_user): int
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT		COUNT(*) AS total_user_wins
		FROM		{db_prefix}quiz Q
		WHERE		top_user_id = {int:id_user}',
		[
			'id_user' => $id_user,
		]
	);

	$count = 0;
	$context['SMFQuiz']['total_user_wins'] = 0;
	while ($row = $smcFunc['db_fetch_assoc']($result)) {
		$count = (int) $row['total_user_wins'];
		$context['SMFQuiz']['total_user_wins'] = $count;
	}

	$smcFunc['db_free_result']($result);

	return $count;
}

/**
 * Get Most Active Players.
 *
 * @return array
 */
function GetMostActivePlayers(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT  	QR.id_user,
					M.real_name,
					COUNT(QR.id_user) as total_plays
		FROM 		{db_prefix}quiz_result QR
		INNER JOIN 	{db_prefix}members M
		ON 			QR.id_user = M.id_member
		GROUP BY 	QR.id_user, 
					M.real_name
		ORDER BY	total_plays DESC
		LIMIT		0, 10'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['mostActivePlayers'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['mostActivePlayers'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['mostActivePlayers'];

}

/**
 * Get Most Quiz Creators.
 *
 * @return array
 */
function GetMostQuizCreators(): array
{
	global $context, $smcFunc;

	$result = $smcFunc['db_query']('', '	
		SELECT 		COUNT(*) AS quizes, 
					Q.creator_id, 
					M.real_name
		FROM 		{db_prefix}quiz Q
		INNER JOIN 	{db_prefix}members M
		ON 			Q.creator_id = M.id_member
		GROUP BY 	Q.creator_id, M.real_name 
		ORDER BY 	quizes DESC
		LIMIT		0, 10'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['mostQuizCreators'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['mostQuizCreators'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['mostQuizCreators'];

}

/**
 * Import Quiz.
 *
 * @param string $title The title value.
 * @param string $description The description value.
 * @param int $play_limit The play limit value.
 * @param int $seconds_per_question The seconds per question value.
 * @param int $show_answers The show answers value.
 * @param int $id_category The id category value.
 * @param int $enabled The enabled value.
 * @param string $image The image value.
 * @param int $creator_id The creator id value.
 * @return int|string
 */
function ImportQuiz(string $title, string $description, int $play_limit, int $seconds_per_question, int $show_answers, int $id_category, int $enabled, string $image, int $creator_id): int|string
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_quiz
		FROM {db_prefix}quiz
		WHERE title = {string:quiz_title}
		LIMIT 1',
		[
			'quiz_title' => $title,
		]
	);
	if ($smcFunc['db_num_rows']($request) > 0)
		return 'quiz_alredy_exists';

	// Add the quiz to the quiz table
	$smcFunc['db_insert']('insert', 
		'{db_prefix}quiz',
		[
			'title' => 'string',
			'description' => 'string',
			'play_limit' => 'int',
			'seconds_per_question' => 'int',
			'show_answers' => 'int',
			'image' => 'string',
			'id_category' => 'int',
			'enabled' => 'int',
			'updated' => 'int',
			'creator_id' => 'int',
		],
		[
			$smcFunc['db_escape_string'] ($title),
			$smcFunc['db_escape_string'] ($description),
			intval($play_limit),
			intval($seconds_per_question),
			intval($show_answers),
			$smcFunc['db_escape_string'] ($image),
			intval($id_category),
			intval($enabled),
			time(),
			intval($creator_id),
		],
		['id_quiz']
	);
 
	// Retrieve the ID for the inserted quiz
	$import_quiz['id_quiz'] = $smcFunc['db_insert_id']('{db_prefix}quiz', 'id_quiz');

	// Update category count
	IncrementCategoryTree($id_category);

	return (int) $import_quiz['id_quiz'];
}

/**
 * Import Quiz Question.
 *
 * @param int $id_quiz The id quiz value.
 * @param string $question_text The question text value.
 * @param int $id_question_type The id question type value.
 * @param string $answer_text The answer text value.
 * @param string $image The image value.
 * @param string $imageData The imageData value.
 * @return int
 */
function ImportQuizQuestion(int $id_quiz, string $question_text, int $id_question_type, string $answer_text, string $image, string $imageData): int
{
	global $smcFunc, $settings, $sourcedir;

	$image = trim($image);
	$validImageTypes = [
		1 => 'gif',
		2 => 'jpeg',
		3 => 'png',
		5 => 'psd',
		6 => 'bmp',
		7 => 'tiff',
		8 => 'tiff',
		9 => 'jpeg',
		14 => 'iff',
	];

	if ($image !== '') {
		$dest = $settings['default_theme_dir'] . '/images/quiz_images/Questions/' . $image;
		if (!file_exists($dest) && is_writable($settings['default_theme_dir'] . '/images/quiz_images/Questions/')) {
			$imageData = base64_decode($imageData);
			file_put_contents($dest, $imageData);
			$size = @getimagesize($dest);
			$fileType = $size[2] ?? 3;
			if (!isset($validImageTypes[$fileType])) {
				$fileType = 3;
			}
			require_once($sourcedir . '/Subs-Graphics.php');
			if (!reencodeImage($dest, $fileType)) {
				@unlink($dest);
				@unlink($dest . '.tmp');
			}
		}
	}

	$smcFunc['db_insert']('insert', 
		'{db_prefix}quiz_question',
		[
			'question_text' => 'string',
			'id_question_type' => 'int',
			'id_quiz' => 'int',
			'answer_text' => 'string',
			'image' => 'string',
			'updated' => 'int'
		],
		[
			$smcFunc['db_escape_string']($question_text),
			(int) $id_question_type,
			(int) $id_quiz,
			$smcFunc['db_escape_string']($answer_text),
			$smcFunc['db_escape_string']($image),
			time()
		],
		['id_question']
	);

	$import_question['id_question'] = $smcFunc['db_insert_id']('{db_prefix}quiz_question', 'id_question');

	return (int) $import_question['id_question'];
}

/**
 * Import Quiz Answer.
 *
 * @param int $id_question The id question value.
 * @param string $answer_text The answer text value.
 * @param int $is_correct The is correct value.
 * @return void
 */
function ImportQuizAnswer(int $id_question, string $answer_text, int $is_correct): void
{
	global $smcFunc;

	$updated = time();

	$smcFunc['db_insert']('insert', 
		'{db_prefix}quiz_answer',
		[
			'id_question' => 'int',
			'answer_text' => 'string',
			'is_correct' => 'int',
			'updated' => 'int'
		],
		[
			intval($id_question),
			$smcFunc['db_escape_string'] ($answer_text),
			intval($is_correct),
			$updated
		],
		['id_answer']
	);

	$import_answer['id_answer'] = $smcFunc['db_insert_id']('{db_prefix}quiz_answer', 'id_answer');

	return;
}

/**
 * Export Quizes.
 *
 * @param array $quizIds The quizIds value.
 * @return array
 */
function ExportQuizes(array $quizIds): array
{
	global $smcFunc, $db_prefix, $settings;

	$exportQuizesResult = $smcFunc['db_query']('', '
		SELECT Q.id_quiz, Q.title, Q.description, Q.play_limit, Q.seconds_per_question,
			Q.show_answers, Q.image, QC.name AS category_name
		FROM {db_prefix}quiz Q
		LEFT JOIN {db_prefix}quiz_category QC
			ON Q.id_category = QC.id_category
		WHERE id_quiz IN ({array_int:quizzes_id})',
		[
			'quizzes_id' => $quizIds,
		]
	);

	// Loop through the results and populate the context accordingly
	$exportQuizesReturn = [];
	while ($row = $smcFunc['db_fetch_assoc']($exportQuizesResult))
	{
		$imgDir = $settings['default_theme_dir'] . '/images/quiz_images/Quizes/' . $row['image'];
		if (file_exists($imgDir))
			$row['image_data'] = base64_encode(file_get_contents($imgDir));
		else
			$row['image_data'] = '';
		$exportQuizesReturn[] = $row;
	}

	// Free the database
	$smcFunc['db_free_result']($exportQuizesResult);

	return $exportQuizesReturn;
}

/**
 * Export Quiz Questions.
 *
 * @param int $id_quiz The id quiz value.
 * @return array
 */
function ExportQuizQuestions(int $id_quiz): array
{
	global $smcFunc, $settings;

// @TODO query
	$exportQuizQuestionResult = $smcFunc['db_query']('', '
		SELECT id_question, question_text, id_question_type,
			answer_text, image
		FROM {db_prefix}quiz_question
		WHERE id_quiz = {int:id_quiz}',
		[
			'id_quiz' => $id_quiz
		]
	);

	// Loop through the results and populate the context accordingly
	$exportQuizQuestionsReturn = [];
	while ($row = $smcFunc['db_fetch_assoc']($exportQuizQuestionResult))
	{
		$imgDir = $settings['default_theme_dir'] . '/images/quiz_images/Questions/' . $row['image'];
		if (file_exists($imgDir))
			$row['image_data'] = base64_encode(file_get_contents($imgDir));
		else
			$row['image_data'] = '';
		$exportQuizQuestionsReturn[] = $row;
	}

	// Free the database
	$smcFunc['db_free_result']($exportQuizQuestionResult);

	return $exportQuizQuestionsReturn;
}

/**
 * Export Quiz Answers.
 *
 * @param int $id_question The id question value.
 * @return array
 */
function ExportQuizAnswers(int $id_question): array
{
	global $smcFunc;

// @TODO query
	$exportQuestionAnswersResult = $smcFunc['db_query']('', '
		SELECT		answer_text,
					is_correct
		FROM		{db_prefix}quiz_answer
		WHERE		id_question = {int:id_question}',
		[
			'id_question' => $id_question
		]
	);

	// Loop through the results and populate the context accordingly
	$exportQuestionAnswersReturn = [];
	while ($row = $smcFunc['db_fetch_assoc']($exportQuestionAnswersResult))
		$exportQuestionAnswersReturn[] = $row;

	// Free the database
	$smcFunc['db_free_result']($exportQuestionAnswersResult);

	return $exportQuestionAnswersReturn;
}

/**
 * Reset Quiz Top Scores.
 *
 * @return void
 */
function ResetQuizTopScores(): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		UPDATE		{db_prefix}quiz
		SET			quiz_plays = 0,
					question_plays = 0,
					total_correct = 0,
					top_user_id = 0,
					top_correct = 0,
					top_time = 0'
	);
}

/**
 * Reset Quiz Results.
 *
 * @return void
 */
function ResetQuizResults(): void
{
	global $smcFunc;

// @TODO permissions?
	$smcFunc['db_query']('', '
		TRUNCATE TABLE {db_prefix}quiz_result'
	);
}

/**
 * Delete Info Board Entries.
 *
 * @param int $date The date value.
 * @return void
 */
function DeleteInfoBoardEntries(int $date): void
{
	global $smcFunc;

// @TODO permissions?
	$smcFunc['db_query']('', '
		DELETE
		FROM 		{db_prefix}quiz_infoboard
		WHERE		entry_date < {int:date}',
		[
			'date' => $date
		]
	);
}

/**
 * Complete Quiz Sessions.
 *
 * @param int $date The date value.
 * @return int
 */
function CompleteQuizSessions(int $date): int
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT		id_quiz_session,
					question_count,
					timeouts,
					correct,
					incorrect,
					id_quiz,
					id_user,
					total_seconds
		FROM		{db_prefix}quiz_session
		WHERE		last_question_start < {int:date}',
		[
			'date' => $date
		]
		
	);

	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
// @TODO query+performance?
		$smcFunc['db_insert']('insert', 
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
				'auto_completed' => 'int'
			],
			[
				$row['id_quiz'],
				$row['id_user'],
				time(),
				$row['question_count'],
				$row['correct'],
				$row['incorrect'],
				$row['timeouts'],
				$row['total_seconds'],
				1
			],
			['id_quiz_result']
		);

// @TODO query
		$smcFunc['db_query']('', '
			DELETE
			FROM		{db_prefix}quiz_session
			WHERE		id_quiz_session = {string:id_quiz_session}',
			[
				'id_quiz_session' => $row['id_quiz_session']
			]
		);
	}

	$rows = $smcFunc['db_num_rows']($result);

	// Free the database
	$smcFunc['db_free_result']($result);

	return (int) $rows;
}

/**
 * Find Orphaned Answers Data.
 *
 * @return array
 */
function FindOrphanedAnswersData(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT  	id_answer,
					id_question,
					answer_text,
					updated
		FROM   		{db_prefix}quiz_answer
		WHERE  		id_question NOT IN
		(
			SELECT 		id_question 
			FROM 		{db_prefix}quiz_question
		)'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['findOrphanedAnswers'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['findOrphanedAnswers'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['findOrphanedAnswers'];

}

/**
 * Find Orphaned Questions Data.
 *
 * @return array
 */
function FindOrphanedQuestionsData(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		id_question,
					id_quiz,
					question_text,
					updated
		FROM 		{db_prefix}quiz_question
		WHERE 		id_quiz NOT IN (
			SELECT 		id_quiz
			FROM 		{db_prefix}quiz
		)'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['findOrphanedQuestions'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['findOrphanedQuestions'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['findOrphanedQuestions'];

}

/**
 * Find Orphaned Quiz Results Data.
 *
 * @return array
 */
function FindOrphanedQuizResultsData(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		id_quiz_result,
					id_quiz,
					id_user,
					result_date
		FROM 		{db_prefix}quiz_result
		WHERE 		id_quiz NOT IN (
			SELECT 		id_quiz
			FROM 		{db_prefix}quiz
		)
		OR			id_user NOT IN (
			SELECT		id_member
			FROM		{db_prefix}members
		)'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['findOrphanedQuizResults'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['findOrphanedQuizResults'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['findOrphanedQuizResults'];

}

/**
 * Find Orphaned Categories Data.
 *
 * @return array
 */
function FindOrphanedCategoriesData(): array
{
	global $context, $smcFunc;

// @TODO query
	$result = $smcFunc['db_query']('', '	
		SELECT 		id_category,
					id_parent,
					name,
					updated
		FROM 		{db_prefix}quiz_category
		WHERE 		id_parent NOT IN (
			SELECT 		id_category
			FROM 		{db_prefix}quiz_category
		) 
		AND 		id_parent != 0'
	);

	// Loop through the results and populate the context accordingly
	$context['SMFQuiz']['findOrphanedCategories'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['findOrphanedCategories'][] = $row;

	// Free the database
	$smcFunc['db_free_result']($result);

	return $context['SMFQuiz']['findOrphanedCategories'];

}

/**
 * Delete Orphaned Questions Data.
 *
 * @return void
 */
function DeleteOrphanedQuestionsData(): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		DELETE
		FROM 		{db_prefix}quiz_question
		WHERE 		id_quiz NOT IN (
			SELECT 		id_quiz
			FROM 		{db_prefix}quiz
		)'
	);
}

/**
 * Delete Orphaned Answers Data.
 *
 * @return void
 */
function DeleteOrphanedAnswersData(): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		DELETE
		FROM   		{db_prefix}quiz_answer
		WHERE  		id_question NOT IN
		(
			SELECT 		id_question 
			FROM 		{db_prefix}quiz_question
		)'
	);
}

/**
 * Delete Orphaned Quiz Results Data.
 *
 * @return void
 */
function DeleteOrphanedQuizResultsData(): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		DELETE
		FROM 		{db_prefix}quiz_result
		WHERE 		id_quiz NOT IN (
			SELECT 		id_quiz
			FROM 		{db_prefix}quiz
		)
		OR			id_user NOT IN (
			SELECT		id_member
			FROM		{db_prefix}members
		)'
	);
}

/**
 * Delete Orphaned Categories Data.
 *
 * @return void
 */
function DeleteOrphanedCategoriesData(): void
{
	global $smcFunc;

	// We can't delete like the other ones, as the query would reference itself and it can't delete in
	// that scenario
// @TODO query
	$findOrphanedCategoriesResult = $smcFunc['db_query']('', '
		SELECT 		id_category
		FROM 		{db_prefix}quiz_category
		WHERE 		id_parent NOT IN (
			SELECT 		id_category
			FROM 		{db_prefix}quiz_category
		) 
		AND 		id_parent != 0'
	);
	
	// Loop through the session results
// @TODO query
	while ($row = $smcFunc['db_fetch_assoc']($findOrphanedCategoriesResult))
		// Delete each category
		$smcFunc['db_query']('', '
			DELETE
			FROM 		{db_prefix}quiz_category
			WHERE 		id_category = {int:id_category}',
			[
				'id_category' => $row['id_category']
			]
		);

	// Free the database
	$smcFunc['db_free_result']($findOrphanedCategoriesResult);	
}

/**
 * Can User Play Quiz League Data.
 *
 * @param int $id_quiz_league The id quiz league value.
 * @param int $id_user The id user value.
 * @return array
 */
function CanUserPlayQuizLeagueData(int $id_quiz_league, int $id_user): array
{
	global $smcFunc, $context;

	$canUserPlayQuizLeagueResult = $smcFunc['db_query']('', '
		SELECT 		QLR.correct,
					QLR.result_date,
					QLR.seconds
		FROM 		{db_prefix}quiz_league_result QLR
		INNER JOIN 	{db_prefix}quiz_league QL
		ON 			QLR.id_quiz_league = QL.id_quiz_league
		WHERE 		QLR.id_quiz_league = {int:id_quiz_league}
		AND 		QLR.id_user = {int:id_user}
		AND 		QLR.round = QL.current_round
		LIMIT		0, 1',
		[
			'id_quiz_league' => $id_quiz_league,
			'id_user' => $id_user,
		]
	);

	$context['SMFQuiz']['CanPlayQuizLeague'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($canUserPlayQuizLeagueResult)) {
		$context['SMFQuiz']['CanPlayQuizLeague'][] = $row;
	}

	$smcFunc['db_free_result']($canUserPlayQuizLeagueResult);

	return $context['SMFQuiz']['CanPlayQuizLeague'];
}

/*
Removes any orphaned quiz disputes. This can happen if the quiz or user is no longer
part of the forum. So this function just removes these entries.
*/
/**
 * Clean Disputes.
 *
 * @return void
 */
function CleanDisputes(): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		DELETE		QD.*
		FROM		{db_prefix}quiz_dispute QD
		LEFT JOIN 	{db_prefix}quiz Q
		ON 			QD.id_quiz = Q.id_quiz
		LEFT JOIN 	{db_prefix}members M
		ON 			QD.id_user = M.id_member
		LEFT JOIN 	{db_prefix}quiz_question QQ
		ON 			QD.id_quiz_question = QQ.id_question
		WHERE 		Q.id_quiz IS NULL 
		OR 			M.id_member IS NULL
		OR 			QQ.id_question IS NULL'
	);
}

/*
Removes any orphaned quiz answers. This could happen if a quiz question was removed,
although the code should be cleaning up this scenario anyway
*/
/**
 * Clean Answers.
 *
 * @return void
 */
function CleanAnswers(): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		DELETE		QA.*
		FROM 		{db_prefix}quiz_answer QA
		LEFT JOIN 	{db_prefix}quiz_question QQ
		ON 			QA.id_question = QQ.id_question
		WHERE 		QQ.id_question IS NULL'
	);
}

/*
Removes any orphaned quiz results. This could happen if a quiz or member was removed
*/
/**
 * Clean Results.
 *
 * @return void
 */
function CleanResults(): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		DELETE		QR.*
		FROM 		{db_prefix}quiz_result QR
		LEFT JOIN 	{db_prefix}quiz Q
		ON 			QR.id_quiz = Q.id_quiz
		LEFT JOIN 	{db_prefix}members M
		ON 			QR.id_user = M.id_member
		WHERE 		Q.id_quiz IS NULL
		OR 			M.id_member IS NULL'
	);
}

/*
Removes any orphaned quiz questions. This could happen if a quiz was removed, but should
be picked up in the code
*/
/**
 * Clean Questions.
 *
 * @return void
 */
function CleanQuestions(): void
{
	global $smcFunc;

// @TODO query
	$smcFunc['db_query']('', '
		DELETE		QQ.*
		FROM 		{db_prefix}quiz_question QQ
		LEFT JOIN 	{db_prefix}quiz Q
		ON 			QQ.id_quiz = Q.id_quiz
		WHERE 		Q.id_quiz IS NULL'
	);
}
?>