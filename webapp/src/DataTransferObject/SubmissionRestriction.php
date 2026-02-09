<?php declare(strict_types=1);

namespace App\DataTransferObject;

use App\DataTransferObject\Scoreboard\Problem;
use App\Entity\Language;
use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;

class SubmissionRestriction
{
    /**
     * @param int|null                                $rejudgingId         ID of a rejudging to filter on
     * @param bool|null                               $verified            If true, only return verified submissions
     *                                                                     If false, only return unverified or unjudged submissions
     * @param bool|null                               $judged              If true, only return judged submissions
     *                                                                     If false, only return unjudged submissions
     * @param bool|null                               $judging             If true, only return submissions currently being judged
     *                                                                     If false, only return submssions which are already judged or still
     *                                                                     need to be judged
     * @param bool|null                               $rejudgingDifference If true, only return judgings that differ from their
     *                                                                     original result in final verdict. Vice versa if false
     * @param int|null                                $teamId              ID of a team to filter on
     * @param list<int>|list<Team>|null               $teamIds             ID's of teams to filter on
     * @param int|null                                $categoryId          ID of a category to filter on
     * @param list<int>|list<TeamCategory>|null       $categoryIds         ID's of categories to filter on
     * @param int|null                                $affiliationId       ID of an affiliation to filter on
     * @param list<int>|list<TeamAffiliation>|null    $affiliationIds      ID's of affiliations to filter on
     * @param int|null                                $problemId           ID of a problem to filter on
     * @param list<int>|list<Problem>|null            $problemIds          ID's of problems to filter on
     * @param int|null                                $languageId          ID of a language to filter on
     * @param list<int>|list<Language>|null           $languageIds         ID's of languages to filter on
     * @param string|null                             $judgehost           Hostname of a judgehost to filter on
     * @param string|null                             $oldResult           Result of old judging to filter on
     * @param string|null                             $result              Result of current judging to filter on
     * @param list<string>|null                       $results             Results of current judging to filter on
     * @param int|null                                $userId              Filter on specific user
     * @param bool|null                               $visible             If true, only return submissions from visible teams
     * @param bool|null                               $externalDifference  If true, only return results with a difference with an
     *                                                                     external system
     *                                                                     If false, only return results without a difference with an
     *                                                                     external system
     * @param string|null                             $externalResult      Result in the external system
     * @param bool|null                               $externallyJudged    If true, only return externally judged submissions
     *                                                                     If false, only return externally unjudged submissions
     * @param bool|null                               $externallyVerified  If true, only return verified submissions
     *                                                                     If false, only return unverified or unjudged submissions
     * @param bool|null                               $withExternalId      If true, only return submissions with an external ID.
     * @param bool|null                               $shadowCompareByScore If true, use score-based comparison for scoring problems
     *                                                                      in external difference filtering
     * @param float|null                              $scoreDiffEpsilon    Score difference epsilon for score-based comparison
     */
    public function __construct(
        public ?int $rejudgingId = null,
        public ?bool $verified = null,
        public ?bool $judged = null,
        public ?bool $judging = null,
        public ?bool $rejudgingDifference = null,
        public ?int $teamId = null,
        public ?array $teamIds = [],
        public ?int $categoryId = null,
        public ?array $categoryIds = [],
        public ?int $affiliationId = null,
        public ?array $affiliationIds = [],
        public ?int $problemId = null,
        public ?array $problemIds = [],
        public ?int $languageId = null,
        public ?array $languageIds = [],
        public ?string $judgehost = null,
        public ?string $oldResult = null,
        public ?string $result = null,
        public ?array $results = [],
        public ?int $userId = null,
        public ?bool $visible = null,
        public ?bool $externalDifference = null,
        public ?string $externalResult = null,
        public ?bool $externallyJudged = null,
        public ?bool $externallyVerified = null,
        public ?bool $withExternalId = null,
        public ?bool $valid = null,
        public ?bool $shadowCompareByScore = null,
        public ?float $scoreDiffEpsilon = null,
    ) {}
}
