<?php

namespace Markt\LaravelAuth\contracts;

interface SmsSender
{
    public function send(string $phoneNumber, string $message): void;
}
