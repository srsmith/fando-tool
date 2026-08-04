<?php

declare(strict_types=1);

namespace Fando\Keeper\Scraper;

/**
 * Authenticated HTTP client for CBS Sportsline.
 *
 * Login is done generically: fetch the login page, find its <form>, submit
 * every hidden field unchanged (to preserve CSRF tokens etc.) plus the
 * username/password into whatever inputs look like the credential fields.
 * This avoids hardcoding CBS's exact field names, which we can't verify from
 * this environment (cbssports.com is blocked by the sandbox's egress
 * policy -- see README-scraper.md). If CBS's form doesn't auto-detect
 * cleanly, override the field names via $usernameField/$passwordField.
 */
final class CbsClient
{
    private string $cookieJar;
    private bool $loggedIn = false;

    public function __construct(
        private readonly string $loginUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly ?string $usernameField = null,
        private readonly ?string $passwordField = null,
    ) {
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'cbs_cookies_');
    }

    public function __destruct()
    {
        if (is_file($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    public function login(): void
    {
        $loginPageHtml = $this->request('GET', $this->loginUrl);

        $form = $this->extractLoginForm($loginPageHtml, $this->loginUrl);

        $fields = $form['fields'];
        $userField = $this->usernameField ?? $this->guessField($fields, ['user', 'email', 'login']);
        $passField = $this->passwordField ?? $this->guessField($fields, ['pass']);

        if ($userField === null || $passField === null) {
            throw new \RuntimeException(
                'Could not auto-detect CBS login form fields. Inspect the captured '
                . 'login page HTML and set usernameField/passwordField explicitly.'
            );
        }

        $fields[$userField] = $this->username;
        $fields[$passField] = $this->password;

        $response = $this->request('POST', $form['action'], $fields);

        // CBS should redirect us on success; a page that still contains a
        // password field means login failed (wrong creds, or our field
        // guesses were wrong).
        if (stripos($response, 'type="password"') !== false) {
            throw new \RuntimeException('CBS login appears to have failed -- response still shows a login form.');
        }

        $this->loggedIn = true;
    }

    public function fetch(string $url): string
    {
        if (!$this->loggedIn) {
            $this->login();
        }

        return $this->request('GET', $url);
    }

    /**
     * @return array{action: string, fields: array<string,string>}
     */
    private function extractLoginForm(string $html, string $pageUrl): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($doc);
        $forms = $xpath->query('//form[.//input[@type="password"]]');

        if ($forms === false || $forms->length === 0) {
            throw new \RuntimeException(
                'No login form (a <form> containing a password input) found on the login page. '
                . 'Capture the real page HTML with scripts/capture_pages.php and update CbsClient.'
            );
        }

        /** @var \DOMElement $form */
        $form = $forms->item(0);
        $actionAttr = $form->getAttribute('action');
        $action = $actionAttr === '' ? $pageUrl : $this->resolveUrl($pageUrl, $actionAttr);

        $fields = [];
        foreach ($xpath->query('.//input', $form) as $input) {
            /** @var \DOMElement $input */
            $type = strtolower($input->getAttribute('type') ?: 'text');
            $name = $input->getAttribute('name');
            if ($name === '' || $type === 'submit' || $type === 'button') {
                continue;
            }
            $fields[$name] = $input->getAttribute('value');
        }

        return ['action' => $action, 'fields' => $fields];
    }

    /** @param array<string,string> $fields */
    private function guessField(array $fields, array $needles): ?string
    {
        foreach (array_keys($fields) as $name) {
            foreach ($needles as $needle) {
                if (stripos($name, $needle) !== false) {
                    return $name;
                }
            }
        }
        return null;
    }

    private function resolveUrl(string $baseUrl, string $maybeRelative): string
    {
        if (parse_url($maybeRelative, PHP_URL_SCHEME) !== null) {
            return $maybeRelative;
        }
        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        if (str_starts_with($maybeRelative, '/')) {
            return "{$scheme}://{$host}{$maybeRelative}";
        }
        $basePath = rtrim(dirname($base['path'] ?? '/'), '/');
        return "{$scheme}://{$host}{$basePath}/{$maybeRelative}";
    }

    /** @param array<string,string>|null $postFields */
    private function request(string $method, string $url, ?array $postFields = null): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FandoKeeperTool/1.0)',
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields ?? []));
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("CBS request to {$url} failed: {$error}");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException("CBS request to {$url} returned HTTP {$status}");
        }

        return $body;
    }
}
