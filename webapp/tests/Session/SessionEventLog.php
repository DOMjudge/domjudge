<?php declare(strict_types=1);

namespace App\Tests\Session;

/**
 * Records when the session is started and saved during a request, together with the
 * phase of the request this happened in. Used to check that actions marked with
 * ReleaseSessionLock really run without the session.
 */
final class SessionEventLog
{
    public const PHASE_REQUEST  = 'request';   // Until the controller arguments are resolved.
    public const PHASE_ACTION   = 'action';    // The controller runs, before any template renders.
    public const PHASE_RENDER   = 'render';    // A Twig template is rendering.
    public const PHASE_RESPONSE = 'response';  // The response is being finalised.

    private static string $phase = self::PHASE_REQUEST;

    /** @var list<array{string, string}> */
    private static array $events = [];

    public static function reset(): void
    {
        self::$phase  = self::PHASE_REQUEST;
        self::$events = [];
    }

    public static function setPhase(string $phase): void
    {
        self::$phase = $phase;
    }

    public static function phase(): string
    {
        return self::$phase;
    }

    public static function record(string $event): void
    {
        self::$events[] = [$event, self::$phase];
    }

    /** @return list<array{string, string}> */
    public static function events(): array
    {
        return self::$events;
    }
}
