<?php

declare(strict_types=1);

namespace Quiz\Tasks;

/**
 * Scheduled maintenance tasks for the Quiz modification.
 *
 * Registered via SMF's scheduled-task infrastructure.
 *
 * @package Quiz\Tasks
 */
class Scheduled
{
    /**
     * Run all scheduled quiz maintenance operations.
     *
     * Advances quiz league rounds when their interval has elapsed,
     * updates positions, and calls the cleanup routine.
     *
     * @return bool True on completion
     */
    public function maintenance(): bool
    {
        global $smcFunc, $modSettings, $sourcedir;

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
            $nextUpdate = strtotime('+' . (int)$row['day_interval'] . ' days', (int)$row['updated']);

            if ($nextUpdate >= time()) {
                continue;
            }

            $this->advanceLeagueRound($row, (int)$nextUpdate, $modSettings, $sourcedir, $smcFunc);
        }

        $smcFunc['db_free_result']($getLeagueDatesResult);

        $this->quiz_clean();

        return true;
    }

    /**
     * Advance a quiz league to the next round.
     *
     * Updates the league table with last-round results, recalculates positions,
     * optionally sends PMs, and either closes the league or opens the next round.
     *
     * @param array<string, mixed> $row League row from the DB query
     * @param int $nextUpdate Unix timestamp for the next scheduled update
     * @param array<string, mixed> $modSettings SMF mod settings
     * @param string $sourcedir SMF source directory
     * @param array<string, callable> $smcFunc SMF database functions
     * @return void
     */
    private function advanceLeagueRound(array $row, int $nextUpdate, array $modSettings, string $sourcedir, array &$smcFunc): void
    {
        $leagueId     = (int)$row['id_quiz_league'];
        $currentRound = (int)$row['current_round'];
        $totalRounds  = (int)$row['total_rounds'];

        // Retrieve last round results
        $lastWeekResultsResult = $smcFunc['db_query']('', '
            SELECT QLR.id_user, QLR.correct, QLR.incorrect, QLR.timeouts, QLR.seconds, QLR.points
            FROM {db_prefix}quiz_league_result QLR
            WHERE QLR.id_quiz_league = {int:id_quiz_league}
                AND QLR.round = {int:lastWeekRound}',
            ['id_quiz_league' => $leagueId, 'lastWeekRound' => $currentRound]
        );

        if ($smcFunc['db_num_rows']($lastWeekResultsResult) > 0) {
            while ($lastWeekResultsRow = $smcFunc['db_fetch_assoc']($lastWeekResultsResult)) {
                $this->upsertLeagueTableEntry($leagueId, $currentRound, $lastWeekResultsRow, $smcFunc);
            }
        }
        $smcFunc['db_free_result']($lastWeekResultsResult);

        // Recalculate positions
        $quizLeaguePosResult = $this->fetchPositionResults($leagueId, $currentRound, (int)$row['plays'], $smcFunc);

        $position = 1;
        $idLeader = 0;

        while ($posRow = $smcFunc['db_fetch_assoc']($quizLeaguePosResult)) {
            if ($position === 1) {
                $idLeader = (int)$posRow['id_user'];
                $smcFunc['db_query']('', '
                    UPDATE {db_prefix}quiz_league
                    SET id_leader = {int:id_leader}
                    WHERE id_quiz_league = {int:id_quiz_league}',
                    ['id_leader' => $idLeader, 'id_quiz_league' => $leagueId]
                );
            }

            $smcFunc['db_query']('', '
                UPDATE {db_prefix}quiz_league_table
                SET current_position = {int:position}, last_position = {int:last_position}
                WHERE id_quiz_league_table = {int:id_quiz_league_table}',
                [
                    'position'             => $position,
                    'last_position'        => (int)$posRow['last_position'],
                    'id_quiz_league_table' => (int)$posRow['id_quiz_league_table'],
                ]
            );

            if (!empty($modSettings['SMFQuiz_SendPMOnLeagueRoundUpdate'])) {
                $this->sendLeagueRoundUpdatePm(
                    $posRow,
                    $row,
                    $position,
                    $modSettings,
                    $sourcedir,
                    $smcFunc
                );
            }

            $position++;
        }
        $smcFunc['db_free_result']($quizLeaguePosResult);

        // Delete active sessions for this league
        $smcFunc['db_query']('', '
            DELETE FROM {db_prefix}quiz_session WHERE id_quiz_league = {int:id_quiz_league}',
            ['id_quiz_league' => $leagueId]
        );

        if ($currentRound > $totalRounds - 1) {
            // Final round — close the league
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}quiz_league QL
                SET current_round = total_rounds, state = 2, id_leader = {int:id_leader}
                WHERE id_quiz_league = {int:id_quiz_league}',
                ['id_quiz_league' => $leagueId, 'id_leader' => $idLeader]
            );
        } else {
            // Copy current round entries to the next round
            $smcFunc['db_query']('', '
                INSERT INTO {db_prefix}quiz_league_table
                (current_position, id_user, last_position, plays, correct, incorrect,
                 timeouts, points, id_quiz_league, round, seconds)
                SELECT current_position, id_user, last_position, plays, correct, incorrect,
                    timeouts, points, id_quiz_league, {int:round}+1, seconds
                FROM {db_prefix}quiz_league_table
                WHERE round = {int:round} AND id_quiz_league = {int:id_quiz_league}',
                ['round' => $currentRound, 'id_quiz_league' => $leagueId]
            );

            $smcFunc['db_query']('', '
                UPDATE {db_prefix}quiz_league QL
                SET current_round = current_round + 1, updated = {int:newUpdateTime}
                WHERE id_quiz_league = {int:id_quiz_league}',
                ['id_quiz_league' => $leagueId, 'newUpdateTime' => $nextUpdate]
            );
        }
    }

    /**
     * Insert or update a league table entry for a player.
     *
     * @param int $leagueId League ID
     * @param int $currentRound Current round number
     * @param array<string, mixed> $resultRow Player result row
     * @param array<string, callable> $smcFunc SMF database functions
     * @return void
     */
    private function upsertLeagueTableEntry(int $leagueId, int $currentRound, array $resultRow, array &$smcFunc): void
    {
        $checkResult = $smcFunc['db_query']('', '
            SELECT QLT.id_user, QLT.id_quiz_league_table
            FROM {db_prefix}quiz_league_table QLT
            WHERE QLT.id_quiz_league = {int:id_quiz_league}
                AND QLT.id_user = {int:id_user}
                AND QLT.round = {int:lastWeekRound}
            LIMIT 0, 1',
            [
                'id_quiz_league' => $leagueId,
                'id_user'        => (int)$resultRow['id_user'],
                'lastWeekRound'  => $currentRound,
            ]
        );

        if ($smcFunc['db_num_rows']($checkResult) > 0) {
            $tableRow = $smcFunc['db_fetch_assoc']($checkResult);
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
                    'correct'              => (int)$resultRow['correct'],
                    'incorrect'            => (int)$resultRow['incorrect'],
                    'timeouts'             => (int)$resultRow['timeouts'],
                    'points'               => (int)$resultRow['points'],
                    'seconds'              => (int)$resultRow['seconds'],
                    'round'                => $currentRound,
                    'id_quiz_league_table' => (int)$tableRow['id_quiz_league_table'],
                    'id_user'              => (int)$resultRow['id_user'],
                ]
            );
        } else {
            $smcFunc['db_insert']('insert',
                '{db_prefix}quiz_league_table',
                [
                    'current_position' => 'int',
                    'id_user'          => 'int',
                    'last_position'    => 'int',
                    'plays'            => 'int',
                    'correct'          => 'int',
                    'incorrect'        => 'int',
                    'timeouts'         => 'int',
                    'points'           => 'int',
                    'id_quiz_league'   => 'int',
                    'round'            => 'int',
                    'seconds'          => 'int',
                ],
                [
                    0,
                    (int)$resultRow['id_user'],
                    0,
                    1,
                    (int)$resultRow['correct'],
                    (int)$resultRow['incorrect'],
                    (int)$resultRow['timeouts'],
                    (int)$resultRow['points'],
                    $leagueId,
                    $currentRound,
                    (int)$resultRow['seconds'],
                ],
                ['id_quiz_league_table']
            );
        }
        $smcFunc['db_free_result']($checkResult);
    }

    /**
     * Fetch the ordered position results for a league round.
     *
     * For the first round (or first play) uses a simple ordered query;
     * subsequent rounds join on the previous round to get last_position.
     *
     * @param int $leagueId League ID
     * @param int $currentRound Current round number
     * @param int $plays Total plays in this round
     * @param array<string, callable> $smcFunc SMF database functions
     * @return mixed Query result resource
     */
    private function fetchPositionResults(int $leagueId, int $currentRound, int $plays, array &$smcFunc): mixed
    {
        if ($currentRound < 2 || $plays < 2) {
            return $smcFunc['db_query']('', '
                SELECT QLT.id_quiz_league_table, QLT.current_position AS last_position, QLT.id_user
                FROM {db_prefix}quiz_league_table QLT
                WHERE QLT.round = {int:current_round} AND QLT.id_quiz_league = {int:id_quiz_league}
                ORDER BY QLT.points DESC, QLT.seconds ASC, QLT.plays ASC',
                ['current_round' => $currentRound, 'id_quiz_league' => $leagueId]
            );
        }

        return $smcFunc['db_query']('', '
            SELECT QLT1.id_quiz_league_table, QLT1.id_user,
                IFNULL(QLT2.current_position,0) AS last_position
            FROM {db_prefix}quiz_league_table QLT1
            LEFT JOIN {db_prefix}quiz_league_table QLT2
                ON QLT1.round = QLT2.round+1 AND QLT1.id_user = QLT2.id_user
            WHERE QLT1.id_quiz_league = {int:id_quiz_league}
                AND QLT1.round = {int:current_round}
            ORDER BY QLT1.points DESC, QLT1.seconds ASC, QLT1.plays ASC',
            ['current_round' => $currentRound, 'id_quiz_league' => $leagueId]
        );
    }

    /**
     * Send a PM to a league participant about their new round position.
     *
     * @param array<string, mixed> $posRow Position row for this user
     * @param array<string, mixed> $leagueRow League row
     * @param int $position New position
     * @param array<string, mixed> $modSettings SMF mod settings
     * @param string $sourcedir SMF source directory
     * @param array<string, callable> $smcFunc SMF database functions
     * @return void
     */
    private function sendLeagueRoundUpdatePm(array $posRow, array $leagueRow, int $position, array $modSettings, string $sourcedir, array &$smcFunc): void
    {
        require_once($sourcedir . '/Subs-Post.php');

        $pmto = ['to' => [], 'bcc' => [(int)$posRow['id_user']]];
        $movement = $position - (int)$posRow['last_position'];

        $subject = ParseLeagueMessage(
            (string)$modSettings['SMFQuiz_PMLeagueRoundUpdateSubject'],
            (string)$leagueRow['title'],
            (int)$posRow['last_position'],
            $position,
            $movement,
            (int)$leagueRow['id_quiz_league']
        );
        $message = ParseLeagueMessage(
            (string)$modSettings['SMFQuiz_PMLeagueRoundUpdateMsg'],
            (string)$leagueRow['title'],
            (int)$posRow['last_position'],
            $position,
            $movement,
            (int)$leagueRow['id_quiz_league']
        );

        $pmfrom = [
            'id'       => (int)($modSettings['SMFQuiz_ImportQuizesAsUserId'] ?? 0),
            'name'     => 'Quiz Notifier',
            'username' => 'Quiz Notifier',
        ];

        sendpm($pmto, $subject, $message, 0, $pmfrom);
    }

    /**
     * Clean up stale infoboard entries, orphaned records, and expired sessions.
     *
     * @return void
     */
    public function quiz_clean(): void
    {
        global $modSettings, $sourcedir;

        if (($modSettings['SMFQuiz_AutoClean'] ?? '') !== 'on') {
            return;
        }

        require_once($sourcedir . '/Quiz/Db.php');

        $date = mktime(0, 0, 0, (int)date('m'), (int)date('d') - 7, (int)date('Y'));

        DeleteInfoBoardEntries($date);
        CleanDisputes();
        CleanAnswers();
        CleanResults();
        CleanQuestions();
        CompleteQuizSessions($date);
    }
}