<?php
declare(strict_types=1);

namespace Quiz\Tasks;

/**
 * Handles scheduled quiz league maintenance and cleanup.
 */
class Scheduled
{
	/**
	 * Runs scheduled maintenance for quiz leagues.
	 *
	 * @return bool
	 */
	public function maintenance(): bool
	{
		global $smcFunc, $modSettings, $sourcedir;

		require_once($sourcedir . '/Quiz/Admin.php');

		$getLeagueDatesResult = $smcFunc['db_query']('', '
			SELECT
				QL.id_quiz_league,
				QL.updated,
				QL.day_interval,
				QL.current_round,
				QL.total_rounds,
				QL.title,
				COUNT(QLR.id_quiz_league_result) AS plays
			FROM {db_prefix}quiz_league QL
			LEFT JOIN {db_prefix}quiz_league_result QLR
				ON QL.id_quiz_league = QLR.id_quiz_league
			WHERE state = 1
			GROUP BY
				QL.id_quiz_league,
				QL.updated,
				QL.day_interval,
				QL.current_round,
				QL.total_rounds,
				QL.title,
				QLR.id_quiz_league_result',
			[]
		);

		while ($row = $smcFunc['db_fetch_assoc']($getLeagueDatesResult)) {
			$nextUpdate = strtotime('+' . $row['day_interval'] . ' days', (int) $row['updated']);
			if ($nextUpdate >= time()) {
				continue;
			}

			$lastWeekResultsResult = $smcFunc['db_query']('', '
				SELECT
					QLR.id_user,
					QLR.correct,
					QLR.incorrect,
					QLR.timeouts,
					QLR.seconds,
					QLR.points
				FROM {db_prefix}quiz_league_result QLR
				WHERE QLR.id_quiz_league = {int:id_quiz_league}
					AND QLR.round = {int:lastWeekRound}',
				[
					'id_quiz_league' => $row['id_quiz_league'],
					'lastWeekRound' => $row['current_round'],
				]
			);

			if ($smcFunc['db_num_rows']($lastWeekResultsResult) > 0) {
				while ($lastWeekResultsRow = $smcFunc['db_fetch_assoc']($lastWeekResultsResult)) {
					$userTableEntryResult = $smcFunc['db_query']('', '
						SELECT
							QLT.id_user,
							QLT.id_quiz_league_table
						FROM {db_prefix}quiz_league_table QLT
						WHERE QLT.id_quiz_league = {int:id_quiz_league}
							AND QLT.id_user = {int:id_user}
							AND QLT.round = {int:lastWeekRound}
						LIMIT 0, 1',
						[
							'id_quiz_league' => $row['id_quiz_league'],
							'id_user' => $lastWeekResultsRow['id_user'],
							'lastWeekRound' => $row['current_round'],
						]
					);

					if ($smcFunc['db_num_rows']($userTableEntryResult) > 0) {
						while ($userTableEntryRow = $smcFunc['db_fetch_assoc']($userTableEntryResult)) {
							$smcFunc['db_query']('', '
								UPDATE {db_prefix}quiz_league_table
								SET
									correct = correct + {int:correct},
									incorrect = incorrect + {int:incorrect},
									timeouts = timeouts + {int:timeouts},
									points = points + {int:points},
									seconds = seconds + {int:seconds},
									plays = plays + 1
								WHERE round = {int:round}
									AND id_quiz_league_table = {int:id_quiz_league_table}
									AND id_user = {int:id_user}',
								[
									'correct' => $lastWeekResultsRow['correct'],
									'incorrect' => $lastWeekResultsRow['incorrect'],
									'timeouts' => $lastWeekResultsRow['timeouts'],
									'points' => $lastWeekResultsRow['points'],
									'seconds' => $lastWeekResultsRow['seconds'],
									'round' => $row['current_round'],
									'id_quiz_league_table' => $userTableEntryRow['id_quiz_league_table'],
									'id_user' => $lastWeekResultsRow['id_user'],
								]
							);
						}
					} else {
						$smcFunc['db_insert']('insert',
							'{db_prefix}quiz_league_table',
							[
								'current_position' => 'int',
								'id_user' => 'int',
								'last_position' => 'int',
								'plays' => 'int',
								'correct' => 'int',
								'incorrect' => 'int',
								'timeouts' => 'int',
								'points' => 'int',
								'id_quiz_league' => 'int',
								'round' => 'int',
								'seconds' => 'int',
							],
							[
								0,
								$lastWeekResultsRow['id_user'],
								0,
								1,
								$lastWeekResultsRow['correct'],
								$lastWeekResultsRow['incorrect'],
								$lastWeekResultsRow['timeouts'],
								$lastWeekResultsRow['points'],
								$row['id_quiz_league'],
								$row['current_round'],
								$lastWeekResultsRow['seconds'],
							],
							['id_quiz_league_table']
						);
					}

					$smcFunc['db_free_result']($userTableEntryResult);
				}
			}
			$smcFunc['db_free_result']($lastWeekResultsResult);

			if ($row['current_round'] < 2 || $row['plays'] < 2) {
				$quizLeaguePosResult = $smcFunc['db_query']('', '
					SELECT
						QLT.id_quiz_league_table,
						QLT.current_position as last_position,
						QLT.id_user
					FROM {db_prefix}quiz_league_table QLT
					WHERE QLT.round = {int:current_round}
						AND QLT.id_quiz_league = {int:id_quiz_league}
					ORDER BY
						QLT.points DESC,
						QLT.seconds ASC,
						QLT.plays ASC',
					[
						'current_round' => $row['current_round'],
						'id_quiz_league' => $row['id_quiz_league'],
					]
				);
			} else {
				$quizLeaguePosResult = $smcFunc['db_query']('', '
					SELECT
						QLT1.id_quiz_league_table,
						QLT1.id_user,
						IFNULL(QLT2.current_position,0) AS last_position
					FROM {db_prefix}quiz_league_table QLT1
					LEFT JOIN {db_prefix}quiz_league_table QLT2
						ON QLT1.round = QLT2.round+1
						AND QLT1.id_user = QLT2.id_user
					WHERE QLT1.id_quiz_league = {int:id_quiz_league}
						AND QLT1.round = {int:current_round}
					ORDER BY
						QLT1.Points DESC,
						QLT1.seconds ASC,
						QLT1.plays ASC',
					[
						'current_round' => $row['current_round'],
						'id_quiz_league' => $row['id_quiz_league'],
					]
				);
			}

			$position = 1;
			$id_leader = 0;
			while ($quizLeaguePosRow = $smcFunc['db_fetch_assoc']($quizLeaguePosResult)) {
				if ($position === 1) {
					$id_leader = $quizLeaguePosRow['id_user'];
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}quiz_league
						SET id_leader = {int:id_leader}
						WHERE id_quiz_league = {int:id_quiz_league}',
						[
							'id_leader' => $id_leader,
							'id_quiz_league' => $row['id_quiz_league'],
						]
					);
				}

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}quiz_league_table
					SET
						current_position = {int:position},
						last_position = {int:last_position}
					WHERE id_quiz_league_table = {int:id_quiz_league_table}',
					[
						'position' => $position,
						'last_position' => $quizLeaguePosRow['last_position'],
						'id_quiz_league_table' => $quizLeaguePosRow['id_quiz_league_table'],
					]
				);

				if (!empty($modSettings['SMFQuiz_SendPMOnLeagueRoundUpdate'])) {
					require_once($sourcedir . '/Subs-Post.php');

					$pmto = [
						'to' => [],
						'bcc' => [$quizLeaguePosRow['id_user']],
					];

					$subject = ParseLeagueMessage($modSettings['SMFQuiz_PMLeagueRoundUpdateSubject'], $row['title'], $quizLeaguePosRow['last_position'], $position, ($position - $quizLeaguePosRow['last_position']), $row['id_quiz_league']);
					$message = ParseLeagueMessage($modSettings['SMFQuiz_PMLeagueRoundUpdateMsg'], $row['title'], $quizLeaguePosRow['last_position'], $position, ($position - $quizLeaguePosRow['last_position']), $row['id_quiz_league']);
					$pmfrom = [
						'id' => $modSettings['SMFQuiz_ImportQuizesAsUserId'],
						'name' => 'Quiz Notifier',
						'username' => 'Quiz Notifier',
					];

					sendpm($pmto, $subject, $message, 0, $pmfrom);
				}

				$position++;
			}
			$smcFunc['db_free_result']($quizLeaguePosResult);

			$smcFunc['db_query']('', '
				DELETE
				FROM {db_prefix}quiz_session
				WHERE id_quiz_league = {int:id_quiz_league}',
				[
					'id_quiz_league' => $row['id_quiz_league'],
				]
			);

			if ($row['current_round'] > $row['total_rounds'] - 1) {
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}quiz_league QL
					SET
						current_round = total_rounds,
						state = 2,
						id_leader = {int:id_leader}
					WHERE id_quiz_league = {int:id_quiz_league}',
					[
						'id_quiz_league' => $row['id_quiz_league'],
						'id_leader' => $id_leader,
					]
				);
				continue;
			}

			$smcFunc['db_query']('', '
				INSERT INTO {db_prefix}quiz_league_table
				(
					current_position,
					id_user,
					last_position,
					plays,
					correct,
					incorrect,
					timeouts,
					points,
					id_quiz_league,
					round,
					seconds
				)
				SELECT
					current_position,
					id_user,
					last_position,
					plays,
					correct,
					incorrect,
					timeouts,
					points,
					id_quiz_league,
					{int:round}+1,
					seconds
				FROM {db_prefix}quiz_league_table
				WHERE round = {int:round}
					AND id_quiz_league = {int:id_quiz_league}',
				[
					'round' => $row['current_round'],
					'id_quiz_league' => $row['id_quiz_league'],
				]
			);

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}quiz_league QL
				SET
					current_round = current_round + 1,
					updated = {int:newUpdateTime}
				WHERE id_quiz_league = {int:id_quiz_league}',
				[
					'id_quiz_league' => $row['id_quiz_league'],
					'newUpdateTime' => $nextUpdate,
				]
			);
		}

		$smcFunc['db_free_result']($getLeagueDatesResult);
		$this->quiz_clean();

		return true;
	}

	/**
	 * Runs quiz cleanup tasks when automatic cleanup is enabled.
	 */
	public function quiz_clean(): void
	{
		global $modSettings, $sourcedir;

		if (($modSettings['SMFQuiz_AutoClean'] ?? '') !== 'on') {
			return;
		}

		require_once($sourcedir . '/Quiz/Db.php');

		$date = mktime(0, 0, 0, (int) date('m'), (int) date('d') - 7, (int) date('Y'));
		$rows = DeleteInfoBoardEntries($date);

		CleanDisputes();
		CleanAnswers();
		CleanResults();
		CleanQuestions();
		CompleteQuizSessions($date);
	}
}
