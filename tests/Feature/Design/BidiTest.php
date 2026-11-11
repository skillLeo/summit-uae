<?php

namespace Tests\Feature\Design;

use App\Design\D2\Bidi;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Design\D2\Bidi::isolate(): Latin runs inside Arabic copy are wrapped in <bdi dir="ltr">; the
 * approved text itself is printed unchanged (escaped); other locales get the escaped text only.
 */
final class BidiTest extends TestCase
{
    use RefreshDatabase;

    public function test_latin_runs_in_arabic_are_isolated(): void
    {
        $html = (string) Bidi::isolate('نزاعات USDT واحتيال المنصات، رقم المعاملة TxID واسم الشبكة', 'ar');

        $this->assertSame('نزاعات <bdi dir="ltr">USDT</bdi> واحتيال المنصات، رقم المعاملة <bdi dir="ltr">TxID</bdi> واسم الشبكة', $html);
    }

    public function test_multi_word_latin_runs_stay_together(): void
    {
        $this->assertSame('خدمات <bdi dir="ltr">Summit Legal</bdi> هنا', (string) Bidi::isolate('خدمات Summit Legal هنا', 'ar'));
    }

    public function test_text_is_escaped_and_unchanged_outside_arabic(): void
    {
        $this->assertSame('USDT &amp; &lt;b&gt;', (string) Bidi::isolate('USDT & <b>', 'en'));
        $this->assertSame('USDT &amp; &lt;b&gt;', (string) Bidi::isolate('USDT & <b>', 'ru'));
        $this->assertSame('نص &amp; <bdi dir="ltr">PDF</bdi>', (string) Bidi::isolate('نص & PDF', 'ar'));
    }

    public function test_empty_and_null_render_nothing(): void
    {
        $this->assertSame('', (string) Bidi::isolate(null, 'ar'));
        $this->assertSame('', (string) Bidi::isolate('', 'en'));
    }

    public function test_the_mega_menu_isolates_latin_codes_on_arabic_pages(): void
    {
        // Seed inside the test transaction (as DesignSwitchTest does); asset tags are irrelevant here.
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        $html = $this->withUnencryptedCookie('summit_design', '2')->get('/ar/')->assertOk()->getContent();

        $this->assertStringContainsString('<bdi dir="ltr">USDT</bdi>', $html);
        $this->assertStringContainsString('<bdi dir="ltr">TxID</bdi>', $html);
    }
}
