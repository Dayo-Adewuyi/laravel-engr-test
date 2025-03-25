<?php

namespace Database\Seeders;


use App\Models\Insurer;
use App\Models\Specialty;
use Illuminate\Database\Seeder;


class InsurerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        $specialties = [
            ['name' => 'Cardiology', 'code' => 'CARD'],
            ['name' => 'Orthopedics', 'code' => 'ORTH'],
            ['name' => 'Neurology', 'code' => 'NEUR'],
            ['name' => 'Oncology', 'code' => 'ONCO'],
            ['name' => 'Pediatrics', 'code' => 'PEDI'],
            ['name' => 'Dermatology', 'code' => 'DERM'],
            ['name' => 'Gastroenterology', 'code' => 'GAST'],
        ];

        foreach ($specialties as $specialty) {
            Specialty::create($specialty);
        }

        $insurers = [
            [
                'name' => 'HealthFirst Insurance',
                'code' => 'HFI',
                'email' => 'claims@healthfirst.example.com',
                'daily_capacity' => 150,
                'min_batch_size' => 10,
                'max_batch_size' => 50,
                'prefers_encounter_date' => true,
                'specialties' => [
                    'CARD' => 0.8, 
                    'ORTH' => 1.1, 
                    'NEUR' => 1.2, 
                    'ONCO' => 0.9, 
                ]
            ],
            [
                'name' => 'National Medical Insurance',
                'code' => 'NMI',
                'email' => 'claims@nationalmedical.example.com',
                'daily_capacity' => 200,
                'min_batch_size' => 5,
                'max_batch_size' => 75,
                'prefers_encounter_date' => false,
                'specialties' => [
                    'ORTH' => 0.7, 
                    'PEDI' => 0.8, 
                    'DERM' => 1.2, 
                    'GAST' => 0.9, 
                ]
            ],
            [
                'name' => 'PremiumCare Health',
                'code' => 'PCH',
                'email' => 'claims@premiumcare.example.com',
                'daily_capacity' => 120,
                'min_batch_size' => 8,
                'max_batch_size' => 40,
                'prefers_encounter_date' => true,
                'specialties' => [
                    'NEUR' => 0.75, 
                    'ONCO' => 0.8,  
                    'CARD' => 1.1,  
                    'PEDI' => 1.15, 
                ]
            ],
            [
                'name' => 'Global Health Insurance',
                'code' => 'GHI',
                'email' => 'claims@globalhealth.example.com',
                'daily_capacity' => 180,
                'min_batch_size' => 15,
                'max_batch_size' => 60,
                'prefers_encounter_date' => false,
                'specialties' => [
                    'DERM' => 0.85, 
                    'GAST' => 0.8,  
                    'ORTH' => 0.9,  
                    'CARD' => 1.05, 
                ]
            ],
        ];

        foreach ($insurers as $insurerData) {
            $specialtyData = $insurerData['specialties'];
            unset($insurerData['specialties']);
            
            $insurer = Insurer::create($insurerData);
            
            foreach ($specialtyData as $specialtyCode => $efficiencyFactor) {
                $specialty = Specialty::where('code', $specialtyCode)->first();
                if ($specialty) {
                    $insurer->specialties()->attach($specialty->id, [
                        'efficiency_factor' => $efficiencyFactor
                    ]);
                }
            }
        }
    }
}