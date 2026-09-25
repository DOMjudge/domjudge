<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\API;

use App\Controller\API\JudgehostController;
use OpenApi\Attributes as OA;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * An API action that returns void sends no body, so Symfony answers 204 No Content. Several
 * such actions documented a 200 instead, which publishes a response code the API never
 * actually returns.
 *
 * This works by reflection rather than by reading the generated OpenAPI document, so it needs
 * no kernel and covers every controller in the namespace, including ones added later.
 */
class ResponseStatusDocumentationTest extends TestCase
{
    private const CONTROLLER_NAMESPACE = 'App\\Controller\\API\\';

    public function testVoidActionsDocumentNoContent(): void
    {
        $checked = 0;
        $wrong = [];

        foreach ($this->apiControllers() as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                $returnType = $method->getReturnType();
                if (!$returnType instanceof ReflectionNamedType || $returnType->getName() !== 'void') {
                    continue;
                }

                foreach ($method->getAttributes(OA\Response::class) as $attribute) {
                    $arguments = $attribute->getArguments();
                    $code = $arguments['response'] ?? $arguments[0] ?? null;
                    if (!is_int($code) || $code < 200 || $code >= 300) {
                        // Only the success response is interesting; error codes are unaffected.
                        continue;
                    }

                    $checked++;
                    if ($code !== 204) {
                        // Collect rather than assert immediately, so a maintainer sees every
                        // offender at once instead of one per run.
                        $wrong[] = sprintf('%s::%s() documents %d', $class, $method->getName(), $code);
                    }
                }
            }
        }

        self::assertGreaterThan(
            0,
            $checked,
            'found no documented void API actions at all, so this test is not checking anything'
        );
        self::assertSame(
            [],
            $wrong,
            "These actions return void, so they answer 204 No Content:\n  " . implode("\n  ", $wrong)
        );
    }

    /**
     * @return list<class-string>
     */
    private function apiControllers(): array
    {
        // Locate the controller directory through a known class rather than by counting
        // parent directories, so moving this test does not silently make it check nothing.
        $directory = dirname((new ReflectionClass(JudgehostController::class))->getFileName());

        $classes = [];
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $class = self::CONTROLLER_NAMESPACE . basename($file, '.php');
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        self::assertNotEmpty($classes, 'no API controllers found');

        return $classes;
    }
}
