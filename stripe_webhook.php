<?php
require_once 'includes/dbconnection.php';
require_once 'includes/stripe_config.php';

// Get the webhook secret from your Stripe dashboard
$webhookSecret = 'your_webhook_secret';

// Get the webhook payload
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    // Verify the webhook signature
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sigHeader, $webhookSecret
    );

    // Handle the event
    switch ($event->type) {
        case 'payment_intent.succeeded':
            $paymentIntent = $event->data->object;
            
            // Update payment status in database
            $stmt = $dbh->prepare("
                UPDATE payments 
                SET status = 'succeeded' 
                WHERE payment_intent_id = ?
            ");
            $stmt->execute([$paymentIntent->id]);
            break;

        case 'payment_intent.payment_failed':
            $paymentIntent = $event->data->object;
            
            // Update payment status in database
            $stmt = $dbh->prepare("
                UPDATE payments 
                SET status = 'failed' 
                WHERE payment_intent_id = ?
            ");
            $stmt->execute([$paymentIntent->id]);
            break;

        default:
            // Unexpected event type
            http_response_code(400);
            exit();
    }

    http_response_code(200);
} catch (\UnexpectedValueException $e) {
    // Invalid payload
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    http_response_code(400);
    exit();
} catch (Exception $e) {
    // Other errors
    http_response_code(500);
    exit();
} 