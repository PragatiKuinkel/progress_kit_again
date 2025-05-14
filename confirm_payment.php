<?php
session_start();
require_once 'includes/dbconnection.php';
require_once 'includes/stripe_config.php';

header('Content-Type: application/json');

try {
    // Get the payment intent ID from the request
    $input = json_decode(file_get_contents('php://input'), true);
    $paymentIntentId = $input['paymentIntentId'] ?? '';

    if (empty($paymentIntentId)) {
        throw new Exception('Payment intent ID is required');
    }

    // Retrieve the payment intent
    $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

    // Confirm the payment
    $intent->confirm();

    if ($intent->status === 'succeeded') {
        // Payment is complete
        echo json_encode([
            'success' => true,
            'message' => 'Payment confirmed successfully'
        ]);
    } else {
        // Payment still requires action
        echo json_encode([
            'requires_action' => true,
            'payment_intent_client_secret' => $intent->client_secret
        ]);
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