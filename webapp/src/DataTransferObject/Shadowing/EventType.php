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
    case ORGANIZATIONS = 'organizations';
    case PROBLEMS = 'problems';
    case RUNS = 'runs';
    case STATE = 'state';
    case SUBMISSIONS = 'submissions';
    case TEAMS = 'teams';
    case TEAM_MEMBERS = 'team-members';

    public static function fromString(string $value): EventType
    {
        if ($value === 'contest') {
            return EventType::CONTESTS;
        }

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
