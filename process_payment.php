<?php
session_start();
require_once 'includes/dbconnection.php';
require_once 'includes/stripe_config.php';

header('Content-Type: application/json');

try {
    // Get the payment details from the request
    $input = json_decode(file_get_contents('php://input'), true);
    $amount = $input['amount'] ?? 0;
    $currency = $input['currency'] ?? 'usd';
    $description = $input['description'] ?? 'Payment for services';
    $paymentMethodId = $input['paymentMethodId'] ?? '';

    if (empty($paymentMethodId)) {
        throw new Exception('Payment method is required');
    }

    // Create a payment intent
    $intent = \Stripe\PaymentIntent::create([
        'amount' => $amount * 100, // Convert to cents
        'currency' => $currency,
        'payment_method' => $paymentMethodId,
        'confirmation_method' => 'manual',
        'confirm' => true,
        'description' => $description,
        'metadata' => [
            'user_id' => $_SESSION['user_id'] ?? '',
            'user_name' => $_SESSION['full_name'] ?? ''
        ]
    ]);

    // Handle the payment intent response
    if ($intent->status === 'requires_action' && $intent->next_action->type === 'use_stripe_sdk') {
        // Tell the client to handle the action
        echo json_encode([
            'requires_action' => true,
            'payment_intent_client_secret' => $intent->client_secret
        ]);
    } else if ($intent->status === 'succeeded') {
        // Payment is complete
        echo json_encode([
            'success' => true,
            'message' => 'Payment successful'
        ]);
    } else {
        // Payment failed
        throw new Exception('Payment failed');
    }
} catch (\Stripe\Exception\CardException $e) {
    // Card was declined
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Other errors
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 