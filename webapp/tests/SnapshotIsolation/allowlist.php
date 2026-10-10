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

    // RejudgingServiceTest::testUpdateFirstToSolve
    'App\Service\RejudgingService::finishRejudging'
        => 'TODO: INSERT IGNORE of balloons after plain reads in the transaction.',

    // JudgehostWorkflowTest::testCompletingAnAutoApplyRejudgingAppliesIt
    'App\Service\RejudgingService::createRejudging'
        => 'locks the judging this transaction inserted a few statements earlier, which no other transaction can change.',
];
