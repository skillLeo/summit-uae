<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Portraits;
use App\Models\Content\TeamMember;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2.1 D42 / D43: the team portraits' shared eye line and the "photo pending" flag
 * (App\Design\D2\Portraits: the CMS columns team_members.focal_point and .photo_pending, imported from
 * config('design.d2.team.portraits'); the eye line stays config('design.d2.team.eye_line'); the admin sets the values
 * per photo in Phase 4).
 */
final class PortraitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_geometry_moves_the_focal_point_onto_the_eye_line_and_always_covers_the_frame(): void
    {
        $eye = 0.35;
        foreach ([[0.497, 0.332], [0.495, 0.343], [0.5, 0.35], [0.3, 0.6], [0.7, 0.2], [0.52, 0.41]] as [$fx, $fy]) {
            $g = Portraits::geometry($fx, $fy, $eye);
            $this->assertGreaterThanOrEqual(1.0, $g['scale'], 'never shrinks below the frame');
            foreach (['x', 'y'] as $axis) {
                $this->assertGreaterThanOrEqual(0.0, $g[$axis]);
                $this->assertLessThanOrEqual(1.0, $g[$axis], 'the origin inside the photo: no frame edge is uncovered');
            }
            // A point P goes to O + s·(P − O): the focal point lands on (0.5, eye).
            $this->assertEqualsWithDelta(0.5, $g['x'] + $g['scale'] * ($fx - $g['x']), 1e-9, "x of ({$fx}, {$fy})");
            $this->assertEqualsWithDelta($eye, $g['y'] + $g['scale'] * ($fy - $g['y']), 1e-9, "y of ({$fx}, {$fy})");
            // The frame's edges stay covered: the photo spans at least [0, 1] on both axes.
            foreach (['x', 'y'] as $axis) {
                $this->assertLessThanOrEqual(1e-9, $g[$axis] * (1 - $g['scale']));
                $this->assertGreaterThanOrEqual(1 - 1e-9, $g[$axis] * (1 - $g['scale']) + $g['scale']);
            }
        }
        $this->assertSame(['scale' => 1.0, 'x' => 0.5, 'y' => 0.35], Portraits::geometry(0.5, 0.35, 0.35), 'already on the line: untouched');
    }

    public function test_the_page_rules_follow_the_config(): void
    {
        $this->seed(ContentSeeder::class);
        $css = Portraits::css();
        $this->assertMatchesRegularExpression('/\.portrait\[data-member="team-ahmed"\]\{--portrait-x:49\.7%;--portrait-y:33\.2%;--portrait-scale:[0-9.]+;--portrait-ox:[0-9.]+%;--portrait-oy:[0-9.]+%\}/', $css);
        $this->assertStringContainsString('.portrait[data-member="team-mohamed"]', $css);
        $this->assertStringNotContainsString('team-sarah', $css, 'no focal point while the photo is pending');
        $this->assertStringNotContainsString('@layer', $css, 'never reorders the stylesheet layers');
        $this->assertTrue(Portraits::pending('team-sarah'));
        $this->assertFalse(Portraits::pending('team-ahmed'));

        // The CMS values win over the config; invalid ones are ignored like before.
        config(['design.d2.team.eye_line' => 40]);
        TeamMember::query()->findOrFail('team-ahmed')->update(['focal_point' => [50, 40]]);
        TeamMember::query()->findOrFail('team-mohamed')->update(['focal_point' => null]);
        foreach (['team-bad' => ['x', 1], 'team-edge' => [0, 50], 'Not A Code"}' => [50, 30]] as $code => $focus) {
            TeamMember::query()->create(['code' => $code, 'anchor' => 'qa', 'position' => 9, 'name' => ['en' => 'QA'], 'focal_point' => $focus]);
        }
        $this->assertSame('.portrait[data-member="team-ahmed"]{--portrait-x:50%;--portrait-y:40%;--portrait-scale:1;--portrait-ox:50%;--portrait-oy:40%}', Portraits::css(), 'invalid codes and focal points are ignored');
        $this->assertNull(Portraits::settings('team-edge')['focus']);
        $this->assertFalse(Portraits::pending('team-unknown'));
    }

    public function test_every_member_has_a_portrait_entry(): void
    {
        $this->seed(ContentSeeder::class);
        foreach (content()->teamMembers() as $member) {
            $this->assertIsArray(config('design.d2.team.portraits.'.$member['code']), $member['code'].': an import default');
            $entry = content()->portraits()[$member['code']] ?? null;
            $this->assertIsArray($entry, $member['code'].': a CMS portrait entry');
            $hasFile = is_string($member['portrait'] ?? null);
            $this->assertSame(! $hasFile, $entry['pending'], $member['code'].': photo_pending matches the supplied files');
            if ($hasFile) {
                $this->assertNotNull(Portraits::settings($member['code'])['focus'], $member['code'].': a focal point');
            }
        }
    }
}
