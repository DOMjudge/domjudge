<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\DataFixtures\Test\ClarificationFixture;
use App\DataFixtures\Test\RemoveTeamFromDemoUserFixture;
use App\Entity\Clarification;
use App\Entity\Problem;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class ClarificationControllerTest extends BaseTest
{
    protected ?string $apiEndpoint = 'clarifications';

    protected ?string $apiUser = 'admin';

    protected static array $fixtures = [ClarificationFixture::class];

    protected array $expectedObjects = [
        ClarificationFixture::class . ':0' => [
            "problem_id"   => "1",
            "from_team_id" => "2",
            "to_team_id"   => null,
            "reply_to_id"  => null,
            "time"         => "2018-02-11T21:48:58.901+00:00",
            "text"         => "Is it necessary to read the problem statement carefully?",
            "answered"     => false,
        ],
        ClarificationFixture::class . ':1' => [
            "problem_id"   => null,
            "from_team_id" => null,
            "to_team_id"   => null,
            "reply_to_id"  => null,
            "time"         => "2018-02-11T21:53:20.000+00:00",
            "text"         => "Lunch is served",
            "answered"     => true,
        ],
        ClarificationFixture::class . ':2' => [
            "problem_id"   => "1",
            "from_team_id" => null,
            "to_team_id"   => "2",
            "reply_to_id"  => null,
            "time"         => "2018-02-11T21:47:43.689+00:00",
            "text"         => "There was a mistake in judging this problem. Please try again",
            "answered"     => true,
        ],
    ];

    protected array $entityReferences = [
        'problem_id' => Problem::class,
    ];

    protected array $expectedAbsent = ['4242', 'nonexistent'];

    public function testAnonymousOnlyGeneral(): void
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $clarificationFromApi = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint", 200);

        $this->assertCount(1, $clarificationFromApi);
        $this->assertEquals("Lunch is served", $clarificationFromApi[0]['text']);
        $this->assertEquals("2018-02-11T21:53:20.000+00:00", $clarificationFromApi[0]['time']);
        $this->assertArrayNotHasKey('answered', $clarificationFromApi[0]);

        // Show that when filtering this does not show up as its not bound to a problem
        $clarificationFromApi = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint?problem=1", 200);
        $this->assertCount(0, $clarificationFromApi);
    }

    public function testTeamOnlyGeneralAndRelatedToTeam(): void
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $clarificationApi = "/contests/$contestId/$apiEndpoint";
        foreach (['1',null] as $problemId) {
            if ($problemId) {
                $postfix = "?problem=$problemId";
                $expectedNumber = 4;
                $mistakJudgingId = 1;
            } else {
                $postfix = '';
                $expectedNumber = 5;
                $mistakJudgingId = 2;
            }
            $clarificationFromApi = $this->verifyApiJsonResponse('GET', $clarificationApi.$postfix, 200, 'demo');
            $this->assertCount($expectedNumber, $clarificationFromApi);

            $this->assertEquals("2", $clarificationFromApi[0]['from_team_id']);
            $this->assertEquals("Is it necessary to read the problem statement carefully?", $clarificationFromApi[0]['text']);
            $this->assertArrayNotHasKey('answered', $clarificationFromApi[0]);

            if (!$problemId) {
                $this->assertNull($clarificationFromApi[1]['to_team_id']);
                $this->assertEquals("Lunch is served", $clarificationFromApi[1]['text']);
                $this->assertArrayNotHasKey('answered', $clarificationFromApi[1]);
            }

            $this->assertEquals("2", $clarificationFromApi[$mistakJudgingId]['to_team_id']);
            $this->assertEquals("There was a mistake in judging this problem. Please try again", $clarificationFromApi[$mistakJudgingId]['text']);
            $this->assertArrayNotHasKey('answered', $clarificationFromApi[$mistakJudgingId]);
        }
        $clarificationFromApi = $this->verifyApiJsonResponse('GET', $clarificationApi."?problem=9999", 200, 'demo');
        $this->assertCount(0, $clarificationFromApi);
    }

    /**
     * Test that a non-logged-in user can not add a clarification.
     */
    public function testAddNoAccess(): void
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $this->verifyApiJsonResponse('POST', "/contests/$contestId/$apiEndpoint", 401);
    }

    /**
     * Test that if invalid data is supplied, the correct message is returned.
     *
     * @dataProvider provideAddInvalidData
     */
    public function testAddInvalidData(string $user, array $dataToSend, string $expectedMessage): void
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $method = isset($dataToSend['id']) ? 'PUT' : 'POST';
        $url = "/contests/$contestId/$apiEndpoint";
        if ($method === 'PUT') {
            $url .= '/' . $dataToSend['id'];
        }
        $data = $this->verifyApiJsonResponse($method, $url, 400, $user, $dataToSend);
        static::assertEquals($expectedMessage, $data['message']);
    }

    public function provideAddInvalidData(): Generator
    {
        yield ['demo', [], "Argument 'text' is mandatory."];
        yield ['demo', ['text' => 'This is a clarification', 'from_team_id' => '1'], "Can not create a clarification from a different team."];
        yield ['demo', ['text' => 'This is a clarification', 'to_team_id' => '2'], "Can not create a clarification that is sent to a team."];
        yield ['demo', ['text' => 'This is a clarification', 'problem_id' => '4'], "Problem '4' not found."];
        yield ['demo', ['text' => 'This is a clarification', 'time' => '1234'], "A team can not assign time."];
        yield ['demo', ['text' => 'This is a clarification', 'id' => '1234'], "A team can not assign id."];
        yield ['demo', ['text' => 'This is a clarification', 'reply_to_id' => 'nonexistent'], "Clarification 'nonexistent' not found."];
        yield ['admin', ['text' => 'This is a clarification', 'from_team_id' => '2', 'to_team_id' => '2'], "Can not send a clarification from and to a team."];
        yield ['admin', ['text' => 'This is a clarification', 'from_team_id' => '3'], "Team with ID '3' not found in contest or not enabled."];
        yield ['admin', ['text' => 'This is a clarification', 'to_team_id' => '3'], "Team with ID '3' not found in contest or not enabled."];
        yield ['admin', ['text' => 'This is a clarification', 'time' => 'this is not a time'], "Can not parse time 'this is not a time'."];
    }

    /**
     * Test that passing an ID is not allowed when performing a POST.
     */
    public function testSupplyIdInPost(): void
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $data = $this->verifyApiJsonResponse('POST', "/contests/$contestId/$apiEndpoint", 400, 'admin', ['text' => 'This is a clarification', 'id' => '1234']);
        static::assertEquals('Passing an ID is not supported for POST.', $data['message']);
    }

    /**
     * Test that passing a wrong ID is not allowed when performing a PUT.
     */
    public function testSupplyWrongIdInPut(): void
    {
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $data = $this->verifyApiJsonResponse('PUT', "/contests/$contestId/$apiEndpoint/id1", 400, 'admin', ['text' => 'This is a clarification', 'id' => 'id2']);
        static::assertEquals('ID does not match URI.', $data['message']);
    }

    /**
     * Test that when creating a clarification as a user without an association team an error is returned.
     */
    public function testMissingTeam(): void
    {
        $this->loadFixture(RemoveTeamFromDemoUserFixture::class);

        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $data = $this->verifyApiJsonResponse('POST', "/contests/$contestId/$apiEndpoint", 400, 'demo', ['text' => 'This is some text']);

        static::assertEquals('User does not belong to a team.', $data['message']);
    }

    /**
     * Test that creating a clarification works as expected.
     *
     * @dataProvider provideAddSuccess
     */
    public function testAddSuccess(
        string $user,
        array $dataToSend,
        bool $idIsExternal,
        string $expectedBody,
        ?int $expectedProblemId,
        ?int $expectedInReplyToId,
        ?int $expectedSenderId,
        ?int $expectedRecipientId,
        ?string $expectedClarificationExternalId, // If known
        ?string $expectedTime // If known
    ): void {
        if (isset($dataToSend['problem_id'])) {
            $dataToSend['problem_id'] = $this->resolveEntityId(Problem::class, $dataToSend['problem_id']);
        }
        $contestId = $this->getDemoContestId();
        $apiEndpoint = $this->apiEndpoint;
        $method = isset($dataToSend['id']) ? 'PUT' : 'POST';
        $url = "/contests/$contestId/$apiEndpoint";
        if ($method === 'PUT') {
            $url .= '/' . $dataToSend['id'];
        }

        $submittedClarification = $this->verifyApiJsonResponse($method, $url, 200, $user, $dataToSend);
        static::assertIsArray($submittedClarification);
        static::assertArrayHasKey('id', $submittedClarification);

        $clarificationId = $submittedClarification['id'];

        // Now load the clarification.
        $clarificationRepository = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Clarification::class);
        if ($idIsExternal) {
            /** @var Clarification $clarification */
            $clarification = $clarificationRepository->findOneBy(['externalid' => $clarificationId]);
        } else {
            $clarification = $clarificationRepository->find($clarificationId);
        }

        static::assertInstanceOf(Clarification::class, $clarification);
        static::assertEquals($expectedBody, $clarification->getBody(), 'Wrong body');
        static::assertEquals($expectedProblemId, $clarification->getProblemId(), 'Wrong problem ID');
        $expectedCategory = $expectedProblemId === null ? 'general' : null;
        static::assertEquals($expectedCategory, $clarification->getCategory());
        static::assertEquals('', $clarification->getQueue());
        static::assertEquals($expectedSenderId, $clarification->getSenderId(), 'Wrong sender ID');
        static::assertEquals($expectedRecipientId, $clarification->getRecipientId(), 'Wrong recipient ID');
        static::assertEquals($expectedInReplyToId, $clarification->getInReplyToId(), 'Wrong in reply to ID');
        if ($expectedClarificationExternalId) {
            static::assertEquals($expectedClarificationExternalId, $clarification->getExternalid(), 'Wrong external clarification ID');
        }
        if ($expectedTime) {
            static::assertEquals($expectedTime, $clarification->getAbsoluteSubmitTime());
        }

        // Also load the clarification from the API, to see it now gets returned.
        $clarificationFromApi = $this->verifyApiJsonResponse('GET', "/contests/$contestId/$apiEndpoint/$clarificationId", 200, 'admin');
        static::assertEquals($expectedBody, $clarificationFromApi['text'], 'Wrong body');
        if ($expectedProblemId !== null) {
            $expectedProblemId = $this->resolveEntityId(Problem::class, (string)$expectedProblemId);
        }
        static::assertEquals($expectedProblemId, $clarificationFromApi['problem_id'], 'Wrong problem ID');
        static::assertEquals($expectedSenderId, $clarificationFromApi['from_team_id'], 'Wrong sender ID');
        static::assertEquals($expectedRecipientId, $clarificationFromApi['to_team_id'], 'Wrong recipient ID');
        static::assertEquals($expectedInReplyToId, $clarificationFromApi['reply_to_id'], 'Wrong in reply to ID');
    }

    public function provideAddSuccess(): Generator
    {
        yield [
            'demo',
            ['text' => 'This is some text'],
            false,
            'This is some text',
            null,
            null,
            2,
            null,
            null,
            null,
        ];
        // yield [
        //     'demo',
        //     ['text' => 'This is a question about problem 1 replying to clarification 2', 'problem_id' => '1', 'reply_to_id' => '2'],
        //     false,
        //     'This is a question about problem 1 replying to clarification 2',
        //     1,
        //     2,
        //     2,
        //     null,
        //     null,
        //     null,
        // ];
        yield [
            'admin',
            ['text' => 'This is a global clarification'],
            false,
            'This is a global clarification',
            null,
            null,
            null,
            null,
            null,
            null,
        ];
        yield [
            'admin',
            ['text' => 'This is a global clarification with a provided ID and time', 'id' => 'someextid', 'time' => '2020-01-01T12:34:56'],
            true,
            'This is a global clarification with a provided ID and time',
            null,
            null,
            null,
            null,
            'someextid',
            '2020-01-01T12:34:56.000+00:00',
        ];
        yield [
            'admin',
            ['text' => 'This is a clarification to a specific team', 'to_team_id' => '2'],
            false,
            'This is a clarification to a specific team',
            null,
            null,
            null,
            2,
            null,
            null,
        ];
        yield [
            'admin',
            ['text' => 'This is a clarification from a specific team', 'from_team_id' => '1'],
            false,
            'This is a clarification from a specific team',
            null,
            null,
            1,
            null,
            null,
            null,
        ];
        // yield [
        //     'admin',
        //     ['text' => 'This is a global clarification on problem 2 replying to clarification 1', 'problem_id' => '2', 'reply_to_id' => '1'],
        //     false,
        //     'This is a global clarification on problem 2 replying to clarification 1',
        //     2,
        //     1,
        //     null,
        //     null,
        //     null,
        //     null,
        // ];
    }
}
