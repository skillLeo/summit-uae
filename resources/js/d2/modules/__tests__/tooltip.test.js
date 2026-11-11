import { describe, expect, it } from 'vitest';
import { markCopyStatus } from '../tooltip.js';

// A data-tooltip bubble is created at the end of <body>, outside its trigger: the DOCX conformance check (brief §5.3)
// reads proposed copy as marked only under data-copy-status="proposed", so the bubble carries the trigger's mark.
describe('tooltip bubble: markCopyStatus', () => {
    it('copies the proposed mark of the trigger', () => {
        const bubble = { dataset: {} };
        markCopyStatus({ dataset: { copyStatus: 'proposed', tooltip: 'Search (/)' } }, bubble);
        expect(bubble.dataset.copyStatus).toBe('proposed');
    });

    it('leaves a bubble of approved or interface copy unmarked, and drops a stale mark', () => {
        const bubble = { dataset: {} };
        markCopyStatus({ dataset: { tooltip: 'Open today' } }, bubble);
        expect('copyStatus' in bubble.dataset).toBe(false);

        const stale = { dataset: { copyStatus: 'proposed' } };
        markCopyStatus({ dataset: {} }, stale);
        expect('copyStatus' in stale.dataset).toBe(false);
    });
});
