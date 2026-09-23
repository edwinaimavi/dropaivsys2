import test from 'node:test';
import assert from 'node:assert/strict';
import {
    currentDateOnlyInTimeZone,
    formatDateOnlyForDisplay,
    normalizeDateOnly,
} from '../../resources/js/utils/date-only.js';

test('la fecha DATE seleccionada se muestra y se envía sin desfase', () => {
    let issueDate = '2026-09-02';

    assert.equal(formatDateOnlyForDisplay(issueDate), '02/09/2026');
    assert.equal(normalizeDateOnly(issueDate), '2026-09-02');

    issueDate = '2026-09-03';

    assert.equal(formatDateOnlyForDisplay(issueDate), '03/09/2026');
    assert.equal(normalizeDateOnly(issueDate), '2026-09-03');
    assert.equal(formatDateOnlyForDisplay(normalizeDateOnly(issueDate)), '03/09/2026');
});

test('la fecha inicial respeta America Lima sin convertir el DATE desde UTC', () => {
    const instantAfterUtcMidnight = new Date('2026-09-03T02:30:00.000Z');

    assert.equal(currentDateOnlyInTimeZone(instantAfterUtcMidnight), '2026-09-02');
});
