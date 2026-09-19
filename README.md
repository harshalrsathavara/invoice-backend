# Invoice Backend

Laravel 12 + MySQL server for the **Invoice Generator** Android app
(`/Applications/workspace/invoice_app`).

The handset stays the working store — it has to keep raising bills with no
signal — so this server is a **mirror, not a source of truth**. It gives the
owner three things the phone alone cannot: a copy of the books that survives
losing the handset, a second device on the same data, and an admin panel over
everything.

---

## What's here

| Area | Detail |
| --- | --- |
| **API** | 29 routes under `/api/v1`, Sanctum bearer tokens, everything addressed by UUID |
| **Sync** | Two-way push/pull, last-write-wins, losing changes kept for review |
| **Import** | Reads the app's own backup JSON and seeds the server from it |
| **Admin** | Session-auth panel at `/admin` — dashboard, books, ledger, owners, devices, conflicts |
| **Domain** | Money maths, numbering and reports ported from the Dart models |
| **Tests** | 129 passing, 467 assertions |

---

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate

# MySQL
mysql -u root -p -e "CREATE DATABASE invoice_backend CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# then set DB_PASSWORD in .env

php artisan migrate
php artisan invoice:admin --name="Your Name" --email=you@example.com
php artisan serve
```

`APP_TIMEZONE` is `Asia/Kolkata`. It is not decoration: the financial-year
reset, the ageing buckets and every date on a bill are Indian, and the sync
comparison depends on the server and the handset agreeing about what "now" is.

To see the panel with data in it before a phone has ever synced:

```bash
php artisan db:seed --class=DemoSeeder   # admin@example.com / password
```

Tests run against SQLite in memory and need no database:

```bash
php artisan test
```

---

## Domain rules, ported not reinvented

These are copied from the app deliberately, and the tests assert they still
agree. If one side changes, both must.

- **Money.** Subtotal → discount (clamped to the subtotal) → taxable → tax →
  round-off → total. Discount before tax is the standard order on an Indian
  invoice. All of it lives on `Invoice` as derived accessors; `total` and
  `paid_amount` are cached columns recomputed on every write.
- **Numbering.** Bills, quotations and challans draw from separate counters, so
  raising a quotation never burns a bill number. Read and increment happen in
  one locked transaction. With FY reset on, the first document on or after
  1 April takes number 1 again, once.
- **The reference is frozen.** `bill_ref` (`RS/26-27/007`) is composed at save
  time and never recomputed — changing the numbering settings later must not
  renumber a bill that has already been printed and handed over.
- **Cancel, never delete.** Voiding keeps the row and its number; deleting
  would leave a hole in a numbered series.
- **Only bills are money.** Quotations, challans and cancelled bills are
  excluded from every total, ledger and report.
- **Tax warnings never block.** A CGST/SGST rate mismatch is surfaced as a
  warning; the person raising the bill decides what is correct.

---

## The sync protocol

Rows are identified by a **client-generated UUID**, never by the server's
auto-increment id — the handset creates rows offline and has never seen one.

### Push

```http
POST /api/v1/sync/push
Authorization: Bearer <token>

{
  "device_uuid": "…",
  "changes": {
    "businesses": [ … ],
    "customers":  [ {"uuid":"…","business_uuid":"…","name":"Mahesh Traders","updated_at":"2026-09-14T10:00:00+05:30"} ],
    "items":      [ … ],
    "invoices":   [ {"uuid":"…","business_uuid":"…","bill_no":7,"bill_ref":"RS/26-27/007","lines":[…],"taxes":[…]} ],
    "payments":   [ {"uuid":"…","invoice_uuid":"…","amount":8000,"mode":"upi"} ]
  }
}
```

Applied parents-first. An invoice arrives as one unit — header, lines and taxes
together — and is replaced wholesale, because that is how the handset edits one.

**The number the handset issued is accepted as-is and never reassigned.** The
server drags its own counter past it, so a document raised here can never
collide with one the phone has already printed.

### Pull

```http
GET /api/v1/sync/pull?since=2026-09-14T10:00:00%2B05:30&device_uuid=…
```

Returns everything changed **at or after** the cursor, soft-deleted rows
included so deletions propagate, plus `server_time` to use as the next cursor.

The cursor is inclusive on purpose. MySQL timestamps are second-precision, so
an exclusive cursor can skip a row written in the same second as the last sync.
Re-sending a handful of rows is harmless — every apply is an idempotent upsert
keyed on the UUID — while silently losing one is not.

### Conflicts

Last-write-wins on `updated_at`, the only rule that can be applied with nobody
present. The losing payload is written to `sync_conflicts` rather than dropped,
and surfaces in the admin panel with both versions side by side.

Client timestamps are normalised to the app timezone on arrival. The handset
sends IST with a `+05:30` offset; comparing that against a stored UTC value
without converting puts every edit five and a half hours adrift, which is
enough to make a stale change win every time.

---

## Seeding from an existing phone

The app's backup file is the only copy of the data that exists today.

```http
POST /api/v1/backup/inspect   # validates, reports counts, applies nothing
POST /api/v1/backup/import    # {"payload": {…}, "replace_existing": false}
```

Accepts the backup either as an uploaded `backup` file or as an inline
`payload` object. The backup carries SQLite integer ids and no UUIDs, so every
row is given one and the **id-to-UUID map is returned**:

```json
{"counts": {…}, "uuid_map": {"businesses": {"1": "…"}, "invoices": {"7": "…"}}}
```

`replace_existing` soft-deletes the owner's current businesses first — an
import that turns out to be the wrong file stays recoverable.

Images travel with the backup as base64 and are written to the `public` disk
under `business_images/`, named by a fresh uuid. An upload from the panel
follows the same convention, so the two are indistinguishable afterwards.
Neither is reachable by a public URL: the signature is the owner's real
signature, so both go out through an authorised route instead, which is why
this project needs no `storage:link`.

---

## What the Flutter app still needs

The server is ready; the app is not yet a sync client. Three changes, in order:

1. **A `uuid` column on every synced table** (`businesses`, `customers`,
   `items`, `invoices`, `invoice_lines`, `invoice_taxes`, `payments`) —
   schema v12. Generate one per row on insert; backfill existing rows from the
   `uuid_map` the import returns.
2. **`updated_at` and `deleted_at` on the same tables.** Sync needs to know
   when a row changed and that a deletion happened. Deletes become soft.
   Note the app currently hard-deletes and offers undo — that flow keeps
   working, it just sets `deleted_at` instead of removing the row.
3. **A sync client**: store the token and the pull cursor in the existing
   `settings` table, push on save when there is signal, pull on app resume.
   `BackupService` already proves the app can serialise every table — the push
   payload is that same shape with UUIDs added.

Until then the API is usable directly, and the import endpoint already gets
today's data onto the server.

---

## API reference

All routes are prefixed `/api/v1` and require `Authorization: Bearer <token>`
except the login route.

| Method | Route | Purpose |
| --- | --- | --- |
| POST | `auth/login` | Sign in, register the device, get a token |
| GET | `auth/me` | Who the token belongs to |
| POST | `auth/logout` | Revoke the current token |
| POST | `sync/push` | Apply offline changes |
| GET | `sync/pull` | Fetch changes since a cursor |
| POST | `backup/inspect` · `backup/import` | Seed from the app's backup file |
| GET/POST | `businesses` | List, create |
| GET/PUT | `businesses/{uuid}` | Show, update |
| GET | `businesses/{uuid}/image/logo` · `/signature` | The images that print on a bill |
| GET/POST | `businesses/{uuid}/customers` | Ledger master |
| PUT/DELETE | `businesses/{uuid}/customers/{uuid}` | Rename (cascades to bills), remove |
| GET/POST | `businesses/{uuid}/items` | Rate catalogue |
| GET/POST | `businesses/{uuid}/invoices` | List (search, status, kind, dates), raise |
| GET/PUT/DELETE | `businesses/{uuid}/invoices/{uuid}` | Show, edit, delete |
| POST | `…/invoices/{uuid}/void` · `/unvoid` | Cancel, reinstate |
| POST/DELETE | `…/invoices/{uuid}/payments[/{uuid}]` | Record, remove a receipt |
| GET | `businesses/{uuid}/reports` | `?period=this_month\|last_month\|this_fy\|all_time\|custom` |

**Device registration.** Send `device_uuid` on every login. The first login may
omit it — the server assigns one and returns it in `device.uuid`, and the client
is expected to store it and send it from then on. A client that never sends one
registers a fresh device, and a fresh token, on every single sign-in.

Renaming a customer rewrites that name across the business's invoices in the
same transaction — the app stores the customer as text on the bill, and without
this the ledger would split one person into two entries.

---

## Admin panel

`/admin`, session auth, `is_admin` required.

- **Dashboard** — outstanding, collected, billed and tax charged; six-month
  billing bars; receivables ageing; latest documents; handset status.
- **Businesses** — per-company figures, GST summary, numbering state. Add a
  company on an owner's behalf, edit what prints on its bills, upload the logo
  and signature that print on it, or remove it (soft, and cascading to its
  books, so the handsets drop it on their next sync).
- **Users & logins** — the accounts handsets sign in as. `invoice:admin` only
  ever makes administrators, so this is the only way to create the ordinary
  case: an owner who syncs their own books from the app and cannot open the
  panel. Deleting an account is refused while it still holds businesses —
  `businesses.user_id` cascades, so it would erase those books outright.
- **Bills & invoices** — all three kinds in one list, filtered by text,
  business, kind, status and date window.
- **Customers & ledger** — heaviest debt first, drilling into one account.
- **Mobile devices** — which handsets hold data, when each last synced, revoke a lost
  phone (the data stays; the device just has to sign in again).
- **Sync conflicts** — discarded changes with both versions side by side.

Every item in the sidebar carries a second line saying what you would come there
to do. The labels are the words an invoice office uses — "Pending payments", not
"chase list"; "Items & rates", not "catalogue" — and each page's heading repeats
the label that led to it, so a link and its destination never disagree.

One hand-written stylesheet, no build step. It carries the app's oxblood
identity (`#8C1D24`, from the `#C00000` the printed bill is inked in) and
follows the viewer's light/dark setting.
