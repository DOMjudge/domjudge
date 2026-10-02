<?php declare(strict_types=1);

namespace App\Attribute;

use Attribute;

/**
 * Release the lock on the session before running this controller action.
 *
 * The session handler locks the session row for the whole request, so all requests
 * from the same browser are served one at a time. Put this on read-only actions that
 * take a while and do not need the session, so the other tabs of the user keep working
 * while this one is computed. Only GET and HEAD requests are affected.
 *
 * The session is reopened automatically when something uses it later on, such as the
 * flash messages in the base template. Do not keep a session bag around from before
 * the action started: it is rebound when the session is reopened.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ReleaseSessionLock
{
}
