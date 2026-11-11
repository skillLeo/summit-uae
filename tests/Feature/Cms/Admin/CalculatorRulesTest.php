<?php

namespace Tests\Feature\Cms\Admin;

use App\Content\Cms\Calculators\GratuityRules;
use App\Content\Cms\Calculators\Money;
use App\Content\Cms\Calculators\RentRules;
use App\Content\Cms\Sources;
use App\Filament\Resources\Calculators\CalculatorResource;
use Tests\TestCase;

/**
 * The admin's PHP formula of the calculators (App\Content\Cms\Calculators, used by the test panel of the Calculators
 * screen) gives exactly what the public scripts give (resources/js/d2/tools/gratuity.js and rent.js) with rule set v1:
 * the fixture holds the scripts' own output for the cases of their Vitest suites and 200 seeded random cases each
 * (regenerate it with `node tests/Feature/Cms/Admin/fixtures/calculators.mjs` after changing a script).
 */
final class CalculatorRulesTest extends TestCase
{
    /** @return array<string, array<string, mixed>> calculator => rule set v1, as the importer reads it from the scripts */
    private function v1(): array
    {
        $out = [];
        foreach ((new Sources)->build()['calculator_rule_sets'] as $row) {
            $this->assertSame(1, $row['version']);
            $out[$row['calculator']] = $row['rules'];
        }

        return $out;
    }

    public function test_the_php_formula_with_rule_set_v1_equals_the_public_scripts(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__.'/fixtures/calculators.json'), true, 64, JSON_THROW_ON_ERROR);
        $v1 = $this->v1();
        $this->assertSame(GratuityRules::DEFAULTS, $v1['gratuity'], 'the PHP defaults are rule set v1');
        $this->assertSame(RentRules::DEFAULTS, $v1['rent']);

        foreach (['gratuity' => new GratuityRules($v1['gratuity']), 'rent' => new RentRules($v1['rent'])] as $calculator => $rules) {
            $this->assertGreaterThan(200, count($fixture[$calculator]));
            foreach ($fixture[$calculator] as $i => $case) {
                $estimate = $rules->estimate($case['input']);
                $php = ['status' => $estimate['status'], 'errors' => $estimate['errors'], 'missing' => $estimate['missing'], 'result' => $estimate['result']];
                // Compare as JSON: the same doubles print the same shortest digits in PHP and JavaScript.
                $this->assertSame(json_encode($case['output']), json_encode($php), "{$calculator} case {$i}: ".json_encode($case['input']));
            }
        }
    }

    public function test_the_spec_cases(): void
    {
        $gratuity = new GratuityRules;
        $five = $gratuity->estimate(['scope' => 'mainland', 'startDate' => '2020-01-01', 'lastWorkingDay' => '2024-12-29', 'basicMonthly' => 10000])['result'];
        $this->assertSame(1825, $five['calendarDays']);
        $this->assertEquals(35000, $five['gratuity']);
        $leap = $gratuity->estimate(['scope' => 'mainland', 'startDate' => '2020-01-01', 'lastWorkingDay' => '2024-12-31', 'basicMonthly' => 10000])['result'];
        $this->assertSame(54.79, $leap['part30']);
        $this->assertSame(35054.79, $leap['gratuity']);
        $this->assertSame('out_of_scope', $gratuity->estimate(['scope' => 'difc'])['status']);

        $rent = new RentRules;
        $this->assertSame(0, $rent->bandIndex(90000, 100000), 'exactly 10 % below: no increase');
        $this->assertSame(1, $rent->bandIndex(89999.99, 100000));
        $this->assertSame(2, $rent->bandIndex(700.7, 1001), 'exactly 30 % in fils');
        $this->assertSame(2234, $rent->gapBasisPoints(116500, 150000));
        $this->assertSame(['days' => 90, 'ok' => true, 'after' => false], $rent->noticeCheck('2026-12-31', '2027-03-31'));

        $this->assertSame(1.01, Money::round2(1.005));
        $this->assertSame(2.68, Money::round2(2.675));
        $this->assertSame(0.0, Money::round2(-0.0));
    }

    public function test_changed_rules_change_the_result(): void
    {
        $case = ['scope' => 'mainland', 'startDate' => '2015-01-01', 'lastWorkingDay' => '2021-12-29', 'basicMonthly' => 10000];
        $this->assertEquals(55000, (new GratuityRules)->estimate($case)['result']['gratuity'], '7 years: 35,000 + 20,000');
        $this->assertEquals(60000, (new GratuityRules(['days_first_years' => 24] + GratuityRules::DEFAULTS))->estimate($case)['result']['gratuity'], '5 × 24 days + 2 × 30 days');
        $bands = RentRules::DEFAULTS['bands'];
        $bands[1]['increase'] = 7;
        $this->assertSame(7, (new RentRules(['bands' => $bands] + RentRules::DEFAULTS))->estimate(['currentAnnualRent' => 85000, 'indexAverageRent' => 100000])['result']['increase']);

        $this->assertNull(CalculatorResource::bandsError(RentRules::DEFAULTS['bands']));
        $this->assertSame('bands_last_open', CalculatorResource::bandsError([['upTo' => 10, 'increase' => 0]]));
        $this->assertSame('bands_order', CalculatorResource::bandsError([['upTo' => 20, 'increase' => 0], ['upTo' => 10, 'increase' => 5], ['upTo' => null, 'increase' => 9]]));
        $this->assertSame(RentRules::DEFAULTS, CalculatorResource::rulesFromForm(RentRules::DEFAULTS, [
            'notice_days' => '90',
            'bands' => array_map(fn ($b) => ['upTo' => $b['upTo'] === null ? null : (string) $b['upTo'], 'increase' => (string) $b['increase']], RentRules::DEFAULTS['bands']),
        ]), 'an unchanged form gives back the stored JSON');
    }
}
