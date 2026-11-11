<?php

namespace Tests\Feature\Content;

use App\Content\Import\Mappers\HomepageMapper;

final class HomepageMapperTest extends MapperTestCase
{
    private function imported(): array
    {
        return $this->import([new HomepageMapper]);
    }

    public function test_every_public_block_is_mapped_in_all_languages(): void
    {
        $this->assertFullCoverage($this->imported());
    }

    public function test_no_designer_note_reaches_public_output(): void
    {
        $this->assertNoDesignerNotes($this->imported());
    }

    public function test_languages_have_identical_structure(): void
    {
        $this->assertNoParityIssues($this->imported());
    }

    public function test_hero_uses_the_approved_h1_in_each_language(): void
    {
        $h1 = $this->imported()['datasets']['pages']['P01']['h1'];
        $this->assertSame('Clear Legal Guidance. Practical Solutions.', $h1['en']);
        $this->assertSame('توجيه قانوني واضح. حلول عملية.', $h1['ar']);
        $this->assertSame('Чёткие правовые рекомендации. Практические решения.', $h1['ru']);
    }

    public function test_eleven_services_nine_guides_and_six_specialist_sites(): void
    {
        $d = $this->imported()['datasets'];
        $this->assertSame(['S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'], array_keys($d['services']));
        $this->assertCount(9, $d['guides']);
        $this->assertCount(6, $d['specialist_sites']);
        $this->assertSame('laborlawuae.com', $d['specialist_sites']['X01']['domain']);
        $this->assertSame('Employment Law', $d['specialist_sites']['X01']['label']['en']);
    }

    public function test_whatsapp_draft_matches_the_encoded_link_in_each_language(): void
    {
        $s = $this->imported()['datasets']['settings'];
        foreach (['en', 'ar', 'ru'] as $locale) {
            parse_str((string) parse_url($s['whatsapp_url'][$locale], PHP_URL_QUERY), $q);
            $this->assertSame($s['whatsapp_draft'][$locale], $q['text'], $locale);
            $this->assertStringStartsWith('https://wa.me/971524666191', $s['whatsapp_url'][$locale]);
        }
        $this->assertSame('tel:+971524666191', $s['phone_href']['en']);
    }

    public function test_review_section_keeps_five_questions_and_four_steps(): void
    {
        $review = $this->imported()['datasets']['pages']['P01']['content']['en']['review'];
        $this->assertCount(5, $review['questions']);
        $this->assertSame('Understand What Happened', $review['steps'][0]['title']);
        $this->assertSame('Agree on the Next Step', $review['steps'][3]['title']);
    }
}
