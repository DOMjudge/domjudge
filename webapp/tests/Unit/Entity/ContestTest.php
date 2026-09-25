<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataTransferObject\ContestState;
use App\Entity\Contest;
use App\Entity\RemovedInterval;
use App\Utils\Utils;
use Exception;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

class ContestTest extends TestCase
{
    /**
     * Build a contest starting at $starttime with the given removed intervals.
     *
     * @param array<array{float|int, float|int}> $intervals List of [starttime, endtime] pairs.
     */
    private static function contestWithIntervals(float $starttime, array $intervals = []): Contest
    {
        $contest = new Contest();
        $contest->setStarttime($starttime);
        foreach ($intervals as [$intervalStart, $intervalEnd]) {
            $contest->addRemovedInterval(
                (new RemovedInterval())
                    ->setStarttime($intervalStart)
                    ->setEndtime($intervalEnd)
            );
        }

        return $contest;
    }

    public function testGetAbsoluteTime(): void
    {
        $contest = new Contest();
        $contest->setStarttime(42);

        self::assertEquals(null, $contest->getAbsoluteTime(null));

        self::assertEquals(1672585200, $contest->getAbsoluteTime('2023-01-01 16:00:00 Europe/Amsterdam'));
        self::assertEquals(1672624800, $contest->getAbsoluteTime('2023-01-01 16:00:00 Pacific/Honolulu'));

        self::assertEquals(42, $contest->getAbsoluteTime("-00:00"));
        self::assertEquals(42, $contest->getAbsoluteTime("+00:00"));
        self::assertEquals(42+4*3600, $contest->getAbsoluteTime("+4:00"));
        self::assertEquals(42-23*60, $contest->getAbsoluteTime("-0:23"));

        self::assertEquals(42-((34*60) + 56.789), $contest->getAbsoluteTime("-0:34:56.789"));
        self::assertEquals(42+(1*3600)+(47*60)+11, $contest->getAbsoluteTime("+1:47:11"));
        $removedInterval = new RemovedInterval();
        $removedInterval
            ->setStarttime(111)
            ->setEndtime(555);
        $contest->addRemovedInterval($removedInterval);
        self::assertEquals(42+(1*3600)+(47*60)+11 + 444, $contest->getAbsoluteTime("+1:47:11"));
    }


    /**
     * @param array<array{float|int, float|int}> $intervals
     */
    #[DataProvider('provideAbsoluteTimeWithRemovedIntervals')]
    public function testGetAbsoluteTimeWithRemovedIntervals(
        array $intervals,
        string $timeString,
        float $expected
    ): void {
        $contest = self::contestWithIntervals(1000, $intervals);
        self::assertEqualsWithDelta($expected, (float)$contest->getAbsoluteTime($timeString), 0.0001);
    }

    /**
     * The contest starts at t=1000 in all of these cases.
     */
    public static function provideAbsoluteTimeWithRemovedIntervals(): Generator
    {
        // Without any intervals the relative time is simply added to the start time.
        yield 'no intervals' => [[], '+0:10:00', 1600.0];

        // An interval that lies entirely before the target time shifts it by its full duration.
        yield 'single interval before target' => [[[1100, 1200]], '+0:10:00', 1700.0];

        // Two intervals before the target time both shift it.
        yield 'two intervals before target' => [[[1100, 1200], [1300, 1400]], '+0:10:00', 1800.0];

        // The order in which the intervals were added must not matter: they get sorted first.
        yield 'intervals added out of order' => [[[1300, 1400], [1100, 1200]], '+0:10:00', 1800.0];

        // An interval starting after the target time does not apply.
        yield 'interval after target' => [[[2000, 2100]], '+0:10:00', 1600.0];

        // An interval starting exactly at the target time does apply (the comparison is <= 0).
        yield 'interval starting exactly at target' => [[[1600, 1650]], '+0:10:00', 1650.0];

        // Intervals cascade: the second interval starts after the *original* target (1600), but
        // the first interval shifts the target to 1700, which moves it past the second interval.
        yield 'cascading intervals' => [[[1100, 1200], [1650, 1700]], '+0:10:00', 1750.0];

        // Negative relative times are shifted by intervals before them just the same.
        yield 'negative relative time' => [[[500, 600]], '-0:05:00', 800.0];

        // Absolute times are not affected by removed intervals at all.
        yield 'absolute time ignores intervals' => [
            [[1100, 1200]],
            '2023-01-01 16:00:00 Europe/Amsterdam',
            1672585200.0,
        ];
    }

    public function testGetAbsoluteTimeReturnsNullForUnparseableString(): void
    {
        $contest = self::contestWithIntervals(1000);
        self::assertNull($contest->getAbsoluteTime('this is not a time at all'));
    }

    /**
     * @param array<array{float|int, float|int}> $intervals
     */
    #[DataProvider('provideContestTime')]
    public function testGetContestTime(array $intervals, float $wallTime, float $expected): void
    {
        $contest = self::contestWithIntervals(1000, $intervals);
        self::assertEqualsWithDelta($expected, $contest->getContestTime($wallTime), 0.0001);
    }

    /**
     * The contest starts at t=1000 in all of these cases.
     */
    public static function provideContestTime(): Generator
    {
        yield 'no intervals' => [[], 1600.0, 600.0];

        // Before the interval starts nothing is subtracted.
        yield 'wall time before interval' => [[[1100, 1200]], 1050.0, 50.0];

        // Exactly at the interval start nothing is subtracted either (the comparison is < 0).
        yield 'wall time at interval start' => [[[1100, 1200]], 1100.0, 100.0];

        // Inside the interval the contest clock stands still.
        yield 'wall time inside interval' => [[[1100, 1200]], 1150.0, 100.0];
        yield 'wall time at interval end' => [[[1100, 1200]], 1200.0, 100.0];

        // After the interval the full interval duration is subtracted.
        yield 'wall time after interval' => [[[1100, 1200]], 1600.0, 500.0];

        // Multiple intervals each subtract their own duration.
        yield 'two intervals passed' => [[[1100, 1200], [1300, 1350]], 1600.0, 450.0];

        // Only the elapsed part of an interval we are currently inside is subtracted.
        yield 'inside the second interval' => [[[1100, 1200], [1300, 1400]], 1350.0, 200.0];

        // Order of insertion does not matter here either.
        yield 'intervals added out of order' => [[[1300, 1350], [1100, 1200]], 1600.0, 450.0];
    }

    /**
     * getContestTime() deliberately asks for the start time even when it is disabled,
     * so a contest whose start time is not enabled still has a well-defined contest clock.
     */
    public function testGetContestTimeUsesStarttimeEvenWhenDisabled(): void
    {
        $contest = self::contestWithIntervals(1000);
        $contest->setStarttimeEnabled(false);

        self::assertNull($contest->getStarttime());
        self::assertEqualsWithDelta(600.0, $contest->getContestTime(1600), 0.0001);
    }

    #[DataProvider('provideIsTimeInContest')]
    public function testIsTimeInContest(float $time, bool $expected): void
    {
        $contest = new Contest();
        $contest
            ->setStarttime(1000)
            ->setEndtime(2000);

        self::assertSame($expected, $contest->isTimeInContest($time));
    }

    /**
     * The contest runs from t=1000 (inclusive) to t=2000 (exclusive).
     */
    public static function provideIsTimeInContest(): Generator
    {
        yield 'before start' => [999.0, false];
        yield 'exactly at start' => [1000.0, true];
        yield 'during' => [1500.0, true];
        yield 'just before end' => [1999.999, true];
        yield 'exactly at end' => [2000.0, false];
        yield 'after end' => [2001.0, false];
    }

    /**
     * getAbsoluteTime() and getContestTime() implement the removed-interval correction
     * in two different ways. They must remain each other's inverse, otherwise contest
     * times shown on the scoreboard drift away from the times events are stamped with.
     *
     * @param array<array{float|int, float|int}> $intervals
     */
    #[DataProvider('provideRoundTripIntervals')]
    public function testAbsoluteTimeAndContestTimeAreInverses(
        array $intervals,
        string $timeString,
        float $expectedContestTime
    ): void {
        $contest = self::contestWithIntervals(1000, $intervals);

        $absolute = (float)$contest->getAbsoluteTime($timeString);
        self::assertEqualsWithDelta($expectedContestTime, $contest->getContestTime($absolute), 0.0001);
    }

    public static function provideRoundTripIntervals(): Generator
    {
        yield 'no intervals' => [[], '+0:10:00', 600.0];
        yield 'single interval' => [[[1100, 1200]], '+0:10:00', 600.0];
        yield 'two intervals' => [[[1100, 1200], [1300, 1400]], '+0:10:00', 600.0];
        yield 'cascading intervals' => [[[1100, 1200], [1650, 1700]], '+0:10:00', 600.0];
        yield 'interval starting exactly at target' => [[[1600, 1650]], '+0:10:00', 600.0];
        yield 'interval after target' => [[[2000, 2100]], '+0:10:00', 600.0];
    }

    /**
     * setStarttimeString() recomputes every other time from the (possibly relative) strings.
     */
    public function testUpdateTimesCascadesToAllOtherTimes(): void
    {
        $starttime = (float)strtotime('2024-01-01 10:00:00 Europe/Amsterdam');

        $contest = new Contest();
        $contest
            ->setStarttimeString('2024-01-01 10:00:00 Europe/Amsterdam')
            ->setActivatetimeString('-1:00')
            ->setFreezetimeString('+4:00')
            ->setEndtimeString('+5:00')
            ->setUnfreezetimeString('+6:00')
            ->setDeactivatetimeString('+7:00');

        $contest->updateTimes();

        self::assertEqualsWithDelta($starttime, $contest->getStarttime(), 0.0001);
        self::assertEqualsWithDelta($starttime - 3600, $contest->getActivatetime(), 0.0001);
        self::assertEqualsWithDelta($starttime + 4 * 3600, $contest->getFreezetime(), 0.0001);
        self::assertEqualsWithDelta($starttime + 5 * 3600, $contest->getEndtime(), 0.0001);
        self::assertEqualsWithDelta($starttime + 6 * 3600, $contest->getUnfreezetime(), 0.0001);
        self::assertEqualsWithDelta($starttime + 7 * 3600, $contest->getDeactivatetime(), 0.0001);
    }

    /**
     * Moving the start time must move all relative times along with it.
     */
    public function testMovingStarttimeMovesRelativeTimes(): void
    {
        $contest = new Contest();
        $contest
            ->setStarttimeString('2024-01-01 10:00:00 Europe/Amsterdam')
            ->setFreezetimeString('+4:00')
            ->setEndtimeString('+5:00');
        $contest->updateTimes();

        $originalFreeze = $contest->getFreezetime();

        $contest->setStarttimeString('2024-01-01 12:00:00 Europe/Amsterdam');
        $contest->updateTimes();

        self::assertEqualsWithDelta($originalFreeze + 2 * 3600, $contest->getFreezetime(), 0.0001);
        self::assertEqualsWithDelta($contest->getStarttime() + 5 * 3600, $contest->getEndtime(), 0.0001);
    }

    /**
     * An absolute time does not move when the start time changes.
     */
    public function testMovingStarttimeKeepsAbsoluteTimes(): void
    {
        $contest = new Contest();
        $contest
            ->setStarttimeString('2024-01-01 10:00:00 Europe/Amsterdam')
            ->setEndtimeString('2024-01-01 18:00:00 Europe/Amsterdam');
        $contest->updateTimes();

        $originalEnd = $contest->getEndtime();

        $contest->setStarttimeString('2024-01-01 12:00:00 Europe/Amsterdam');
        $contest->updateTimes();

        self::assertEqualsWithDelta($originalEnd, $contest->getEndtime(), 0.0001);
    }

    #[DataProvider('provideValidTimeStrings')]
    public function testCheckValidTimeStringAccepts(string $timeString): void
    {
        // checkValidTimeString() throws on invalid input; returning at all is the assertion.
        $this->expectNotToPerformAssertions();

        (new Contest())->checkValidTimeString($timeString);
    }

    public static function provideValidTimeStrings(): Generator
    {
        yield ['+1:00'];
        yield ['-1:00'];
        yield ['0:00'];
        yield ['+1:47:11'];
        yield ['-0:34:56.789'];
        yield ['2024-01-01 10:00:00 Europe/Amsterdam'];
        yield ['2024-01-01T10:00:00+01:00'];
    }

    #[DataProvider('provideInvalidTimeStrings')]
    public function testCheckValidTimeStringRejects(string $timeString): void
    {
        $contest = new Contest();

        $this->expectException(Exception::class);
        $contest->checkValidTimeString($timeString);
    }

    public static function provideInvalidTimeStrings(): Generator
    {
        yield ['this is not a time at all'];
        yield ['nonsense'];
        yield ['25:61:61 not a time'];
    }

    /**
     * @param array<string, float> $times    Offsets in seconds from now, so negative is past.
     * @param list<string>         $expected Fields that should carry a time.
     * @param string|null          $endOfUpdates Field end_of_updates should equal, if any.
     */
    #[DataProvider('provideContestStates')]
    public function testGetState(array $times, array $expected, ?string $endOfUpdates): void
    {
        $state = $this->stateForOffsets(...$times);

        foreach (['started', 'ended', 'frozen', 'thawed', 'finalized'] as $field) {
            if (in_array($field, $expected, true)) {
                self::assertNotNull($state->$field, $field . ' should be set');
            } else {
                self::assertNull($state->$field, $field . ' should not be set');
            }
        }

        self::assertSame($endOfUpdates === null ? null : $state->$endOfUpdates, $state->endOfUpdates);
    }

    public static function provideContestStates(): Generator
    {
        // A field is only reported once the one it depends on is: ended needs started, thawed
        // needs frozen and finalized needs ended. Times that pass out of order are a
        // misconfiguration and must not report the later state.
        yield 'not started yet' => [
            ['start' => 100, 'end' => 200], [], null,
        ];
        yield 'running' => [
            ['start' => -100, 'end' => 200], ['started'], null,
        ];
        yield 'frozen but not ended' => [
            ['start' => -300, 'freeze' => -100, 'end' => 200], ['started', 'frozen'], null,
        ];
        yield 'end time passed before the start time' => [
            ['start' => 100, 'end' => -50], [], null,
        ];
        yield 'unfreeze time but no freeze time' => [
            ['start' => -300, 'end' => -200, 'unfreeze' => -100], ['started', 'ended'], null,
        ];
        yield 'finalize time passed before the end time' => [
            ['start' => -300, 'end' => 200, 'finalize' => -100], ['started'], null,
        ];

        // end_of_updates is the moment the scoreboard stopped changing, so it needs both the
        // finalization and, if there was a freeze, the thaw.
        yield 'finalized, never frozen' => [
            ['start' => -300, 'end' => -200, 'finalize' => -100],
            ['started', 'ended', 'finalized'],
            'finalized',
        ];
        yield 'finalized but still frozen' => [
            ['start' => -400, 'freeze' => -300, 'end' => -200, 'unfreeze' => 100, 'finalize' => -100],
            ['started', 'ended', 'frozen', 'finalized'],
            null,
        ];
        yield 'finalized after the freeze' => [
            ['start' => -500, 'freeze' => -400, 'end' => -300, 'unfreeze' => -200, 'finalize' => -100],
            ['started', 'ended', 'frozen', 'thawed', 'finalized'],
            'finalized',
        ];
        yield 'finalized before the freeze' => [
            ['start' => -600, 'finalize' => -500, 'freeze' => -400, 'end' => -300, 'unfreeze' => -200],
            ['started', 'ended', 'frozen', 'thawed', 'finalized'],
            'thawed',
        ];
    }

    /**
     * Build the contest state for times given as offsets in seconds relative to now.
     */
    private function stateForOffsets(
        float $start,
        ?float $end = null,
        ?float $freeze = null,
        ?float $unfreeze = null,
        ?float $finalize = null
    ): ContestState {
        $now     = Utils::now();
        $contest = new Contest();
        $contest->setStarttime($now + $start);
        if ($end !== null) {
            $contest->setEndtime($now + $end);
        }
        if ($freeze !== null) {
            $contest->setFreezetime($now + $freeze);
        }
        if ($unfreeze !== null) {
            $contest->setUnfreezetime($now + $unfreeze);
        }
        if ($finalize !== null) {
            $contest->setFinalizetime($now + $finalize);
        }

        return $contest->getState();
    }

    public function testValidateAcceptsAConsistentContest(): void
    {
        $violations = $this->collectViolations($this->validContest());

        self::assertSame([], $violations);
    }

    #[DataProvider('provideInvalidContestTimes')]
    public function testValidateRejectsInconsistentTimes(
        string $setter,
        ?string $value,
        string $expectedPath,
        string $expectedMessage
    ): void {
        $contest = $this->validContest();
        $contest->$setter($value);

        $violations = $this->collectViolations($contest);

        self::assertContains(
            [$expectedPath, $expectedMessage],
            $violations,
            sprintf('Expected a violation on %s, got: %s', $expectedPath, json_encode($violations))
        );
    }

    /**
     * The valid contest runs from +0:00 to +5:00, activates at -1:00, freezes at +4:00,
     * unfreezes at +6:00 and deactivates at +7:00.
     */
    public static function provideInvalidContestTimes(): Generator
    {
        yield 'end before start' => [
            'setEndtimeString', '-1:00',
            'endtimeString', 'Contest ends before it even starts.',
        ];
        yield 'end equal to start' => [
            'setEndtimeString', '+0:00',
            'endtimeString', 'Contest ends before it even starts.',
        ];
        yield 'freeze after end' => [
            'setFreezetimeString', '+6:00',
            'freezetimeString', 'Freezetime is out of start/endtime range',
        ];
        yield 'freeze before start' => [
            'setFreezetimeString', '-0:30',
            'freezetimeString', 'Freezetime is out of start/endtime range',
        ];
        yield 'activate after start' => [
            'setActivatetimeString', '+0:30',
            'activatetimeString', 'Activate time is later than starttime',
        ];
        yield 'unfreeze before end' => [
            'setUnfreezetimeString', '+4:30',
            'unfreezetimeString', 'Unfreezetime must be larger than endtime.',
        ];
        yield 'deactivate before unfreeze' => [
            'setDeactivatetimeString', '+5:30',
            'deactivatetimeString', 'Deactivatetime must be larger than unfreezetime.',
        ];
    }

    public function testValidateRejectsUnfreezeWithoutFreeze(): void
    {
        $contest = $this->validContest();
        $contest->setFreezetimeString(null);

        $violations = $this->collectViolations($contest);

        self::assertContains(
            ['unfreezetimeString', 'Unfreezetime set but no freeze time. That makes no sense.'],
            $violations
        );
    }

    /**
     * Without an unfreeze time the deactivate time is compared against the end time instead.
     */
    public function testValidateRejectsDeactivateBeforeEndWithoutUnfreeze(): void
    {
        $contest = $this->validContest();
        $contest
            ->setFreezetimeString(null)
            ->setUnfreezetimeString(null)
            ->setDeactivatetimeString('+4:00');

        $violations = $this->collectViolations($contest);

        self::assertContains(
            ['deactivatetimeString', 'Deactivatetime must be larger than endtime.'],
            $violations
        );
    }

    public function testValidateReportsUnparseableTimeStrings(): void
    {
        $contest = $this->validContest();
        $contest->setFreezetimeString('not a time');

        $violations = $this->collectViolations($contest);

        $paths = array_column($violations, 0);
        self::assertContains('freezetimeString', $paths);
    }

    /**
     * Contests without a freeze, unfreeze or deactivate time are perfectly valid.
     */
    public function testValidateAcceptsContestWithoutOptionalTimes(): void
    {
        $contest = new Contest();
        $contest
            ->setStarttimeString('2024-01-01 10:00:00 Europe/Amsterdam')
            ->setActivatetimeString('-1:00')
            ->setEndtimeString('+5:00');

        self::assertSame([], $this->collectViolations($contest));
    }

    /**
     * The activate time string defaults to the empty string rather than to null, and an
     * empty string is resolved by DateTime as "now". A contest that never had its activate
     * time set therefore activates at the current time, which for a contest starting in the
     * past means validation reports it as activating after the start.
     *
     * This pins down current behaviour; it is surprising enough to be worth noticing if it
     * ever changes.
     */
    public function testValidateTreatsEmptyActivateTimeAsNow(): void
    {
        $contest = new Contest();
        $contest
            ->setStarttimeString('2024-01-01 10:00:00 Europe/Amsterdam')
            ->setEndtimeString('+5:00');

        self::assertSame('', $contest->getActivatetimeString());
        self::assertContains(
            ['activatetimeString', 'Activate time is later than starttime'],
            $this->collectViolations($contest)
        );
    }

    private function validContest(): Contest
    {
        $contest = new Contest();

        return $contest
            ->setStarttimeString('2024-01-01 10:00:00 Europe/Amsterdam')
            ->setActivatetimeString('-1:00')
            ->setFreezetimeString('+4:00')
            ->setEndtimeString('+5:00')
            ->setUnfreezetimeString('+6:00')
            ->setDeactivatetimeString('+7:00');
    }

    /**
     * Run the entity's own validation callback and return what it reported as a list of
     * [path, message] pairs.
     *
     * @return list<array{string, string}>
     */
    private function collectViolations(Contest $contest): array
    {
        $violations = [];
        foreach (Validation::createValidator()->validate($contest, new Assert\Callback('validate')) as $violation) {
            $violations[] = [$violation->getPropertyPath(), (string)$violation->getMessage()];
        }

        return $violations;
    }
}
