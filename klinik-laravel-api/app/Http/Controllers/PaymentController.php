<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Offer;
use App\Models\Order;
use Stripe\StripeClient;
use App\Mail\PaymentSuccess;
use Illuminate\Http\Request;
use App\Mail\OrderPaymentSuccess;
use App\Models\OrderNotification;
use App\Events\OrderNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use OpenApi\Annotations as OA;

class PaymentController extends Controller
{


    /**
     * @OA\Post(
     *     path="/api/v1/order-checkout",
     *     summary="Create a checkout session for an order",
     *     tags={"Checkout"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"offerId", "quantity", "fullName", "gender", "nationalCardID"},
     *             @OA\Property(property="offerId", type="integer", example=1),
     *             @OA\Property(property="quantity", type="integer", example=2),
     *             @OA\Property(property="fullName", type="string", example="John Doe"),
     *             @OA\Property(property="gender", type="string", example="Male"),
     *             @OA\Property(property="nationalCardID", type="string", example="123456789")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with checkout session details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Checkout session created successfully."),
     *             @OA\Property(property="checkout_url", type="string", example="https://checkout.stripe.com/sessions/xyz"),
     *             @OA\Property(property="order_id", type="string", example="1234567890123456789"),
     *             @OA\Property(property="order", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_id", type="string", example="1234567890123456789"),
     *                 @OA\Property(property="status", type="string", example="unpaid"),
     *                 @OA\Property(property="total_price", type="number", format="float", example=100.00),
     *                 @OA\Property(property="session_id", type="string", example="sess_123456"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="offer_id", type="integer", example=1),
     *                 @OA\Property(property="full_name", type="string", example="John Doe"),
     *                 @OA\Property(property="gender", type="string", example="Male"),
     *                 @OA\Property(property="national_card_id", type="string", example="123456789")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Offer not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Offer not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error creating checkout session",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="There was an error creating the checkout session: ...")
     *         )
     *     )
     * )
     */

    public function orderCheckout(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'offerId' => 'required|integer|exists:offers,id',
            'quantity' => 'required|integer|min:1|max:1000',
            'fullName' => 'required|string|max:255',
            'gender' => 'required|string|max:255',
            'nationalCardID' => 'required|string|max:500'
        ]);

        // Find the offer by ID
        if (!config('services.stripe.secret')) {
            return response()->json(['message' => 'Stripe payment is not configured.'], 503);
        }

        $offer = Offer::find($validatedData['offerId']);

        // Return an error response if the offer is not found
        if (!$offer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Offer not found.'
            ], 404);
        }

        try {
            // Initialize the Stripe client
            $stripe = app(StripeClient::class);

            // Create a new Stripe Checkout session
            $checkout_session = $stripe->checkout->sessions->create([
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $offer->name,
                        ],
                        'unit_amount' => $offer->total_with_tax * 100,
                    ],
                    'quantity' => $validatedData['quantity'],
                ]],
                'mode' => 'payment',
                'success_url' => config('app.frontend_url') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/checkout/cancel',
            ]);

            // Generate a unique order ID
            $order_id = uniqid() . mt_rand(100000, 999999);

            // Create a new order and save it to the database
            $order = new Order();
            $order->order_id = $order_id;
            $order->status = 'unpaid';
            $order->total_price = $offer->total_with_tax * $validatedData['quantity'];
            $order->session_id = $checkout_session->id;
            $order->user_id = Auth::user()->id;
            $order->offer_id = $validatedData['offerId'];
            $order->full_name = $validatedData['fullName'];
            $order->gender = $validatedData['gender'];
            $order->national_card_id = $validatedData['nationalCardID'];
            $order->save();

            // Clear cache related to orders
            $countOrdersCacheKey = 'count_orders';
            if (Cache::get($countOrdersCacheKey)) {
                Cache::forget($countOrdersCacheKey);
            }

            // Notify admins about the new order
            $admins = User::where('is_admin', 1)->pluck('id');
            DB::transaction(function () use ($admins, $order) {
                $notifications = [];
                foreach ($admins as $adminId) {
                    $notifications[] = [
                        'user_id' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'order_id' => $order->id
                    ];
                }
                OrderNotification::insert($notifications);

                // Broadcast the order notifications event
                event(new OrderNotifications('New Order! Check it out'));

                // Clear cache for each admin
                foreach ($admins as $adminId) {
                    Cache::forget("order_notifications_{$adminId}");
                }
            });

            // Clear the cache for the current user's order count
            $userId = auth()->user()->id;
            $cacheKey = "count_orders_patient_{$userId}";
            if (Cache::has($cacheKey)) {
                Cache::forget($cacheKey);
            }

            // Return a successful response with checkout URL and order details
            return response()->json([
                'status' => 'success',
                'message' => 'Checkout session created successfully.',
                'checkout_url' => $checkout_session->url,
                'order_id' => $order_id,
                'order' => $order
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Return an error response if something goes wrong
            return response()->json([
                'status' => 'error',
                'message' => 'There was an error creating the checkout session: ' . $e->getFile()
            ], 500);
        }
    }



    /**
     * @OA\Post(
     *     path="/api/v1/order-success-payment",
     *     summary="Handle successful payment notification",
     *     tags={"Checkout"},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="query",
     *         required=true,
     *         description="Stripe Checkout session ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment was successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Payment successful."),
     *             @OA\Property(property="order", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_id", type="string", example="1234567890123456789"),
     *                 @OA\Property(property="status", type="string", example="paid"),
     *                 @OA\Property(property="total_price", type="number", format="float", example=100.00),
     *                 @OA\Property(property="session_id", type="string", example="sess_123456"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="offer_id", type="integer", example=1),
     *                 @OA\Property(property="full_name", type="string", example="John Doe"),
     *                 @OA\Property(property="gender", type="string", example="Male"),
     *                 @OA\Property(property="national_card_id", type="string", example="123456789")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Session or order not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Session not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving session",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Error retrieving session.")
     *         )
     *     )
     * )
     */

    public function success(Request $request)
    {
        return app(\App\Services\ConfirmCheckoutPayment::class)->handle($request, 'order');
    }
}
