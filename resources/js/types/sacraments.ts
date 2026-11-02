import type { MemberSummary, Option } from './people';

/** A sacrament and what its register records. */
export type SacramentTypeOption = Option & {
    isOnce: boolean;
    recordsParents: boolean;
    maxSponsors: number;
    sponsorRoles: Option[];
};

export type SacramentOptions = {
    types: SacramentTypeOption[];
    holyOrders: Option[];
    genders: Option[];
};

export type SacramentRow = {
    id: number;
    type: string;
    typeLabel: string;
    personName: string;
    spouseName: string | null;
    receivedOn: string;
    elsewhere: boolean;
    place: string | null;
    minister: string | null;
    reference: string | null;
    member: MemberSummary | null;
};

export type SacramentSponsor = {
    id: number;
    name: string;
    role: string;
    roleLabel: string;
    member: MemberSummary | null;
};

export type SacramentDetail = SacramentRow & {
    gender: string | null;
    genderLabel: string | null;
    dateOfBirth: string | null;
    fatherName: string | null;
    motherName: string | null;
    spouse: MemberSummary | null;
    confirmationName: string | null;
    orderLabel: string | null;
    registerBook: string | null;
    registerPage: string | null;
    entryNo: string | null;
    notes: string | null;
    recordedBy: string | null;
    createdAt: string | null;
    sponsors: SacramentSponsor[];
};

export type SponsorFields = {
    member_id: number | null;
    name: string;
    role: string;
};

/** The fields of a register entry as the form edits them. */
export type SacramentFields = {
    type: string;
    member_id: number | null;
    person_name: string;
    gender: string | null;
    date_of_birth: string | null;
    received_on: string;
    elsewhere: boolean;
    place: string | null;
    minister: string | null;
    father_name: string | null;
    mother_name: string | null;
    spouse_member_id: number | null;
    spouse_name: string | null;
    details: { confirmation_name: string | null; order: string | null };
    register_book: string | null;
    register_page: string | null;
    entry_no: string | null;
    notes: string | null;
    sponsors: SponsorFields[];
};

/** What the form can copy from a picked member. */
export type SacramentPerson = MemberSummary & {
    dateOfBirth: string | null;
    fatherName: string | null;
    motherName: string | null;
};
