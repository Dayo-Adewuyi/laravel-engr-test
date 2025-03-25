<?php

namespace Database\Seeders;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Provider::count() == 0) {
            $providers = [
                ['name' => 'City Medical Center', 'code' => 'CMC', 'email' => 'billing@citymedical.example.com'],
                ['name' => 'Riverside Health Clinic', 'code' => 'RHC', 'email' => 'claims@riverside.example.com'],
                ['name' => 'Valley General Hospital', 'code' => 'VGH', 'email' => 'billing@valleygeneral.example.com'],
            ];
            
            foreach ($providers as $providerData) {
                Provider::create($providerData);
            }
        }
        
        User::create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'provider_id' => null,
            'role' => 'admin',
        ]);
        
        $providers = Provider::all();
        
        foreach ($providers as $provider) {
            $code = strtolower($provider->code);
            User::create([
                'name' => "{$provider->name} Admin",
                'email' => "admin@{$code}.example.com",
                'password' => Hash::make('password'),
                'provider_id' => $provider->id,
                'role' => 'provider_user',
            ]);
            
            for ($i = 1; $i <= 2; $i++) {
                User::create([
                    'name' => "Staff {$i} at {$provider->name}",
                    'email' => "staff{$i}@{$code}.example.com",
                    'password' => Hash::make('password'),
                    'provider_id' => $provider->id,
                    'role' => 'provider_user',
                ]);
            }
        }
    }
}