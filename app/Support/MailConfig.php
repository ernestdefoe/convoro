<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Applies the admin-configured mail settings over Laravel's config at boot, so
 * the whole app (digests, notifications, password resets) uses whatever the
 * admin picked in Admin → Email — without editing .env. Until the admin saves,
 * the .env mailer is used unchanged.
 */
class MailConfig
{
    public static function apply(): void
    {
        if (! (bool) Settings::get('mail.configured', false)) {
            return;
        }

        $transport = Settings::get('mail.transport', 'sendmail') === 'smtp' ? 'smtp' : 'sendmail';

        $from = trim((string) Settings::get('mail.from_address', ''));
        $fromName = trim((string) Settings::get('mail.from_name', '')) ?: (string) Settings::get('site.name', 'Convoro');

        Config::set('mail.default', $transport);

        if ($from !== '') {
            Config::set('mail.from.address', $from);
            Config::set('mail.from.name', $fromName);
        }

        if ($transport === 'smtp') {
            $encryption = Settings::get('mail.smtp_encryption', 'tls');
            Config::set('mail.mailers.smtp', array_merge(
                Config::get('mail.mailers.smtp', []),
                [
                    'transport' => 'smtp',
                    'host' => (string) Settings::get('mail.smtp_host', ''),
                    'port' => (int) Settings::get('mail.smtp_port', 587),
                    'username' => (string) Settings::get('mail.smtp_username', '') ?: null,
                    'password' => (string) Settings::get('mail.smtp_password', '') ?: null,
                    'encryption' => $encryption === 'none' ? null : $encryption,
                ],
            ));
        }
    }
}
