<?php

namespace App\Support\Permissions;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Reads role and permission names out of Spatie's untyped collections.
 */
class Names
{
    /**
     * Get the names of the given roles or permissions.
     *
     * @param  iterable<mixed>  $models
     * @return list<string>
     */
    public static function of(iterable $models): array
    {
        $names = [];

        foreach ($models as $model) {
            if ($model instanceof Role || $model instanceof Permission) {
                $names[] = $model->name;
            }
        }

        return $names;
    }
}
