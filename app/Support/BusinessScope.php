<?php

namespace App\Support;

use App\Models\Business;

/**
 * Which firm's books the panel is currently open on.
 *
 * The server holds several businesses at once, and nearly every screen here —
 * the bills, the ledger, the catalogue, all three reports — already took a
 * `?business=` filter of its own. Set one per page and the panel reads as
 * several half-filtered views of the same data: the bill list showing one
 * firm, the ledger still showing all of them.
 *
 * So the choice is made once, in the header, and kept for the session. This
 * object is what the request is scoped to; the middleware fills it, the views
 * read it through `$currentBusiness`, and the controllers that need to know
 * (the dashboard's figures, the "new" forms) ask it directly.
 *
 * Null means all businesses, which is the panel's original behaviour and
 * stays the default.
 */
class BusinessScope
{
    private ?Business $business = null;

    public function set(?Business $business): void
    {
        $this->business = $business;
    }

    public function current(): ?Business
    {
        return $this->business;
    }

    /** True when the panel is showing every business. */
    public function isAll(): bool
    {
        return $this->business === null;
    }

    /** The row id to filter on, or null when nothing is selected. */
    public function id(): ?int
    {
        return $this->business?->id;
    }

    public function uuid(): ?string
    {
        return $this->business?->uuid;
    }
}
