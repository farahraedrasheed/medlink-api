<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ────────────────────────────────────────────────────────
        User::create([
            'first_name' => 'System',
            'last_name'  => 'Admin',
            'email'      => 'admin@medlink.com',
            'password'   => Hash::make('Admin@1234'),
            'role'       => 'admin',
            'is_active'  => true,
            'permissions'=> ['manage_users', 'manage_medicines', 'manage_pharmacies', 'view_reports'],
        ]);

        // ── Categories ───────────────────────────────────────────────────
        $categories = [
            ['name' => 'Pain Relief',        'description' => 'Analgesics and anti-inflammatory medicines'],
            ['name' => 'Antibiotics',         'description' => 'Antibacterial medications'],
            ['name' => 'Vitamins',            'description' => 'Vitamins and supplements'],
            ['name' => 'Cardiovascular',      'description' => 'Heart and blood pressure medicines'],
            ['name' => 'Respiratory',         'description' => 'Asthma, cough, and respiratory care'],
            ['name' => 'Diabetes',            'description' => 'Blood sugar management'],
            ['name' => 'Dermatology',         'description' => 'Skin care and treatment'],
            ['name' => 'Gastrointestinal',    'description' => 'Digestive system medications'],
            ['name' => 'Mental Health',       'description' => 'Antidepressants, anxiolytics'],
            ['name' => 'Pediatrics',          'description' => 'Medicines for children'],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }

        // ── Medicines ────────────────────────────────────────────────────
        $painRelief = Category::where('name', 'Pain Relief')->first();
        $antibiotics = Category::where('name', 'Antibiotics')->first();
        $vitamins = Category::where('name', 'Vitamins')->first();

        $medicines = [
            [
                'name'                  => 'Panadol Extra (500mg)',
                'generic_name'          => 'Paracetamol',
                'category_id'           => $painRelief->id,
                'strength'              => '500mg',
                'form'                  => 'tablet',
                'manufacturer'          => 'GlaxoSmithKline',
                'description'           => 'Effective relief from pain and fever.',
                'side_effects'          => 'Rare: liver problems if overused.',
                'precautions'           => 'Not for children under 6 years.',
                'active_ingredients'    => ['Paracetamol 500mg'],
                'requires_prescription' => false,
                'is_controlled'         => false,
                'expiry_date'           => '2027-12-31',
            ],
            [
                'name'                  => 'Ibuprofen 400mg',
                'generic_name'          => 'Ibuprofen',
                'category_id'           => $painRelief->id,
                'strength'              => '400mg',
                'form'                  => 'tablet',
                'manufacturer'          => 'Advil Healthcare',
                'description'           => 'NSAID for pain, fever, and inflammation.',
                'side_effects'          => 'May cause stomach upset.',
                'precautions'           => 'Avoid on empty stomach. Not for kidney disease.',
                'active_ingredients'    => ['Ibuprofen 400mg'],
                'requires_prescription' => false,
                'is_controlled'         => false,
                'expiry_date'           => '2027-06-30',
            ],
            [
                'name'                  => 'Amoxicillin 500mg',
                'generic_name'          => 'Amoxicillin',
                'category_id'           => $antibiotics->id,
                'strength'              => '500mg',
                'form'                  => 'capsule',
                'manufacturer'          => 'MedPharm',
                'description'           => 'Broad-spectrum antibiotic for bacterial infections.',
                'side_effects'          => 'May cause diarrhea, nausea, or allergic reactions.',
                'precautions'           => 'Complete full course. Not for penicillin-allergic patients.',
                'active_ingredients'    => ['Amoxicillin trihydrate 500mg'],
                'requires_prescription' => true,
                'is_controlled'         => false,
                'expiry_date'           => '2026-12-31',
            ],
            [
                'name'                  => 'Vitamin C 1000mg',
                'generic_name'          => 'Ascorbic Acid',
                'category_id'           => $vitamins->id,
                'strength'              => '1000mg',
                'form'                  => 'tablet',
                'manufacturer'          => 'NutriVita',
                'description'           => 'Immune system support and antioxidant.',
                'active_ingredients'    => ['Ascorbic Acid 1000mg'],
                'requires_prescription' => false,
                'is_controlled'         => false,
                'expiry_date'           => '2028-01-31',
            ],
        ];

        $createdMedicines = [];
        foreach ($medicines as $med) {
            $createdMedicines[] = Medicine::create($med);
        }

        // ── Pharmacies ───────────────────────────────────────────────────
        $pharmacies = [
            [
                'name'               => 'Al Shifa Pharmacy',
                'first_name'         => 'Al Shifa Pharmacy',
                'email'              => 'alshifa@pharmacy.com',
                'password'           => Hash::make('Pharmacy@1234'),
                'role'               => 'pharmacy',
                'phone'              => '961123456789',
                'address'            => 'Street 5, Downtown',
                'license_number'     => 'PH-2024-001',
                'license_expiry'     => '2026-06-30',
                'area'               => 'Downtown',
                'latitude'           => 33.8886,
                'longitude'          => 35.4955,
                'status'             => 'verified',
                'delivery_available' => true,
                'delivery_fee'       => 5.0,
                'working_hours'      => [
                    'monday' => '08:00-22:00', 'tuesday' => '08:00-22:00',
                    'wednesday' => '08:00-22:00', 'thursday' => '08:00-22:00',
                    'friday' => '10:00-20:00', 'saturday' => '10:00-20:00',
                    'sunday' => 'closed',
                ],
                'is_active'          => true,
            ],
            [
                'name'               => 'CityMed Pharmacy',
                'first_name'         => 'CityMed Pharmacy',
                'email'              => 'citymed@pharmacy.com',
                'password'           => Hash::make('Pharmacy@1234'),
                'role'               => 'pharmacy',
                'phone'              => '961987654321',
                'address'            => 'North District, Block 3',
                'license_number'     => 'PH-2024-002',
                'license_expiry'     => '2026-09-30',
                'area'               => 'North District',
                'latitude'           => 33.9000,
                'longitude'          => 35.5100,
                'status'             => 'verified',
                'delivery_available' => false,
                'delivery_fee'       => 0,
                'working_hours'      => [
                    'monday' => '09:00-21:00', 'tuesday' => '09:00-21:00',
                    'wednesday' => '09:00-21:00', 'thursday' => '09:00-21:00',
                    'friday' => '09:00-21:00', 'saturday' => '10:00-18:00',
                    'sunday' => 'closed',
                ],
                'is_active'          => true,
            ],
        ];

        $createdPharmacies = [];
        foreach ($pharmacies as $pharma) {
            $createdPharmacies[] = User::create($pharma);
        }

        // ── Inventory ────────────────────────────────────────────────────
        foreach ($createdPharmacies as $pharmacy) {
            foreach ($createdMedicines as $i => $medicine) {
                InventoryItem::create([
                    'pharmacy_id'   => $pharmacy->id,
                    'medicine_id'   => $medicine->id,
                    'quantity'      => rand(50, 300),
                    'price'         => round(rand(3, 25) + 0.99, 2),
                    'cost_price'    => round(rand(2, 15) + 0.50, 2),
                    'minimum_stock' => 20,
                    'maximum_stock' => 500,
                    'expiry_date'   => '2027-06-30',
                ]);
            }
        }

        // ── Citizens ─────────────────────────────────────────────────────
        $citizens = [
            [
                'first_name' => 'Ahmed',
                'last_name'  => 'Ali',
                'email'      => 'ahmed@citizen.com',
                'password'   => Hash::make('Citizen@1234'),
                'role'       => 'citizen',
                'phone'      => '961712345678',
                'address'    => 'Downtown Beirut',
                'is_active'  => true,
            ],
            [
                'first_name' => 'Sara',
                'last_name'  => 'Hassan',
                'email'      => 'sara@citizen.com',
                'password'   => Hash::make('Citizen@1234'),
                'role'       => 'citizen',
                'phone'      => '961798765432',
                'address'    => 'North District',
                'is_active'  => true,
            ],
        ];

        $createdCitizens = [];
        foreach ($citizens as $citizen) {
            $createdCitizens[] = User::create($citizen);
        }

        // ── Sample Reviews ───────────────────────────────────────────────
        Review::create([
            'citizen_id'  => $createdCitizens[0]->id,
            'pharmacy_id' => $createdPharmacies[0]->id,
            'rating'      => 4.9,
            'review_text' => 'Excellent service, very fast delivery!',
        ]);

        Review::create([
            'citizen_id'  => $createdCitizens[1]->id,
            'pharmacy_id' => $createdPharmacies[0]->id,
            'rating'      => 4.5,
            'review_text' => 'Good pharmacy, staff is very helpful.',
        ]);

        $this->command->info('✅ MedLink database seeded successfully!');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',    'admin@medlink.com',    'Admin@1234'],
                ['Citizen',  'ahmed@citizen.com',    'Citizen@1234'],
                ['Citizen',  'sara@citizen.com',     'Citizen@1234'],
                ['Pharmacy', 'alshifa@pharmacy.com', 'Pharmacy@1234'],
                ['Pharmacy', 'citymed@pharmacy.com', 'Pharmacy@1234'],
            ]
        );
    }
}
