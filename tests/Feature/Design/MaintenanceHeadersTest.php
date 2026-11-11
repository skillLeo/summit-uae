<?php

namespace Tests\Feature\Design;

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Tests\TestCase;

/**
 * Maintenance mode's 503 (thrown by PreventRequestsDuringMaintenance) carries the same CSP and hardening
 * headers as every other response: SecurityHeaders runs ahead of the framework's global middleware
 * (bootstrap/app.php). Headers only: the page itself is unchanged, in both designs.
 */
final class MaintenanceHeadersTest extends TestCase
{
    private function fakeMaintenance(): void
    {
        $this->app->instance(MaintenanceMode::class, new class implements MaintenanceMode
        {
            public function activate(array $payload): void {}

            public function deactivate(): void {}

            public function active(): bool
            {
                return true;
            }

            public function data(): array
            {
                return ['retry' => 120];
            }
        });
    }

    public function test_the_maintenance_503_carries_the_security_headers_in_both_designs(): void
    {
        $this->fakeMaintenance();

        foreach (['1', '2'] as $design) {
            foreach (['en', 'ar', 'ru'] as $locale) {
                $response = $this->withUnencryptedCookies(['summit_design' => $design])->get("/{$locale}/");
                $response->assertStatus(503)->assertHeader('Retry-After', '120');
                $label = "design {$design} {$locale}";
                $csp = (string) $response->headers->get('Content-Security-Policy');
                $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $csp, $label);
                $this->assertStringContainsString("frame-ancestors 'none'", $csp, $label);
                $this->assertSame('DENY', $response->headers->get('X-Frame-Options'), $label);
                $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'), $label);
                $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'), $label);
                $this->assertNotNull($response->headers->get('Permissions-Policy'), $label);
                $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'), $label);
            }
        }
    }
}
