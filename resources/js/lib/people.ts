import type { Option } from '@/types';

/** Labels for member fields, as shown in change requests and imports. */
export const memberFieldLabels: Record<string, string> = {
    title: 'Title',
    first_name: 'First name',
    middle_name: 'Middle name',
    last_name: 'Last name',
    baptismal_name: 'Baptismal name',
    date_of_birth: 'Date of birth',
    marital_status: 'Marital status',
    wedding_date: 'Wedding date',
    phone: 'Phone',
    alt_phone: 'Other phone',
    email: 'Email',
    address: 'Address',
    occupation: 'Occupation',
};

/** The badge tone for each member status. */
export const memberStatusTone: Record<string, string> = {
    active: 'green',
    inactive: 'slate',
    transferred_in: 'blue',
    transferred_out: 'amber',
    deceased: 'purple',
};

/**
 * Find the label of an option value, falling back to the value itself.
 */
export function optionLabel(
    options: Option[],
    value: string | null | undefined,
): string {
    if (!value) {
        return '—';
    }

    return options.find((option) => option.value === value)?.label ?? value;
}

/**
 * Describe when a celebration falls, e.g. "Today", "Tomorrow", "In 5 days".
 */
export function inDays(days: number): string {
    if (days === 0) {
        return 'Today';
    }

    return days === 1 ? 'Tomorrow' : `In ${days} days`;
}

/**
 * Format a plain date (YYYY-MM-DD) without shifting it across time zones.
 */
export function formatPlainDate(
    value: string | null | undefined,
    options: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    },
): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-').map(Number);

    return new Intl.DateTimeFormat(undefined, {
        ...options,
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}
