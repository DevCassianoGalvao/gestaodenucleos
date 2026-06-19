<?php

class Brevo
{
    private const API_URL = 'https://api.brevo.com/v3/smtp/email';

    /**
     * Send a transactional email via Brevo API.
     *
     * $to = [['email' => '...', 'name' => '...'], ...]
     * Returns true on success, false on error (never throws).
     */
    public static function send(
        array  $to,
        string $subject,
        string $html,
        string $text = ''
    ): bool {
        if (empty(BREVO_API_KEY) || BREVO_API_KEY === 'your_brevo_api_key_here') {
            error_log('[Brevo] API key not configured — email not sent: ' . $subject);
            return false;
        }

        $payload = [
            'sender'      => ['name' => APP_NAME, 'email' => BREVO_FROM_EMAIL],
            'to'          => $to,
            'subject'     => $subject,
            'htmlContent' => $html,
        ];
        if ($text !== '') {
            $payload['textContent'] = $text;
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'api-key: ' . BREVO_API_KEY,
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('[Brevo] cURL error: ' . $error);
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[Brevo] HTTP ' . $httpCode . ' — ' . $response);
            return false;
        }

        return true;
    }
}
