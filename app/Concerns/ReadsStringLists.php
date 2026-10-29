<?php

namespace App\Concerns;

trait ReadsStringLists
{
    /**
     * Get a validated array input as a list of strings.
     *
     * @return list<string>
     */
    protected function stringList(string $key): array
    {
        return array_values(array_filter($this->array($key), is_string(...)));
    }
}
