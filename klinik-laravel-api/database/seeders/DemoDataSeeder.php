<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\Laboratorist;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Create the demo accounts advertised on the login screen.
     * The seeder is idempotent so it can run on every container start.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'General Medicine', 'slug' => 'general-medicine', 'desc' => 'Primary care and general health consultations.'],
            ['name' => 'Pediatrics', 'slug' => 'pediatrics', 'desc' => 'Medical care for infants, children, and adolescents.'],
            ['name' => 'Cardiology', 'slug' => 'cardiology', 'desc' => 'Heart and cardiovascular health services.'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(['slug' => $department['slug']], $department);
        }

        $accounts = [
            ['name' => 'Admin User', 'email' => 'admin@gmail.com', 'role' => 'is_admin'],
            ['name' => 'Doctor User', 'email' => 'doctor@gmail.com', 'role' => 'is_doctor'],
            ['name' => 'Nurse User', 'email' => 'nurse@gmail.com', 'role' => 'is_nurse'],
            ['name' => 'Pharmacist User', 'email' => 'pharmacist@gmail.com', 'role' => 'is_pharmacist'],
            ['name' => 'Laboratorist User', 'email' => 'laboratorist@gmail.com', 'role' => 'is_laboratorist'],
            ['name' => 'Patient User', 'email' => 'patient@gmail.com', 'role' => 'is_patient'],
        ];

        $roles = ['is_admin', 'is_doctor', 'is_nurse', 'is_pharmacist', 'is_laboratorist'];

        foreach ($accounts as $account) {
            $attributes = [
                'name' => $account['name'],
                'password' => Hash::make('islamislam'),
                'email_verified_at' => now(),
            ];

            foreach ($roles as $role) {
                $attributes[$role] = $account['role'] === $role;
            }

            User::updateOrCreate(['email' => $account['email']], $attributes);
        }

        $department = Department::where('slug', 'general-medicine')->first();

        $doctorUser = User::where('email', 'doctor@gmail.com')->first();
        Doctor::updateOrCreate(
            ['email' => $doctorUser->email],
            [
                'user_id' => $doctorUser->id,
                'department_id' => $department->id,
                'name' => $doctorUser->name,
                'small_description' => 'General medicine physician with a focus on preventive care.',
                'qualification' => 'MD',
                'appointment_price' => 50,
                'consultation_price' => 75,
            ]
        );

        foreach ([
            'nurse@gmail.com' => Nurse::class,
            'pharmacist@gmail.com' => Pharmacist::class,
            'laboratorist@gmail.com' => Laboratorist::class,
        ] as $email => $model) {
            $user = User::where('email', $email)->first();
            $model::updateOrCreate(
                ['email' => $user->email],
                ['user_id' => $user->id, 'name' => $user->name]
            );
        }

        $patientUser = User::where('email', 'patient@gmail.com')->first();
        Patient::updateOrCreate(
            ['email' => $patientUser->email],
            [
                'user_id' => $patientUser->id,
                'name' => $patientUser->name,
                'gender' => 'female',
                'blood_group' => 'O+',
                'notes' => 'Demo patient account.',
            ]
        );
    }
}
