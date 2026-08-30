<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\BaseController;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * BaseController::isLocalRefererUrl() decides whether a Referer header points at a page we
 * can safely redirect back to. It resolves the referer with the router, which throws for
 * paths it cannot match with GET.
 *
 * It used to catch only ResourceNotFoundException, so a referer pointing at a route that
 * exists but does not accept GET - every form-handling POST route in the application -
 * escaped as an uncaught MethodNotAllowedException and turned the response into a 500.
 */
class LocalRefererTest extends KernelTestCase
{
    private const PREFIX = 'https://example.org';

    #[DataProvider('provideReferers')]
    public function testIsLocalRefererUrl(string $referer, bool $expected, string $why): void
    {
        self::bootKernel();

        // isLocalRefererUrl() only uses its arguments, so the controller's services are not
        // needed and constructing it without them keeps this test free of the container.
        $controller = (new ReflectionClass(LocalRefererTestController::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(BaseController::class, 'isLocalRefererUrl');

        self::assertSame(
            $expected,
            $method->invoke($controller, self::router(), $referer, self::PREFIX),
            $why
        );
    }

    private static function router(): RouterInterface
    {
        return self::getContainer()->get(RouterInterface::class);
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function provideReferers(): iterable
    {
        yield 'GET route' => [
            self::PREFIX . '/login',
            true,
            'a local page that can be reached with GET is a usable referer',
        ];
        yield 'GET route with query string' => [
            self::PREFIX . '/login?foo=bar',
            true,
            'the query string must be ignored when matching',
        ];
        yield 'POST-only route' => [
            self::PREFIX . '/jury/1/verify',
            false,
            'a local path that does not accept GET must be rejected, not raise MethodNotAllowedException',
        ];
        yield 'unknown local path' => [
            self::PREFIX . '/does-not-exist',
            false,
            'a local path with no route is not a usable referer',
        ];
        yield 'external URL' => [
            'https://example.com/login',
            false,
            'a referer outside this application is never local',
        ];
    }
}

/**
 * BaseController is abstract and its constructor takes services this test does not need, so
 * give it a concrete subclass to reflect on.
 */
class LocalRefererTestController extends BaseController
{
}
