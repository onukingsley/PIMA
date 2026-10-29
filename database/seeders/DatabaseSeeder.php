<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ========== HELPER FUNCTIONS ==========
        $this->generateData();

        $this->command->info('✅ Database seeding completed successfully!');
    }

    private function generateData(): void
    {
        // ========== GET PROPER IDs FROM RESPECTIVE TABLES ==========
        // These will be populated after insertion
        $patientIds = collect();
        $doctorIds = collect();
        $nurseIds = collect();
        $labScientistIds = collect();
        $clerkIds = collect();
        $accountantIds = collect();
        $pharmaIds = collect();
        $categoryIds = collect();
        $rateIds = collect();
        $drugStockIds = collect();
        $labStockIds = collect();
        $diagnosisIds = collect();
        $paymentIds = collect();
        $pharmacyIds = collect();
        $branchIds = collect();
        $saleIds = collect();
        $labTestIds = collect();


        /*Pharmacy Table*/

        $plans = ['pima yanga','pima premium', 'pima banger', 'trial'];
        for ($i = 0; $i < 10; $i++) {
            $pharmacyPayload[] = [
                'pharmacy_name' => fake()->name,
                'email' => fake()->email,
                'phone_no' => fake()->phoneNumber,
                'reg_ID' => $this->generateRegID("pharmacy", $i),
                'subscription_name' => $plans[array_rand($plans)],
                'PIMA_ID' => $this->generateRegID("pharmacy", $i),
                'subscription_date' => now()->addMonths(rand(1, 10)),
                'created_at' => Carbon::now()->subDays(rand(1, 180)),
                'updated_at' => now(),
            ];
        }

        DB::table('pharmacies')->insert($pharmacyPayload);
        $pharmacyIds = DB::table('pharmacies')->pluck('id');
        $this->command->info('pharmacies seeded: ' . count($pharmacyPayload));


        /*Branch Table*/
        for ($i = 0; $i < 40; $i++) {
            $branchPayload[] = [
                'branch_name' => fake()->name(),
                'address' => fake()->address(),
                'pharmacy_id' => $pharmacyIds->random(),
                'phone_no' => fake()->phoneNumber,
                'created_at' => Carbon::now()->subDays(rand(1, 180)),
                'updated_at' => now(),
            ];
        }

        DB::table('branches')->insert($branchPayload);
        $branchIds = DB::table('branches')->pluck('id');
        $this->command->info('branches seeded: ' . count($branchPayload));


        /*subscription seed*/


        for ($i = 0; $i < 60; $i++) {
            $subscriptionSeed[] = [
                'subscription_name' => $plans[array_rand($plans)],
                'amount' => fake()->numberBetween(10000, 200000),
                'duration' => fake()->numberBetween(1, 24),
                'pharmacy_id' => $pharmacyIds->random(),
                'expiring_date' => Carbon::now()->subDays(rand(10, 365)),
                'created_at' => Carbon::now()->subDays(rand(1, 180)),
                'updated_at' => now(),
            ];
        }

        DB::table('subscriptions')->insert($subscriptionSeed);
        $this->command->info('subscription seeded: ' . count($subscriptionSeed));


        /*todo add branches ids to payment,sales, drug, restock etc....*/


        // ========== USERS TABLE ==========
        $users = [];
        $userRoles = ['sales', 'manager', 'admin'];
        $usedEmails = [];
        $usedRegIDs = [];
        $usedPhoneNos = [];

        // Generate multiple users for each role
        for ($i = 1; $i <= 50; $i++) {
            $role = $userRoles[array_rand($userRoles)];
            $name = $this->generateName($role, $i);
            $email = strtolower(str_replace(' ', '.', $name)) . rand(100, 999) . '@hospital.com';
            $regID = $this->generateRegID($role, $i);
            $phone = '080' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);

            // Ensure uniqueness
            while (in_array($email, $usedEmails)) {
                $email = strtolower(str_replace(' ', '.', $name)) . rand(100, 999) . '@hospital.com';
            }
            while (in_array($regID, $usedRegIDs)) {
                $regID = $this->generateRegID($role, $i . rand(1, 99));
            }
            while (in_array($phone, $usedPhoneNos)) {
                $phone = '080' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            }
            $pharmaSpecializations = ['Clinical Pharmacy', 'Industrial Pharmacy', 'Community Pharmacy', 'Hospital Pharmacy'];

            $usedEmails[] = $email;
            $usedRegIDs[] = $regID;
            $usedPhoneNos[] = $phone;

            $users[] = [
                'name' => $name,
                'email' => $email,
                'phone_no' => $phone,
                'regID' => $regID,
                'password' => Hash::make('password'),
                'user_role' => $role,
                'branch_id' => $branchIds->random(),
                'email_verified_at' => now(),
                'license_no' => 'PHA' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                'specialization' => $pharmaSpecializations[array_rand($pharmaSpecializations)],
                'date_of_birth' => Carbon::now()->subDays(rand(1, 10000)),
                'created_at' => Carbon::now()->subDays(rand(1, 365)),
                'updated_at' => now(),
            ];
        }


        // Add specific admin user
        $users[] = [
            'name' => 'Administrator',
            'email' => 'admin@hospital.com',
            'phone_no' => '08012345678',
            'regID' => 'ADMIN001',
            'password' => Hash::make('password'),
            'user_role' => 'admin',
            'branch_id' => $branchIds->random(),
            'license_no' => 'PHA' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
            'specialization' => $pharmaSpecializations[array_rand($pharmaSpecializations)],
            'date_of_birth' => Carbon::now()->subDays(rand(100, 10000)),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('users')->insert($users);
        $this->command->info('Users seeded: ' . count($users));

        $allUserIds = DB::table('users')->pluck('id');


        // ========== CATEGORIES TABLE ==========
        $categories = [
            ['name' => 'Antibiotics', 'description' => 'Medications used to treat bacterial infections', 'type' => 'drug'],
            ['name' => 'Pain Relievers', 'description' => 'Medications for pain management', 'type' => 'drug'],
            ['name' => 'Blood Tests', 'description' => 'Laboratory blood analysis tests', 'type' => 'lab'],
            ['name' => 'Medical Equipment', 'description' => 'Medical accessories and equipment', 'type' => 'accessories'],
            ['name' => 'Vitamins', 'description' => 'Dietary supplements and vitamins', 'type' => 'drug'],
            ['name' => 'Imaging Services', 'description' => 'X-ray, MRI, CT scan services', 'type' => 'lab'],
            ['name' => 'Antimalarials', 'description' => 'Medications for malaria treatment', 'type' => 'drug'],
            ['name' => 'Antihypertensives', 'description' => 'Blood pressure medications', 'type' => 'drug'],
            ['name' => 'Antidiabetics', 'description' => 'Diabetes medications', 'type' => 'drug'],
            ['name' => 'Lab Consumables', 'description' => 'Laboratory consumables and supplies', 'type' => 'lab'],
        ];

        DB::table('categories')->insert($categories);
        $categoryIds = DB::table('categories')->pluck('id');
        $this->command->info('Categories seeded: ' . count($categories));


        // ========== DRUG STOCKS TABLE ==========
        $drugNames = [
            ['Amoxicillin', 'Amoxicillin Trihydrate', 'Antibiotics'],
            ['Ciprofloxacin', 'Ciprofloxacin HCl', 'Antibiotics'],
            ['Paracetamol', 'Acetaminophen', 'Pain Relievers'],
            ['Ibuprofen', 'Ibuprofen', 'Pain Relievers'],
            ['Artemether/Lumefantrine', 'Artemether and Lumefantrine', 'Antimalarials'],
            ['Amlodipine', 'Amlodipine Besylate', 'Antihypertensives'],
            ['Metformin', 'Metformin Hydrochloride', 'Antidiabetics'],
            ['Vitamin C', 'Ascorbic Acid', 'Vitamins'],
            ['Vitamin D', 'Cholecalciferol', 'Vitamins'],
            ['Omeprazole', 'Omeprazole', 'Antibiotics'],
        ];

        $drugs = [];
        $statuses = ['inStock', 'lowStock', 'outOfStock', 'pendingApproval'];

        foreach ($drugNames as $drug) {
            $category = DB::table('categories')->where('name', $drug[2])->first();
            $drugs[] = [
                'category_id' => $category ? $category->id : 1,
                'name' => $drug[0],
                'generic' => $drug[1],
                'amount' => (string)rand(200, 5000),
                'status' => $statuses[array_rand($statuses)],
                'branch_id' => $branchIds->random(),
                'quantity' => (string)rand(50, 2000),
                'description' => $this->generateDrugDescription($drug[0]),
                'created_at' => Carbon::now()->subDays(rand(1, 365)),
                'updated_at' => now(),
            ];
        }

        DB::table('drugs')->insert($drugs);
        $drugStockIds = DB::table('drugs')->pluck('id');
        $this->command->info('Drug Stocks seeded: ' . count($drugs));


        $drugBatches = [];

        foreach ($drugNames as $drug) {
            $drugBatches[] = [
                'drug_id' => $drugStockIds->random(),
                'batch_no' => 'BCH' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'cost_price' => (string)rand(200, 5000),
                'selling_price' => (string)rand(200, 5000),
                'quantity' => (string)rand(50, 2000),
                'expiring_date' => Carbon::now()->subDays(rand(10, 365)),
                'created_at' => Carbon::now()->subDays(rand(1, 365)),
                'updated_at' => now(),
            ];
        }

        DB::table('drug_batches')->insert($drugBatches);
        $drugBatchesIds = DB::table('drug_batches')->pluck('id');
        $this->command->info('Drug Batches seeded: ' . count($drugBatches));


        // ========== PAYMENTS TABLE ==========
        $payments = [];
        $paymentTypes = ['drugSales',  'drugStock'];
        $paymentStatuses = ['credit', 'debit'];

        for ($i = 0; $i < 200; $i++) {
            $payments[] = [
                'title' => $paymentTypes[array_rand($paymentTypes)] . ' payment',
                'description' => 'Payment for ' . $paymentTypes[array_rand($paymentTypes)],
                'payment_type' => $paymentTypes[array_rand($paymentTypes)],
                'user_id' => $allUserIds->random(),
                'invoice_id' => 'INV' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'amount' => (string)rand(1000, 100000),
                'branch_id' => $branchIds->random(),
                'status' => $paymentStatuses[array_rand($paymentStatuses)],
                'created_at' => Carbon::now()->subDays(rand(1, 180)),
                'updated_at' => now(),
            ];
        }

        DB::table('payments')->insert($payments);
        $paymentIds = DB::table('payments')->pluck('id');
        $this->command->info('Payments seeded: ' . count($payments));

        // ========== SALES TABLE ==========
        $sales = [];
        $deliveryStatuses = ['unissued', 'pending', 'delivered'];
        $paymentSalesStatuses = ['paid', 'unpaid'];

        for ($i = 0; $i < 100; $i++) {
            $sales[] = [
                'user_id' => $allUserIds->random(),
                'payment_id' => $paymentIds->random(),
                'total_amount' => (string)rand(1000, 50000),
                'branch_id' => $branchIds->random(),
                'payment_status' => $paymentSalesStatuses[array_rand($paymentSalesStatuses)],
                'delivery_status' => $deliveryStatuses[array_rand($deliveryStatuses)],
                'created_at' => Carbon::now()->subDays(rand(1, 180)),
                'updated_at' => now(),
            ];
        }

        DB::table('sales')->insert($sales);
        $saleIds = DB::table('sales')->pluck('id');
        $this->command->info('Sales seeded: ' . count($sales));

        // ========== DRUG SALES TABLE ==========
        $drugSales = [];
        $dosages = ['500mg', '250mg', '1000mg', '750mg', '100mg', '50mg'];
        $drugStatuses = ['active', 'cancelled', 'completed'];

        foreach ($saleIds as $saleId) {
            $numDrugs = rand(1, 4);
            for ($i = 0; $i < $numDrugs; $i++) {
                $drugSales[] = [
                    'sales_id' => $saleId,
                    'drug_id' => $drugStockIds->random(),
                    'drug_batch_id' => $drugBatchesIds->random(),
                    'quantity' => (string)rand(1, 30),
                    'dosage' => $dosages[array_rand($dosages)],
                    'status' => $drugStatuses[array_rand($drugStatuses)],
                    'unit_price' => (string)rand(100, 5000),
                    'created_at' => Carbon::now()->subDays(rand(1, 180)),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('drug_sales')->insert($drugSales);
        $this->command->info('Drug Sales seeded: ' . count($drugSales));


        // ========== STOCK REQUESTS TABLE ==========
        $stockRequests = [];
        $requestStatuses = ['pending', 'approved', 'rejected'];


        for ($i = 0; $i < 50; $i++) {

            $stockRequests[] = [
                'drug_id' => $drugStockIds->random(),
                'user_id' => $allUserIds->random(),
                'quantity' => (string)rand(50, 1000),
                /*'payment_id' => $paymentIds->random(),*/
                'unit_price' => (string)rand(100, 5000),
                'branch_id' => $branchIds->random(),
                'title' => 'Stock request for ' . $this->generateName('product', $i),
                'status' => $requestStatuses[array_rand($requestStatuses)],
                'purchase_date' => Carbon::now()->subDays(rand(1, 180)),
                'created_at' => Carbon::now()->subDays(rand(1, 180)),
                'updated_at' => now(),
            ];
        }

        DB::table('restocks')->insert($stockRequests);
        $this->command->info('Stock Requests seeded: ' . count($stockRequests));


        // ========== SALARY ALLOWANCES TABLE ==========
        $salaryAllowances = [];
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        foreach ($allUserIds as $userId) {
            if (rand(1, 10) <= 3) {
                $salaryAllowances[] = [
                    'user_id' => $userId,
                    'payment_id' => $paymentIds->random(),
                    'month' => $months[array_rand($months)],
                    'year' => (string)rand(2023, 2026),
                    'created_at' => Carbon::now()->subDays(rand(1, 180)),
                    'updated_at' => now(),
                ];
            }
        }

    }
    // ========== HELPER FUNCTIONS ==========

    private function generateName($role, $index): string
    {
        $firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'James', 'Mary', 'Robert', 'Patricia', 'William', 'Jennifer', 'Richard', 'Linda', 'Joseph', 'Barbara', 'Thomas', 'Elizabeth', 'Charles', 'Susan'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee'];

        if ($role === 'emergency') {
            return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
        }

        if ($role === 'doctor') {
            return 'Dr. ' . $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
        }

        if ($role === 'nurse') {
            return 'Nurse ' . $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
        }

        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    private function generateRegID($role, $index): string
    {
        $prefixes = [
            'patient' => 'PAT',
            'doctor' => 'DOC',
            'nurse' => 'NRS',
            'labScientist' => 'LAB',
            'clerk' => 'CLK',
            'accountant' => 'ACC',
            'pharmasist' => 'PHA',
            'admin' => 'ADMIN',
            'sales' => 'SLE',
            'manager' => 'MGR'
        ];

        $prefix = $prefixes[$role] ?? 'USR';
        return $prefix . str_pad($index, 3, '0', STR_PAD_LEFT);
    }

    private function generateAddress(): string
    {
        $streetNames = ['Main', 'Park', 'Oak', 'Pine', 'Maple', 'Cedar', 'Elm', 'Lake', 'Hill', 'Forest'];
        $streetTypes = ['St', 'Ave', 'Rd', 'Blvd', 'Ln', 'Dr'];
        $cities = ['Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Kano', 'Enugu', 'Calabar', 'Warri'];

        return rand(1, 999) . ' ' . $streetNames[array_rand($streetNames)] . ' ' . $streetTypes[array_rand($streetTypes)] . ', ' . $cities[array_rand($cities)] . ', Nigeria';
    }

    private function generateDrugDescription($name): string
    {
        $descriptions = [
            'Amoxicillin' => 'Broad-spectrum antibiotic for bacterial infections',
            'Ciprofloxacin' => 'Fluoroquinolone antibiotic for various infections',
            'Paracetamol' => 'Pain reliever and fever reducer',
            'Ibuprofen' => 'NSAID for pain and inflammation',
            'Artemether/Lumefantrine' => 'Antimalarial medication for uncomplicated malaria',
            'Amlodipine' => 'Calcium channel blocker for hypertension',
            'Metformin' => 'First-line medication for type 2 diabetes',
            'Vitamin C' => 'Immune system support and antioxidant',
            'Vitamin D' => 'Essential for bone health and immune function',
            'Omeprazole' => 'Proton pump inhibitor for acid reflux',
        ];

        return $descriptions[$name] ?? 'Medication for treatment of various conditions';
    }

    private function generateLabResult(): string
    {
        $results = [
            'Normal range - No abnormalities detected',
            'Elevated white blood cell count - Possible infection',
            'Low hemoglobin - Anemia detected',
            'Positive for malaria parasites',
            'Negative for malaria parasites',
            'Elevated glucose levels - Diabetes risk',
            'Normal liver function test results',
            'Elevated cholesterol levels',
            'Normal kidney function test results',
            'Positive for COVID-19 PCR',
            'Negative for COVID-19 PCR',
            'Normal urinalysis results',
        ];

        return $results[array_rand($results)];
    }

    private function generateRemark(): string
    {
        $remarks = [
            'Follow-up required in 2 weeks',
            'Immediate attention needed',
            'Results within normal limits',
            'Slight deviation from normal range',
            'Close monitoring required',
            'Patient to be informed of results',
            'Further testing required',
            'Urgent referral to specialist',
            'Results pending confirmation',
            'Repeat test recommended',
        ];

        return $remarks[array_rand($remarks)];
    }

    private function generateReportDescription(): string
    {
        $descriptions = [
            'Comprehensive analysis of department performance',
            'Detailed breakdown of financial transactions',
            'Statistical analysis of patient data',
            'Inventory management and stock utilization report',
            'Quality assurance and improvement metrics',
            'Staff productivity and performance review',
            'Patient satisfaction survey results',
            'Annual departmental performance summary',
        ];

        return $descriptions[array_rand($descriptions)];
    }
}
