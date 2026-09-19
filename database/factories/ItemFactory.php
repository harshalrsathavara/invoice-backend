<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = \App\Models\Item::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => 'Lathe Job Work',
            'default_rate' => 450,
            'hsn_code' => '998873',
        ];
    }
}
