<?php declare(strict_types=1);

namespace App\Tests\Controller\API;

use App\Entity\Clarification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class ClarificationControllerTest extends BaseTest
{
    /**
     * Test that a non logged in user can not add a clarification
     */
    public function testAddSubmissionNoAccess()
    {
        $this->verifyApiJsonResponse('POST', '/contests/2/clarifications', 401);
    }

    /**
     * Test that if not all data is supplied, the correct message is returned
     *
     * @dataProvider provideAddMissingData
     */
    public function testAddMissingData(string $user, array $dataToSend, string $expectedMessage)
    {
        $data = $this->verifyApiJsonResponse('POST', '/contests/2/clarifications', 400, $user, $dataToSend);
        static::assertEquals($expectedMessage, $data['message']);
    }

    public function provideAddMissingData(): Generator
    {
        yield ['dummy', [], "Argument 'text' is mandatory"];
        yield ['dummy', ['text' => 'This is a clarification', 'from_team_id' => '1'], "Can not create a clarification from a different team"];
        yield ['dummy', ['text' => 'This is a clarification', 'to_team_id' => '2'], "Can not create a clarification that is sent to a team"];
        yield ['dummy', ['text' => 'This is a clarification', 'problem_id' => '4'], "Problem 4 not found"];
        yield ['dummy', ['text' => 'This is a clarification', 'time' => '1234'], "A team can not assign time"];
        yield ['dummy', ['text' => 'This is a clarification', 'id' => '1234'], "A team can not assign id"];
        yield ['dummy', ['text' => 'This is a clarification', 'reply_to_id' => 'nonexistent'], "Clarification nonexistent not found"];
        yield ['admin', ['text' => 'This is a clarification', 'from_team_id' => '2', 'to_team_id' => '2'], "Can not send a clarification from and to a team"];
        yield ['admin', ['text' => 'This is a clarification', 'from_team_id' => '3'], "Team 3 not found or not enabled"];
        yield ['admin', ['text' => 'This is a clarification', 'to_team_id' => '3'], "Team 3 not found or not enabled"];
        yield ['admin', ['text' => 'This is a clarification', 'time' => 'this is not a time'], "Can not parse time this is not a time"];
        yield ['admin', ['text' => 'This is a clarification', 'id' => '1'], "Clarification with ID 1 already exists"];
    }

    /**
     * Test that when creating a clarification as a user without an association team an error is returned
     */
    public function testMissingTeam()
    {
        // First, remove the team from the dummy user
        /** @var User $user */
        $user = static::$container->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['username' => 'dummy']);
        $user->setTeam();
        static::$container->get(EntityManagerInterface::class)->flush();

        $data = $this->verifyApiJsonResponse('POST', '/contests/2/clarifications', 400, 'dummy', ['text' => 'This is some text']);

        static::assertEquals('User does not belong to a team', $data['message']);
    }

    /**
     * Test that creating a clarification works as expected
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
    )
    {
        $clarificationId = $this->verifyApiJsonResponse('POST', '/contests/2/clarifications', 200, $user, $dataToSend);
        static::assertIsString($clarificationId);

        // Now load the clarification
        $clarificationRepository = static::$container->get(EntityManagerInterface::class)->getRepository(Clarification::class);
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
    }

    public function provideAddSuccess(): Generator
    {
        yield [
            'dummy',
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
        yield [
            'dummy',
            ['text' => 'This is a question about problem 1 replying to clarification 2', 'problem_id' => '1', 'reply_to_id' => '2'],
            false,
            'This is a question about problem 1 replying to clarification 2',
            1,
            2,
            2,
            null,
            null,
            null,
        ];
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
        yield [
            'admin',
            ['text' => 'This is a global clarification on problem 2 replying to clarification 1', 'problem_id' => '2', 'reply_to_id' => '1'],
            false,
            'This is a global clarification on problem 2 replying to clarification 1',
            2,
            1,
            null,
            null,
            null,
            null,
        ];
    }
}
