<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The app's backup file is the only copy of data that exists today, so the
 * importer is what turns an installed phone into a seeded server.
 */
class BackupImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** The exact shape BackupService.exportBackup() writes on the handset. */
    private function backup(array $overrides = []): array
    {
        return array_merge([
            'magic' => 'invoice_app_backup',
            'format_version' => 1,
            'created_at' => '2026-09-14T18:30:00.000',
            'tables' => [
                'businesses' => [[
                    'id' => 1,
                    'name' => 'Rajesh Steel Works',
                    'tagline' => 'ALL KIND OF: Mill Machinery Job Work',
                    'address' => 'Plot 14, GIDC Estate, Ahmedabad',
                    'mobile' => '9876543210',
                    'jurisdiction_text' => 'Subject to Ahmedabad Jurisdiction',
                    'gst_number' => '24AAAPL1234C1ZV',
                    'email' => '',
                    'bank_details' => '',
                    'logo_path' => '',
                    'next_bill_no' => 8,
                    'upi_id' => 'rajesh@okhdfcbank',
                    'signature_path' => null,
                    'bill_prefix' => 'RS',
                    'fy_reset' => 1,
                    'bill_fy' => '26-27',
                    'terms_text' => 'Payment within 30 days.',
                    'next_quote_no' => 3,
                    'next_challan_no' => 1,
                ]],
                'customers' => [
                    ['id' => 1, 'business_id' => 1, 'name' => 'Mahesh Traders', 'phone' => '9876500001', 'address' => '', 'gst_number' => ''],
                    ['id' => 2, 'business_id' => 1, 'name' => 'Kiran Engineering', 'phone' => '', 'address' => '', 'gst_number' => ''],
                ],
                'items' => [
                    ['id' => 1, 'business_id' => 1, 'name' => 'Lathe Job Work', 'default_rate' => 450, 'hsn_code' => '998873'],
                ],
                'invoices' => [[
                    'id' => 7,
                    'business_id' => 1,
                    'bill_no' => 7,
                    'customer_name' => 'Mahesh Traders',
                    'date' => '2026-09-14T00:00:00.000',
                    'amount_in_words' => '',
                    'total' => 0,
                    'paid_amount' => 8000,
                    'discount_type' => 'none',
                    'discount_value' => 0,
                    'notes' => '',
                    'round_off' => 0,
                    'bill_ref' => 'RS/26-27/007',
                    'doc_type' => 'bill',
                    'voided_at' => null,
                    'void_reason' => '',
                    'converted_from_id' => null,
                    'photo_path' => null,
                ]],
                'invoice_lines' => [
                    ['id' => 1, 'invoice_id' => 7, 'particulars' => 'Lathe Job Work', 'quantity' => 40, 'rate' => 450],
                ],
                'invoice_taxes' => [
                    ['id' => 1, 'invoice_id' => 7, 'label' => 'CGST', 'percent' => 9],
                    ['id' => 2, 'invoice_id' => 7, 'label' => 'SGST', 'percent' => 9],
                ],
                'payments' => [
                    ['id' => 1, 'invoice_id' => 7, 'date' => '2026-09-14T00:00:00.000', 'amount' => 8000, 'mode' => 'upi', 'note' => 'GPay 4471'],
                ],
                'settings' => [
                    ['key' => 'ui_language', 'value' => 'gu'],
                ],
            ],
            'images' => [],
        ], $overrides);
    }

    public function test_inspect_reports_what_the_file_holds_without_applying_it(): void
    {
        $this->postJson('/api/v1/backup/inspect', ['payload' => $this->backup()])
            ->assertOk()
            ->assertJson([
                'businesses' => 1,
                'customers' => 2,
                'items' => 1,
                'invoices' => 1,
                'payments' => 1,
            ]);

        $this->assertSame(0, Business::count());
    }

    public function test_a_file_that_is_not_a_backup_is_refused(): void
    {
        $this->postJson('/api/v1/backup/inspect', ['payload' => ['magic' => 'something_else']])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This file is not an Invoice Generator backup.');
    }

    public function test_a_backup_from_a_newer_app_is_refused(): void
    {
        $this->postJson('/api/v1/backup/inspect', ['payload' => $this->backup(['format_version' => 99])])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This backup was made by a newer version of the app. Update the server first.');
    }

    public function test_import_rebuilds_the_business_with_its_bills_intact(): void
    {
        $response = $this->postJson('/api/v1/backup/import', ['payload' => $this->backup()])
            ->assertOk()
            ->assertJsonPath('counts.businesses', 1)
            ->assertJsonPath('counts.customers', 2)
            ->assertJsonPath('counts.invoices', 1)
            ->assertJsonPath('counts.payments', 1);

        $business = $this->user->businesses()->firstOrFail();
        $this->assertSame('Rajesh Steel Works', $business->name);
        $this->assertSame('RS', $business->bill_prefix);
        $this->assertTrue($business->fy_reset);

        $invoice = $business->invoices()->with(['lines', 'taxes', 'payments'])->firstOrFail();

        // 40 × 450 = 18,000, plus CGST 9% and SGST 9% = 21,240.
        $this->assertEquals(21240, $invoice->total);
        $this->assertEquals(8000, $invoice->paid_amount);
        $this->assertEquals(13240, $invoice->balance);
        $this->assertSame('Partial', $invoice->status);
        $this->assertSame('RS/26-27/007', $invoice->display_no);

        // The handset's integer ids map to the UUIDs the two sides now share.
        $map = $response->json('uuid_map');
        $this->assertSame($business->uuid, $map['businesses'][1]);
        $this->assertSame($invoice->uuid, $map['invoices'][7]);
    }

    public function test_the_counter_is_dragged_past_every_imported_bill(): void
    {
        $backup = $this->backup();
        // A backup whose counter trails the bills it contains.
        $backup['tables']['businesses'][0]['next_bill_no'] = 2;

        $this->postJson('/api/v1/backup/import', ['payload' => $backup])->assertOk();

        $this->assertSame(8, $this->user->businesses()->first()->next_bill_no);
    }

    public function test_importing_twice_without_replace_keeps_both_copies(): void
    {
        $this->postJson('/api/v1/backup/import', ['payload' => $this->backup()])->assertOk();
        $this->postJson('/api/v1/backup/import', ['payload' => $this->backup()])->assertOk();

        $this->assertSame(2, $this->user->businesses()->count());
    }

    public function test_replace_existing_puts_the_old_data_beyond_reach_but_not_beyond_recovery(): void
    {
        $this->postJson('/api/v1/backup/import', ['payload' => $this->backup()])->assertOk();
        $first = $this->user->businesses()->firstOrFail();

        $this->postJson('/api/v1/backup/import', [
            'payload' => $this->backup(),
            'replace_existing' => true,
        ])->assertOk();

        $this->assertSame(1, $this->user->businesses()->count());
        $this->assertSoftDeleted('businesses', ['id' => $first->id]);
    }

    public function test_an_import_is_recorded(): void
    {
        $this->postJson('/api/v1/backup/import', ['payload' => $this->backup()])->assertOk();

        $this->assertDatabaseHas('sync_logs', ['user_id' => $this->user->id, 'direction' => 'import']);
    }
}
