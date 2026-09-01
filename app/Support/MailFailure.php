<?php

namespace App\Support;

class MailFailure
{
    public static function message(\Throwable $e): string
    {
        $raw = $e->getMessage();
        $lower = strtolower($raw);

        if (str_contains($raw, '535') || str_contains($lower, 'username and password not accepted')) {
            return 'Gmail rejected the sending mailbox login (535). Use a Google App Password for the account saved in Email Delivery, not the normal Gmail password. The From address must be that same Gmail account. Each user still receives mail at the address saved on their account.';
        }

        if (str_contains($raw, '534') || str_contains($lower, 'application-specific') || str_contains($lower, 'less secure')) {
            return 'Gmail requires an App Password. Enable 2-Step Verification on the sending Gmail account, create an App Password, and save it in Maintenance → Email Delivery.';
        }

        if (str_contains($lower, 'could not authenticate') || str_contains($lower, 'authentication failed')) {
            return 'The SMTP username or password was rejected. Check the sending mailbox in Maintenance → Email Delivery.';
        }

        report($e);

        return 'The mail server could not send the message. Please try again or contact an administrator.';
    }
}
