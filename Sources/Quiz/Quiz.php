<?php
declare(strict_types=1);


if (!defined('SMF'))
	die('Hacking attempt...');

// Include the SMF2 specific database file
// @TODO move into the function/s
require_once($sourcedir . '/Quiz/Db.php');

/**
 * Handle the SMF quiz frontend hook.
 * @return void
 */
function SMFQuiz(): void
{
	global $context, $txt;

	// Load the language file
	loadLanguage('Quiz/Quiz');
	loadLanguage('Quiz/Admin');

	isAllowedTo('quiz_view');

	$context['page_title'] = $txt['SMFQuiz'];
	addJavaScriptVar('id_user', $context['user']['id'], false);

	if (($context['current_subaction'] ?? '') === 'play')
	{
		$context['template_layers'] = [];
		$context['sub_template'] = 'quiz_play';
	}

	$postQuizId = (int) ($_POST['id_quiz'] ?? 0);
	$getQuizId = (int) ($_GET['id_quiz'] ?? 0);
	if ($postQuizId !== 0)
		$context['id_quiz'] = $postQuizId;
	elseif ($getQuizId !== 0)
		$context['id_quiz'] = $getQuizId;
	else
		$context['id_quiz'] = (int) ($context['id_quiz'] ?? 0);

	// Create an array of possible actions with the functions that will be called
	$actions = [
		'home' => 'GetHomePageData',
		'categories' => 'GetCategoriesData',
		'quizleagues' => 'GetQuizLeaguesData',
		'statistics' => 'GetStatisticsData',
		'userdetails' => 'GetUserDetailsData',
		'userquizes' => 'GetUserQuizesData',
		'addquiz' => 'GetAddQuizData',
		'saveQuizAndAddQuestions' => 'SaveQuizData',
		'saveQuiz' => 'SaveQuizData',
		'saveQuestion' => 'SaveQuestionData',
		'saveQuestionAndAddMore' => 'SaveQuestionData',
		'updateQuestion' => 'GetUpdateQuestionData',
		'updateQuestionAndAddMore' => 'GetUpdateQuestionData',
		'quizQuestions' => 'GetQuestionsData',
		'updateQuiz' => 'GetUpdateQuizData',
		'updateQuizAndAddQuestions' => 'GetUpdateQuizData',
		'deleteQuestion' => 'GetDeleteQuestionData',
		'newQuestion' => 'GetNewQuestionData',
		'deleteQuiz' => 'GetDeleteQuizData',
		'editQuiz' => 'GetEditQuizData',
		'search' => 'QuizSearchXML',
		'quizscores' => 'GetQuizScoresData',
		'quizes' => 'GetQuizesData',
		'quizmasters' => 'GetQuizMastersData',
		'quizleaguetable' => 'GetQuizLeagueData',
		'quizleagueresults' => 'GetQuizLeagueResultsData',
		'unplayedQuizes' => 'GetUnplayedQuizesData',
		'playedQuizes' => 'GetPlayedQuizesData',
		'preview' => 'GetPreviewQuizData',
	];

// @TODO localization
	$context['tab_links'] = [];
	$context['tab_links'][] = [
		'action' => 'home',
		'label' => $txt['SMFQuiz_tabs']['home'] ?? 'Home'
	];
	$context['tab_links'][] = [
		'action' => 'categories',
		'label' => $txt['SMFQuiz_tabs']['categories'] ?? 'Categories'
	];
	$context['tab_links'][] = [
		'action' => 'quizleagues',
		'label' => $txt['SMFQuiz_tabs']['quizleagues'] ?? 'Quiz Leagues'
	];
	$context['tab_links'][] = [
		'action' => 'statistics',
		'label' => $txt['SMFQuiz_tabs']['statistics'] ?? 'Statistics'
	];
	$context['tab_links'][] = [
		'action' => 'userdetails',
		'label' => $txt['SMFQuiz_tabs']['userDetails'] ?? 'User Details',
		'show' => $context['user']['is_logged'],
	];
	$context['tab_links'][] = [
		'action' => 'userquizes',
		'label' => $txt['SMFQuiz_tabs']['userQuizes'] ?? 'User Quizzes'
	];

	$formAction = (string) ($_POST['formaction'] ?? '');
	if ($formAction !== '')
		$action = $formAction;
	elseif (!isset($_GET['sa']))
	{
		$action = 'home';
		$context['current_subaction'] = 'home';
	}
	else
		$action = (string) ($_GET['sa'] ?? 'home');

	// Load the template
	if ($action !== 'search')
		loadTemplate('Quiz/Quiz');

	$actionHandler = $actions[$action] ?? null;
	if ($actionHandler !== null)
		$actionHandler();
}

/**
 * Render the XML quiz list response.
 * @return void
 */
function template_xml_list(): void
{
	global $context, $txt;

	echo '<smf>';

	$quizes = $context['quiz']['search']['quizes'] ?? [];
	foreach ($quizes as $quiz)
		echo '
			<quiz>
				<id>', $quiz['id'], '</id>
				<name><![CDATA[', $quiz['title'], ']]></name>
				<url><![CDATA[', $quiz['url'], ']]></url>
			</quiz>';

	echo '</smf>';
}

/**
 * Load quiz search results for the XML response.
 * @return void
 */
function QuizSearchXML(): void
{
	global $smcFunc, $scripturl, $db_prefix, $context;

	$context['template_layers'] = [];
	$limit = 5;
	$searchTerm = (string) ($_REQUEST['name'] ?? '');

	// @TODO check input before queries
	$search = '%' . addslashes($searchTerm) . '%';
	$result = $smcFunc['db_query']('', '
		SELECT count(*) AS quizes
		FROM {db_prefix}quiz as Q
		WHERE Q.Title LIKE {string:quiz}',
		[
		'quiz' => $search,
		]
	);
	$row = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);
	// @TODO $row['quizes'] ?
	$how_many = $row[0];

	$context['SMFQuiz']['search'] = [];
	$context['quiz']['search']['quizes'] = [];

	$result = $smcFunc['db_query']('', '
		SELECT Q.id_quiz, Q.title
		FROM {db_prefix}quiz as Q
		WHERE Q.title LIKE {string:quiz}
		LIMIT 0, {int:limit}',
		[
		'quiz' => $search,
		'limit' => $limit,
		]
	);

	while ($quiz = $smcFunc['db_fetch_assoc']($result))
	{
		$context['quiz']['search']['quizes'][] = [
		'title' => $quiz['title'],
		'id' => $quiz['id_quiz'],
		'url' => $scripturl . '?action=SMFQuiz;sa=categories;id_quiz=' . $quiz['id_quiz']
		];
	}
	$smcFunc['db_free_result']($result);

	$context['sub_template'] = 'xml_list';
}

/**
 * Get Questions Data.
 * @return void
 */
function GetQuestionsData(): void
{
	global $context;

	$idQuiz = (int) ($_GET['id_quiz'] ?? 0);

	// They need a quiz to access here...
	if ($idQuiz === 0)
		fatal_lang_error('no_access', false);

	$questionId = (int) ($_GET['questionId'] ?? 0);
	if ($questionId > 0)
	{
		QuestionScript();
		GetQuestionAndAnswers($questionId);
		$context['current_subaction'] = 'editQuestion';
		return;
	}

	// Create an array that will map the sort selection to the query value
	$sort_methods = [
		'Question' => 'Q.question_text',
		'Type' => 'QT.description',
		'Quiz' => 'Q.id_quiz',
	];

	$orderBy = (string) ($_GET['orderBy'] ?? '');
	if ($orderBy === '')
	{
		$context['SMFQuiz']['orderBy'] = 'Question';
		$context['SMFQuiz']['orderDir'] = 'up';
	}
	else
	{
		// Otherwise set the sort query string and reset context
		// @TODO check input
		$context['SMFQuiz']['orderBy'] = $orderBy;
		$context['SMFQuiz']['orderDir'] = ((string) ($_GET['orderDir'] ?? '')) === 'up' ? 'down' : 'up';
	}

	$context['current_subaction'] = 'quizQuestions';
	$context['SMFQuiz']['page'] = max(1, (int) ($_GET['page'] ?? 1));
	GetUserQuestionCount($context['id_quiz'], $context['user']['id']);
	GetUserQuestionDetails($context['SMFQuiz']['page'], $sort_methods[$context['SMFQuiz']['orderBy']], $context['SMFQuiz']['orderDir'], $context['id_quiz'], $context['user']['id']);
}

/**
 * Append the quiz form JavaScript.
 * @return void
 */
function QuizScript(): void
{
	global $context;

	// Add javascript for multiple checkbox selection
	// TODO: Make this dependant on what we are showing
	$context['html_headers'] .= '<script type="text/javascript"><!-- // --><![CDATA[
			function validateQuiz(form, action)
			{
				if (document.getElementById("title").value.length > 0)
				{
					document.getElementById("formaction").value = action;
					form.submit();
				}
				else
				{
					// @TODO localization
					alert("The quiz must have a title");
					document.getElementById("title").focus();
				}
			}
			// ]]></script>
	';
}

/**
 * Append the question form JavaScript.
 * @return void
 */
function QuestionScript(): void
{
	global $context;

	// Add javascript for multiple checkbox selection
	// TODO: Make this dependant on what we are showing
	$context['html_headers'] .= '<script type="text/javascript"><!-- // --><![CDATA[
			function checkAll(selectedForm, checked)
			{
				for (var i = 0; i < selectedForm.elements.length; i++)
				{
					var e = selectedForm.elements[i];
					if (e.type === \'checkbox\') {
						e.checked = checked;
					}
				}
			}
			function changeQuestionType(selectedForm)
			{
				switch (selectedForm.options[selectedForm.options.selectedIndex].value)
				{
					case \'1\' : // Multiple Choice
						document.getElementById("freeTextAnswerdiv").style.display = \'none\';
						document.getElementById("multipleChoiceAnswer").style.display = \'block\';
						document.getElementById("trueFalseAnswer").style.display = \'none\';
						break;
					case \'2\' : // Free Text
						document.getElementById("freeTextAnswerdiv").style.display = \'block\';
						document.getElementById("multipleChoiceAnswer").style.display = \'none\';
						document.getElementById("trueFalseAnswer").style.display = \'none\';
						break;
					case \'3\' : // True/False
						document.getElementById("freeTextAnswerdiv").style.display = \'none\';
						document.getElementById("multipleChoiceAnswer").style.display = \'none\';
						document.getElementById("trueFalseAnswer").style.display = \'block\';
						break;
				}
			}

			function addRow()
			{
				var rowCount = document.getElementById("answerTable").rows.length;

				var radioElement = document.createElement("input");
				radioElement.setAttribute("name", "correctAnswer");
				radioElement.setAttribute("value", rowCount);
				radioElement.setAttribute("type", "radio");

				var answerElement = document.createElement("input");
				answerElement.setAttribute("name", "answer" + rowCount);
				answerElement.setAttribute("size", "50");
				answerElement.setAttribute("type", "text");

				var tbody = document.getElementById("answerTable").getElementsByTagName("TBODY")[0];
				var row = document.createElement("TR");
				var td1 = document.createElement("TD");
				td1.appendChild(radioElement);
				var td2 = document.createElement("TD");
				td2.appendChild (answerElement);
				row.appendChild(td1);
				row.appendChild(td2);
				tbody.appendChild(row);
			}

			function deleteRow()
			{
				var rowCount = document.getElementById("answerTable").rows.length - 1;

				if (rowCount > 1)
					document.getElementById("answerTable").deleteRow(rowCount);
			}

			function validateQuestion(form, action)
			{
				var isValid = true;

				if (document.getElementById("question_text").value.length == 0)
				{
					// @TODO localization
					alert("The question must have a title");
					document.getElementById("question_text").focus();
					isValid = false;
				}

				if (isValid == true)
				{
					switch (document.getElementById("id_question_type").value)
					{
						case "1" : // Multiple choice
							// No validation for the moment
							break;

						case "2" : // Free text
							if (document.getElementById("freeTextAnswer").value.length == 0)
							{
								// @TODO localization
								alert("The free text answer cannot be empty");
								document.getElementById("freeTextAnswer").focus();
								isValid = false;
							}
							break;

						case "3" : // True fase
							// No need to do anything here
							break;
					}
				}

				if (isValid == true)
				{
					document.getElementById("formaction").value = action;
					form.submit();
				}
			}
			// ]]></script>';
}

/**
 * Save Question Data.
 * @return void
 */
function SaveQuestionData(): void
{
	global $context;

	// Retrieve the form values
	// TODO - Need some validation on front end
	$questionText = ReplaceCurlyQuotes((string) ($_POST['question_text'] ?? ''));
	$questionTypeId = (int) ($_POST['id_question_type'] ?? 0);
	$imageUrl = (string) ($_POST['image'] ?? '');
	$answerText = ReplaceCurlyQuotes((string) ($_POST['answer_text'] ?? ''));

	// Save the Question
	$questionId = SaveQuestion($questionText, $questionTypeId, $context['id_quiz'], $imageUrl, $answerText);

	// Save the answer
	switch ($questionTypeId)
	{
		case 1: // Multiple Choice
			AddMultipleChoiceAnswer($questionId);
			break;

		case 2: // Free Text
			AddFreeTextAnswer($questionId);
			break;

		case 3: // True/False
			AddTrueFalseAnswer($questionId);
			break;
	}

	// @TODO check input
	if ((string) ($_POST['formaction'] ?? '') === 'saveQuestion')
	{
		GetQuestionsData();
		return;
	}

	GetNewQuestionData();
}

/**
 * Save Quiz Data.
 * @return void
 */
function SaveQuizData(): void
{
	global $context;

	// Retrieve the form values
	// TODO - Need some validation on front end
	$title = (string) ($_POST['title'] ?? '');
	$description = (string) ($_POST['description'] ?? '');
	$limit = (int) ($_POST['limit'] ?? 0);
	$seconds = (int) ($_POST['seconds'] ?? 0);
	$showanswers = (string) ($_POST['showanswers'] ?? '');
	$categoryId = (int) ($_POST['id_category'] ?? 0);
	$image = (string) ($_POST['image'] ?? '');
	$userId = (int) $context['user']['id'];

	$showanswers = $showanswers === 'on' ? 1 : 0;

	if ($image === '-')
		$image = '';

	// Save the data and return the identifier for this newly created quiz
	$newQuizId = SaveQuiz($title, $description, $limit, $seconds, $showanswers, $image, $categoryId, 0, $userId, 0);

	// If the user wants to add questions after saving the quiz we need to output the appropriate page which is dictated by these context values
	if ((string) ($_POST['formaction'] ?? '') === 'saveQuizAndAddQuestions')
	{
		$context['id_quiz'] = $newQuizId;

		// We need to get the data required for new questions
		GetNewQuestionData();
		return;
	}

	// We need to get new quiz data, as that will be the next page shown
	GetUserQuizesData();
	$context['current_subaction'] = 'userquizes';
}

/**
 * Get Add Quiz Data.
 * @return void
 */
function GetAddQuizData(): void
{
	QuizScript();

	AddShowImageScript();

	// The new quiz page also shows a list of categories, so we must get this data
	GetAllCategoryDetails();
}

/**
 * Add Show Image Script.
 * @return void
 */
function AddShowImageScript(): void
{
	global $context, $boardurl;

	$context['html_headers'] .= '
	<script type="text/javascript"><!-- // --><![CDATA[
		function show_image(imgId, selectElement, imageFolder)
		{
			var imgElement = document.getElementById(imgId);
			var selectedValue = selectElement[selectElement.selectedIndex].text;
			var imageUrl = "' . $boardurl . '/Themes/default/images/quiz_images/blank.gif";
			if (selectedValue != "-")
				imageUrl = "' . $boardurl . '/Themes/default/images/quiz_images/" + imageFolder + "/" + selectedValue;

			imgElement.src = imageUrl;
		}
	// ]]></script>';
}

/**
 * Get User Quizes Data.
 * @return void
 */
function GetUserQuizesData(): void
{
	global $context, $sourcedir, $txt;

	isAllowedTo('quiz_submit');

	QuizScript();

	$userId = (int) ($_GET['id_user'] ?? $context['user']['id']);
	$reviewId = (int) ($_GET['review'] ?? 0);

	// @TODO check input
	if ($reviewId > 0)
	{
		SetQuizForReview($reviewId);

		include_once($sourcedir . '/Subs-Post.php');

		$pmto = [
			'to' => [1],
			'bcc' => []
		];

		$subject = $txt['SMFQuiz_UserQuizes_Page']['UserQuizSubmittedForReview'];
		$message = $txt['SMFQuiz_UserQuizes_Page']['QuizSubmittedForReview'];

		$pmfrom = [
			'id' => $userId,
			'name' => 'Quiz',
			'username' => 'Quiz'
		];

		// Send message
		sendpm($pmto, $subject, $message, 0, $pmfrom);
	}

	GetUserQuizes($userId);
	$context['current_subaction'] = 'userquizes';
}

/**
 * Get Quiz Leagues Data.
 * @return void
 */
function GetQuizLeaguesData(): void
{
	global $context;

	$leagueId = (int) ($_GET['id'] ?? 0);

	// If the ID has been set then the user has selected a specific league
	if ($leagueId > 0)
	{
		// Check whether user can play this league - they might have already played it. This will populate a context param
		CanUserPlayQuizLeagueData($leagueId, $context['user']['id']);

		GetQuizLeagueDetails($leagueId);

		foreach ($context['SMFQuiz']['quizLeague'] as $quizLeagueRow)
			GetQuizLeagueTable($leagueId, $quizLeagueRow['current_round'] - 1);

		GetQuizLeagueResults($leagueId);
		return;
	}

	// Otherwise just show the quiz league listing
	GetUserQuizLeagueDetails($context['user']['id']);
}

/**
 * Get User Details Data.
 * @return void
 */
function GetUserDetailsData(): void
{
	global $context, $memberContext, $user_info;

	// Guests can't see details...
	if ($user_info['is_guest'])
		redirectexit('action=SMFQuiz');

	// @TODO isAllowed?
	$userId = (int) ($_GET['id_user'] ?? $context['user']['id']);

	// Get member statistics
	GetMemberStatistics($userId);

	// Get member wins
	GetTotalUserWins($userId);

	// Get latest scores by this user
	GetUserQuizScores($userId);

	// Get correct scores by this user
	GetUserCorrectScores($userId);

	// Get category plays
	GetUserCategoryPlays($userId);

	// Let's have some information about this member ready, too.
	loadMemberData($userId, false, 'profile');
	loadMemberContext($userId);
	$context['member'] = $memberContext[$userId];

	$context['id_user'] = $userId;
}

/**
 * Get Home Page Data.
 * @return void
 */
function GetHomePageData(): void
{
	global $context, $modSettings;

	$context['html_headers'] .= '
		<script type="text/javascript">
		var search_wait = false;
		var search_url = smf_scripturl + "?action=SMFQuiz;sa=search;xml";
		var search_divQ = "quick_div";
		function quizSearchLoader() {
			var quizSearchTrigger = document.getElementById("quick_name");
			sessionStorage.setItem("quizQuickNameVal", quizSearchTrigger.value.trim());
			if (quizSearchTrigger) {
				quizSearchTrigger.onkeypress = function(){
					QuizQuickSearch();
					setTimeout(function(){
						var quick_name = document.getElementById("quick_name").value.trim();
						if (sessionStorage.getItem("quizQuickNameVal") != quick_name)
							QuizQuickSearch();
					}, 2000);
				};
			}
			setInterval(function(){
				var quick_name = document.getElementById("quick_name").value.trim();
				if (quick_name == "")
					document.getElementById(search_divQ).innerHTML = "";
			}, 5000);
		}
		function QuizQuickSearch()
		{
			if (search_wait) // Wait before new search.
			{
				setTimeout(function(){QuizQuickSearch();}, 800);
				return 1;
			}

			search_wait = true;
			setInterval(function(){resetWait();}, 800);

			var i, x = [];
			var n = document.getElementById("quick_name").value.trim();
			x[0] = "name=" + escape(textToEntities(n.replace(/&#/g, "&#38;#"))).replace(/\+/g, "%2B");
			sendXMLDocument(search_url, x.join("&"), onQuizSearch);
			ajax_indicator(true);
		}

		function textToEntities(text)
		{
			var entities = "";
			for (var i = 0; i < text.length; i++)
			{
				if (text.charCodeAt(i) > 127)
					entities += "&#" + text.charCodeAt(i) + ";";
				else
					entities += text.charAt(i);
			}

			return entities;
		}
		function decodeQuizHTML(html) {
			var txt = document.createElement("textarea");
			txt.innerHTML = html;
			return txt.value;
		}
		function resetWait() {

		}
		function onQuizSearch(XMLDoc)
		{
			if (!XMLDoc)
				document.getElementById(search_divQ).textContent = "Error";
			else {
				search_wait = false;
				var quizzes = XMLDoc.getElementsByTagName("quiz");
				var addNewNode = [], addNewLink = [], addNewText = [],searchDiv = document.createElement("DIV"), searchMainDiv = document.getElementById(search_divQ), i=0;
				for (i = 0; i < quizzes.length; i++) {
					addNewNode[i] = document.createElement("div");
					addNewLink[i] = document.createElement("a");
					addNewLink[i].href = quizzes[i].getElementsByTagName("url")[0].firstChild.nodeValue;
					addNewText[i] = document.createTextNode(decodeQuizHTML(quizzes[i].getElementsByTagName("name")[0].firstChild.nodeValue));
					addNewLink[i].appendChild(addNewText[i]);
					addNewNode[i].appendChild(addNewLink[i]);
					searchDiv.appendChild(addNewNode[i]);
				}
				searchMainDiv.innerHTML = searchDiv.innerHTML;
			}
			ajax_indicator(false);
		}
		if (window.addEventListener) {
			window.addEventListener("load", quizSearchLoader, false);
		}
		else {
			window.attachEvent("onload", quizSearchLoader);
		}
	</script>';

	// Get any outstanding sessions
	GetQuizSessions($context['user']['id']);

	// Need to get the latest quizes
	GetLatestQuizes();

	// Need to get the most popular quizes
	GetPopularQuizes(8);

	// Need this for calculations
	GetTotalQuizes();

	// Need to get the most popular quizes
	GetQuizMasters(8);

	// Need to get the quiz league leaders
	GetQuizLeagueLeaders(8);

	// Get some random quizzes
	GetRandomQuizzes(5, $context['user']['id']);

	// Finally we need to get the Infoboard data
	GetLatestInfoBoard($modSettings['SMFQuiz_InfoBoardItemsToDisplay']);
}

/**
 * Get Categories Data.
 * @return void
 */
function GetCategoriesData(): void
{
	global $context, $txt;

	if ($context['id_quiz'] !== 0)
	{
		GetQuiz($context['id_quiz']);
		GetQuizResults($context['id_quiz']);
		GetQuizCorrect($context['id_quiz']);
		return;
	}

	$categoryId = (int) ($_GET['categoryId'] ?? 0);

	// Get all categories in this category
	GetParentCategoryDetails($categoryId);

	// Get the details for the selected category
	if ($categoryId !== 0)
		GetCategory($categoryId);
	else
	{
		// Otherwise this is the top level category, so populate with default data
		// TODO - Get this out of modsettings
		$row = [];
		$row['name'] = $txt['SMFQuiz_Categories_Page']['TopLevel'];
		$row['description'] = $txt['SMFQuiz_Categories_Page']['ThisIsTheTopLevelCategory'];
		$context['SMFQuiz']['category'][] = $row;
	}

	// Get any quizes that exist in this category
	GetQuizesInCategoryData($categoryId, $context['user']['id']);
}

/**
 * Get Statistics Data.
 * @return void
 */
function GetStatisticsData(): void
{
	// @TODO Performance?
	// Could probably do this a little more efficiently, but for the meantime this will do

	// Get total quizes
	GetTotalQuizStats();

	// Need this for calculations
	GetTotalQuizes();

	// Get total questions
	GetTotalQuestions();

	// Get total answers
	GetTotalAnswers();

	// Get total categories
	GetTotalCategories();

	// Get quiz masters
	GetQuizMasters(10);

	// Need to get the most popular quizes
	GetPopularQuizes(10);

	// Get the best quiz result
	GetBestQuizResult();

	// Get the worst quiz result
	GetWorstQuizResult();

	// Get the newest quiz
	GetNewestQuiz();

	// Get the oldest quiz
	GetOldestQuiz();

	// Get the most quiz wins
	MostQuizWins();

	// Get the hardest quizes
	GetHardestQuizes();

	// Get the easiest quizes
	GetEasiestQuizes();

	// Get the most active players
	GetMostActivePlayers();

	// Get the most quiz creators
	GetMostQuizCreators();
}

/**
 * Get New Question Data.
 * @return void
 */
function GetNewQuestionData(): void
{
	global $context;

	QuestionScript();

	// The new question page provides a list of quizes to select. Therefore we need to obtain a list of category data
	GetUserQuizes($context['user']['id']);

	// The new question page provides a list of question types to select. Therefore we need to obtain a list of question type data
	GetAllQuestionTypes();

	// We need to set the SMFQuiz specific action here so the template knows what to do. This could be achieved through the FORM
	// variable, but tidier this way
	$context['SMFQuiz']['Action'] = 'NewQuestion';
	$context['current_subaction'] = 'questions';
}

/**
 * Get Edit Quiz Data.
 * @return void
 */
function GetEditQuizData(): void
{
	global $context, $user_info;

	QuizScript();
	AddShowImageScript();
	GetQuiz($context['id_quiz']);

	// Only the quiz creator can edit the quiz
	if ((int) $user_info['id'] !== (int) $context['SMFQuiz']['quiz'][0]['creator_id'] && !allowedTo('quiz_admin'))
		fatal_lang_error('no_access', false);

	// The edit quiz page also shows a list of categories, so we must get this data
	GetAllCategoryDetails();

	$context['current_subaction'] = 'editquiz';
}

/**
 * Update Free Text Answer.
 * @return void
 */
function UpdateFreeTextAnswer(): void
{
	// Free text answer simply has the text entered as the answer, so we only need to insert this into the database marking it as correct
	$answerText = ReplaceCurlyQuotes((string) ($_POST['freeTextAnswer'] ?? ''));
	$answerId = (int) ($_POST['id_answer'] ?? 0);

	// Update the data
	UpdateAnswer($answerId, $answerText, 1);
}

/**
 * Add Free Text Answer.
 * @param int $questionId Question identifier.
 * @return void
 */
function AddFreeTextAnswer(int $questionId): void
{
	// Free text answer simply has the text entered as the answer, so we only need to insert this into the database marking it as correct
	$answerText = ReplaceCurlyQuotes((string) ($_POST['freeTextAnswer'] ?? ''));

	// Save the data
	SaveAnswer($questionId, $answerText, 1);
}

/**
 * Update True False Answer.
 * @return void
 */
function UpdateTrueFalseAnswer(): void
{
	$correctAnswerId = (int) ($_POST['trueFalseAnswer'] ?? 0);

	foreach ($_POST as $key => $value)
	{
		$key = (string) $key;
		$value = (string) $value;
		if (substr($key, 0, 8) !== 'id_answer' || $value === '')
			continue;

		$answerId = (int) substr($key, 8);
		UpdateAnswer($answerId, $value, $answerId === $correctAnswerId ? 1 : 0);
	}
}

/**
 * Add True False Answer.
 * @param int $questionId Question identifier.
 * @return void
 */
function AddTrueFalseAnswer(int $questionId): void
{
	// True false answer is simply saved as one asnwer that is correct
	$answerText = ReplaceCurlyQuotes((string) ($_POST['trueFalseAnswer'] ?? 'false'));

	SaveAnswer($questionId, $answerText, 1);

	// Add the alternative answer
	if ($answerText === 'false')
		SaveAnswer($questionId, 'true', 0);
	else
		SaveAnswer($questionId, 'false', 0);
}

/**
 * Update Multiple Choice Answer.
 * @return void
 */
function UpdateMultipleChoiceAnswer(): void
{
	// For mutiple choice answers we need to loop through each choice adding the answer and setting the correct one
	$correctAnswerId = (int) ($_POST['correctAnswer'] ?? 0);

	foreach ($_POST as $key => $value)
	{
		$key = (string) $key;
		$value = (string) $value;
		if (substr($key, 0, 6) !== 'answer' || $key === 'answer_text' || $value === '')
			continue;

		$answerId = (int) substr($key, 6);
		UpdateAnswer($answerId, $value, $answerId === $correctAnswerId ? 1 : 0);
	}
}

/**
 * Add Multiple Choice Answer.
 * @param int $questionId Question identifier.
 * @return void
 */
function AddMultipleChoiceAnswer(int $questionId): void
{
	// For mutiple choice answers we need to loop through each choice adding the answer and setting the correct one
	$correctAnswerId = (int) ($_POST['correctAnswer'] ?? 0);

	foreach ($_POST as $key => $value)
	{
		$key = (string) $key;
		$value = (string) $value;
		if (substr($key, 0, 6) !== 'answer' || $key === 'answer_text' || $value === '')
			continue;

		$answerId = (int) substr($key, 6);
		SaveAnswer($questionId, $value, $answerId === $correctAnswerId ? 1 : 0);
	}
}

/**
 * Normalize curly quotes in quiz text.
 * @param string $stringToReplace Text to normalize.
 * @return string Normalized text.
 */
function ReplaceCurlyQuotes(string $stringToReplace): string
{
	$replaceString = str_replace('�', '"', $stringToReplace);
	$replaceString = str_replace('�', '"', $replaceString);
	$replaceString = str_replace('�', '\'', $replaceString);
	return $replaceString;
}

/**
 * Get Update Quiz Data.
 * @return void
 */
function GetUpdateQuizData(): void
{
	global $context;

	// Retrieve the form values
	// TODO - Need some validation on front end
	$title = (string) ($_POST['title'] ?? '');
	$description = (string) ($_POST['description'] ?? '');
	$limit = (int) ($_POST['limit'] ?? 0);
	$seconds = (int) ($_POST['seconds'] ?? 0);
	$showanswers = (string) ($_POST['showanswers'] ?? '');
	$image = (string) ($_POST['image'] ?? '');
	$categoryId = (int) ($_POST['id_category'] ?? 0);
	$oldCategoryId = (int) ($_POST['oldCategoryId'] ?? 0); // Need the old category, as if it is different we need to change quiz counts

	$showanswers = $showanswers === 'on' ? 1 : 2;

	// Save the data and return the identifier for this newly created quiz
	UpdateQuiz($context['id_quiz'], $title, $description, $limit, $seconds, $showanswers, $image, $categoryId, $oldCategoryId, 0, 0);

	// If the user wants to add questions after saving the quiz we need to output the appropriate page which is dictated by these context values
	// We need to get the data required for new questions
	if ((string) ($_POST['formaction'] ?? '') === 'updateQuizAndAddQuestions')
	{
		GetNewQuestionData();
		return;
	}

	GetUserQuizesData();
	$context['current_subaction'] = 'userquizes';
}

/**
 * Get Delete Question Data.
 * @return void
 */
function GetDeleteQuestionData(): void
{
	global $context;

	// Get the key ids for the questions to delete. This function returns a string containing a comma separated list of id's
	$deleteKeys = GetKeysFromPost('question');

	if (!empty($deleteKeys))
		DeleteQuestions($deleteKeys);

	GetQuestionsData();
}

// From the specified id key, loop through the form variables and extract the associated identifiers. Return a string containing these
// identifiers in a comma separated list
/**
 * Extract matching identifiers from POST keys.
 * @param string $id POST key prefix to match.
 * @return string Comma-separated identifier list.
 */
function GetKeysFromPost(string $id): string
{
	$deleteKeys = [];
	$idLength = strlen($id);

	// @TODO check input
	foreach ($_POST as $key => $_value)
	{
		$key = (string) $key;
		if (substr($key, 0, $idLength) !== $id)
			continue;

		$deleteKeys[] = substr($key, $idLength);
	}

	return implode(',', $deleteKeys);
}

/**
 * Get Delete Quiz Data.
 * @return void
 */
function GetDeleteQuizData(): void
{
	global $context, $user_info;

	// Get the key ids for the questions to delete. This function returns a string containing a comma separated list of id's

	// Get the key ids for the quiz leagues to delete. This function returns a string containing a comma separated list of id's
	$deleteKeys = GetKeysFromPost('quiz');

	// Get quiz info
	GetQuiz($context['id_quiz']);

	// Check if the user is the owner of the quiz
	if ((int) $user_info['id'] !== (int) $context['SMFQuiz']['quiz'][0]['creator_id'] && !allowedTo('quiz_admin'))
		fatal_lang_error('no_access', false);

	if (!empty($context['id_quiz']))
		DeleteQuizes($context['id_quiz']);

	GetUserQuizesData();
}


/**
 * Get Update Question Data.
 * @return void
 */
function GetUpdateQuestionData(): void
{
	global $context, $smcFunc, $db_prefix;

	// Retrieve the form values
	// TODO - Need some validation on front end
	$questionId = (int) ($_POST['questionId'] ?? 0);
	$questionText = ReplaceCurlyQuotes((string) ($_POST['question_text'] ?? ''));
	$imageUrl = (string) ($_POST['image'] ?? '');
	$answerText = ReplaceCurlyQuotes((string) ($_POST['answer_text'] ?? ''));
	$questionTypeId = (int) ($_POST['id_question_type'] ?? 0);

	// Update the Question
	UpdateQuestion($questionId, $questionText, $imageUrl, $answerText);

	// Update the answer
	switch ($questionTypeId)
	{
		case 1: // Multiple Choice
			// @TODO query
			$smcFunc['db_query']('', "
				DELETE FROM {$db_prefix}quiz_answer
				WHERE id_question = {$questionId}");
			AddMultipleChoiceAnswer($questionId);
			break;

		case 2: // Free Text
			UpdateFreeTextAnswer();
			break;

		case 3: // True/False
			UpdateTrueFalseAnswer();
			break;
	}

	// @TODO check input
	if ((string) ($_POST['formaction'] ?? '') === 'updateQuestion')
	{
		// The next page will show all the questions, so get this data
		GetQuestionsData();
		return;
	}

	GetNewQuestionData();
	// @TODO why commented?
	//$context['SMFQuiz']['Action'] = 'NewQuestion';
}

/**
 * Get Quiz Scores Data.
 * @return void
 */
function GetQuizScoresData(): void
{
	global $context, $scripturl, $smcFunc, $txt, $modSettings;

	$id_quiz = (int) ($_GET['id_quiz'] ?? 0);
	$sort = (string) ($_REQUEST['sort'] ?? 'default');
	if ($sort === '')
		$sort = 'default';
	$limit = $modSettings['SMFQuiz_ListPageSizes'];
	$start = (int) ($_GET['start'] ?? 0);
	$isDescending = isset($_REQUEST['desc']);

	// Set up the columns...
	$context['columns'] = [
		'user' => [
			'label' => $txt['SMFQuiz_Common']['Member']
		],
		'date' => [
			'label' => $txt['SMFQuiz_Common']['Date']
		],
		'questions' => [
			'label' => $txt['SMFQuiz_Common']['Questions'],
			'width' => '20'
		],
		'correct' => [
			'label' => $txt['SMFQuiz_Common']['Correct'],
			'width' => '20'
		],
		'incorrect' => [
			'label' => $txt['SMFQuiz_Common']['Incorrect'],
			'width' => '20'
		],
		'timeouts' => [
			'label' => $txt['SMFQuiz_Common']['Timeouts'],
			'width' => '20'
		],
		'seconds' => [
			'label' => $txt['SMFQuiz_Common']['Seconds'],
			'width' => '20'
		],
	];

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=SMFQuiz;sa=quizscores;id_quiz=' . $id_quiz . ';sort=' . $col . ';start=0';

		if ((!$isDescending && $col === $sort) || ($col !== $sort && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $sort === $col;
	}

	$context['sort_by'] = $sort;
	$context['sort_direction'] = !$isDescending ? 'up' : 'down';

	// List out the different sorting methods...
	$sort_methods = [
		'default' => [
			'up' => 'correct DESC, total_seconds ASC, result_date ASC'
		],
		'user' => [
			'down' => 'real_name DESC',
			'up' => 'real_name ASC'
		],
		'date' => [
			'down' => 'result_date DESC',
			'up' => 'result_date ASC'
		],
		'questions' => [
			'down' => 'questions DESC',
			'up' => 'questions ASC'
		],
		'correct' => [
			'down' => 'correct DESC',
			'up' => 'correct ASC'
		],
		'incorrect' => [
			'down' => 'incorrect DESC',
			'up' => 'incorrect ASC'
		],
		'timeouts' => [
			'down' => 'timeouts DESC',
			'up' => 'timeouts ASC'
		],
		'seconds' => [
			'down' => 'total_seconds DESC',
			'up' => 'total_seconds ASC'
		]
	];

	$query_parameters = [
		'sort' => $sort_methods[$sort][$context['sort_direction']] ?? $sort_methods['default']['up'],
		'limit' => $limit,
		'id_quiz' => $id_quiz,
		'start' => $start,
	];

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM  {db_prefix}quiz_result
		WHERE id_quiz = {int:id_quiz}',
		$query_parameters
	);
	list ($context['num_quizes']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=SMFQuiz;sa=quizscores;id_quiz=' . $id_quiz . ';sort=' . $sort . ($isDescending ? ';desc' : ''), $start, $context['num_quizes'], $limit);

	// Send the data to the template.
	$context['start'] = $start + 1;
	$context['end'] = min($start + $limit, $context['num_quizes']);

	$result = $smcFunc['db_query']('', '
		SELECT
			QR.id_user,
			M.real_name,
			Q.title,
			QR.result_date,
			QR.questions,
			QR.correct,
			QR.incorrect,
			QR.timeouts,
			QR.total_seconds,
			QR.auto_completed
		FROM {db_prefix}quiz Q
		INNER JOIN {db_prefix}quiz_result QR
			ON Q.id_quiz = QR.id_quiz
		INNER JOIN {db_prefix}members M
			ON QR.id_user = M.id_member
		WHERE QR.id_quiz = {int:id_quiz}
		ORDER BY {raw:sort}
		LIMIT {int:start} , {int:limit}',
		$query_parameters
	);

	$context['SMFQuiz']['quiz_results'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['SMFQuiz']['quiz_results'][] = $row;
		$context['SMFQuiz']['quiz_title'] = $row['title'];
	}

	$smcFunc['db_free_result']($result);
	$context['SMFQuiz']['Action'] = 'quiz_results';
}

/**
 * Get Unplayed Quizes Data.
 * @return void
 */
function GetUnplayedQuizesData(): void
{
	global $context, $scripturl, $smcFunc, $txt, $modSettings;

	$userId = (int) ($_GET['id_user'] ?? $context['user']['id']);
	$starts_with = (string) ($_GET['starts_with'] ?? '');
	$sort = (string) ($_REQUEST['sort'] ?? 'title');
	if ($sort === '')
		$sort = 'title';
	$limit = $modSettings['SMFQuiz_ListPageSizes'];
	$start = (int) ($_GET['start'] ?? 0);
	$isDescending = isset($_REQUEST['desc']);

	// Set up the columns...
	$context['columns'] = [
		'' => [
			'label' => '',
			'width' => '2'
		],
		'title' => [
			'label' => $txt['SMFQuiz_Common']['Title']
		],
		'owner' => [
			'label' => $txt['SMFQuiz_Common']['Owner'],
			'width' => '25'
		],
		'description' => [
			'label' => $txt['SMFQuiz_Common']['Description']
		],
		'category' => [
			'label' => $txt['SMFQuiz_Common']['Category'],
			'width' => '20',
			'link_with' => 'website',
		],
		'play_limit' => [
			'label' => $txt['SMFQuiz_Common']['PlayLimit'],
			'width' => '20'
		],
		'questions' => [
			'label' => $txt['SMFQuiz_Common']['Qs'],
			'width' => '20'
		],
		'seconds' => [
			'label' => $txt['SMFQuiz_Common']['Secs'],
			'width' => '20'
		],
		'auto_compleyed' => [
			'label' => '',
			'width' => '1'
		]
	];

	// Set the filter links
	$context['letter_links'] = '<a href="' . $scripturl . '?action=SMFQuiz;sa=unplayedQuizes;id_user=' . $userId . '">*</a> ';
	for ($i = 97; $i < 123; $i++)
		$context['letter_links'] .= '<a href="' . $scripturl . '?action=SMFQuiz;sa=unplayedQuizes;id_user=' . $userId . ';starts_with=' . chr($i) . '">' . strtoupper(chr($i)) . '</a> ';

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=SMFQuiz;sa=unplayedQuizes;id_user=' . $userId . ';starts_with=' . $starts_with . ';sort=' . $col . ';start=0';

		if ((!$isDescending && $col === $sort) || ($col !== $sort && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $sort === $col;
	}

	$context['sort_by'] = $sort;
	$context['sort_direction'] = !$isDescending ? 'up' : 'down';

	// List out the different sorting methods...
	$sort_methods = [
		'title' => [
			'down' => 'title DESC',
			'up' => 'title ASC'
		],
		'owner' => [
			'down' => 'real_name DESC',
			'up' => 'real_name ASC'
		],
		'description' => [
			'down' => 'description DESC',
			'up' => 'description ASC'
		],
		'category' => [
			'down' => 'category_name DESC',
			'up' => 'category_name ASC'
		],
		'play_limit' => [
			'down' => 'play_limit DESC',
			'up' => 'play_limit ASC'
		],
		'questions' => [
			'down' => 'questions_per_session DESC',
			'up' => 'questions_per_session ASC'
		],
		'seconds' => [
			'down' => 'seconds_per_question DESC',
			'up' => 'seconds_per_question ASC'
		]
	];

	$query_parameters = [
		'sort' => $sort_methods[$sort][$context['sort_direction']] ?? 'Q.title ASC',
		'starts_with' => $starts_with . '%',
		'limit' => $limit,
		'start' => $start,
		'id_user' => $userId
	];

	$request = $smcFunc['db_query']('','
		SELECT QR.id_quiz_result
		FROM {db_prefix}quiz Q
		LEFT JOIN	{db_prefix}quiz_category QC
			ON Q.id_category = QC.id_category
		INNER JOIN {db_prefix}quiz_question U
			ON Q.id_quiz = U.id_quiz
		INNER JOIN {db_prefix}members M
			ON Q.creator_id = M.id_member
		LEFT JOIN {db_prefix}quiz_result QR
			ON Q.id_quiz = QR.id_quiz
			AND QR.id_user = {int:id_user}
		WHERE Q.enabled = 1
			AND id_quiz_result IS NULL
		GROUP BY Q.id_quiz,QR.id_quiz_result',
		$query_parameters
	);
	$context['num_quizes'] = $smcFunc['db_num_rows']($request);
	$smcFunc['db_free_result']($request);

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=SMFQuiz;id_user=' . $userId . ';sa=unplayedQuizes;starts_with=' . $starts_with . ';sort=' . $sort . ($isDescending ? ';desc' : ''), $start, $context['num_quizes'], $limit);

	// Send the data to the template.
	$context['start'] = $start + 1;
	$context['end'] = min($start + $limit, $context['num_quizes']);

	// Left join on category as may be top level
	$result = $smcFunc['db_query']('', '
		SELECT
			Q.id_quiz,
			Q.title,
			Q.image,
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
		FROM {db_prefix}quiz Q
		LEFT JOIN {db_prefix}quiz_category QC
			ON Q.id_category = QC.id_category
		INNER JOIN {db_prefix}quiz_question U
			ON Q.id_quiz = U.id_quiz
		INNER JOIN {db_prefix}members M
			ON Q.creator_id = M.id_member
		LEFT JOIN {db_prefix}quiz_result QR
			ON Q.id_quiz = QR.id_quiz
			AND QR.id_user = {int:id_user}
		WHERE Q.enabled = 1' . (!empty($starts_with) ? '
			AND Q.title LIKE {string:starts_with}' : '') . '
		GROUP BY Q.id_quiz, QR.id_quiz_result,
		  Q.title,
			Q.image,
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
			HAVING COUNT(QR.id_quiz_result) = 0
		ORDER BY {raw:sort}
		LIMIT {int:start} , {int:limit}',
		$query_parameters
	);

	$context['SMFQuiz']['quizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizes'][] = $row;

	$smcFunc['db_free_result']($result);

	$context['SMFQuiz']['Action'] = 'quizes';
}

/**
 * Get Played Quizes Data.
 * @return void
 */
function GetPlayedQuizesData(): void
{
	global $context, $scripturl, $smcFunc, $txt, $modSettings;

	// @TODO allowedTo?
	$userId = (int) ($_GET['id_user'] ?? $context['user']['id']);
	$starts_with = (string) ($_GET['starts_with'] ?? '');
	$sort = (string) ($_REQUEST['sort'] ?? 'result_date');
	if ($sort === '')
		$sort = 'result_date';
	$limit = $modSettings['SMFQuiz_ListPageSizes'];
	$start = (int) ($_GET['start'] ?? 0);
	$isDescending = isset($_REQUEST['desc']);

	// Set up the columns...
	$context['columns'] = [
	// @TODO '' => ???
		'' => [
			'label' => '',
			'width' => '2'
		],
		'result_date' => [
			'label' => $txt['SMFQuiz_Common']['ResultDate']
		],
		'title' => [
			'label' => $txt['SMFQuiz_Common']['Quiz'],
		],
		'questions' => [
			'label' => $txt['SMFQuiz_Common']['Qs']
		],
		'correct' => [
			'label' => $txt['SMFQuiz_Common']['Crct'],
		],
		'incorrect' => [
			'label' => $txt['SMFQuiz_Common']['Incrt'],
		],
		'timeouts' => [
			'label' => $txt['SMFQuiz_Common']['Touts'],
		],
		'seconds' => [
			'label' => $txt['SMFQuiz_Common']['Secs'],
		],
		'percentage_correct' => [
			'label' => '% ' . $txt['SMFQuiz_Common']['Correct'],
		],
		'auto_compleyed' => [
			'label' => '',
			'width' => '1'
		]
	];

	// Set the filter links
	$context['letter_links'] = '<a href="' . $scripturl . '?action=SMFQuiz;sa=playedQuizes;id_user=' . $userId . '">*</a> ';
	for ($i = 97; $i < 123; $i++)
		$context['letter_links'] .= '<a href="' . $scripturl . '?action=SMFQuiz;sa=playedQuizes;id_user=' . $userId . ';starts_with=' . chr($i) . '">' . strtoupper(chr($i)) . '</a> ';

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=SMFQuiz;sa=playedQuizes;id_user=' . $userId . ';starts_with=' . $starts_with . ';sort=' . $col . ';start=0';

		if ((!$isDescending && $col === $sort) || ($col !== $sort && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $sort === $col;
	}

	$context['sort_by'] = $sort;
	$context['sort_direction'] = !$isDescending ? 'down' : 'up';

	// List out the different sorting methods...
	$sort_methods = [
		'result_date' => [
			'down' => 'result_date DESC',
			'up' => 'result_date ASC'
		],
		'title' => [
			'down' => 'title DESC',
			'up' => 'title ASC'
		],
		'questions' => [
			'down' => 'questions DESC',
			'up' => 'questions ASC'
		],
		'correct' => [
			'down' => 'correct DESC',
			'up' => 'correct ASC'
		],
		'incorrect' => [
			'down' => 'incorrect DESC',
			'up' => 'incorrect ASC'
		],
		'timeouts' => [
			'down' => 'timeouts DESC',
			'up' => 'timeouts ASC'
		],
		'seconds' => [
			'down' => 'total_seconds DESC',
			'up' => 'total_seconds ASC'
		],
		'percentage_correct' => [
			'down' => 'percentage_correct DESC',
			'up' => 'percentage_correct ASC'
		]
	];

	$query_parameters = [
		'sort' => $sort_methods[$sort][$context['sort_direction']] ?? 'Q.title ASC',
		'starts_with' => $starts_with . '%',
		'limit' => $limit,
		'start' => $start,
		'id_user' => $userId
	];

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}quiz Q
		INNER JOIN {db_prefix}quiz_result QR
			ON Q.id_quiz = QR.id_quiz
		WHERE title LIKE {string:starts_with}
			AND Q.enabled = 1
			AND QR.id_user = {int:id_user}',
		$query_parameters
	);
	list ($context['num_quizes']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=SMFQuiz;sa=playedQuizes;id_user=' . $userId . ';starts_with=' . $starts_with . ';sort=' . $sort . ($isDescending ? ';desc' : ''), $start, $context['num_quizes'], $limit);

	// Send the data to the template.
	$context['start'] = $start + 1;
	$context['end'] = min($start + $limit, $context['num_quizes']);

	$result = $smcFunc['db_query']('', '
		SELECT
			Q.id_quiz,
			Q.title,
			Q.image,
			Q.description,
			QR.result_date,
			QR.questions,
			QR.correct,
			QR.incorrect,
			QR.timeouts,
			QR.total_seconds,
			IFNULL(round((QR.correct / QR.questions) * 100),0) AS percentage_correct,
			(CASE Q.top_user_id
			WHEN {int:id_user} THEN 1
			ELSE 0
			END) AS top_score,
			auto_completed
		FROM {db_prefix}quiz Q
		INNER JOIN {db_prefix}quiz_result QR
			ON Q.id_quiz = QR.id_quiz
		WHERE Q.enabled = 1
			AND QR.id_user = {int:id_user}' . (!empty($starts_with) ? '
			AND Q.title LIKE {string:starts_with}' : '') . '
		ORDER BY {raw:sort}
		LIMIT {int:start} , {int:limit}',
		$query_parameters
	);

	$context['SMFQuiz']['quizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizes'][] = $row;

	$smcFunc['db_free_result']($result);

	$context['SMFQuiz']['Action'] = 'quizes';
}

/**
 * Get Quizes In Category Data.
 * @param int $id_category Category identifier.
 * @param int $id_user User identifier.
 * @return void
 */
function GetQuizesInCategoryData(int $id_category, int $id_user): void
{
	global $context, $scripturl, $smcFunc, $txt, $modSettings;

	$type = (string) ($_REQUEST['type'] ?? 'all');
	$limit = $modSettings['SMFQuiz_ListPageSizes'];
	$sort = (string) ($_REQUEST['sort'] ?? 'title');
	if ($sort === '')
		$sort = 'title';
	$start = (int) ($_GET['start'] ?? 0);
	$isDescending = isset($_REQUEST['desc']);

	// Set up the columns...
	$context['columns'] = [
		// @TODO '' => ???
		'' => [
			'label' => '',
			'width' => '2'
		],
		'title' => [
			'label' => $txt['SMFQuiz_Common']['Title']
		],
		'difficulty' => [
			'label' => $txt['SMFQuiz_Common']['Difficulty']
		],
		'questions' => [
			'label' => $txt['SMFQuiz_Common']['Questions']
		],
		'plays' => [
			'label' => $txt['SMFQuiz_Common']['Plays']
		],
		'played' => [
			'label' => $txt['SMFQuiz_Common']['Played']
		],
		'updated' => [
			'label' => $txt['SMFQuiz_Common']['Updated']
		]
	];

	$context['sort_direction'] = !$isDescending ? 'up' : 'down';

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=SMFQuiz;sa=categories;categoryId=' . $id_category . ';type=' . $type . ';sort=' . $col . ';start=0';

		if ((!$isDescending && $col === $sort) || ($col !== $sort && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $sort === $col;
	}

	$context['sort_by'] = $sort;

	// List out the different sorting methods...
	$sort_methods = [
		'title' => [
			'down' => 'title DESC',
			'up' => 'title ASC'
		],
		'difficulty' => [
			'down' => 'percentage DESC',
			'up' => 'percentage ASC'
		],
		'questions' => [
			'down' => 'questions_per_session DESC',
			'up' => 'questions_per_session ASC'
		],
		'plays' => [
			'down' => 'question_plays DESC',
			'up' => 'question_plays ASC'
		],
		'played' => [
			'down' => 'played DESC',
			'up' => 'played ASC'
		],
		'updated' => [
			'down' => 'updated DESC',
			'up' => 'updated ASC'
		]
	];

	$query_parameters = [
		'sort' => $sort_methods[$sort][$context['sort_direction']] ?? 'Q.title ASC',
		'limit' => $limit,
		'start' => $start,
		'id_category' => $id_category,
		'id_user' => $id_user
	];

	$request = $smcFunc['db_query']('','
		SELECT COUNT(*)
		FROM {db_prefix}quiz Q
		WHERE Q.id_category = {int:id_category}
			AND Q.enabled = 1',
		$query_parameters
	);

	list ($context['num_quizes']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=SMFQuiz;sa=categories;categoryId=' . $id_category . ';sort=' . $sort . ($isDescending ? ';desc' : ''), $start, $context['num_quizes'], $limit);

	// Send the data to the template.
	$context['start'] = $start + 1;
	$context['end'] = min($start + $limit, $context['num_quizes']);

	$result = $smcFunc['db_query']('', '
		SELECT
			Q.id_quiz,
			Q.title,
			Q.description,
			Q.image,
			Q.quiz_plays,
			Q.updated,
			Q.question_plays,
			Q.total_correct,
			round((Q.total_correct / Q.question_plays) * 100) AS percentage,
			COUNT(U.id_quiz) AS questions_per_session,
			IFNULL(QR.id_quiz_result,0) AS played
		FROM {db_prefix}quiz Q
		LEFT JOIN {db_prefix}quiz_question U
			ON Q.id_quiz = U.id_quiz
		LEFT JOIN {db_prefix}quiz_result QR
			ON Q.id_quiz = QR.id_quiz
			AND QR.id_user = {int:id_user}
		WHERE Q.id_category = {int:id_category}
			AND Q.enabled = 1
		GROUP BY
			Q.id_quiz,
			Q.title,
			Q.description,
			Q.quiz_plays,
			Q.updated,
			U.id_quiz,
			QR.id_quiz_result,
			Q.image,
			Q.question_plays,
			Q.total_correct,
			percentage
		ORDER BY {raw:sort}
		LIMIT {int:start} , {int:limit}',
		$query_parameters
	);

	$context['SMFQuiz']['quizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizes'][] = $row;

	$smcFunc['db_free_result']($result);

	$context['SMFQuiz']['Action'] = 'quizes';
}

/**
 * Get Quizes Data.
 * @return void
 */
function GetQuizesData(): void
{
	global $context, $scripturl, $smcFunc, $txt, $modSettings;

	$starts_with = (string) ($_GET['starts_with'] ?? '');
	$type = (string) ($_REQUEST['type'] ?? 'all');
	$limit = $modSettings['SMFQuiz_ListPageSizes'];
	$start = (int) ($_GET['start'] ?? 0);
	$isDescending = isset($_REQUEST['desc']);
	$sort = (string) ($_REQUEST['sort'] ?? 'title');
	if ($sort === '')
		$sort = 'title';

	// Set up the columns...
	$context['columns'] = [
		// @TODO '' => ???
		'' => [
			'label' => '',
			'width' => '2'
		],
		'title' => [
			'label' => $txt['SMFQuiz_Common']['Title']
		],
		'owner' => [
			'label' => $txt['SMFQuiz_Common']['Owner'],
			'width' => '25'
		],
		'description' => [
			'label' => $txt['SMFQuiz_Common']['Description']
		],
		'category' => [
			'label' => $txt['SMFQuiz_Common']['Category'],
			'width' => '20',
			'link_with' => 'website',
		],
		'play_limit' => [
			'label' => $txt['SMFQuiz_Common']['PlayLimit'],
			'width' => '20'
		],
		'questions' => [
			'label' => $txt['SMFQuiz_Common']['Qs'],
			'width' => '20'
		],
		'seconds' => [
			'label' => $txt['SMFQuiz_Common']['Secs'],
			'width' => '20'
		]
	];

	$context['sort_direction'] = !$isDescending ? 'up' : 'down';

	switch ($type)
	{
		case 'unplayed':
			break;
		case 'all':
			break;
		case 'new':
			$context['columns']['updated'] = [
				'label' => $txt['SMFQuiz_Common']['Updated'],
				'width' => '20'
			];
			$sort = (string) ($_REQUEST['sort'] ?? 'updated');
			if ($sort === '')
				$sort = 'updated';
			$context['sort_direction'] = !$isDescending ? 'down' : 'up';
			break;
		case 'popular':
			$context['columns']['quiz_plays'] = [
				'label' => $txt['SMFQuiz_Common']['Plays'],
				'width' => '20'
			];
			$sort = (string) ($_REQUEST['sort'] ?? 'quiz_plays');
			if ($sort === '')
				$sort = 'quiz_plays';
			$context['sort_direction'] = !$isDescending ? 'down' : 'up';
			break;
		case 'easiest':
			$context['columns']['percentage_correct'] = [
				'label' => $txt['SMFQuiz_Common']['PercentageCorrect'],
				'width' => '20'
			];
			$sort = (string) ($_REQUEST['sort'] ?? 'percentage_correct');
			if ($sort === '')
				$sort = 'percentage_correct';
			$context['sort_direction'] = !$isDescending ? 'down' : 'up';
			break;
		case 'hardest':
			$context['columns']['percentage_correct'] = [
				'label' => $txt['SMFQuiz_Common']['PercentageCorrect'],
				'width' => '20'
			];
			$sort = (string) ($_REQUEST['sort'] ?? 'percentage_correct');
			if ($sort === '')
				$sort = 'percentage_correct';
			$context['sort_direction'] = !$isDescending ? 'up' : 'down';
			break;
	}

	// Set the filter links
	$context['letter_links'] = '<a href="' . $scripturl . '?action=SMFQuiz;sa=quizes;type=' . $type . '">*</a> ';
	for ($i = 97; $i < 123; $i++)
		$context['letter_links'] .= '<a href="' . $scripturl . '?action=SMFQuiz;sa=quizes;type=' . $type . ';starts_with=' . chr($i) . '">' . strtoupper(chr($i)) . '</a> ';

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=SMFQuiz;sa=quizes;type=' . $type . ';starts_with=' . $starts_with . ';sort=' . $col . ';start=0';

		if ((!$isDescending && $col === $sort) || ($col !== $sort && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $sort === $col;
	}

	$context['sort_by'] = $sort;

	// List out the different sorting methods...
	$sort_methods = [
		'title' => [
			'down' => 'title DESC',
			'up' => 'title ASC'
		],
		'owner' => [
			'down' => 'real_name DESC',
			'up' => 'real_name ASC'
		],
		'description' => [
			'down' => 'description DESC',
			'up' => 'description ASC'
		],
		'category' => [
			'down' => 'category_name DESC',
			'up' => 'category_name ASC'
		],
		'play_limit' => [
			'down' => 'play_limit DESC',
			'up' => 'play_limit ASC'
		],
		'questions' => [
			'down' => 'questions_per_session DESC',
			'up' => 'questions_per_session ASC'
		],
		'seconds' => [
			'down' => 'seconds_per_question DESC',
			'up' => 'seconds_per_question ASC'
		],
		'updated' => [
			'down' => 'updated DESC',
			'up' => 'updated ASC'
		],
		'quiz_plays' => [
			'down' => 'quiz_plays DESC',
			'up' => 'quiz_plays ASC'
		],
		'percentage_correct' => [
			'down' => 'percentage_correct DESC',
			'up' => 'percentage_correct ASC'
		]
	];

	$query_parameters = [
		'sort' => $sort_methods[$sort][$context['sort_direction']] ?? 'Q.title ASC',
		'starts_with' => $starts_with . '%',
		'limit' => $limit,
		'start' => $start,
	];

	$request = $smcFunc['db_query']('','
		SELECT COUNT(*)
		FROM {db_prefix}quiz Q
		INNER JOIN {db_prefix}members M
			ON Q.creator_id = M.id_member
		WHERE Q.title LIKE {string:starts_with}
			AND Q.enabled = 1',
		$query_parameters
	);
	list ($context['num_quizes']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=SMFQuiz;type=' . $type . ';sa=quizes;starts_with=' . $starts_with . ';sort=' . $sort . ($isDescending ? ';desc' : ''), $start, $context['num_quizes'], $limit);

	// Send the data to the template.
	$context['start'] = $start + 1;
	$context['end'] = min($start + $limit, $context['num_quizes']);

	$result = $smcFunc['db_query']('', '
		SELECT
			Q.id_quiz,
			Q.title,
			Q.image,
			Q.creator_id,
			M.real_name,
			Q.description,
			Q.play_limit,
			Q.seconds_per_question,
			Q.show_answers,
			Q.enabled,
			Q.updated,
			Q.quiz_plays,
			round(Q.total_correct / Q.question_plays * 100) AS percentage_correct,
			QC.id_category,
			(CASE WHEN Q.id_category = 0 THEN \'Top Level\' ELSE QC.name END) AS category_name,
			COUNT(U.id_quiz) AS questions_per_session
		FROM {db_prefix}quiz Q
		LEFT JOIN {db_prefix}quiz_category QC
			ON Q.id_category = QC.id_category
		INNER JOIN {db_prefix}quiz_question U
			ON Q.id_quiz = U.id_quiz
		INNER JOIN {db_prefix}members M
			ON Q.creator_id = M.id_member
		WHERE Q.enabled = 1' . (!empty($starts_with) ? '
			AND Q.title LIKE {string:starts_with}' : '') . '
		GROUP BY Q.id_quiz,
			Q.title,
			Q.image,
			Q.creator_id,
			M.real_name,
			Q.description,
			Q.total_correct,
			Q.question_plays,
			Q.play_limit,
			Q.seconds_per_question,
			Q.show_answers,
			Q.enabled,
			Q.updated,
			Q.quiz_plays,
			QC.id_category,
			Q.id_category,
			QC.name,
			U.id_quiz
		ORDER BY {raw:sort}
		LIMIT {int:start} , {int:limit}',
		$query_parameters
	);

	$context['SMFQuiz']['quizes'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quizes'][] = $row;

	$smcFunc['db_free_result']($result);

	$context['SMFQuiz']['Action'] = 'quizes';
}

/**
 * Get Quiz Masters Data.
 * @return void
 */
function GetQuizMastersData(): void
{
	global $context, $scripturl, $smcFunc, $txt, $modSettings;

	$sort = (string) ($_REQUEST['sort'] ?? 'default');
	if ($sort === '')
		$sort = 'default';
	$limit = $modSettings['SMFQuiz_ListPageSizes'];
	$start = (int) ($_GET['start'] ?? 0);
	$isDescending = isset($_REQUEST['desc']);

	// Set up the columns...
	$context['columns'] = [
		'user' => [
			'label' => $txt['SMFQuiz_Common']['Member'],
			'width' => '2000'
		],
		'total_wins' => [
			'label' => $txt['SMFQuiz_Common']['Wins'],
			'width' => '2'
		]
	];

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=SMFQuiz;sa=quizmasters;sort=' . $col . ';start=0';

		if ((!$isDescending && $col === $sort) || ($col !== $sort && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $sort === $col;
	}

	$context['sort_by'] = $sort;
	$context['sort_direction'] = !$isDescending ? 'up' : 'down';

	// List out the different sorting methods...
	$sort_methods = [
		'default' => [
			'up' => 'total_wins DESC'
		],
		'user' => [
			'down' => 'real_name DESC',
			'up' => 'real_name ASC'
		],
		'total_wins' => [
			'down' => 'total_wins DESC',
			'up' => 'total_wins ASC'
		]
	];

	$query_parameters = [
		'sort' => $sort_methods[$sort][$context['sort_direction']],
		'limit' => $limit,
		'start' => $start,
	];

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}quiz Q
		INNER JOIN {db_prefix}members M
			ON Q.top_user_id = M.id_member
		WHERE Q.top_user_id <> 0
		GROUP BY Q.top_user_id',
		$query_parameters
	);
	list ($context['num_quizes']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=SMFQuiz;sa=quizmasters;sort=' . $sort . ($isDescending ? ';desc' : ''), $start, $context['num_quizes'], $limit);

	// Send the data to the template.
	$context['start'] = $start + 1;
	$context['end'] = min($start + $limit, $context['num_quizes']);

	$result = $smcFunc['db_query']('', '
		SELECT
			Q.top_user_id AS id_user,
			M.real_name,
			COUNT(*) AS total_wins
		FROM {db_prefix}quiz Q
		INNER JOIN {db_prefix}members M
			ON Q.top_user_id = M.id_member
		WHERE Q.top_user_id <> 0
		GROUP BY Q.top_user_id, M.real_name
		ORDER BY {raw:sort}
		LIMIT {int:start} , {int:limit}',
		$query_parameters
	);

	$context['SMFQuiz']['quiz_masters'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['quiz_masters'][] = $row;

	$smcFunc['db_free_result']($result);

	$context['SMFQuiz']['Action'] = 'quiz_masters';
}

/**
 * Get Quiz League Data.
 * @return void
 */
function GetQuizLeagueData(): void
{
	global $context, $scripturl, $smcFunc, $txt, $modSettings;

	$id_quiz_league = (int) ($_GET['id_quiz_league'] ?? 0);
	$current_round = (int) ($_GET['current_round'] ?? 0);
	$sort = (string) ($_REQUEST['sort'] ?? 'default');
	if ($sort === '')
		$sort = 'default';
	$limit = $modSettings['SMFQuiz_ListPageSizes'];
	$start = (int) ($_GET['start'] ?? 0);
	$isDescending = isset($_REQUEST['desc']);

	// Set up the columns...
	$context['columns'] = [
		'position' => [
			'label' => $txt['SMFQuiz_Common']['Position'],
			'width' => '20'
		],
		'posmove' => [
			'label' => '',
			'width' => '20'
		],
		'member' => [
			'label' => $txt['SMFQuiz_Common']['Member'],
			'width' => '2000'
		],
		'plays' => [
			'label' => $txt['SMFQuiz_Common']['Plays'],
			'width' => '20'
		],
		'correct' => [
			'label' => $txt['SMFQuiz_Common']['Correct'],
			'width' => '20'
		],
		'incorrect' => [
			'label' => $txt['SMFQuiz_Common']['Incorrect'],
			'width' => '20'
		],
		'timeouts' => [
			'label' => $txt['SMFQuiz_Common']['Timeouts'],
			'width' => '20'
		],
		'seconds' => [
			'label' => $txt['SMFQuiz_Common']['Seconds'],
			'width' => '20'
		],
		'points' => [
			'label' => $txt['SMFQuiz_Common']['Points'],
			'width' => '20'
		]
	];

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=SMFQuiz;sa=quizleaguetable;current_round=' . $current_round . ';id_quiz_league=' . $id_quiz_league . ';sort=' . $col . ';start=0';

		if ((!$isDescending && $col === $sort) || ($col !== $sort && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $sort === $col;
	}

	$context['sort_by'] = $sort;
	$context['sort_direction'] = !$isDescending ? 'up' : 'down';

	// List out the different sorting methods...
	$sort_methods = [
		'default' => [
			'up' => 'QLT.current_position ASC'
		],
		'position' => [
			'down' => 'current_position DESC',
			'up' => 'current_position ASC'
		],
		'member' => [
			'down' => 'real_name DESC',
			'up' => 'real_name ASC'
		],
		'plays' => [
			'down' => 'plays DESC',
			'up' => 'plays ASC'
		],
		'correct' => [
			'down' => 'correct DESC',
			'up' => 'correct ASC'
		],
		'incorrect' => [
			'down' => 'incorrect DESC',
			'up' => 'incorrect ASC'
		],
		'timeouts' => [
			'down' => 'timeouts DESC',
			'up' => 'timeouts ASC'
		],
		'seconds' => [
			'down' => 'seconds DESC',
			'up' => 'seconds ASC'
		],
		'points' => [
			'down' => 'points DESC',
			'up' => 'points ASC'
		]
	];

	$query_parameters = [
		'sort' => $sort_methods[$sort][$context['sort_direction']],
		'limit' => $limit,
		'current_round' => $current_round - 1,
		'id_quiz_league' => $id_quiz_league,
		'start' => $start,
	];

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}quiz_league_table QLT
		INNER JOIN {db_prefix}members M
			ON QLT.id_user = M.id_member
		WHERE QLT.round = {int:current_round}
			AND QLT.id_quiz_league = {int:id_quiz_league}',
		$query_parameters
	);
	list ($context['num_quizes']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=SMFQuiz;sa=quizleaguetable;current_round=' . $current_round . ';id_quiz_league=' . $id_quiz_league . ';sort=' . $sort . ($isDescending ? ';desc' : ''), $start, $context['num_quizes'], $limit);

	// Send the data to the template.
	$context['start'] = $start + 1;
	$context['end'] = min($start + $limit, $context['num_quizes']);

	$result = $smcFunc['db_query']('', '
		SELECT
			QLT.id_quiz_league_table,
			QLT.current_position,
			QLT.id_user,
			M.real_name,
			QLT.last_position,
			QLT.plays,
			QLT.correct,
			QLT.incorrect,
			QLT.timeouts,
			QLT.seconds,
			QLT.points,
			QL.title,
			QLT.last_position - QLT.current_position AS pos_move
		FROM {db_prefix}quiz_league_table QLT
		INNER JOIN {db_prefix}members M
			ON QLT.id_user = M.id_member
		INNER JOIN {db_prefix}quiz_league QL
			ON QLT.id_quiz_league = QL.id_quiz_league
		WHERE QLT.round = {int:current_round}
			AND QLT.id_quiz_league = {int:id_quiz_league}
		ORDER BY {raw:sort}
		LIMIT {int:start} , {int:limit}',
		$query_parameters
	);

	$context['SMFQuiz']['quiz_league_table'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['SMFQuiz']['quiz_league_table'][] = $row;
		$context['SMFQuiz']['quiz_league_title'] = $row['title'];
	}

	$smcFunc['db_free_result']($result);

	$context['SMFQuiz']['Action'] = 'quiz_league_table';
}

/**
 * Get Preview Quiz Data.
 * @return void
 */
function GetPreviewQuizData(): void
{
	global $context, $smcFunc;

	GetQuiz($context['id_quiz']);

	// Get creator
	$creator = 0;
	foreach ($context['SMFQuiz']['quiz'] as $row)
		$creator = (int) $row['creator_id'];

	$context['current_subaction'] = 'preview';

	// We don't want to return a preview if the user requesting the preview is not the creator
	if ($creator !== (int) $context['user']['id'])
		return;

	$result = $smcFunc['db_query']('', '
	SELECT
		QQ.id_question,
		QQ.question_text,
		QQ.answer_text AS question_answer_text,
		QA.id_answer,
		QA.answer_text,
		QA.is_correct
	FROM {db_prefix}quiz_question QQ
		LEFT JOIN {db_prefix}quiz_answer QA
			ON QQ.id_question = QA.id_question
	WHERE id_quiz = {int:id_quiz}
	ORDER BY QQ.id_question',
		[
			'id_quiz' => $context['id_quiz']
		]
	);

	// Loop through leagues that are enabled
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$context['SMFQuiz']['questions'][] = $row;
	$smcFunc['db_free_result']($result);
}

/**
 * Get Quiz League Results Data.
 * @return void
 */
function GetQuizLeagueResultsData(): void
{
	global $context, $scripturl, $smcFunc, $txt, $modSettings;

	$id_quiz_league = (int) ($_GET['id_quiz_league'] ?? 0);
	$sort = (string) ($_REQUEST['sort'] ?? 'default');
	if ($sort === '')
		$sort = 'default';
	$limit = $modSettings['SMFQuiz_ListPageSizes'];
	$start = (int) ($_GET['start'] ?? 0);
	$isDescending = isset($_REQUEST['desc']);

	// Set up the columns...
	$context['columns'] = [
		'result_date' => [
			'label' => $txt['SMFQuiz_Common']['ResultDate'],
			'width' => '50'
		],
		'round' => [
			'label' => $txt['SMFQuiz_Common']['Round'],
			'width' => '20'
		],
		'member' => [
			'label' => $txt['SMFQuiz_Common']['Member'],
			'width' => '20'
		],
		'correct' => [
			'label' => $txt['SMFQuiz_Common']['Correct'],
			'width' => '20'
		],
		'incorrect' => [
			'label' => $txt['SMFQuiz_Common']['Incorrect'],
			'width' => '20'
		],
		'timeouts' => [
			'label' => $txt['SMFQuiz_Common']['Timeouts'],
			'width' => '20'
		],
		'seconds' => [
			'label' => $txt['SMFQuiz_Common']['Seconds'],
			'width' => '20'
		],
		'points' => [
			'label' => $txt['SMFQuiz_Common']['Points'],
			'width' => '20'
		]
	];

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		$context['columns'][$col]['href'] = $scripturl . '?action=SMFQuiz;sa=quizleagueresults;id_quiz_league=' . $id_quiz_league . ';sort=' . $col . ';start=0';

		if ((!$isDescending && $col === $sort) || ($col !== $sort && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $sort === $col;
	}

	$context['sort_by'] = $sort;
	$context['sort_direction'] = !$isDescending ? 'up' : 'down';

	// List out the different sorting methods...
	$sort_methods = [
		'default' => [
			'up' => 'result_date DESC'
		],
		'result_date' => [
			'down' => 'result_date DESC',
			'up' => 'result_date ASC'
		],
		'round' => [
			'down' => 'round DESC',
			'up' => 'round ASC'
		],
		'member' => [
			'down' => 'real_name DESC',
			'up' => 'real_name ASC'
		],
		'correct' => [
			'down' => 'correct DESC',
			'up' => 'correct ASC'
		],
		'incorrect' => [
			'down' => 'incorrect DESC',
			'up' => 'incorrect ASC'
		],
		'timeouts' => [
			'down' => 'timeouts DESC',
			'up' => 'timeouts ASC'
		],
		'seconds' => [
			'down' => 'seconds DESC',
			'up' => 'seconds ASC'
		],
		'points' => [
			'down' => 'points DESC',
			'up' => 'points ASC'
		]
	];

	$query_parameters = [
		'sort' => $sort_methods[$sort][$context['sort_direction']],
		'limit' => $limit,
		'id_quiz_league' => $id_quiz_league,
		'start' => $start,
	];

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}quiz_league_result QLR
		INNER JOIN {db_prefix}members M
			ON QLR.id_user = M.id_member
		WHERE QLR.id_quiz_league = {int:id_quiz_league}',
		$query_parameters
	);
	list ($context['num_quizes']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Construct the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=SMFQuiz;sa=quizleagueresults;id_quiz_league=' . $id_quiz_league . ';sort=' . $sort . ($isDescending ? ';desc' : ''), $start, $context['num_quizes'], $limit);

	// Send the data to the template.
	$context['start'] = $start + 1;
	$context['end'] = min($start + $limit, $context['num_quizes']);

	$result = $smcFunc['db_query']('', '
		SELECT
			QLR.id_user,
			M.real_name,
			QLR.correct,
			QLR.incorrect,
			QLR.timeouts,
			QLR.points,
			QLR.result_date,
			QLR.round,
			QLR.seconds,
			QL.title
		FROM {db_prefix}quiz_league_result QLR
		INNER JOIN {db_prefix}members M
			ON QLR.id_user = M.id_member
		INNER JOIN {db_prefix}quiz_league QL
			ON QL.id_quiz_league = QLR.id_quiz_league
		WHERE QLR.id_quiz_league = {int:id_quiz_league}
		ORDER BY {raw:sort}
		LIMIT {int:start} , {int:limit}',
		$query_parameters
	);

	$context['SMFQuiz']['quiz_league_results'] = [];
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['SMFQuiz']['quiz_league_results'][] = $row;
		$context['SMFQuiz']['quiz_league_title'] = $row['title'];
	}

	$smcFunc['db_free_result']($result);

	$context['SMFQuiz']['Action'] = 'quiz_league_results';
}
