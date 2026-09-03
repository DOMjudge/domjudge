<?php declare(strict_types=1);

namespace App\Tests\Session;

use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

class RecordingSessionStorage extends MockFileSessionStorage
{
    public function start(): bool
    {
        if (!$this->isStarted()) {
            SessionEventLog::record('start');
        }
        return parent::start();
    }

    public function save(): void
    {
        SessionEventLog::record('save');
        parent::save();
    }
}
