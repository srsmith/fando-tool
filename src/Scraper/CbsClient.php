<?php

declare(strict_types=1);

namespace Fando\Keeper\Scraper;

/**
 * Authenticated HTTP client for CBS Sportsline.
 *
 * CBS's login page is a JS single-page app protected by reCAPTCHA -- there's
 * no way for a plain HTTP client to complete that login itself (nor should
 * it try to defeat the CAPTCHA). Instead, a human logs into CBS normally in
 * their own browser and pastes the resulting session cookie into the admin
 * screen; this client just replays that cookie on every request. The cookie
 * will eventually expire and need refreshing the same way.
 */
final class CbsClient
{
    public function __construct(private readonly string $sessionCookie)
    {
    }

    public function fetch(string $url): string
    {
        [$body, $effectiveUrl] = $this->request($url);

        if ($this->looksLoggedOut($body, $effectiveUrl)) {
            throw new \RuntimeException(
                "CBS session cookie appears to be expired or invalid (landed on {$effectiveUrl}). "
                . 'Log into CBS in your browser again and paste a fresh session cookie on the admin screen.'
            );
        }

        return $body;
    }

    private function looksLoggedOut(string $html, string $effectiveUrl): bool
    {
        return stripos($effectiveUrl, 'login') !== false
            || stripos($html, 'type="password"') !== false;
    }

    /** @return array{0: string, 1: string} [body, effective URL after redirects] */
    private function request(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_COOKIE => $this->sessionCookie,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FandoKeeperTool/1.0)',
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("CBS request to {$url} failed: {$error}");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException("CBS request to {$url} returned HTTP {$status}");
        }

        return [$body, $effectiveUrl];
    }
}
