<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * How a member is related to the family they belong to.
 *
 * The cases are in family-tree order, which is also how families list
 * their members: parents first, then grandparents, children and the rest.
 */
enum FamilyRole: string
{
    use HasOptions;

    /**
     * An SQL expression that sorts members into family-tree order. It lists
     * the cases in the order they are declared below.
     */
    public const string ORDER_EXPRESSION = "case family_role when 'father' then 0 when 'mother' then 1 when 'grandparent' then 2 "
        ."when 'child' then 3 when 'grandchild' then 4 when 'relative' then 5 when 'dependent' then 6 when 'other' then 7 else 8 end";

    case Father = 'father';
    case Mother = 'mother';
    case Grandparent = 'grandparent';
    case Child = 'child';
    case Grandchild = 'grandchild';
    case Relative = 'relative';
    case Dependent = 'dependent';
    case Other = 'other';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::Grandparent => 'Grandparent',
            self::Child => 'Child',
            self::Grandchild => 'Grandchild',
            self::Relative => 'Relative',
            self::Dependent => 'Dependent',
            self::Other => 'Other',
        };
    }

    /**
     * Whether the role is a parent of the household.
     */
    public function isParent(): bool
    {
        return $this === self::Father || $this === self::Mother;
    }
}
