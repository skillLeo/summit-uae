/**
 * Writes calculators.json: the public estimators (resources/js/d2/tools/gratuity.js and rent.js, rule set v1) run on
 * the cases of their Vitest suites plus 200 seeded random cases each, with their status, errors, missing fields and
 * result. tests/Feature/Cms/Admin/CalculatorRulesTest.php checks that the admin's PHP formula
 * (App\Content\Cms\Calculators) gives exactly the same output with rule set v1.
 *
 *   node tests/Feature/Cms/Admin/fixtures/calculators.mjs
 */
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as gratuity from '../../../../../resources/js/d2/tools/gratuity.js';
import * as rent from '../../../../../resources/js/d2/tools/rent.js';

const here = dirname(fileURLToPath(import.meta.url));

let seed = 20261112;
const rand = () => {
    seed = (seed * 16807) % 2147483647;
    return seed / 2147483647;
};
const pick = (list) => list[Math.floor(rand() * list.length)];
const money = (max) => Math.round((rand() * max) * 100) / 100;
function addDays(iso, n) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d + n)).toISOString().slice(0, 10);
}
const service = (days, extra = {}) => ({ scope: 'mainland', startDate: '2015-01-01', lastWorkingDay: addDays('2015-01-01', days - 1), basicMonthly: 10000, ...extra });

const gratuityCases = [
    service(Math.round(0.9 * 365)),
    { scope: 'mainland', startDate: '2020-01-01', lastWorkingDay: '2024-12-29', basicMonthly: 10000 },
    service(7 * 365),
    service(7 * 365, { unpaidAbsenceDays: 365 }),
    service(380, { unpaidAbsenceDays: 20 }),
    service(7 * 365, { contractType: 'part', hoursPerWeek: 24, fullTimeHoursPerWeek: 48 }),
    service(40 * 365),
    service(40 * 365, { totalMonthlyWage: 15000 }),
    service(40 * 365, { totalMonthlyWage: 20000 }),
    service(364),
    service(365),
    service(10 * 365, { contractType: 'temporary', unusedLeaveDays: 3 }),
    service(365 + 73),
    service(2 * 365, { unusedLeaveDays: 15, totalMonthlyWage: 15000 }),
    service(2 * 365, { noticeDaysInContract: 30, noticeDaysServed: 10, totalMonthlyWage: 12000 }),
    service(2 * 365, { noticeDaysInContract: 30, noticeDaysServed: 10 }),
    service(2 * 365, { noticeDaysInContract: 30, noticeDaysServed: 45 }),
    service(2 * 365, { noticeDaysServed: 5 }),
    service(7 * 365, { unusedLeaveDays: 10, noticeDaysInContract: 60, noticeDaysServed: 0, totalMonthlyWage: 15000, unpaidWages: 2500.5 }),
    { scope: 'mainland', startDate: '2020-01-01', lastWorkingDay: '2024-12-31', basicMonthly: 10000 },
    service(400, { unusedLeaveDays: 1 }),
    service(400, { unusedLeaveDays: 2 }),
    service(400, { unusedLeaveDays: 3 }),
    { startDate: '2026-05-02', lastWorkingDay: '2026-05-01', basicMonthly: 9000 },
    { startDate: '2026-02-30' },
    { startDate: '0002-01-01' },
    { lastWorkingDay: '26-01-01' },
    { ...service(800), unpaidAbsenceDays: -1, unusedLeaveDays: -2, unpaidWages: -5, noticeDaysServed: -1, basicMonthly: 'x' },
    { ...service(800), basicMonthly: 'ten' },
    { ...service(800), basicMonthly: 0 },
    { ...service(800), basicMonthly: 2e9 },
    { ...service(800), noticeDaysInContract: 29 },
    { ...service(800), noticeDaysInContract: 91 },
    { ...service(800), noticeDaysInContract: 30 },
    { ...service(800), noticeDaysInContract: 90 },
    { ...service(800), contractType: 'part', hoursPerWeek: null, fullTimeHoursPerWeek: 48 },
    { ...service(800), contractType: 'part', hoursPerWeek: 0, fullTimeHoursPerWeek: 48 },
    { ...service(800), contractType: 'part', hoursPerWeek: 20, fullTimeHoursPerWeek: 200 },
    { ...service(800), contractType: 'part', hoursPerWeek: 50, fullTimeHoursPerWeek: 48 },
    { ...service(800), contractType: 'part', hoursPerWeek: 48, fullTimeHoursPerWeek: 48 },
    service(800, { unpaidAbsenceDays: 2.5 }),
    service(800, { unusedLeaveDays: 1.5 }),
    service(100, { unpaidAbsenceDays: 100 }),
    service(100, { unpaidAbsenceDays: 99 }),
    service(800, { totalMonthlyWage: 9000 }),
    service(800, { totalMonthlyWage: 0 }),
    service(800, { totalMonthlyWage: 10000 }),
    {},
    { startDate: '', lastWorkingDay: '  ', basicMonthly: '' },
    ...gratuity.SCOPES.map((scope) => service(800, { scope })),
    { ...service(800), scope: 'mars', contractType: 'nope' },
];
for (let i = 0; i < 200; i += 1) {
    gratuityCases.push(service(1 + Math.floor(rand() * 15000), {
        scope: rand() > 0.9 ? pick(gratuity.SCOPES) : 'mainland',
        contractType: pick(['full', 'full', 'full', 'part', 'temporary']),
        hoursPerWeek: 10 + Math.floor(rand() * 30),
        fullTimeHoursPerWeek: 40 + Math.floor(rand() * 8),
        basicMonthly: money(60000) + 1000,
        totalMonthlyWage: rand() > 0.5 ? money(20000) + 61000 : null,
        unusedLeaveDays: rand() > 0.5 ? Math.floor(rand() * 60) : money(10),
        noticeDaysInContract: rand() > 0.3 ? 30 + Math.floor(rand() * 61) : null,
        noticeDaysServed: Math.floor(rand() * 40),
        unpaidAbsenceDays: rand() > 0.7 ? Math.floor(rand() * 200) : null,
        unpaidWages: rand() > 0.5 ? money(9999.99) : '',
    }));
}

const rentCases = [
    [90000, 100000], [63000, 70000], [89999.99, 100000], [89999, 100000], [80000, 100000], [68000, 85000],
    [79999.99, 100000], [70000, 100000], [69999.99, 100000], [60000, 100000], [51000, 85000], [59999.99, 100000],
    [10000, 100000], [100000, 100000], [120000, 100000], [700.7, 1001], [900.9, 1001], [77500, 100000],
    [116500, 150000], [80000, 103000], [75555.55, 90000],
].map(([currentAnnualRent, indexAverageRent]) => ({ currentAnnualRent, indexAverageRent }));
rentCases.push(
    { currentAnnualRent: 90000, indexAverageRent: 100000, expiryDate: '2027-03-31', noticeReceivedDate: '2026-12-31' },
    { currentAnnualRent: 90000, indexAverageRent: 100000, expiryDate: '2027-03-31', noticeReceivedDate: '2026-12-30' },
    { currentAnnualRent: 90000, indexAverageRent: 100000, expiryDate: '2027-03-31', noticeReceivedDate: '2027-01-01' },
    { currentAnnualRent: 90000, indexAverageRent: 100000, expiryDate: '2027-03-31', noticeReceivedDate: '2027-03-31' },
    { currentAnnualRent: 90000, indexAverageRent: 100000, expiryDate: '2027-03-31', noticeReceivedDate: '2027-04-02' },
    { currentAnnualRent: 1, indexAverageRent: 2, expiryDate: '2027-03-31' },
    { currentAnnualRent: 1, indexAverageRent: 2, expiryDate: '2027-03-31', noticeReceivedDate: '2027-02-30' },
    {},
    { currentAnnualRent: -1, indexAverageRent: 'x' },
    { currentAnnualRent: 1000, indexAverageRent: 0 },
    { currentAnnualRent: 5e9, indexAverageRent: 1 },
    { currentAnnualRent: 90000, indexAverageRent: 100000, expiryDate: '2027-13-01' },
);
for (let i = 0; i < 200; i += 1) {
    const average = money(400000) + 5000;
    rentCases.push({
        currentAnnualRent: rand() > 0.2 ? Math.round(average * (0.4 + rand() * 0.7) * 100) / 100 : Math.round(average * (0.9 - pick([0, 0.1, 0.2, 0.3])) * 100) / 100,
        indexAverageRent: average,
        expiryDate: rand() > 0.5 ? addDays('2027-01-01', Math.floor(rand() * 365)) : '',
        noticeReceivedDate: addDays('2026-09-01', Math.floor(rand() * 200)),
    });
}

const out = (estimate) => ({ status: estimate.status, errors: estimate.errors, missing: estimate.missing, result: estimate.result });
const fixture = {
    generated_by: 'tests/Feature/Cms/Admin/fixtures/calculators.mjs',
    gratuity: gratuityCases.map((input) => ({ input, output: out(gratuity.estimate(input)) })),
    rent: rentCases.map((input) => ({ input, output: out(rent.estimate(input)) })),
};
const lines = (cases) => cases.map((c) => `  ${JSON.stringify(c)}`).join(',\n');
writeFileSync(join(here, 'calculators.json'), `{"generated_by": ${JSON.stringify(fixture.generated_by)},\n"gratuity": [\n${lines(fixture.gratuity)}\n],\n"rent": [\n${lines(fixture.rent)}\n]}\n`);
console.log(`calculators.json: ${fixture.gratuity.length} gratuity and ${fixture.rent.length} rent cases`);
