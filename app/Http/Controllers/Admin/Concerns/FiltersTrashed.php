<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

/**
 * The live / deleted / all switch shared by the panel's list screens.
 *
 * Deleting a customer, an item or a bill on a handset does not erase it: the
 * row is soft-deleted here and stays on file. The panel is the only place
 * those records can still be read, so every list that can hide a row this way
 * offers the same switch, spelled the same way.
 *
 * The parameter is `records` rather than `status` because the bills list
 * already uses `status` for paid/partial/unpaid, and one word cannot mean two
 * things in the same query string.
 */
trait FiltersTrashed
{
    /**
     * Applies the filter and returns which one is in force, for the view.
     */
    protected function applyRecordsFilter($query, Request $request): string
    {
        $records = (string) $request->query('records', 'live');

        if ($records === 'deleted') {
            $query->onlyTrashed();
        } elseif ($records === 'all') {
            $query->withTrashed();
        } else {
            $records = 'live';
        }

        return $records;
    }
}
