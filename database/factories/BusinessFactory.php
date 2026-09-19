<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    protected $model = \App\Models\Business::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Rajesh Steel Works',
            'tagline' => 'ALL KIND OF: Mill Machinery Job Work',
            'address' => 'Plot 14, GIDC Estate, Ahmedabad',
            'mobile' => '9876543210',
            'jurisdiction_text' => 'Subject to Ahmedabad Jurisdiction',
            'gst_number' => '24AAAPL1234C1ZV',
            'email' => '',
            'bank_details' => '',
            'upi_id' => '',
            'bill_prefix' => '',
            'fy_reset' => false,
            'bill_fy' => '',
            'terms_text' => '',
        ];
    }

    /** Numbering as 'RS/26-27/001'. */
    public function withSeries(string $prefix = 'RS'): static
    {
        return $this->state(fn () => [
            'bill_prefix' => $prefix,
            'fy_reset' => true,
        ]);
    }
}
