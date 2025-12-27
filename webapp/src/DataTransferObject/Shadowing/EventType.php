<?php declare(strict_types=1);

namespace App\DataTransferObject\Shadowing;

enum EventType: string
{
    case ACCOUNTS = 'accounts';
    case AWARDS = 'awards';
    case CLARIFICATIONS = 'clarifications';
    case CONTESTS = 'contests';
    case GROUPS = 'groups';
    case JUDGEMENTS = 'judgements';
    case JUDGEMENT_TYPES = 'judgement-types';
    case LANGUAGES = 'languages';
    case MAP_INFO = 'map-info';
    case ORGANIZATIONS = 'organizations';
    case PERSONS = 'persons';
    case PROBLEMS = 'problems';
    case RUNS = 'runs';
    case STATE = 'state';
    case START_STATUS = 'start-status';
    case SUBMISSIONS = 'submissions';
    case TEAMS = 'teams';
    case TEAM_MEMBERS = 'team-members';

    public static function fromString(string $value): EventType
    {
        if ($value === 'contest') {
            return EventType::CONTESTS;
        }

        // When encountering an error for an unknown event
        // consider if we need to verify this as shadow or
        // ignore it by adding the case in:
        // webapp/src/DataTransferObject/Shadowing/EventType.php
        // webapp/src/Service/ExternalContestSourceService.php
        return EventType::from($value);
    }

    /**
     * @return class-string<EventData>|null
     */
    public function getEventClass(): ?string
    {
        return match ($this) {
            self::CLARIFICATIONS => ClarificationEvent::class,
            self::CONTESTS => ContestEvent::class,
            self::GROUPS => GroupEvent::class,
            self::JUDGEMENTS => JudgementEvent::class,
            self::JUDGEMENT_TYPES => JudgementTypeEvent::class,
            self::LANGUAGES => LanguageEvent::class,
            self::ORGANIZATIONS => OrganizationEvent::class,
            self::PROBLEMS => ProblemEvent::class,
            self::RUNS => RunEvent::class,
            self::STATE => StateEvent::class,
            self::SUBMISSIONS => SubmissionEvent::class,
            self::TEAMS => TeamEvent::class,
            default => null,
        };
    }
}
