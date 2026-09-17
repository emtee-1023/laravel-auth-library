<?php

namespace Markt\LaravelAuth\Tests\Fixtures;

use Markt\LaravelAuth\Contracts\SmsSender;

class FakeSmsSender implements SmsSender
{
    /**
     * @var array<string, list<string>>
     */
    public array $messages = [];

    public function send(string $phoneNumber, string $message): void
    {
        $this->messages[$phoneNumber][] = $message;
    }

    public function messagesFor(string $phoneNumber): ?string
    {
        return $this->messages[$phoneNumber][0] ?? null;
    }

    public function countFor(string $phoneNumber): int
    {
        return count($this->messages[$phoneNumber] ?? []);
    }
}