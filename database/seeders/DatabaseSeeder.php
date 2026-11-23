<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Ambulance;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Models\Hospital;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Real Administrators with Hashed Passwords
        $admins = [
            [
                'email' => 'admin@gmail.com',
                'name' => 'Super Administrator',
                'role' => 'super_admin',
                'role_name' => 'Super Administrator',
                'badge_number' => 'HQ-ADMIN-01',
                'phone' => '+91 422 2485000',
                'password' => 'Admin@1234',
                'mfa_enabled' => false,
            ],
            [
                'email' => 'staff.admin@liferoute.org',
                'name' => 'Command Center Administrator',
                'role' => 'super_admin',
                'role_name' => 'Super Administrator',
                'badge_number' => 'HQ-STAFF-02',
                'phone' => '+91 422 2485000',
                'password' => 'Admin@1234',
                'mfa_enabled' => false,
            ],
            [
                'email' => 'admin@ambulance.gov',
                'name' => 'Commander Sarah Jenkins',
                'role' => 'super_admin',
                'role_name' => 'Super Administrator',
                'badge_number' => 'CMD-108-01',
                'phone' => '+91 94441 23456',
                'password' => 'AdminSecure2026!',
                'mfa_enabled' => false,
            ],
            [
                'email' => 'dispatch@ambulance.gov',
                'name' => 'Officer David Ross',
                'role' => 'dispatcher',
                'role_name' => 'Senior Dispatcher',
                'badge_number' => 'DSP-108-04',
                'phone' => '+91 98400 98765',
                'password' => 'DispatchSecure2026!',
                'mfa_enabled' => false,
            ],
            [
                'email' => 'hospital@ambulance.gov',
                'name' => 'Dr. Robert Vance',
                'role' => 'hospital_manager',
                'role_name' => 'Hospital Network Director',
                'badge_number' => 'MED-108-12',
                'phone' => '+91 97890 12345',
                'password' => 'HospitalSecure2026!',
                'mfa_enabled' => false,
            ],
        ];

        foreach ($admins as $a) {
            Admin::updateOrCreate(
                ['email' => $a['email']],
                [
                    'name' => $a['name'],
                    'role' => $a['role'],
                    'role_name' => $a['role_name'],
                    'badge_number' => $a['badge_number'],
                    'phone' => $a['phone'],
                    'password' => $a['password'], // Mutator hashes it automatically
                    'mfa_enabled' => $a['mfa_enabled'],
                ]
            );
        }

        // 2. Seed Verified Real Hospitals
        $hospitals = [
            [
                'slug' => 'cmch-coimbatore-108',
                'name' => 'Government Coimbatore Medical College Hospital (CMCH 108)',
                'address' => 'Trichy Road, Gopalapuram, Coimbatore, Tamil Nadu 641018',
                'lat' => 11.0015,
                'lng' => 76.9695,
                'emergency_phone' => '108',
                'emergency_services' => ['Free 108 Emergency', 'Level 1 Trauma Care', '24x7 Casualty', '100-Bed Emergency ICU', 'Blood Bank'],
                'trauma_level' => 'Level 1 Trauma Care',
                'has_trauma_center' => true,
                'has_icu_bed' => true,
                'total_icu_beds' => 120,
                'available_icu_beds' => 28,
                'has_cardiac_care' => true,
                'has_stroke_unit' => true,
                'has_blood_bank' => true,
                'operating_hours' => '24x7 Emergency',
                'verified' => true,
            ],
            [
                'slug' => 'ganga-hospital-saibaba',
                'name' => 'Ganga Hospital & Orthopaedic Trauma Centre',
                'address' => '313, Mettupalayam Road, Saibaba Colony, Coimbatore, Tamil Nadu 641011',
                'lat' => 11.0285,
                'lng' => 76.9480,
                'emergency_phone' => '+91 422 248 5000',
                'emergency_services' => ['Level 1 Super-Specialty Trauma', 'Microvascular Replantation', '24x7 Casualty', 'Burns ICU'],
                'trauma_level' => 'Level 1 Trauma Care',
                'has_trauma_center' => true,
                'has_icu_bed' => true,
                'total_icu_beds' => 80,
                'available_icu_beds' => 18,
                'has_cardiac_care' => true,
                'has_stroke_unit' => true,
                'has_blood_bank' => true,
                'operating_hours' => '24x7 Emergency',
                'verified' => true,
            ],
            [
                'slug' => 'ramakrishna-hospital',
                'name' => 'Sri Ramakrishna Hospital & Emergency Trauma Centre',
                'address' => '395, Sarojini Naidu Road, Siddhapudur, Coimbatore, Tamil Nadu 641044',
                'lat' => 11.0228,
                'lng' => 76.9745,
                'emergency_phone' => '+91 422 450 0000',
                'emergency_services' => ['24x7 Emergency', 'Comprehensive Stroke Unit', 'Primary PCI Cath Lab', 'Neuro ICU'],
                'trauma_level' => 'Level 1 Trauma Care',
                'has_trauma_center' => true,
                'has_icu_bed' => true,
                'total_icu_beds' => 65,
                'available_icu_beds' => 14,
                'has_cardiac_care' => true,
                'has_stroke_unit' => true,
                'has_blood_bank' => true,
                'operating_hours' => '24x7 Emergency',
                'verified' => true,
            ],
            [
                'slug' => 'kmch-hospital-main',
                'name' => 'Kovai Medical Center and Hospital (KMCH Level 1 Trauma)',
                'address' => '99, Avinashi Road, Civil Aerodrome Post, Peelamedu, Coimbatore, Tamil Nadu 641014',
                'lat' => 11.0425,
                'lng' => 77.0370,
                'emergency_phone' => '+91 422 432 3800',
                'emergency_services' => ['Level 1 Comprehensive Trauma', 'Air Ambulance & Helipad', '24x7 Stroke Cath Lab', 'Organ Transplant ICU'],
                'trauma_level' => 'Level 1 Comprehensive Trauma',
                'has_trauma_center' => true,
                'has_icu_bed' => true,
                'total_icu_beds' => 150,
                'available_icu_beds' => 35,
                'has_cardiac_care' => true,
                'has_stroke_unit' => true,
                'has_blood_bank' => true,
                'operating_hours' => '24x7 Emergency',
                'verified' => true,
            ],
            [
                'slug' => 'psg-hospitals-peelamedu',
                'name' => 'PSG Hospitals & Super Specialty Trauma Care',
                'address' => 'Avinashi Road, Peelamedu, Coimbatore, Tamil Nadu 641004',
                'lat' => 11.0298,
                'lng' => 77.0034,
                'emergency_phone' => '+91 422 434 5353',
                'emergency_services' => ['24x7 Emergency', 'Trauma Resuscitation Center', 'Pediatric ICU', 'Dialysis Bay'],
                'trauma_level' => 'Level 1 Trauma Care',
                'has_trauma_center' => true,
                'has_icu_bed' => true,
                'total_icu_beds' => 90,
                'available_icu_beds' => 22,
                'has_cardiac_care' => true,
                'has_stroke_unit' => true,
                'has_blood_bank' => true,
                'operating_hours' => '24x7 Emergency',
                'verified' => true,
            ],
        ];

        foreach ($hospitals as $h) {
            Hospital::updateOrCreate(['slug' => $h['slug']], $h);
        }

        // 3. Seed Verified Ambulance Units
        $ambulances = [
            [
                'registration_number' => 'TN-01-EA-1080',
                'vehicle_type' => 'als',
                'vehicle_type_display' => 'Advanced Life Support (ALS)',
                'model' => 'Force Traveller ICU Ambulance',
                'year' => 2024,
                'status' => 'available',
                'lat' => 11.0210,
                'lng' => 76.9380,
                'equipment_list' => ['Transport Ventilator', 'Biphasic Defibrillator', 'Multi-Para Monitor', 'Suction Machine', 'Syringe Infusion Pumps', 'Oxygen Tank Array'],
            ],
            [
                'registration_number' => 'TN-38-AL-1081',
                'vehicle_type' => 'als',
                'vehicle_type_display' => 'Advanced Life Support (ALS)',
                'model' => 'Tata Winger ICU Mobile Ambulance',
                'year' => 2024,
                'status' => 'available',
                'lat' => 11.0290,
                'lng' => 76.9580,
                'equipment_list' => ['Transport Ventilator', 'Automated External Defibrillator (AED)', 'Pulse Oximeter', 'Spine Board', 'Oxygen Delivery'],
            ],
            [
                'registration_number' => 'TN-38-BL-1082',
                'vehicle_type' => 'bls',
                'vehicle_type_display' => 'Basic Life Support (BLS)',
                'model' => 'Mahindra Bolero Emergency Ambulance',
                'year' => 2023,
                'status' => 'available',
                'lat' => 11.0150,
                'lng' => 76.9720,
                'equipment_list' => ['AED', 'Manual Resuscitator (Ambu Bag)', 'First Aid Kit', 'Foldable Stretcher', 'Oxygen Cylinder'],
            ],
            [
                'registration_number' => 'TN-66-TR-1083',
                'vehicle_type' => 'als',
                'vehicle_type_display' => 'Critical Neonatal / Pediatric ICU',
                'model' => 'Force Traveller Mobile ICU',
                'year' => 2025,
                'status' => 'available',
                'lat' => 11.0380,
                'lng' => 77.0150,
                'equipment_list' => ['Neonatal Transport Incubator', 'Pediatric Ventilator', 'Vital Signs Monitor', 'Infusion Pump'],
            ],
        ];

        foreach ($ambulances as $amb) {
            Ambulance::updateOrCreate(['registration_number' => $amb['registration_number']], $amb);
        }

        // 4. Seed Verified Duty Driver
        Driver::updateOrCreate(
            ['phone_key' => '9840010801'],
            [
                'name' => 'Paramedic Rajesh Kumar',
                'phone' => '+91 98400 10801',
                'vehicle_number' => 'TN-01-EA-1080',
                'vehicle_plate' => 'TN-01-EA-1080',
                'vehicle_type' => 'als',
                'vehicle_type_display' => 'Advanced Life Support (ALS)',
                'vehicle_model' => 'Force Traveller ICU Ambulance',
                'license_number' => 'TN-38-20180001080',
                'verification_status' => 'approved',
                'is_online' => true,
                'lat' => 11.0210,
                'lng' => 76.9380,
                'heading' => 45.0,
                'speed' => 0.0,
                'rating' => 4.95,
                'total_trips' => 142,
            ]
        );

        if (DriverNotification::count() === 0) {
            DriverNotification::create([
                'title' => '🏥 Central Dispatch System Online',
                'message' => 'LifeRoute emergency dispatch server, verified hospital database, and real-time GPS tracking channels are fully operational.',
                'type' => 'system',
            ]);
        }
    }
}
