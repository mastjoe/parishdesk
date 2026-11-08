import type { MemberSummary, Option } from './people';

export type MarriageRow = {
    id: number;
    applicationNo: string;
    status: string;
    statusLabel: string;
    groomName: string;
    brideName: string;
    groom: MemberSummary | null;
    bride: MemberSummary | null;
    proposedDate: string | null;
    bannsPublished: number;
    hasOpenObjection: boolean;
    createdAt: string | null;
};

export type MarriageCheckItem = Option & { done: boolean };

export type BannsPublication = {
    id: number;
    number: number;
    publishedOn: string;
    recordedBy: string | null;
};

export type BannsObjection = {
    id: number;
    raisedBy: string;
    raisedOn: string;
    details: string;
    resolution: string | null;
    resolvedAt: string | null;
    resolvedBy: string | null;
    isOpen: boolean;
};

/** The steps an application is ready for, as the server decides them. */
export type MarriageSteps = {
    changeChecks: boolean;
    verifyDocuments: boolean;
    publishBanns: boolean;
    undoBanns: boolean;
    receiveObjections: boolean;
    clear: boolean;
    marry: boolean;
};

export type MarriageDetail = MarriageRow & {
    groomParish: string | null;
    brideParish: string | null;
    officiant: string | null;
    notes: string | null;
    checks: MarriageCheckItem[];
    documentsVerifiedAt: string | null;
    clearedAt: string | null;
    withdrawnAt: string | null;
    withdrawalReason: string | null;
    bannsRequired: number;
    publications: BannsPublication[];
    objections: BannsObjection[];
    matrimonyRecordId: number | null;
    createdBy: string | null;
    steps: MarriageSteps;
};

/** The fields of an application as the form edits them. */
export type MarriageFields = {
    groom_member_id: number | null;
    groom_name: string;
    groom_parish: string | null;
    bride_member_id: number | null;
    bride_name: string;
    bride_parish: string | null;
    proposed_date: string | null;
    officiant: string | null;
    notes: string | null;
};
