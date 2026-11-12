<?php

namespace App\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Mail: a mailer that sends. "log" and "array" write the messages to the log or keep them in memory (a warning:
 * nothing reaches anyone); smtp needs a host; the sender address must be set. No credentials are read or shown.
 */
final class MailCheck extends Check
{
    protected ?string $name = 'mail';

    public function run(): Result
    {
        $mailer = (string) config('mail.default');
        $config = (array) config('mail.mailers.'.$mailer, []);
        $transport = (string) ($config['transport'] ?? $mailer);

        if ($config === []) {
            return Outcome::failed('mail_unknown', ['mailer' => $mailer]);
        }
        if (in_array($transport, ['log', 'array'], true)) {
            return Outcome::warning('mail_log', ['mailer' => $mailer]);
        }
        if ($transport === 'smtp' && blank($config['host'] ?? null)) {
            return Outcome::failed('mail_no_host', ['mailer' => $mailer]);
        }
        if (blank(config('mail.from.address'))) {
            return Outcome::warning('mail_no_from', ['mailer' => $mailer]);
        }

        return Outcome::ok('mail_ok', ['mailer' => $mailer]);
    }
}
