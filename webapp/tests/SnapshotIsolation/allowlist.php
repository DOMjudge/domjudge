<?php declare(strict_types=1);

/**
 * Methods exempt from the snapshot-isolation detector, see
 * App\Tests\SnapshotIsolation\TransactionTracker. Each entry exempts everything below it on
 * the stack, so keep this short. A key ending in a backslash exempts a namespace.
 *
 * TODO entries are known-unsafe sites for #2848; remove each with its fix. The comment names
 * the test that fails once the entry is gone.
 */

return [
    'App\DataFixtures\Test\\'
        => 'Fixtures load single-threaded during test setup.',
    'App\Doctrine\ExternalIdAssigner::__invoke'
        => 'postPersist: only updates the row this transaction just inserted.',

    // JudgehostWorkflowTest::testJudgingIsCompletedOnceEveryRunIsReported
    'App\Controller\API\JudgehostController::addSingleJudgingRun'
        => 'TODO: drop the outer transaction, lock inside maybeUpdateActiveJudging.',
    // JudgehostWorkflowTest::testCheckVersionsRecordsTheReportedVersion
    'App\Controller\API\JudgehostController::checkVersions'
        => 'TODO: guard the language auto-promote write, drop the transaction.',
    // JudgehostWorkflowTest::testInternalErrorForACompileScriptInvalidatesQueuedJudgeTasks
    'App\Controller\API\JudgehostController::internalErrorAction'
        => 'TODO: drop the transaction, use guarded bulk UPDATEs. The 1020 reported in #2848.',
    // Jury\SubmissionControllerTest::testVerifyAndUnverifyJudging
    'App\Controller\Jury\SubmissionController::verifyAction'
        => 'TODO: verify with one bulk UPDATE, no transaction.',
    // Jury\ProblemControllerTest::testMultiDeleteProblems
    'App\Controller\BaseController::commitDeleteEntity'
        => 'TODO: do the reads of cascaded deletes outside the transaction.',
    // RejudgingServiceTest::testUpdateFirstToSolve
    'App\Service\RejudgingService::finishRejudging'
        => 'TODO: INSERT IGNORE of balloons after plain reads in the transaction.',
    // No test: needs a judging in a rejudging with auto-apply.
    'App\Controller\API\JudgehostController::updateJudgingAction'
        => 'TODO: guard the compile-error claim, drop the transaction.',
    // No test: JudgehostWorkflowTest reaches it with the entities already loaded.
    'App\Controller\API\JudgehostController::giveBackJudging'
        => 'TODO: replace the ORM loop with guarded bulk UPDATEs.',
];
