<?php

namespace Antlion\SilverwindSite\Middleware;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;

/**
 * Security headers for front-end responses (not the CMS, /dev or /Security,
 * which need looser policies).
 *
 * The Content-Security-Policy starts in report-only mode: browsers report
 * violations in the console (and to csp_report_uri if set) without
 * blocking anything. Once a site has run clean for a while, set
 * csp_enforce: true. Everything is configurable:
 *
 *   Antlion\SilverwindSite\Middleware\SecurityHeadersMiddleware:
 *     csp_enforce: true
 *     csp:
 *       script-src: ["'self'", "'unsafe-eval'", 'https://www.googletagmanager.com']
 */
class SecurityHeadersMiddleware implements HTTPMiddleware
{
    use Configurable;

    /** URL prefixes left alone (the CMS needs eval, inline scripts, frames) */
    private static array $excluded_paths = ['admin', 'dev', 'Security', 'graphql'];

    private static array $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()',
        'X-Frame-Options' => 'SAMEORIGIN',
    ];

    /** Sent over HTTPS only; browsers ignore it on HTTP */
    private static string $hsts = 'max-age=31536000; includeSubDomains';

    private static bool $csp_enforce = false;

    private static ?string $csp_report_uri = null;

    /**
     * Directive => sources. Alpine.js evaluates its expressions, hence
     * 'unsafe-eval'; templates use inline style attributes (backgrounds,
     * object-position), hence 'unsafe-inline' for styles. Google entries
     * cover reCAPTCHA, Maps embeds and Analytics/Tag Manager.
     */
    private static array $csp = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-eval'", 'https://www.google.com', 'https://www.gstatic.com', 'https://www.googletagmanager.com'],
        'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
        'img-src' => ["'self'", 'data:', 'https:'],
        'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
        'connect-src' => ["'self'", 'https://www.google.com', 'https://*.google-analytics.com', 'https://*.googletagmanager.com'],
        'frame-src' => ["'self'", 'https://www.google.com', 'https://www.youtube-nocookie.com', 'https://www.youtube.com', 'https://player.vimeo.com'],
        'frame-ancestors' => ["'self'"],
        'form-action' => ["'self'"],
        'base-uri' => ["'self'"],
        'object-src' => ["'none'"],
    ];

    public function process(HTTPRequest $request, callable $delegate)
    {
        /** @var HTTPResponse $response */
        $response = $delegate($request);

        if (!$response || $this->isExcluded($request)) {
            return $response;
        }

        foreach (static::config()->get('headers') as $name => $value) {
            if ($value && !$response->getHeader($name)) {
                $response->addHeader($name, $value);
            }
        }

        if (Director::is_https($request) && ($hsts = static::config()->get('hsts'))) {
            $response->addHeader('Strict-Transport-Security', $hsts);
        }

        if ($policy = $this->contentSecurityPolicy()) {
            $header = static::config()->get('csp_enforce')
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';
            $response->addHeader($header, $policy);
        }

        return $response;
    }

    public function contentSecurityPolicy(): string
    {
        $directives = [];
        foreach ((array) static::config()->get('csp') as $directive => $sources) {
            $sources = array_filter((array) $sources);
            if ($sources) {
                $directives[] = $directive . ' ' . implode(' ', array_unique($sources));
            }
        }
        if ($uri = static::config()->get('csp_report_uri')) {
            $directives[] = 'report-uri ' . $uri;
        }

        return implode('; ', $directives);
    }

    private function isExcluded(HTTPRequest $request): bool
    {
        $first = strtok(trim($request->getURL(), '/'), '/');

        return in_array($first, (array) static::config()->get('excluded_paths'), true);
    }
}
