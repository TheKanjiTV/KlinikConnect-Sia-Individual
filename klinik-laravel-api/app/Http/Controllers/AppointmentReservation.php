<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Department;
use App\Models\DoctorTime;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Models\AppointmentNotification;
use App\Events\AppointmentNotifications;
use App\Mail\AppointmentReservasionSuccess;
use App\Models\AppointmentNotification as ModelsAppointmentNotification;
use App\Models\Patient;
use OpenApi\Annotations as OA;

class AppointmentReservation extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/v1/departments",
     *     summary="Get all departments",
     *     description="Retrieve a list of all departments.",
     *     operationId="getDepartments",
     *     tags={"Appointments Reservasion"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with the list of departments",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="message", type="string", example="Departments retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving departments",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to retrieve departments"),
     *             @OA\Property(property="error", type="string", example="Detailed error message")
     *         )
     *     )
     * )
     */
    public function getDepartments()
    {
        try {
            // Fetch all departments from the database
            $departments = Department::all();

            // Return a JSON response with the list of departments
            return response()->json([
                'data' => $departments,
                'message' => 'Departments retrieved successfully',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Handle exceptions by returning a JSON response with an error message
            return response()->json([
                'message' => 'Failed to retrieve departments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * @OA\Post(
     *     path="/api/v1/appointments-fetch-doctors",
     *     summary="Get doctors by department",
     *     description="Retrieve a list of doctors based on the specified department ID.",
     *     tags={"Appointments Reservasion"},
     *     @OA\Parameter(
     *         name="departmentId",
     *         in="query",
     *         required=true,
     *         description="ID of the department to filter doctors by",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with the list of doctors",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="message", type="string", example="Doctors retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving doctors",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to retrieve doctors"),
     *             @OA\Property(property="error", type="string", example="Detailed error message")
     *         )
     *     )
     * )
     */
    public function getDoctors(Request $request)
    {
        try {
            // Validate the request data to ensure 'departmentId' is required, an integer, and exists in the 'departments' table
            $validatedData = $request->validate([
                'departmentId' => 'required|integer|exists:departments,id',
            ]);

            // Fetch doctors from the specified department
            $doctors = Doctor::where('department_id', $validatedData['departmentId'])->get();

            // Return a JSON response with the list of doctors
            return response()->json([
                'data' => $doctors,
                'message' => 'Doctors retrieved successfully',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Handle exceptions by returning a JSON response with an error message
            return response()->json([
                'message' => 'Failed to retrieve doctors',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    /**
     * @OA\Post(
     *     path="/api/v1/appointments-search-availability",
     *     summary="Search doctor availability",
     *     description="Search for available doctor times based on the specified date and doctor ID.",
     *     operationId="searchAvailability",
     *     tags={"Appointments Reservasion"},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Date for which to check availability, must be today or in the future",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="doctor",
     *         in="query",
     *         required=true,
     *         description="ID of the doctor to check availability for",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with available times",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Search success"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid date provided",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The date should be in the future!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Authentication required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No available doctor times for the specified day",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No available doctor times for the specified day")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation error message")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error searching availability",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to search availability"),
     *             @OA\Property(property="error", type="string", example="Detailed error message")
     *         )
     *     )
     * )
     */
    public function searchAvailability(Request $request)
    {
        try {
            // Validate the request data to ensure 'date' is required, a date, and 'doctor' is required, an integer, and exists in the 'doctors' table
            $validatedData = $request->validate([
                'date' => 'required|date|after_or_equal:today',
                'doctor' => 'required|integer|exists:doctors,id',
            ]);

            // Check if the user is authenticated
            if (!auth()->check()) {
                return response()->json([
                    'message' => 'Authentication required',
                ], 401);
            }

            // Fetch doctor's available times
            $doctorTimes = DoctorTime::where('doctor_id', $validatedData['doctor'])->get();
            $parsedDate = Carbon::parse($validatedData['date']);
            $dayName = $parsedDate->format('l');

            // Check if the date is in the future
            if ($parsedDate->isFuture()) {
                // Loop through doctor's times to find availability
                foreach ($doctorTimes as $doctorTime) {
                    if ($doctorTime->day === $dayName) {
                        $availableOn = DoctorTime::where('doctor_id', $validatedData['doctor'])
                            ->where('day', $doctorTime->day)
                            ->where(function ($query) use ($validatedData) {
                                $query->whereNull('date')
                                    ->orWhereJsonDoesntContain('date', $validatedData['date']);
                            })
                            ->get();

                        // Return response based on availability
                        if ($availableOn->count() > 0) {
                            return response()->json([
                                'message' => 'Search success',
                                'data' => $availableOn, // Include available times in response
                            ], 200);
                        } else {
                            return response()->json([
                                'message' => 'No available doctor times',
                            ], 200);
                        }
                    }
                }

                // If no matching days are found
                return response()->json([
                    'message' => 'No available doctor times for the specified day',
                ], 404);
            } else {
                return response()->json([
                    'message' => 'The date should be in the future!',
                ], 400); // 400 Bad Request for invalid date
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation exceptions
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Handle general exceptions
            return response()->json([
                'message' => 'Failed to search availability',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    /**
     * @OA\Post(
     *     path="/api/v1/appointments-register-appointment",
     *     summary="Register a new appointment",
     *     description="Create a new appointment and initiate a payment through Stripe.",
     *     operationId="registerAppointment",
     *     tags={"Appointments Reservasion"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"day", "doctor", "startTime", "date"},
     *             @OA\Property(property="day", type="string", enum={"Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"}, example="Monday"),
     *             @OA\Property(property="doctor", type="integer", example=1),
     *             @OA\Property(property="startTime", type="string", example="09:00:00"),
     *             @OA\Property(property="date", type="string", format="date", example="2024-09-30")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Appointment registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Appointment registered successfully"),
     *             @OA\Property(property="checkout_url", type="string", example="https://checkout.stripe.com/..."),
     *             @OA\Property(property="appointment_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Doctor time slot not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Doctor time slot not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error registering appointment",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to register appointment"),
     *             @OA\Property(property="error", type="string", example="Detailed error message")
     *         )
     *     )
     * )
     */
    public function registerAppointment(Request $request)
    {
        $validatedData = $request->validate([
            'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'doctor' => 'required|integer|exists:doctors,id',
            'startTime' => 'required|date_format:H:i,H:i:s',
            'date' => 'required|date|after_or_equal:today',
        ]);
        $date = Carbon::parse($validatedData['date'])->format('Y-m-d');
        $startTime = Carbon::parse($validatedData['startTime'])->format('H:i:s');
        if (Carbon::parse($date)->format('l') !== $validatedData['day'] || Carbon::parse($date.' '.$startTime)->isPast()) {
            return response()->json(['message' => 'Select a future time and its matching day.'], 422);
        }
        if (!config('services.stripe.secret')) {
            return response()->json(['message' => 'Stripe payment is not configured.'], 503);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($validatedData, $date, $startTime) {
            $selectedTime = DoctorTime::where('doctor_id', $validatedData['doctor'])
                ->where('day', $validatedData['day'])->where('start_time', $startTime)
                ->lockForUpdate()->first();
            if (!$selectedTime) {
                return response()->json(['message' => 'Doctor time slot not found'], 404);
            }
            $dates = $selectedTime->date ?? [];
            $alreadyBooked = Appointment::where('doctor_id', $selectedTime->doctor_id)
                ->where('date', $date)->where('start_time', $startTime)
                ->where('general_status', '!=', 'canceled')->exists();
            if (in_array($date, $dates, true) || $alreadyBooked) {
                return response()->json(['message' => 'This appointment time is already reserved.'], 409);
            }

            $stripe = app(\Stripe\StripeClient::class);
            try {
                $checkoutSession = $stripe->checkout->sessions->create([
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => ['name' => 'Appointment Reservation'],
                            'unit_amount' => (int) round($selectedTime->doctor->appointment_price * 100),
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => config('app.frontend_url').'/appointment-checkout/success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => config('app.frontend_url').'/appointment-checkout/cancel',
                ], [
                    'idempotency_key' => hash('sha256', implode('|', [auth()->id(), $selectedTime->id, $date, $selectedTime->updated_at])),
                ]);
            } catch (\Throwable $e) {
                report($e);
                return response()->json(['message' => 'Unable to create Stripe checkout.'], 502);
            }

            $appointment = Appointment::create([
                'order_id' => (string) \Illuminate\Support\Str::uuid(),
                'payment_status' => 'unpaid', 'general_status' => 'pending',
                'doctor_id' => $selectedTime->doctor_id, 'day' => $validatedData['day'],
                'start_time' => $startTime, 'end_time' => $selectedTime->end_time,
                'session_id' => $checkoutSession->id, 'user_id' => auth()->id(),
                'email' => auth()->user()->email, 'date' => $date,
            ]);
            $dates[] = $date;
            $selectedTime->update(['date' => $dates]);
            AppointmentNotification::create(['doctor_id' => $appointment->doctor_id]);
            Cache::forget('count_appointments');
            Cache::forget('count_patient_'.auth()->id().'_appointments');
            Cache::forget('count_doctor_'.$selectedTime->doctor->user_id.'_appointments');

            return response()->json([
                'message' => 'Appointment registered successfully',
                'checkout_url' => $checkoutSession->url, 'appointment_id' => $appointment->id,
            ]);
        });
    }

    /**
     * @OA\Post(
     *     path="/api/v1/appointment-success-payment",
     *     summary="Handle successful Stripe payment",
     *     description="Retrieve the Stripe session and update the appointment status upon successful payment.",
     *     operationId="paymentSuccess",
     *     tags={"Appointments Reservasion"},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="query",
     *         required=true,
     *         description="The session ID returned by Stripe after checkout.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Payment successful."),
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found or session not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Order not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving session",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Error retrieving session.")
     *         )
     *     )
     * )
     */
    public function success(Request $request)
    {
        return app(\App\Services\ConfirmCheckoutPayment::class)->handle($request, 'appointment');
    }
}
