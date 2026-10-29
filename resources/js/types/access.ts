export type CatalogueGroup = {
    key: string;
    label: string;
    description: string;
    permissions: CataloguePermission[];
};

export type CataloguePermission = {
    name: string;
    label: string;
    description: string;
};

export type RoleBadge = {
    name: string;
    color: string;
};
