<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Device;
use App\Models\User;
use App\Services\InvoiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Three working businesses, so the panel can be opened and judged before a
 * real handset has ever synced — and so the header's business switcher has
 * something to switch between.
 *
 * Runs on every deployment. That is only safe because it is keyed: each
 * business is found by its bill prefix, and one that already has documents
 * against it is left exactly as it is. So a redeploy against an empty
 * database fills it, a redeploy against a live one changes nothing, and
 * nobody has to remember which kind they are pointing at.
 *
 * The bills are written through InvoiceWriter rather than inserted, so they
 * draw real numbers from the series, and the customer and item lists fill
 * themselves the same way a handset fills them.
 */
class DemoSeeder extends Seeder
{
    /**
     * Three firms that bill differently on purpose: job work by the hour,
     * timber by the cubic foot, contract work by the job. Between them the
     * dashboard, the GST report and the ageing chart all have something to
     * show.
     */
    private const FIRMS = [
        [
            'prefix' => 'RS',
            'name' => 'Rajesh Steel Works',
            'tagline' => 'ALL KIND OF: Mill Machinery Job Work',
            'address' => 'Plot 14, GIDC Estate, Vatva, Ahmedabad 382445',
            'mobile' => '9876543210',
            'jurisdiction_text' => 'Subject to Ahmedabad Jurisdiction',
            'gst_number' => '24AAAPL1234C1ZV',
            'upi_id' => 'rajesh@okhdfcbank',
            'terms_text' => 'Payment within 30 days. Goods once delivered will not be taken back.',
            'bills' => 18,
            'work' => [
                ['Lathe Job Work', 450],
                ['Milling Job Work', 620],
                ['Welding', 2000],
                ['Boring Work', 750],
                ['Surface Grinding', 380],
            ],
            'customers' => [
                'Mahesh Traders',
                'Kiran Engineering',
                'Shreeji Industries',
                'Patel Metal Works',
                'Umiya Fabrication',
            ],
        ],
        [
            'prefix' => 'PT',
            'name' => 'Patel Timber Mart',
            'tagline' => 'Plywood, Blockboard & Sawn Timber',
            'address' => 'Ring Road, Opp. Sardar Market, Surat 395002',
            'mobile' => '9825011223',
            'jurisdiction_text' => 'Subject to Surat Jurisdiction',
            'gst_number' => '24AABCP7788K1Z3',
            'upi_id' => 'pateltimber@ybl',
            'terms_text' => 'Interest at 18% p.a. on bills outstanding beyond 15 days.',
            'bills' => 12,
            'work' => [
                ['Teak Wood (CFT)', 3200],
                ['Marine Plywood 19mm', 2850],
                ['Blockboard 25mm', 1950],
                ['Sawing Charges', 120],
                ['Door Frame Section', 640],
            ],
            'customers' => [
                'Sahyog Furniture',
                'Krishna Interiors',
                'Bhavani Builders',
                'Deep Carpentry Works',
            ],
        ],
        [
            'prefix' => 'SE',
            'name' => 'Shakti Electricals',
            'tagline' => 'Electrical Contractor & Panel Wiring',
            'address' => '3, Gokul Complex, Gondal Road, Rajkot 360004',
            'mobile' => '9737044556',
            'jurisdiction_text' => 'Subject to Rajkot Jurisdiction',
            'gst_number' => '24AAFCS4455M1ZP',
            'upi_id' => 'shaktielec@okaxis',
            'terms_text' => 'Material and labour billed separately. E&OE.',
            'bills' => 9,
            'work' => [
                ['Panel Wiring (per point)', 340],
                ['LED Fitting Installation', 180],
                ['Cable Laying (per metre)', 95],
                ['Earthing Work', 4500],
                ['AMC Visit', 1200],
            ],
            'customers' => [
                'Riddhi Siddhi Apartments',
                'Anand Plastics',
                'Gokul Dairy Farm',
            ],
        ],
    ];

    public function run(): void
    {
        $writer = app(InvoiceWriter::class);
        $user = $this->owner();
        $seeded = [];

        foreach (self::FIRMS as $firm) {
            $business = $this->businessFor($user, $firm);

            // Already has books. Leaving them alone is the whole reason this
            // is safe to run on a deployment.
            if ($business->invoices()->withTrashed()->exists()) {
                continue;
            }

            $this->fill($writer, $business, $firm);
            $seeded[] = $business->name;
        }

        $this->device($user);

        $this->command?->info($seeded === []
            ? 'Demo businesses already have documents — nothing seeded.'
            : 'Seeded: '.implode(', ', $seeded).'.');
        $this->command?->line("Owner: {$user->email}");
    }

    /**
     * The real administrator if one exists — on a deployment that is the
     * account made from the env vars a moment earlier — so seeding never
     * leaves a second, pointless login behind.
     */
    private function owner(): User
    {
        return User::where('is_admin', true)->orderBy('id')->first()
            ?? User::updateOrCreate(
                ['email' => 'admin@example.com'],
                ['name' => 'Administrator', 'password' => Hash::make('password'), 'is_admin' => true],
            );
    }

    /** Found by its bill prefix, which is the one thing about a firm that its numbering depends on. */
    private function businessFor(User $user, array $firm): Business
    {
        return $user->businesses()->firstOrCreate(
            ['bill_prefix' => $firm['prefix']],
            [
                'name' => $firm['name'],
                'tagline' => $firm['tagline'],
                'address' => $firm['address'],
                'mobile' => $firm['mobile'],
                'jurisdiction_text' => $firm['jurisdiction_text'],
                'gst_number' => $firm['gst_number'],
                'upi_id' => $firm['upi_id'],
                'terms_text' => $firm['terms_text'],
                'fy_reset' => true,
            ],
        );
    }

    /**
     * A run of bills spread back over the last few months, settled, part
     * settled and untouched in roughly equal thirds — plus a quotation, a
     * challan and one cancelled bill, so every state the panel can show is
     * on screen somewhere.
     */
    private function fill(InvoiceWriter $writer, Business $business, array $firm): void
    {
        $work = $firm['work'];
        $customers = $firm['customers'];
        $count = $firm['bills'];

        foreach (range(1, $count) as $i) {
            $date = now()->subDays((int) round(($count - $i) * 7.5));
            [$particulars, $rate] = $work[$i % count($work)];

            $invoice = $writer->create($business, [
                'customer_name' => $customers[$i % count($customers)],
                'date' => $date->toDateString(),
                'lines' => [
                    ['particulars' => $particulars, 'quantity' => 10 + ($i * 3 % 40), 'rate' => $rate],
                    ...($i % 3 === 0 ? [['particulars' => 'Transport', 'quantity' => 1, 'rate' => 850]] : []),
                ],
                'taxes' => $i % 4 === 0 ? [] : [
                    ['label' => 'CGST', 'percent' => 9],
                    ['label' => 'SGST', 'percent' => 9],
                ],
                'discount_type' => $i % 5 === 0 ? 'percent' : 'none',
                'discount_value' => $i % 5 === 0 ? 5 : 0,
            ]);

            if ($i % 3 === 0) {
                $writer->addPayment($invoice, [
                    'date' => $date->copy()->addDays(6)->toDateString(),
                    'amount' => round($invoice->total, 2),
                    'mode' => 'upi',
                    'note' => 'GPay ref '.(4400 + $i),
                ]);
            } elseif ($i % 3 === 1) {
                // Part paid, so the pending list and the ageing buckets have
                // the case that is easiest to get wrong.
                $writer->addPayment($invoice, [
                    'date' => $date->copy()->addDays(9)->toDateString(),
                    'amount' => round($invoice->total * 0.4, 2),
                    'mode' => 'cheque',
                    'note' => 'Cheque 1140'.$i,
                ]);
            }
        }

        $writer->create($business, [
            'doc_type' => 'quotation',
            'customer_name' => $customers[1 % count($customers)],
            'date' => now()->subDays(4)->toDateString(),
            'lines' => [[
                'particulars' => $work[1][0],
                'quantity' => 60,
                'rate' => $work[1][1],
            ]],
        ]);

        $writer->create($business, [
            'doc_type' => 'challan',
            'customer_name' => $customers[2 % count($customers)],
            'date' => now()->subDays(2)->toDateString(),
            'lines' => [[
                'particulars' => $work[0][0],
                'quantity' => 25,
                'rate' => $work[0][1],
            ]],
        ]);

        // A cancelled bill, because a numbered series with a hole in it is
        // exactly what the void flow exists to prevent.
        $cancelled = $writer->create($business, [
            'customer_name' => $customers[0],
            'date' => now()->subDays(11)->toDateString(),
            'lines' => [[
                'particulars' => $work[2][0],
                'quantity' => 2,
                'rate' => $work[2][1],
            ]],
        ]);
        $writer->void($cancelled, 'Raised twice by mistake');
    }

    /** One handset on the account, so the Devices screen isn't empty. */
    private function device(User $user): void
    {
        Device::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'OnePlus Nord'],
            [
                'platform' => 'android',
                'app_version' => '1.0.0',
                'last_pushed_at' => now()->subHours(3),
                'last_pulled_at' => now()->subHours(3),
                'last_seen_at' => now()->subHours(3),
            ],
        );
    }
}
