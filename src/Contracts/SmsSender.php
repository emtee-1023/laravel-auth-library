<?php

namespace Markt\LaravelAuth\Contracts;

interface SmsSender
{
    public function send(string $phoneNumber, string $message): void;
}
