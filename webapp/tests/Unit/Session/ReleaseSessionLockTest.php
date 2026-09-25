<?php declare(strict_types=1);

namespace App\Tests\Unit\Session;

use App\Attribute\ReleaseSessionLock;
use App\Entity\Contest;
use App\DataFixtures\Test\RejudgingFirstToSolveFixture;
use App\Entity\Judging;
use App\Entity\Rejudging;
use App\Entity\Team;
use App\Tests\Session\SessionEventLog;
use App\Tests\Unit\BaseTestCase;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionMethod;
use Symfony\Component\Routing\RouterInterface;

/**
 * Every action marked with ReleaseSessionLock must release the session before it runs
 * and must not touch the session again until a template starts rendering. The base
 * template reads the flash messages, so a reopen during rendering is expected; a reopen
 * before that means the action silently lost the point of the attribute.
 */
class ReleaseSessionLockTest extends BaseTestCase
{
    protected array $roles = ['admin', 'jury', 'team'];

    public function testMarkedActionsRunWithoutTheSession(): void
    {
        foreach ($this->markedRoutes() as $name => $url) {
            SessionEventLog::reset();
            $this->client->request('GET', $url);
            self::assertSame(200, $this->client->getResponse()->getStatusCode(), "$name should render normally");

            $events = SessionEventLog::events();
            self::assertContains(['start', SessionEventLog::PHASE_REQUEST], $events, "$name: the session of the logged in user should have been loaded");
            self::assertContains(['save', SessionEventLog::PHASE_REQUEST], $events, "$name: the session should be released before the action runs");
            self::assertNotContains(['start', SessionEventLog::PHASE_ACTION], $events, "$name: the session should not be reopened before rendering starts");
        }
    }

    public function testTheCheckSeesAnActionThatReopensTheSession(): void
    {
        SessionEventLog::reset();
        $this->client->request('GET', '/test/session-probe/clean');
        self::assertContains(['save', SessionEventLog::PHASE_REQUEST], SessionEventLog::events());
        self::assertNotContains(['start', SessionEventLog::PHASE_ACTION], SessionEventLog::events());

        SessionEventLog::reset();
        $this->client->request('GET', '/test/session-probe/reopen');
        self::assertContains(['start', SessionEventLog::PHASE_ACTION], SessionEventLog::events());
    }

    public function testTheSessionIsKeptForUnmarkedActionsAndUnsafeMethods(): void
    {
        SessionEventLog::reset();
        $this->client->request('GET', '/test/session-probe/unmarked');
        self::assertNotContains(['save', SessionEventLog::PHASE_REQUEST], SessionEventLog::events());

        SessionEventLog::reset();
        $this->client->request('POST', '/test/session-probe/clean');
        self::assertNotContains(['save', SessionEventLog::PHASE_REQUEST], SessionEventLog::events());
    }

    /**
     * URLs of all routes whose action carries the attribute, keyed by route name.
     *
     * @return array<string, string>
     */
    private function markedRoutes(): array
    {
        /** @var RouterInterface $router */
        $router     = static::getContainer()->get(RouterInterface::class);
        $parameters = $this->routeParameters();
        $urls       = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (!is_string($controller) || !str_starts_with($controller, 'App\\Controller\\')) {
                continue;
            }
            [$class, $method] = explode('::', $controller, 2);
            if (!(new ReflectionMethod($class, $method))->getAttributes(ReleaseSessionLock::class)) {
                continue;
            }

            $missing = array_diff($route->compile()->getVariables(), array_keys($parameters[$name] ?? []), array_keys($route->getDefaults()));
            self::assertSame([], $missing, "Add route parameters for $name to " . self::class . '::routeParameters()');

            $urls[$name] = $router->generate($name, $parameters[$name] ?? []);
        }

        self::assertNotEmpty($urls, 'No action carries the ReleaseSessionLock attribute');
        return $urls;
    }

    /**
     * Route parameters for marked actions whose routes have placeholders. A marked
     * route without an entry here fails the test, so every new use of the attribute
     * is covered.
     *
     * @return array<string, array<string, string>>
     */
    private function routeParameters(): array
    {
        return [
            'analysis_team'    => ['team' => 'exteam'],
            'analysis_problem' => ['probid' => 'hello'],
            'jury_balloons'    => ['contestId' => 'demo'],
            'jury_rejudging'   => ['rejudgingId' => $this->rejudgingWithJudgings()],
            'jury_submissions' => ['contestId' => 'demo'],
        ];
    }

    /**
     * A rejudging as created for a judged submission: the original judging stays valid
     * and the rejudging holds the new one.
     */
    private function rejudgingWithJudgings(): string
    {
        $this->loadFixture(RejudgingFirstToSolveFixture::class);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var Team $team */
        $team       = $em->getRepository(Team::class)->findOneBy(['name' => 'Another team']);
        $submission = $team->getSubmissions()->first();
        /** @var Judging $original */
        $original   = $submission->getJudgings()->first();
        $rejudging  = (new Rejudging())
            ->setStarttime(Utils::now())
            ->setReason(__METHOD__);
        $newJudging = (new Judging())
            ->setContest($submission->getContest())
            ->setStarttime($original->getStarttime())
            ->setEndtime($original->getEndtime())
            ->setRejudging($rejudging)
            ->setOriginalJudging($original)
            ->setValid(false)
            ->setResult('correct');
        $newJudging->setSubmission($submission);
        $submission->addJudging($newJudging);
        $submission->setRejudging($rejudging);
        $em->persist($rejudging);
        $em->persist($newJudging);
        $em->flush();

        return (string)$rejudging->getRejudgingid();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The shadow differences page redirects away unless the contest is in shadow mode.
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($em->getRepository(Contest::class)->findAll() as $contest) {
            $contest->setExternalSourceEnabled(true);
        }
        $em->flush();
    }
}
