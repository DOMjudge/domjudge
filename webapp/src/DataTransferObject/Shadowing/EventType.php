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
        switch ($this) {
            case self::CLARIFICATIONS:
                return ClarificationEvent::class;
            case self::CONTESTS:
                return ContestEvent::class;
            case self::GROUPS:
                return GroupEvent::class;
            case self::JUDGEMENTS:
                return JudgementEvent::class;
            case self::JUDGEMENT_TYPES:
                return JudgementTypeEvent::class;
            case self::LANGUAGES:
                return LanguageEvent::class;
            case self::ORGANIZATIONS:
                return OrganizationEvent::class;
            case self::PROBLEMS:
                return ProblemEvent::class;
            case self::RUNS:
                return RunEvent::class;
            case self::STATE:
                return StateEvent::class;
            case self::SUBMISSIONS:
                return SubmissionEvent::class;
            case self::TEAMS:
                return TeamEvent::class;
        }
        return null;
    }
}
