/** The badge tone of each stage of a marriage application. */
export const marriageTone: Record<string, string> = {
    applied: 'slate',
    documents_verified: 'blue',
    banns: 'amber',
    cleared: 'teal',
    married: 'rose',
    withdrawn: 'red',
};

/** The stages an application passes through on its way to a wedding. */
export const marriageStages = [
    { value: 'applied', label: 'Applied' },
    { value: 'documents_verified', label: 'Documents verified' },
    { value: 'banns', label: 'Banns' },
    { value: 'cleared', label: 'Cleared' },
    { value: 'married', label: 'Married' },
];
