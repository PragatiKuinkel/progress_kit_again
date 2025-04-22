<?php
// Stripe API Configuration
define('STRIPE_SECRET_KEY', 'your_stripe_secret_key');
define('STRIPE_PUBLISHABLE_KEY', 'your_stripe_publishable_key');

// Initialize Stripe
require_once '../vendor/autoload.php';
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY); 