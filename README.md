# markt/laravel-auth

Reusable authentication infrastructure for Laravel applications.

`markt/laravel-auth` provides phone-based authentication, OTP verification, password reset, Laravel Sanctum authentication, and optional two-factor authentication through SMS.

The package is designed to keep authentication logic reusable across multiple Laravel applications while minimizing the amount of authentication-specific code that each consuming application needs to implement.

---

## Table of Contents

1. [What is this package?](#what-is-this-package)
2. [How Composer Packages Work](#how-composer-packages-work)
3. [Requirements](#requirements)
4. [Package Architecture](#package-architecture)
5. [Installation](#installation)
6. [Using the Package from GitHub/VCS](#using-the-package-from-githubvcs)
7. [Configuration](#configuration)
8. [Database](#database)
9. [SMS Provider](#sms-provider)
10. [Authentication API](#authentication-api)
11. [Registration Flow](#registration-flow)
12. [Login Flow](#login-flow)
13. [Two-Factor Authentication](#two-factor-authentication)
14. [Password Reset](#password-reset)
15. [Logout](#logout)
16. [Rate Limiting](#rate-limiting)
17. [Automatic Cleanup](#automatic-cleanup)
18. [Understanding the Package Internals](#understanding-the-package-internals)
19. [Consuming Application Responsibilities](#consuming-application-responsibilities)
20. [Deployment](#deployment)
21. [Testing](#testing)
22. [Development](#development)
23. [Updating the Package](#updating-the-package)
24. [Versioning](#versioning)
25. [Security Considerations](#security-considerations)

---

# What is this package?

`markt/laravel-auth` is a Laravel package that provides reusable authentication infrastructure.

Instead of implementing authentication separately in every Laravel application, an application can install this package and obtain functionality such as:

- Phone-number registration
- SMS OTP verification
- Password authentication
- Phone verification
- Laravel Sanctum access tokens
- Password reset through OTP
- Two-factor authentication
- Authentication rate limiting
- OTP and 2FA challenge cleanup
- Configurable authentication models
- Configurable OTP settings
- Configurable rate limits

The package is intended to provide the **authentication infrastructure**, while the consuming Laravel application remains responsible for its own application-specific user model and SMS provider.

---

# How Composer Packages Work

If you have never used a Composer package before, the basic idea is simple.

Imagine your Laravel application contains:

```text
app/
config/
database/
routes/
```

Normally, you write all of your application's functionality yourself.

Composer allows you to install functionality written by another developer:

```bash
composer require vendor/package
```

Composer downloads the package into:

```text
vendor/
```

Your application can then use classes from that package.

For example:

```php
use Markt\LaravelAuth\Services\AuthService;
```

You don't copy `AuthService.php` into your application.

Instead:

```text
Your Laravel application
        |
        | Composer
        v
vendor/markt/laravel-auth/
        |
        v
Markt\LaravelAuth
```

Composer also manages the package's dependencies.

For example, this package depends on:

```text
Laravel
Sanctum
```

Composer makes sure those dependencies are available.

A package is therefore essentially a reusable codebase with its own:

- `composer.json`
- PHP source code
- configuration
- migrations
- routes
- tests
- service provider

---

# Requirements

Current package requirements:

- PHP 8.3+
- Laravel 13.x
- Laravel Sanctum 4.x

Development/testing uses:

- Orchestra Testbench 11.x

The package currently declares:

```json
{
  "require": {
    "laravel/framework": "^13.0",
    "laravel/sanctum": "^4.3"
  },
  "require-dev": {
    "orchestra/testbench": "^11.0"
  }
}
```

---

# Package Architecture

The package follows a service-oriented structure.

```text
markt/laravel-auth/
│
├── config/
│   └── laravel-auth.php
│
├── database/
│   └── migrations/
│
├── src/
│   ├── Contracts/
│   │   └── SmsSender.php
│   │
│   ├── Enums/
│   │   └── OtpPurpose.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── AuthController.php
│   │   └── Requests/
│   │
│   ├── Models/
│   │   ├── Otp.php
│   │   ├── TwoFactorChallenge.php
│   │   └── TwoFactorSetting.php
│   │
│   ├── Services/
│   │   ├── OtpService.php
│   │   ├── AuthService.php
│   │   └── TwoFactorChallengeService.php
│   │
│   ├── routes/
│   │   └── api.php
│   │
│   └── LaravelAuthServiceProvider.php
│
├── tests/
│
├── composer.json
└── README.md
```

The important architectural idea is:

```text
Controller
    ↓
AuthService
    ↓
Specialized Services
    ↓
Models / Database
```

Controllers are intentionally kept thin.

Business logic belongs primarily in services.

---

# Installation

There are two main ways to install this package:

1. From a Git repository using Composer VCS
2. From Packagist once the package is published there

During development, GitHub/VCS is appropriate.

---

# Using the Package from GitHub/VCS

Composer supports VCS repositories such as Git repositories hosted on GitHub.

Suppose the package repository is:

```text
https://github.com/YOUR-USERNAME/laravel-auth
```

The consuming Laravel application's `composer.json` can contain:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/YOUR-USERNAME/laravel-auth"
    }
  ]
}
```

Then require the package:

```bash
composer require markt/laravel-auth:dev-main
```

The package's `composer.json` must contain:

```json
{
  "name": "markt/laravel-auth"
}
```

The name is important because Composer identifies the package using this name. Composer's package name convention is `vendor/package`.

## Development branch

If the package is still under active development, you can install:

```bash
composer require markt/laravel-auth:dev-main
```

For a branch named:

```text
feature/something
```

Composer uses:

```bash
composer require markt/laravel-auth:dev-feature/something
```

Composer uses the `dev-` prefix when referring to development branches.

---

# VCS vs Packagist

For the current stage of this package, VCS is perfectly suitable.

### VCS

```text
GitHub
   ↓
Composer VCS repository
   ↓
Laravel application
```

This is useful while:

- developing the package
- testing the package with client applications
- using private repositories
- installing unreleased versions
- testing specific branches

### Packagist

Once the package reaches a stable release, it can be published through Packagist.

Then installation becomes much simpler:

```bash
composer require markt/laravel-auth
```

without manually adding the GitHub repository to the application's `composer.json`.

So the progression can be:

```text
Development
    ↓
GitHub + VCS
    ↓
Stable release + Git tag
    ↓
Packagist
```

---

# Laravel Package Discovery

Laravel packages normally expose their service provider through Composer package discovery.

The package service provider is:

```php
Markt\LaravelAuth\LaravelAuthServiceProvider
```

The service provider connects the package to Laravel.

It is responsible for things such as:

- loading configuration
- registering routes
- publishing configuration
- publishing migrations
- registering Artisan commands
- registering rate limiters

Laravel supports package discovery so applications do not have to manually register every package service provider.

---

# Configuration

The package configuration is:

```text
config/laravel-auth.php
```

The default configuration contains:

```php
return [
    'models' => [
        'user' => App\Models\User::class,
        'otp' => Markt\LaravelAuth\Models\Otp::class,
        'two_factor_challenge' => Markt\LaravelAuth\Models\TwoFactorChallenge::class,
        'two_factor_setting' => Markt\LaravelAuth\Models\TwoFactorSetting::class,
    ],

    'otp' => [
        'length' => 6,
        'expires_in' => 5,
        'max_attempts' => 5,
    ],

    'two_factor' => [
        'enabled' => true,
        'challenge_expires_in' => 5,
    ],

    'rate_limits' => [
        'login' => [
            'attempts' => 5,
            'decay_seconds' => 60,
        ],

        'otp_verification' => [
            'attempts' => 5,
            'decay_seconds' => 300,
        ],

        'password_reset' => [
            'attempts' => 3,
            'decay_seconds' => 300,
        ],

        'two_factor' => [
            'attempts' => 5,
            'decay_seconds' => 300,
        ],
    ],

    'routes' => [
        'prefix' => 'api',
    ],
];
```

## Publishing configuration

The consuming application can publish the configuration:

```bash
php artisan vendor:publish --tag=laravel-auth-config
```

This creates:

```text
config/laravel-auth.php
```

in the consuming application.

This allows the application to override package defaults.

Laravel packages commonly publish configuration this way so consuming applications can customize package behavior.

---

# Database

The package owns its authentication-specific tables.

These include:

```text
otps
two_factor_challenges
two_factor_settings
```

The consuming application's `users` table remains owned by the consuming application.

This is intentional.

The package does not require the consuming application to modify its `users` table to add a 2FA column.

---

# Publishing Migrations

The package provides its migrations through the service provider.

The consuming application can publish them:

```bash
php artisan vendor:publish --tag=laravel-auth-migrations
```

Then run:

```bash
php artisan migrate
```

Laravel supports publishing package migrations into the application's own migration directory.

---

# User Model Requirements

The consuming application's user model must support Laravel Sanctum.

For example:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

The application must also have the required user fields used by the package.

At minimum, the current implementation expects:

```text
id
phone_number
phone_verified_at
password
```

`phone_number` should be unique.

---

# SMS Provider

The package deliberately does not hard-code an SMS provider.

Instead, it defines a contract:

```php
Markt\LaravelAuth\Contracts\SmsSender
```

The contract is:

```php
interface SmsSender
{
    public function send(
        string $phoneNumber,
        string $message
    ): void;
}
```

This is an important package design decision.

The package knows:

> "I need to send an SMS."

The consuming application decides:

> "How do I actually send that SMS?"

For example, the client application could implement:

```php
class AfricaTalkingSmsSender implements SmsSender
{
    public function send(
        string $phoneNumber,
        string $message
    ): void {
        // Africa's Talking implementation
    }
}
```

Then bind it in the application's service container:

```php
$this->app->bind(
    SmsSender::class,
    AfricaTalkingSmsSender::class
);
```

This means the package does not need to know whether the application uses:

- Africa's Talking
- Twilio
- another SMS provider
- a custom gateway

The package only depends on the contract.

---

# Authentication API

The package exposes its routes under:

```text
/api/auth
```

Current endpoints include:

| Method | Endpoint                    | Purpose                |
| ------ | --------------------------- | ---------------------- |
| POST   | `/api/auth/register`        | Register a user        |
| POST   | `/api/auth/verify-phone`    | Verify phone with OTP  |
| POST   | `/api/auth/login`           | Login                  |
| POST   | `/api/auth/forgot-password` | Request password reset |
| POST   | `/api/auth/reset-password`  | Reset password         |
| POST   | `/api/auth/2fa/verify`      | Complete 2FA login     |
| POST   | `/api/auth/logout`          | Logout                 |
| GET    | `/api/auth/user`            | Get authenticated user |
| POST   | `/api/auth/2fa/enable`      | Start 2FA setup        |
| POST   | `/api/auth/2fa/confirm`     | Confirm 2FA            |
| POST   | `/api/auth/2fa/disable`     | Disable 2FA            |

The exact prefix can be changed through:

```php
' routes ' => [
    'prefix' => 'api',
],
```

without the spaces:

```php
'routes' => [
    'prefix' => 'api',
],
```

---

# Registration Flow

The registration process is:

```text
Client
   |
   | POST /api/auth/register
   v
AuthController
   |
   v
AuthService
   |
   ├── create user
   |
   └── send registration OTP
           |
           v
       SmsSender
```

The OTP is stored as a hash rather than plain text.

The user then verifies the OTP:

```text
POST /api/auth/verify-phone
```

Successful verification sets:

```text
phone_verified_at
```

on the user.

---

# OTP System

OTP purposes are represented using:

```php
enum OtpPurpose: string
{
    case Registration = 'registration';
    case PasswordReset = 'password_reset';
    case TwoFactor = 'two_factor';
}
```

This prevents different authentication flows from accidentally sharing OTP state.

An OTP contains:

```text
phone_number
purpose
otp_hash
expires_at
attempts
verified_at
```

The actual OTP is never stored directly.

Instead:

```text
Generated OTP
      ↓
Hash::make()
      ↓
otp_hash
```

When verifying:

```text
User OTP
   ↓
Hash::check()
   ↓
valid / invalid
```

---

# OTP Security

The package protects OTPs in several ways.

## Expiration

Default:

```text
5 minutes
```

## Attempt limit

Default:

```text
5 attempts
```

## Single use

Once verified:

```text
verified_at = current timestamp
```

The same OTP cannot be used again.

## New OTP invalidates previous OTP

When a new OTP is generated for the same:

```text
phone number + purpose
```

previous unverified OTPs are invalidated.

This prevents multiple active OTPs from being usable simultaneously.

---

# Login Flow

Normal login:

```text
POST /api/auth/login
```

The package:

1. Finds the user by phone number.
2. Verifies the password.
3. Checks that the phone number has been verified.
4. Checks whether 2FA is enabled.
5. Creates a Sanctum token if 2FA is not required.

Successful login returns:

```json
{
  "message": "Login successful.",
  "token": "...",
  "token_type": "Bearer"
}
```

---

# Two-Factor Authentication

2FA is optional.

It is controlled by:

```php
'two_factor' => [
    'enabled' => true,
],
```

This determines whether the application supports the package's 2FA functionality.

Individual user settings are stored separately in:

```text
two_factor_settings
```

This avoids requiring the client application to add:

```text
two_factor_enabled
```

to its `users` table.

---

# Enabling 2FA

Authenticated user sends:

```text
POST /api/auth/2fa/enable
```

The package sends an OTP.

The user then confirms:

```text
POST /api/auth/2fa/confirm
```

with the OTP.

If valid:

```text
two_factor_settings.enabled = true
```

---

# 2FA Login

When 2FA is enabled:

```text
POST /api/auth/login
```

does not immediately return a Sanctum token.

Instead:

```json
{
  "message": "Two-factor authentication required.",
  "requires_two_factor": true,
  "challenge_token": "..."
}
```

The user then submits:

```text
POST /api/auth/2fa/verify
```

with:

```json
{
  "challenge_token": "...",
  "otp": "123456"
}
```

If both are valid:

```text
2FA verified
      ↓
challenge consumed
      ↓
Sanctum token created
```

The challenge is therefore single-use.

---

# Two-Factor Challenges

Challenges are stored in:

```text
two_factor_challenges
```

A challenge contains:

```text
user_id
token
expires_at
```

Only one active challenge is maintained per user.

Creating a new challenge invalidates an existing challenge.

Successful verification consumes the challenge.

Expired challenges are rejected.

---

# Disabling 2FA

Disabling 2FA requires:

- an authenticated Sanctum session
- the user's current password

Endpoint:

```text
POST /api/auth/2fa/disable
```

If successful:

```text
two_factor_settings.enabled = false
```

Existing 2FA challenges are also removed.

This prevents an old login challenge from remaining valid after 2FA has been disabled.

---

# Password Reset

The password reset process uses OTP.

First:

```text
POST /api/auth/forgot-password
```

The package intentionally does not reveal whether a phone number belongs to an account.

If the account exists, a password-reset OTP is sent.

Then:

```text
POST /api/auth/reset-password
```

with:

```json
{
  "phone_number": "0712345678",
  "otp": "123456",
  "password": "new-password"
}
```

After successful reset:

```text
new password saved
        ↓
all existing Sanctum tokens deleted
```

This means existing sessions are invalidated after a password reset.

---

# Logout

Authenticated users can call:

```text
POST /api/auth/logout
```

The current Sanctum access token is deleted.

The package also supports deleting a specific token when a token ID is supplied internally.

---

# Rate Limiting

Authentication endpoints are protected by named Laravel rate limiters.

Configured limits include:

```text
Login
5 attempts / minute

OTP verification
5 attempts / 5 minutes

Password reset
3 attempts / 5 minutes

2FA
5 attempts / 5 minutes
```

The rate limiter keys requests using information such as:

```text
IP address
phone number
```

This helps prevent brute-force attacks against authentication endpoints.

---

# Automatic Cleanup

Expired authentication records don't need to remain in the database indefinitely.

The package provides:

```bash
php artisan laravel-auth:cleanup
```

The command removes:

- expired OTPs
- expired 2FA challenges

The cleanup command is also registered with Laravel's scheduler and runs every ten minutes.

The consuming application does not need to add application-level scheduling code.

The server still needs Laravel's scheduler infrastructure running.

For a normal Linux deployment, the application server can invoke:

```cron
* * * * * cd /var/www/client-app && php artisan schedule:run >> /dev/null 2>&1
```

Laravel then determines when the package's ten-minute cleanup task is due.

---

# Understanding the Package Internals

If you're learning how Composer packages are built, these are the most important pieces.

## `composer.json`

This tells Composer what the package is.

It defines:

```text
package name
dependencies
autoloading
development dependencies
Laravel package discovery
```

The important package name is:

```text
markt/laravel-auth
```

---

# PSR-4 Autoloading

The package uses:

```json
"autoload": {
    "psr-4": {
        "Markt\\LaravelAuth\\": "src/"
    }
}
```

This means:

```text
Markt\LaravelAuth\Services\AuthService
```

maps to:

```text
src/Services/AuthService.php
```

Composer generates the autoloader that makes this possible.

You therefore don't manually include PHP files.

---

# Service Provider

The central integration point is:

```text
src/LaravelAuthServiceProvider.php
```

A Laravel package service provider connects the package to the Laravel application.

It handles things such as:

```text
configuration
routes
migrations
commands
rate limiters
```

Laravel describes service providers as the connection point between a package and the Laravel application.

---

# `AuthService`

`AuthService` is the main authentication orchestration layer.

It coordinates:

```text
registration
phone verification
login
2FA login
password reset
logout
2FA enable/disable
```

It does not need to know the low-level details of how OTPs are generated or how challenges are stored.

Those responsibilities belong to specialized services.

---

# `OtpService`

Responsible for:

```text
OTP generation
OTP hashing
OTP storage
OTP verification
OTP expiration
OTP attempt limits
OTP invalidation
OTP cleanup
```

This separation makes OTP behavior reusable across:

```text
registration
password reset
2FA
```

---

# `TwoFactorChallengeService`

Responsible for:

```text
creating challenges
finding valid challenges
consuming challenges
cleaning expired challenges
```

This keeps challenge lifecycle logic out of `AuthService`.

---

# Models

Package-owned models include:

```text
Otp
TwoFactorChallenge
TwoFactorSetting
```

The package intentionally does not own the application's `User` model.

Instead:

```php
'models' => [
    'user' => App\Models\User::class,
],
```

allows the consuming application to define which model represents its users.

---

# Contracts

The package contains:

```text
Contracts/SmsSender.php
```

A contract defines an interface rather than an implementation.

This is one of the most important concepts when building reusable packages.

The package says:

```text
"I need something capable of sending SMS."
```

The client application supplies:

```text
"Here is my SMS implementation."
```

This keeps the package provider-independent.

---

# Enums

The package uses:

```text
OtpPurpose
```

to distinguish OTP use cases.

This is safer and clearer than scattering strings such as:

```text
"registration"
"password_reset"
"two_factor"
```

throughout the code.

---

# HTTP Layer

The HTTP layer contains:

```text
Controllers
Form Requests
Routes
```

The controller receives the HTTP request and delegates business logic.

For example:

```text
HTTP Request
      ↓
AuthController
      ↓
AuthService
      ↓
OtpService / TwoFactorChallengeService
      ↓
Database
```

This keeps controllers small and easier to maintain.

---

# Consuming Application Responsibilities

The package intentionally handles most authentication infrastructure, but the client application still has a few responsibilities.

The client must provide:

### 1. User model

The application owns:

```php
App\Models\User
```

### 2. Sanctum

The package requires Laravel Sanctum.

### 3. SMS implementation

The application must bind:

```php
Markt\LaravelAuth\Contracts\SmsSender
```

to an actual SMS implementation.

### 4. Database

The client runs the package migrations.

### 5. Scheduler infrastructure

The client/server must ensure Laravel's scheduler runs.

The client does **not** need to implement the cleanup logic itself.

---

# Deployment

A typical client deployment looks like:

```text
1. Create Laravel application
        ↓
2. Configure database
        ↓
3. Install Sanctum / package dependencies
        ↓
4. Install markt/laravel-auth
        ↓
5. Configure SMS sender
        ↓
6. Run migrations
        ↓
7. Configure server scheduler
        ↓
8. Deploy application
```

For a GitHub VCS installation:

```bash
composer require markt/laravel-auth:dev-main
```

Then:

```bash
php artisan migrate
```

Optionally publish configuration:

```bash
php artisan vendor:publish --tag=laravel-auth-config
```

Then configure the SMS provider binding.

Finally ensure the server runs:

```bash
php artisan schedule:run
```

every minute.

---

# Testing

The package uses PHPUnit/Laravel testing infrastructure and Orchestra Testbench.

Tests should cover:

```text
OTP
registration
phone verification
login
password reset
2FA
logout
rate limiting
cleanup
```

The purpose of package testing is to make sure that functionality continues working when the package changes.

Run:

```bash
php artisan test
```

or:

```bash
vendor/bin/phpunit
```

depending on the package's configured test command.

---

# Development

Clone the repository:

```bash
git clone <repository-url>
```

Install dependencies:

```bash
composer install
```

The package can then be tested independently using its Testbench environment.

For local development against a Laravel application, Composer's `path` repository is useful.

Example:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-auth"
    }
  ]
}
```

Then:

```bash
composer require markt/laravel-auth:@dev
```

Composer's `path` repositories are specifically useful for local package development and can symlink the package into `vendor` when possible.

---

# Updating the Package

When developing locally using a path repository, changes to the package source are immediately available to the consuming application when Composer has symlinked the package.

If you change Composer autoload definitions, regenerate the autoloader:

```bash
composer dump-autoload
```

If you change dependency definitions:

```bash
composer update
```

For a Git/VCS installation, the consuming application can update the package using:

```bash
composer update markt/laravel-auth
```

For a specific development branch:

```bash
composer update markt/laravel-auth
```

Composer resolves the package according to the configured repository and version constraint.

---

# Versioning

Once the package is ready for real client applications, use Git tags for releases.

For example:

```text
v1.0.0
v1.0.1
v1.1.0
v2.0.0
```

A typical release workflow is:

```text
Development
    ↓
Tests pass
    ↓
Commit
    ↓
Merge to main
    ↓
Create Git tag
    ↓
v1.0.0
    ↓
Client installs stable version
```

Instead of:

```bash
composer require markt/laravel-auth:dev-main
```

clients can eventually use:

```bash
composer require markt/laravel-auth:^1.0
```

This is preferable for production applications because they can depend on stable semantic versions rather than an actively changing development branch.

Composer recommends omitting a manually specified `version` field when the version can be inferred from VCS tags.

---

# Recommended Repository Strategy

During development:

```text
GitHub repository
        ↓
VCS
        ↓
dev-main
```

For production:

```text
GitHub repository
        ↓
Git tags
        ↓
Packagist
        ↓
composer require markt/laravel-auth:^1.0
```

This gives you:

```text
Development flexibility
+
Production stability
```

---

# Security Considerations

This package handles authentication and therefore should be treated as security-sensitive infrastructure.

Important security properties include:

### OTPs are hashed

The database does not store plaintext OTPs.

### OTPs expire

Expired OTPs cannot be verified.

### OTP attempts are limited

Repeated incorrect guesses are restricted.

### OTPs are single-use

Successful OTP verification marks the OTP as verified.

### New OTPs invalidate old OTPs

Only the latest OTP for a particular purpose remains valid.

### 2FA challenges expire

Challenges have their own expiration time.

### 2FA challenges are consumed

Successful challenges cannot be reused.

### Password resets invalidate existing tokens

After a successful password reset, existing Sanctum tokens are deleted.

### Authentication endpoints are rate limited

Login, OTP, password reset, and 2FA endpoints have rate limits.

---

# Package Philosophy

The package follows a few important principles.

## Keep client integration small

A Laravel application should not have to rewrite authentication infrastructure every time it needs phone authentication.

## Keep the User model owned by the application

The package should not take ownership of application-specific user data.

## Use contracts for external services

SMS delivery is an external concern, so the package defines:

```text
SmsSender
```

rather than forcing one provider.

## Keep business logic out of controllers

Controllers delegate to services.

## Keep package-owned state in package-owned tables

OTP and 2FA state are stored independently from the consuming application's user table.

## Make security behavior automatic

Expiration, attempt limits, challenge invalidation, rate limiting, and cleanup are built into the package rather than relying on every client developer to remember them.

---

# Typical Client Experience

The intended experience for a developer installing the package is approximately:

```bash
composer require markt/laravel-auth
```

```bash
php artisan migrate
```

Configure the SMS implementation.

Then the application immediately has:

```text
/api/auth/register
/api/auth/verify-phone
/api/auth/login
/api/auth/forgot-password
/api/auth/reset-password
/api/auth/2fa/verify
/api/auth/logout
/api/auth/user
/api/auth/2fa/enable
/api/auth/2fa/confirm
/api/auth/2fa/disable
```

The package handles the authentication infrastructure behind those endpoints.

The consuming application can therefore focus on its actual business functionality instead of rebuilding authentication for every project.

---

# Summary

`markt/laravel-auth` is a reusable Laravel authentication package built around:

```text
Composer
    +
Laravel
    +
Sanctum
    +
OTP authentication
    +
SMS abstraction
    +
2FA
    +
rate limiting
    +
automated cleanup
```

Its architecture separates responsibilities:

```text
AuthController
       ↓
AuthService
       ↓
 ┌─────┴─────────────┐
 ↓                   ↓
OtpService      TwoFactorChallengeService
 ↓                   ↓
Otp model       Challenge model
                       ↓
                TwoFactorSetting
```

The consuming application supplies the `User` model and SMS implementation, while the package provides the reusable authentication infrastructure.

For the current development stage, GitHub + Composer VCS is an appropriate distribution method. Once the package reaches a stable release, Git tags and Packagist provide the cleaner production installation experience.
