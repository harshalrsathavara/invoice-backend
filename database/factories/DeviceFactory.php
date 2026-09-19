<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    protected $model = \App\Models\Device::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'OnePlus Nord',
            'platform' => 'android',
            'app_version' => '1.0.0',
        ];
    }
}
