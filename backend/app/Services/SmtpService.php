<?php

namespace App\Services;

use App\Mail\SmtpTestMailable;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SmtpService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Return current SMTP settings with masked password.
     *
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $saved = SystemSetting::get('smtp', []);

        $host = $saved['host'] ?? config('mail.mailers.smtp.host') ?? '';
        $port = (int) ($saved['port'] ?? config('mail.mailers.smtp.port') ?? 587);
        $encryption = $saved['encryption'] ?? config('mail.mailers.smtp.encryption') ?? 'tls';
        $username = $saved['username'] ?? config('mail.mailers.smtp.username') ?? '';
        $hasPassword = ! empty($saved['password']) || ! empty(config('mail.mailers.smtp.password'));
        $fromEmail = $saved['from_email'] ?? config('mail.from.address') ?? '';
        $fromName = $saved['from_name'] ?? config('mail.from.name') ?? 'NCCAA HRMS';
        $replyTo = $saved['reply_to'] ?? '';

        return [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'has_password' => $hasPassword,
            'password_masked' => $hasPassword ? '••••••••••••' : '',
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to' => $replyTo,
        ];
    }

    /**
     * Save SMTP settings securely.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveSettings(User $actor, array $data): array
    {
        $existing = SystemSetting::get('smtp', []);

        $password = $data['password'] ?? null;
        if ($password === null || $password === '' || $password === '••••••••••••') {
            $password = $existing['password'] ?? config('mail.mailers.smtp.password') ?? '';
        }

        $payload = [
            'host' => $data['host'],
            'port' => (int) $data['port'],
            'encryption' => in_array($data['encryption'] ?? 'tls', ['tls', 'ssl', 'starttls', 'none'], true) ? $data['encryption'] : 'tls',
            'username' => $data['username'] ?? '',
            'password' => $password,
            'from_email' => $data['from_email'],
            'from_name' => $data['from_name'] ?? 'NCCAA HRMS',
            'reply_to' => $data['reply_to'] ?? null,
        ];

        SystemSetting::set('smtp', $payload);

        $this->auditLogger->record($actor, 'updated_smtp_settings', $actor, null, null, [
            'host' => $payload['host'],
            'port' => $payload['port'],
            'from_email' => $payload['from_email'],
        ]);

        $this->applyConfig();

        return $this->getSettings();
    }

    /**
     * Apply the SMTP configuration dynamically to Laravel mail config.
     */
    public function applyConfig(): void
    {
        $settings = SystemSetting::get('smtp', []);

        if (empty($settings['host'])) {
            return;
        }

        $encryption = $settings['encryption'] ?? 'tls';
        if ($encryption === 'none') {
            $encryption = null;
        }

        if (! app()->environment('testing')) {
            Config::set('mail.default', 'smtp');
        }

        Config::set('mail.mailers.smtp.host', $settings['host']);
        Config::set('mail.mailers.smtp.port', $settings['port'] ?? 587);
        Config::set('mail.mailers.smtp.encryption', $encryption);
        Config::set('mail.mailers.smtp.username', $settings['username'] ?? null);
        Config::set('mail.mailers.smtp.password', $settings['password'] ?? null);

        if (! empty($settings['from_email'])) {
            Config::set('mail.from.address', $settings['from_email']);
            Config::set('mail.from.name', $settings['from_name'] ?? 'NCCAA HRMS');
        }
    }

    /**
     * Test SMTP configuration by sending a verification email.
     *
     * @param  array<string, mixed>|null  $overrideSettings
     * @return array{success: bool, message: string}
     */
    public function testConnection(User $actor, string $testRecipient, ?array $overrideSettings = null): array
    {
        if ($overrideSettings) {
            $settings = $overrideSettings;
            if (empty($settings['password']) || $settings['password'] === '••••••••••••') {
                $saved = SystemSetting::get('smtp', []);
                $settings['password'] = $saved['password'] ?? config('mail.mailers.smtp.password');
            }
        } else {
            $settings = SystemSetting::get('smtp', []);
        }

        if (empty($settings['host'])) {
            throw ValidationException::withMessages(['host' => ['SMTP host is not configured.']]);
        }

        $this->applyConfig();

        try {
            $fromEmail = $settings['from_email'] ?? config('mail.from.address') ?? 'noreply@nccaa.local';
            $fromName = $settings['from_name'] ?? 'NCCAA HRMS Test';

            Mail::to($testRecipient)->send(new SmtpTestMailable($fromEmail, $fromName));

            $this->auditLogger->record($actor, 'smtp_test_sent', $actor, null, null, [
                'recipient' => $testRecipient,
                'status' => 'success',
            ]);

            return [
                'success' => true,
                'message' => "Test email sent successfully to {$testRecipient}.",
            ];
        } catch (\Throwable $e) {
            $errorMessage = $this->sanitizeErrorMessage($e->getMessage());

            $this->auditLogger->record($actor, 'smtp_test_failed', $actor, null, null, [
                'recipient' => $testRecipient,
                'error' => $errorMessage,
            ]);

            throw ValidationException::withMessages([
                'smtp' => ['SMTP Connection failed: '.$errorMessage],
            ]);
        }
    }

    private function sanitizeErrorMessage(string $raw): string
    {
        // Strip sensitive credentials or raw passwords from transport exception strings
        $sanitized = preg_replace('/password=[^\s&]+/i', 'password=••••', $raw) ?? $raw;
        $sanitized = preg_replace('/AUTH\s+(PLAIN|LOGIN)\s+[A-Za-z0-9=]+/i', 'AUTH [REDACTED]', $sanitized) ?? $sanitized;

        return Str::limit($sanitized, 300);
    }
}
