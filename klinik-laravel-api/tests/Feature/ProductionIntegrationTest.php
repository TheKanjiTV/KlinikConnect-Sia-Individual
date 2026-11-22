<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Stripe\StripeClient;

test('every registered API controller action exists', function () {
    foreach (Route::getRoutes() as $route) {
        if (!str_starts_with($route->uri(), 'api/v1/') || !str_contains($route->getActionName(), '@')) {
            continue;
        }
        [$class, $method] = explode('@', $route->getActionName());
        expect(method_exists($class, $method))->toBeTrue($route->uri().' missing '.$method);
    }
});

test('all role dashboard and list routes handle a fresh seeded database', function () {
    $this->seed(DemoDataSeeder::class);
    $patient = User::where('email', 'patient@gmail.com')->firstOrFail();
    $doctor = Doctor::firstOrFail();
    Appointment::create([
        'email' => $patient->email, 'doctor_id' => $doctor->id, 'day' => 'Monday',
        'start_time' => '09:00:00', 'end_time' => '09:15:00', 'date' => '2026-12-07',
        'payment_status' => 'paid', 'general_status' => 'scheduled', 'order_id' => 'read-fixture',
        'session_id' => 'cs_read_fixture', 'user_id' => $patient->id,
    ]);
    $failures = [];
    foreach (Route::getRoutes() as $route) {
        if (!in_array('GET', $route->methods()) || !str_starts_with($route->uri(), 'api/v1/')
            || str_contains($route->uri(), 'integrations/') || str_ends_with($route->uri(), '/sse')
            || str_contains($route->uri(), 'delete-department')) {
            continue;
        }
        $middleware = implode(',', $route->gatherMiddleware());
        $role = 'admin';
        if (str_contains($middleware, "doctor") && !str_contains($middleware, 'admin')) {
            $role = 'doctor';
        } elseif (str_contains($middleware, 'patient')) {
            $role = 'patient';
        } elseif (str_contains($middleware, 'pharmacist') && !str_contains($middleware, 'admin')) {
            $role = 'pharmacist';
        } elseif (str_contains($middleware, 'nurse') && !str_contains($middleware, 'admin')) {
            $role = 'nurse';
        }
        $user = User::where('email', $role.'@gmail.com')->firstOrFail();
        $response = $this->actingAs($user)->getJson('/'.$route->uri().'?'.http_build_query([
            'per_page' => 15, 'search_query' => '', 'role' => 'all', 'status' => 'paid', 'paymentStatus' => 'paid',
        ]));
        if ($response->status() >= 500) {
            $failures[] = $route->uri().' '.$response->getContent();
        }
    }
    expect($failures)->toBeEmpty(implode("\n", $failures));
});

test('bearer tokens reach role endpoints and logout revokes the current token', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $token = $admin->createToken('integration-test');
    $this->withToken($token->plainTextToken)->getJson('/api/v1/fetch-all-offers')->assertOk();
    $this->withToken($token->plainTextToken)->postJson('/api/v1/logout')->assertNoContent();
    expect(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeFalse();
});

test('staff cannot enter patient routes and guests cannot inspect private user data', function () {
    $this->getJson('/api/v1/get-user-data')->assertUnauthorized();
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->getJson('/api/v1/patient-dashboard-data')->assertUnauthorized();
});

test('input errors return validation responses and optional medicine fields can be omitted', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->postJson('/api/v1/add-medicine', [])->assertUnprocessable();
    $this->postJson('/api/v1/add-medicine', [
        'medicineName' => 'Regression Medicine', 'medicineCategory' => 'antipyretic',
        'medicinePrice' => 5, 'manufacturingCompany' => 'Test', 'medicineStatus' => 'instock',
        'expirationDate' => '2030-01-01', 'quantity' => 2,
    ])->assertOk()->assertJsonPath('newMedicine.description', null);
    $this->getJson('/api/v1/fetch-appointments-by-status?paymentStatus=invalid')->assertUnprocessable();
});

test('signed webhooks authenticate reject replay conflicts and store each event once', function () {
    config(['integrations.inbound_api_key' => 'test-key', 'integrations.verify_secret' => 'test-secret']);
    $this->getJson('/api/v1/integrations/health')->assertUnauthorized();
    $this->withHeader('X-API-Key', 'test-key')->getJson('/api/v1/integrations/health')->assertOk();
    $body = json_encode(['event_id' => 'test-1', 'event_type' => 'connection.test', 'source' => 'test', 'data' => ['ok' => true]]);
    $timestamp = (string) time();
    $headers = [
        'HTTP_X_API_KEY' => 'test-key', 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
        'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'test-secret'),
    ];
    $this->call('POST', '/api/v1/integrations/webhook', [], [], [], $headers, $body)->assertStatus(202);
    $this->call('POST', '/api/v1/integrations/webhook', [], [], [], $headers, $body)->assertOk()->assertJsonPath('status', 'duplicate');
    $changed = str_replace('true', 'false', $body);
    $this->call('POST', '/api/v1/integrations/webhook', [], [], [], $headers, $changed)->assertUnauthorized();
    $headers['HTTP_X_WEBHOOK_SIGNATURE'] = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$changed, 'test-secret');
    $this->call('POST', '/api/v1/integrations/webhook', [], [], [], $headers, $changed)->assertConflict();
    expect(DB::table('integration_events')->count())->toBe(1);
});

test('billing connector uses the documented sales endpoint and fails clearly without credentials', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    config(['integrations.billing.token' => null]);
    $this->getJson('/api/v1/integrations/billing/invoices')->assertStatus(503);
    config(['integrations.billing.token' => 'billing-test']);
    Http::fake(['*/api/v1/sales/invoices*' => Http::response(['data' => []])]);
    $this->getJson('/api/v1/integrations/billing/invoices')->assertOk()->assertJson(['data' => []]);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer billing-test')
        && str_contains($request->url(), '/api/v1/sales/invoices'));
});

test('payment confirmation requires ownership and a paid complete Stripe session', function () {
    $this->seed(DemoDataSeeder::class);
    Mail::fake();
    $patient = User::where('email', 'patient@gmail.com')->firstOrFail();
    $offer = Offer::create(['name' => 'Test', 'total_before_discount' => 10, 'discount_value' => 0,
        'total_after_discount' => 10, 'tax_rate' => 0, 'total_with_tax' => 10]);
    $order = Order::create(['status' => 'unpaid', 'total_price' => 10, 'session_id' => 'cs_test',
        'user_id' => $patient->id, 'offer_id' => $offer->id, 'order_id' => 'test-order',
        'full_name' => 'Patient', 'gender' => 'male', 'national_card_id' => 'test']);
    config(['services.stripe.secret' => 'test-secret']);
    $session = (object) ['payment_status' => 'unpaid', 'status' => 'open'];
    $sessions = Mockery::mock();
    $sessions->shouldReceive('retrieve')->with('cs_test')->andReturn($session);
    app()->instance(StripeClient::class, (object) ['checkout' => (object) ['sessions' => $sessions]]);
    $this->actingAs($patient)->postJson('/api/v1/order-success-payment', ['sessionId' => 'cs_test'])->assertConflict();
    expect($order->fresh()->status)->toBe('unpaid');
    $session->payment_status = 'paid';
    $session->status = 'complete';
    $this->postJson('/api/v1/order-success-payment', ['sessionId' => 'cs_test'])->assertOk();
    $this->postJson('/api/v1/order-success-payment', ['sessionId' => 'cs_test'])->assertOk();
    Mail::assertSentCount(1);
    $other = User::factory()->create();
    $this->actingAs($other)->postJson('/api/v1/order-success-payment', ['sessionId' => 'cs_test'])->assertNotFound();
});

test('API login works without browser session middleware', function () {
    $user = User::factory()->create();
    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()->assertJsonStructure(['token', 'status']);
});

test('appointment reservation rolls back failed checkout and prevents duplicate bookings', function () {
    $this->seed(DemoDataSeeder::class);
    config(['services.stripe.secret' => 'test-secret']);
    $doctor = Doctor::firstOrFail();
    $date = now()->addWeek()->format('Y-m-d');
    $day = now()->addWeek()->format('l');
    $slot = \App\Models\DoctorTime::create([
        'doctor_id' => $doctor->id, 'day' => $day,
        'start_time' => '09:00:00', 'end_time' => '09:15:00',
    ]);
    $patient = User::where('email', 'patient@gmail.com')->firstOrFail();
    $payload = ['day' => $day, 'date' => $date, 'doctor' => $doctor->id, 'startTime' => '09:00'];
    $sessions = Mockery::mock();
    $sessions->shouldReceive('create')->once()->andThrow(new RuntimeException('Checkout unavailable'));
    app()->instance(StripeClient::class, (object) ['checkout' => (object) ['sessions' => $sessions]]);
    $this->actingAs($patient)->postJson('/api/v1/appointments-register-appointment', $payload)->assertStatus(502);
    expect($slot->fresh()->date)->toBeNull();
    expect(Appointment::count())->toBe(0);
    $sessions = Mockery::mock();
    $sessions->shouldReceive('create')->once()->andReturn((object) ['id' => 'cs_booking_test', 'url' => 'https://checkout.stripe.com/test']);
    app()->instance(StripeClient::class, (object) ['checkout' => (object) ['sessions' => $sessions]]);
    $this->postJson('/api/v1/appointments-register-appointment', $payload)->assertOk();
    $this->postJson('/api/v1/appointments-register-appointment', $payload)->assertConflict();
    expect(Appointment::count())->toBe(1);
    expect($slot->fresh()->date)->toBe([$date]);
    $appointment = Appointment::firstOrFail();
    $appointment->update(['general_status' => 'canceled']);
    expect($slot->fresh()->date)->toBe([]);
});

test('signed outbound delivery matches the receiver protocol', function () {
    config([
        'integrations.outbound_webhook_url' => 'https://peer.example.test/webhook',
        'integrations.outbound_api_key' => 'outbound-test', 'integrations.signing_secret' => 'signing-test',
    ]);
    Http::fake(['peer.example.test/*' => Http::response(['status' => 'received'], 202)]);
    app(\App\Services\SendIntegrationWebhook::class)->send([
        'event_id' => 'outbound-1', 'event_type' => 'connection.test', 'source' => 'klinikconnect', 'data' => ['ok' => true],
    ]);
    Http::assertSent(function ($request) {
        $timestamp = $request->header('X-Webhook-Timestamp')[0];
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'signing-test');
        return $request->hasHeader('X-Webhook-Signature', $signature)
            && $request->hasHeader('X-API-Key', 'outbound-test');
    });
});

test('clinical and catalog records can be created updated and deleted', function () {
    $this->seed(DemoDataSeeder::class);
    $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());
    $cases = [
        ['blood', 'blood_id', 'newBlood', ['bloodName' => 'Whole Blood', 'bloodType' => 'O+', 'quantity' => 2]],
        ['blood-donor', 'bloodDonorId', 'newBloodDonor', ['donorName' => 'Test', 'donorAge' => 25, 'donorSex' => 'male',
            'quantity' => 1, 'bloodName' => 'Whole Blood', 'bloodType' => 'O+', 'lastDonationDate' => '2026-01-01', 'identityCardId' => '123']],
        ['medicine', 'medicineId', 'newMedicine', ['medicineName' => 'Test', 'medicineCategory' => 'antipyretic', 'medicinePrice' => 5,
            'manufacturingCompany' => 'Test', 'medicineStatus' => 'instock', 'expirationDate' => '2030-01-01', 'quantity' => 1]],
        ['service', 'serviceId', 'newService', ['serviceTitle' => 'Test', 'servicePrice' => 5, 'serviceStatus' => true]],
        ['bed-allotment', 'bedAllotmentId', 'newBedAllotment', ['bedNumber' => 1, 'bedType' => 'General',
            'allotmentDate' => '2026-01-01', 'patientEmail' => 'patient@gmail.com']],
        ['vaccine', 'vaccineId', 'newVaccine', ['patientEmail' => 'patient@gmail.com', 'nurseEmail' => 'nurse@gmail.com',
            'vaccineName' => 'Test', 'doseNumber' => '1', 'dateGiven' => '2026-01-01']],
        ['birth-report', 'birthReportId', 'newBirthReport', ['birthDetails' => 'Test', 'birthDate' => '2026-01-01',
            'patientEmail' => 'patient@gmail.com', 'doctorEmail' => 'doctor@gmail.com']],
        ['death-report', 'deathReportId', 'newDeathReport', ['deathDetails' => 'Test', 'deathDate' => '2026-01-01',
            'patientEmail' => 'patient@gmail.com', 'doctorEmail' => 'doctor@gmail.com']],
        ['operation-report', 'operationReportId', 'newOperationReport', ['operationDetails' => 'Test', 'operationDate' => '2026-01-01',
            'patientEmail' => 'patient@gmail.com', 'doctorEmail' => 'doctor@gmail.com', 'departmentId' => $departmentId = Doctor::firstOrFail()->department_id]],
    ];
    foreach ($cases as [$resource, $idField, $resultKey, $body]) {
        $response = $this->postJson('/api/v1/add-'.$resource, $body);
        expect($response->status())->toBe(200, $resource.' create '.$response->getContent());
        $id = $response->json($resultKey.'.id');
        $update = $body + [$idField => $id];
        if ($resource === 'blood-donor') {
            $update['bloodId'] = $id;
        }
        if (in_array($resource, ['birth-report', 'death-report'], true)) {
            $update['date'] = '2026-01-02';
        }
        if ($resource === 'operation-report') {
            $update += ['date' => '2026-01-02', 'patient_email' => 'patient@gmail.com', 'doctor_email' => 'doctor@gmail.com',
                'department_name' => 'General Medicine', 'operation_details' => 'Updated', 'department_id' => $departmentId];
        }
        $response = $this->postJson('/api/v1/update-'.$resource, $update);
        expect($response->status())->toBe(200, $resource.' update '.$response->getContent());
        $this->postJson('/api/v1/delete-'.$resource, [$idField => $id])->assertOk();
    }
});
