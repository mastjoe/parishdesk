export type Option = {
    value: string;
    label: string;
};

export type PeopleOptions = {
    genders: Option[];
    maritalStatuses: Option[];
    statuses: Option[];
    familyRoles: Option[];
    communityTypes: Option[];
    clergyRoles: Option[];
};

export type CommunityOption = {
    id: number;
    name: string;
};

export type MemberSummary = {
    id: number;
    memberNo: string;
    name: string;
    title: string | null;
    photoUrl: string | null;
    gender: string;
    age: number | null;
    status: string;
    familyRole: string | null;
};

export type MemberRow = MemberSummary & {
    phone: string | null;
    email: string | null;
    community: string | null;
    family: { id: number; name: string } | null;
    hasAccount: boolean;
};

/** The fields of a member as a form edits them. */
export type MemberFields = {
    title: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    baptismal_name: string | null;
    gender: string;
    date_of_birth: string | null;
    marital_status: string | null;
    wedding_date: string | null;
    phone: string | null;
    alt_phone: string | null;
    email: string | null;
    address: string | null;
    occupation: string | null;
    state_of_origin: string | null;
    lga_of_origin: string | null;
    community_id: number | null;
    family_id: number | null;
    family_role: string | null;
    status: string;
    joined_at: string | null;
    notes: string | null;
};

export type Celebration = MemberSummary & {
    phone: string | null;
    date: string;
    years: number;
    inDays: number;
};
