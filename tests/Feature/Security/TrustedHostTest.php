<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

class TrustedHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_trusted_host_is_the_exact_app_url_host(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        $this->assertSame(['^'.preg_quote($host, '/').'$'], config('app.trusted_hosts'));
    }

    public function test_trusted_proxy_allowlist_never_uses_a_wildcard(): void
    {
        $this->assertIsArray(config('app.trusted_proxies'));
        $this->assertNotContains('*', config('app.trusted_proxies'));
        $this->assertNotContains('**', config('app.trusted_proxies'));
    }

    public function test_untrusted_host_headers_are_rejected_by_the_configured_allowlist(): void
    {
        Request::setTrustedHosts(config('app.trusted_hosts'));

        try {
            $trustedHost = parse_url(config('app.url'), PHP_URL_HOST);
            $this->assertSame($trustedHost, Request::create('https://'.$trustedHost.'/')->getHost());

            $this->expectException(SuspiciousOperationException::class);
            Request::create('https://attacker.invalid/')->getHost();
        } finally {
            Request::setTrustedHosts([]);
        }
    }
}
