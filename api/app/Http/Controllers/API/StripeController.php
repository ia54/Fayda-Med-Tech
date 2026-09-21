<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\StripeClient;

/**
 * PDF Section 15: Integrations & API - Stripe Payment Gateway
 * PDF Section 6: Billing & Invoice Management - Payment Processing
 */
class StripeController extends Controller
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a payment intent for client payment
     */
    public function createPaymentIntent(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'amount' => 'required|numeric|min:0.50',
            'currency' => 'nullable|string|size:3',
            'invoice_id' => 'nullable|exists:invoices,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int) ($request->amount * 100), // Convert to cents
                'currency' => $request->currency ?? 'usd',
                'metadata' => [
                    'invoice_id' => $request->invoice_id,
                    'user_id' => $request->user()->id,
                    'organization_id' => $request->user()->organization_id,
                ],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment intent created successfully',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create payment intent: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Stripe webhook events
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $webhook_secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $webhook_secret
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                // Update invoice/payment record
                $this->handleSuccessfulPayment($paymentIntent);
                break;
            case 'payment_intent.payment_failed':
                // Log failed payment
                break;
            default:
                // Unexpected event type
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful payment
     */
    protected function handleSuccessfulPayment($paymentIntent): void
    {
        // Find and update the invoice
        $invoiceId = $paymentIntent->metadata->invoice_id ?? null;
        if ($invoiceId) {
            \App\Models\Invoice::where('id', $invoiceId)->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        // Create payment record
        \App\Models\Payment::create([
            'invoice_id' => $invoiceId,
            'amount' => $paymentIntent->amount / 100,
            'payment_method' => 'stripe',
            'transaction_id' => $paymentIntent->id,
            'paid_at' => now(),
        ]);
    }
}
