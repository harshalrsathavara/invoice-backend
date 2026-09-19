<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Device;
use App\Models\User;
use App\Services\InvoiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A working set of data so the panel can be opened and judged before a real
 * handset has ever synced. Safe to run repeatedly — it keys off the email.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $writer = app(InvoiceWriter::class);

        // Attach to the real administrator if one has already been created,
        // so seeding doesn't leave a second, pointless account behind.
        $user = User::where('is_admin', true)->orderBy('id')->first()
            ?? User::updateOrCreate(
                ['email' => 'admin@example.com'],
                ['name' => 'Administrator', 'password' => Hash::make('password'), 'is_admin' => true],
            );

        if ($user->businesses()->exists()) {
            $this->command->warn('Demo data already present — nothing seeded.');

            return;
        }

        $business = $user->businesses()->create([
            'name' => 'Rajesh Steel Works',
            'tagline' => 'ALL KIND OF: Mill Machinery Job Work',
            'address' => 'Plot 14, GIDC Estate, Vatva, Ahmedabad 382445',
            'mobile' => '9876543210',
            'jurisdiction_text' => 'Subject to Ahmedabad Jurisdiction',
            'gst_number' => '24AAAPL1234C1ZV',
            'upi_id' => 'rajesh@okhdfcbank',
            'bill_prefix' => 'RS',
            'fy_reset' => true,
            'terms_text' => 'Payment within 30 days. Goods once delivered will not be taken back.',
        ]);

        Device::create([
            'user_id' => $user->id,
            'name' => 'OnePlus Nord',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'last_pushed_at' => now()->subHours(3),
            'last_pulled_at' => now()->subHours(3),
            'last_seen_at' => now()->subHours(3),
        ]);

        $work = [
            ['Lathe Job Work', 450],
            ['Milling Job Work', 620],
            ['Welding', 2000],
            ['Boring Work', 750],
            ['Surface Grinding', 380],
        ];

        $customers = ['Mahesh Traders', 'Kiran Engineering', 'Shreeji Industries', 'Patel Metal Works', 'Umiya Fabrication'];

        foreach (range(1, 18) as $i) {
            $daysAgo = (int) round((18 - $i) * 7.5);
            $date = now()->subDays($daysAgo);
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

            // A spread of settled, part-settled and untouched bills, so the
            // ledger and the ageing chart both have something to show.
            if ($i % 3 === 0) {
                $writer->addPayment($invoice, [
                    'date' => $date->copy()->addDays(6)->toDateString(),
                    'amount' => round($invoice->total, 2),
                    'mode' => 'upi',
                    'note' => 'GPay ref '.(4400 + $i),
                ]);
            } elseif ($i % 3 === 1) {
                $writer->addPayment($invoice, [
                    'date' => $date->copy()->addDays(9)->toDateString(),
                    'amount' => round($invoice->total * 0.4, 2),
                    'mode' => 'cheque',
                    'note' => 'Cheque 1140'.$i,
                ]);
            }
        }

        // One quotation and one challan, so the list shows all three kinds.
        $writer->create($business, [
            'doc_type' => 'quotation',
            'customer_name' => 'Kiran Engineering',
            'date' => now()->subDays(4)->toDateString(),
            'lines' => [['particulars' => 'Milling Job Work', 'quantity' => 60, 'rate' => 620]],
        ]);

        $writer->create($business, [
            'doc_type' => 'challan',
            'customer_name' => 'Shreeji Industries',
            'date' => now()->subDays(2)->toDateString(),
            'lines' => [['particulars' => 'Lathe Job Work', 'quantity' => 25, 'rate' => 450]],
        ]);

        // And one cancelled bill, because a numbered series with a hole in it
        // is exactly what the void flow exists to prevent.
        $cancelled = $writer->create($business, [
            'customer_name' => 'Patel Metal Works',
            'date' => now()->subDays(11)->toDateString(),
            'lines' => [['particulars' => 'Welding', 'quantity' => 2, 'rate' => 2000]],
        ]);
        $writer->void($cancelled, 'Raised twice by mistake');

        $this->command->info('Seeded 21 documents for Rajesh Steel Works.');
        $this->command->line("Owner: {$user->email}");
    }
}
