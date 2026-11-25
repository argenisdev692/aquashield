import { describe, expect, it } from 'vitest';
import {
    availabilityExceptionSchema,
    availabilityRuleSchema,
    bulkClosureSchema,
    wireWindow,
    type AvailabilityExceptionFormValues,
} from './availability';

const openDay: AvailabilityExceptionFormValues = {
    date: '2026-12-24',
    is_available: true,
    start_time: '08:00',
    end_time: '12:00',
    reason: 'Christmas Eve',
};

describe('availabilityExceptionSchema', () => {
    it('accepts an open day with a valid window', () => {
        expect(availabilityExceptionSchema.safeParse(openDay).success).toBe(true);
    });

    it('accepts a closed day without a window', () => {
        expect(
            availabilityExceptionSchema.safeParse({
                ...openDay,
                is_available: false,
                start_time: '',
                end_time: '',
            }).success,
        ).toBe(true);
    });

    it('rejects an end at or before the start', () => {
        const result = availabilityExceptionSchema.safeParse({ ...openDay, end_time: '08:00' });

        expect(result.success).toBe(false);

        if (!result.success) {
            expect(result.error.issues[0]?.path).toEqual(['end_time']);
        }
    });

    it('rejects a 24-hour clock that is not HH:MM', () => {
        const result = availabilityExceptionSchema.safeParse({ ...openDay, start_time: '8:00' });

        expect(result.success).toBe(false);

        if (!result.success) {
            expect(result.error.issues[0]?.path).toEqual(['start_time']);
        }
    });

    it('rejects a malformed date and an over-long reason', () => {
        expect(
            availabilityExceptionSchema.safeParse({ ...openDay, date: '24/12/2026' }).success,
        ).toBe(false);
        expect(
            availabilityExceptionSchema.safeParse({ ...openDay, reason: 'x'.repeat(256) }).success,
        ).toBe(false);
    });
});

describe('bulkClosureSchema', () => {
    it('rejects a range that ends before it starts', () => {
        const result = bulkClosureSchema.safeParse({
            from: '2026-05-10',
            to: '2026-05-09',
            reason: '',
        });

        expect(result.success).toBe(false);

        if (!result.success) {
            expect(result.error.issues[0]?.path).toEqual(['to']);
        }
    });

    it('accepts a single-day range', () => {
        expect(
            bulkClosureSchema.safeParse({ from: '2026-05-10', to: '2026-05-10', reason: '' })
                .success,
        ).toBe(true);
    });
});

describe('availabilityRuleSchema', () => {
    it('rejects an inverted weekly window', () => {
        const result = availabilityRuleSchema.safeParse({
            day_of_week: '1',
            start_time: '17:00',
            end_time: '09:00',
        });

        expect(result.success).toBe(false);
    });

    it('rejects a weekday outside the 0-6 range', () => {
        expect(
            availabilityRuleSchema.safeParse({
                day_of_week: '7',
                start_time: '08:00',
                end_time: '17:00',
            }).success,
        ).toBe(false);
    });
});

describe('wireWindow', () => {
    it('keeps the window for an open day', () => {
        expect(wireWindow(openDay)).toEqual({ start_time: '08:00', end_time: '12:00' });
    });

    it('sends null times for a closed day', () => {
        expect(wireWindow({ ...openDay, is_available: false })).toEqual({
            start_time: null,
            end_time: null,
        });
    });

    it('sends null times when the window never became valid', () => {
        expect(wireWindow({ ...openDay, start_time: '' })).toEqual({
            start_time: null,
            end_time: null,
        });
    });
});
