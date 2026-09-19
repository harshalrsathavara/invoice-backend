# Fake data — raw SQL

Direct `INSERT`s, deliberately not a Laravel seeder. Run them in order against
the `invoice_backend` database:

```bash
M="/opt/homebrew/opt/mysql@8.0/bin/mysql -u root -p invoice_backend"
$M < 01_business_customers_items.sql
$M < 02_invoices_lines_taxes.sql
$M < 03_totals_payments_counters.sql
$M < 04_devices_sync_history.sql
```

Use the **mysql@8.0** client. The default `mysql` (9.7) cannot connect to this
server at all — 9.x dropped `mysql_native_password`, which the root account
still uses.

## How it fits together

`02` inserts every document with `total` and `paid_amount` left at **0**. `03`
computes them in SQL, mirroring the `Invoice` model exactly: subtotal →
discount (clamped to the subtotal) → taxable → tax → round-off → total, with
each tax row rounded on its own before summing, as `taxAmountFor()` does.
Nothing touches `updated_at` — those two columns are derived, not edited.

`03` then inserts payments as a fraction of the total it just computed, keyed
off `invoice_id % 3` so the split of settled / part-settled / untouched bills
is deterministic. **It applies to every live bill in the table**, so run it on a
clean payments table — running it twice, or over payments written by
`DemoSeeder`, pays some bills more than once and drives their balance negative.

`amount_in_words` is the one column not written here: spelling a number in the
Indian system is domain logic, so it is filled afterwards from the existing
`NumberToWords` service via a direct `UPDATE`.

## Checking it landed correctly

```bash
php artisan tinker --execute='
use App\Models\Invoice;
$bad = 0;
foreach (Invoice::with(["lines","taxes","payments"])->cursor() as $i) {
    if (abs((float) $i->total - $i->computed_total) > 0.005) $bad++;
    if (abs((float) $i->paid_amount - $i->payments->sum("amount")) > 0.005) $bad++;
}
echo "mismatches: $bad\n";'
```

It should print `0` — that is the SQL and the PHP model agreeing row for row.

## What it creates

A second company (**Umiya Engineering Works**, prefix `UE`) alongside the
seeded one, 15 more customers, 11 more catalogue items, and 53 more documents
spread from October 2025 to September 2026 — bills, quotations, challans and a
few cancelled ones, with a realistic spread of paid, part-paid and unpaid.

Business 1's bills run across **two financial years**, so `RS/25-26/001` and
`RS/26-27/001` both exist. That is the case that proved the unique index on
`(business_id, doc_type, bill_no)` was wrong, and it is worth keeping in the
data as a regression guard.
