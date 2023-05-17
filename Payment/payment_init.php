<?php
// // Include the configuration file 
// require_once 'config.php';

// // Include the database connection file 
// include_once 'dbConnect.php';

// Include the Stripe PHP library
// Error Lines
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'stripe-php/init.php';

// Set API key 
\Stripe\Stripe::setApiKey(STRIPE_API_KEY);

// Database Tables
global $plans_table, $users_table, $subscription_table, $wpdb;
$plans_table = $wpdb->prefix . 'membership_plans';
$users_table = $wpdb->prefix . 'membership_users';
$subscription_table = $wpdb->prefix . 'members_subscriptions';

// Retrieve JSON from POST body 
$jsonStr = file_get_contents('php://input');
$jsonObj = json_decode($jsonStr);

// Get user ID from current SESSION 
$userID = isset($_SESSION['loggedInUserID']) ? $_SESSION['loggedInUserID'] : 0;

if ($jsonObj->request_type == 'create_customer_subscription') {
    $subscr_plan_id = !empty($jsonObj->subscr_plan_id) ? $jsonObj->subscr_plan_id : '';
    $name = !empty($jsonObj->name) ? $jsonObj->name : '';
    $email = !empty($jsonObj->email) ? $jsonObj->email : '';

    // Fetch plan details from the database 
    // $sqlQ = "SELECT `name`,`price`,`interval` FROM " . $plans_table . " WHERE id=?";
    // $stmt = $db->prepare($sqlQ);
    // $stmt->bind_param("i", $subscr_plan_id);
    // $stmt->execute();
    // $stmt->bind_result($planName, $planPrice, $planInterval);
    // $stmt->fetch();
    $sqlQ = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM " . $plans_table . " WHERE id = %d",
            $subscr_plan_id
        )
    );
    $planName = $sqlQ->name;
    $planPrice = $sqlQ->price;
    $planInterval = $sqlQ->interval;

    // Convert price to cents 
    $planPriceCents = round($planPrice * 100);

    // Add customer to stripe 
    try {
        $customer = \Stripe\Customer::create([
            'name' => $name,
            'email' => $email
        ]);
    } catch (Exception $e) {
        $api_error = $e->getMessage();
    }

    if (empty($api_error) && $customer) {
        try {
            // Create price with subscription info and interval 
            $price = \Stripe\Price::create([
                'unit_amount' => $planPriceCents,
                'currency' => STRIPE_CURRENCY,
                'recurring' => ['interval' => $planInterval],
                'product_data' => ['name' => $planName],
            ]);
        } catch (Exception $e) {
            $api_error = $e->getMessage();
        }

        if (empty($api_error) && $price) {
            // Create a new subscription 
            try {
                $subscription = \Stripe\Subscription::create([
                    'customer' => $customer->id,
                    'items' => [
                        [
                            'price' => $price->id,
                        ]
                    ],
                    'payment_behavior' => 'default_incomplete',
                    'expand' => ['latest_invoice.payment_intent'],
                ]);
            } catch (Exception $e) {
                $api_error = $e->getMessage();
            }

            if (empty($api_error) && $subscription) {
                $output = [
                    'subscriptionId' => $subscription->id,
                    'clientSecret' => $subscription->latest_invoice->payment_intent->client_secret,
                    'customerId' => $customer->id
                ];

                echo json_encode($output);
            } else {
                echo json_encode(['error' => $api_error]);
            }
        } else {
            echo json_encode(['error' => $api_error]);
        }
    } else {
        echo json_encode(['error' => $api_error]);
    }
} elseif ($jsonObj->request_type == 'payment_insert') {
    $payment_intent = !empty($jsonObj->payment_intent) ? $jsonObj->payment_intent : '';
    $subscription_id = !empty($jsonObj->subscription_id) ? $jsonObj->subscription_id : '';
    $customer_id = !empty($jsonObj->customer_id) ? $jsonObj->customer_id : '';
    $subscr_plan_id = !empty($jsonObj->subscr_plan_id) ? $jsonObj->subscr_plan_id : '';

    // Fetch plan details from the database 
    // $sqlQ = "SELECT `interval` FROM " . $plans_table . " WHERE id=?";
    // $stmt = $db->prepare($sqlQ);
    // $stmt->bind_param("i", $subscr_plan_id);
    // $stmt->execute();
    // $stmt->bind_result($interval);
    // $stmt->fetch();
    // $planInterval = $interval;
    // $stmt->close();
    // Fetch plan details from the database
    global $wpdb;
    $plans_table = $wpdb->prefix . 'membership_plans';

    $planInterval = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT `interval` FROM $plans_table WHERE id = %d",
            $subscr_plan_id
        )
    );
    // Retrieve customer info 
    try {
        $customer = \Stripe\Customer::retrieve($customer_id);
    } catch (Exception $e) {
        $api_error = $e->getMessage();
    }

    // Check whether the charge was successful 
    if (!empty($payment_intent) && $payment_intent->status == 'succeeded') {

        // Retrieve subscription info 
        try {
            $subscriptionData = \Stripe\Subscription::retrieve($subscription_id);
        } catch (Exception $e) {
            $api_error = $e->getMessage();
        }

        $payment_intent_id = $payment_intent->id;
        $paidAmount = $payment_intent->amount;
        $paidAmount = ($paidAmount / 100);
        $paidCurrency = $payment_intent->currency;
        $payment_status = $payment_intent->status;

        $created = date("Y-m-d H:i:s", $payment_intent->created);
        $current_period_start = $current_period_end = '';
        if (!empty($subscriptionData)) {
            $created = date("Y-m-d H:i:s", $subscriptionData->created);
            $current_period_start = date("Y-m-d H:i:s", $subscriptionData->current_period_start);
            $current_period_end = date("Y-m-d H:i:s", $subscriptionData->current_period_end);
        }

        $customer_name = $customer_email = '';
        if (!empty($customer)) {
            $customer_name = !empty($customer->name) ? $customer->name : '';
            $customer_email = !empty($customer->email) ? $customer->email : '';

            if (!empty($customer_name)) {
                $name_arr = explode(' ', $customer_name);
                $first_name = !empty($name_arr[0]) ? $name_arr[0] : '';
                $last_name = !empty($name_arr[1]) ? $name_arr[1] : '';
            }

            // Insert user details if not exists in the DB users table 
            if (empty($userID)) {
                // $sqlQ = "INSERT INTO " . $users_table . " (first_name,last_name,email) VALUES (?,?,?)";
                // $stmt = $db->prepare($sqlQ);
                // $stmt->bind_param("sss", $first_name, $last_name, $customer_email);
                // $insertUser = $stmt->execute();

                // if ($insertUser) {
                //     $userID = $stmt->insert_id;
                // }
                // Insert user details if not exists in the DB users table 
                global $wpdb; // Access the WordPress database object

                $users_table = $wpdb->prefix . 'membership_users'; // Define the users table name

                // Prepare data for insertion
                $user_data = array(
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $customer_email,
                );

                // Insert user details using the $wpdb object
                $insertUser = $wpdb->insert($users_table, $user_data);

                if ($insertUser) {
                    $userID = $wpdb->insert_id; // Get the inserted user's ID
                }
            }


        }

        // Check if any transaction data exists already with the same TXN ID 
        // $sqlQ = "SELECT id FROM " . $subscription_table . " WHERE stripe_payment_intent_id = ?";
        // $stmt = $db->prepare($sqlQ);
        // $stmt->bind_param("s", $payment_intent_id);
        // $stmt->execute();
        // $stmt->bind_result($id);
        // $stmt->fetch();
        // $prevPaymentID = $id;
        // $stmt->close();
        global $wpdb;

        $sqlQ = $wpdb->prepare("SELECT id FROM $subscription_table WHERE stripe_payment_intent_id = %s", $payment_intent_id);
        $prevPaymentID = $wpdb->get_var($sqlQ);

        $payment_id = 0;
        if (!empty($prevPaymentID)) {
            $payment_id = $prevPaymentID;
        } else {
            // // Insert transaction data into the database 
            // $sqlQ = "INSERT INTO " . $subscription_table . " (user_id,plan_id,stripe_subscription_id,stripe_customer_id,stripe_payment_intent_id,paid_amount,paid_amount_currency,plan_interval,customer_name,customer_email,created,plan_period_start,plan_period_end,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            // $stmt = $db->prepare($sqlQ);
            // $stmt->bind_param("iisssdssssssss", $userID, $subscr_plan_id, $subscription_id, $customer_id, $payment_intent_id, $paidAmount, $paidCurrency, $planInterval, $customer_name, $customer_email, $created, $current_period_start, $current_period_end, $payment_status);
            // $insert = $stmt->execute();

            // if ($insert) {
            //     $payment_id = $stmt->insert_id;

            //     // Update subscription ID in users table 
            //     $sqlQ = "UPDATE " . $users_table . " SET subscription_id=? WHERE id=?";
            //     $stmt = $db->prepare($sqlQ);
            //     $stmt->bind_param("ii", $payment_id, $userID);
            //     $update = $stmt->execute();
            // }
            // Insert transaction data into the database
            global $wpdb, $users_table, $subscription_table;

            $wpdb->insert(
                $subscription_table,
                array(
                    'user_id' => $userID,
                    'plan_id' => $subscr_plan_id,
                    'stripe_subscription_id' => $subscription_id,
                    'stripe_customer_id' => $customer_id,
                    'stripe_payment_intent_id' => $payment_intent_id,
                    'paid_amount' => $paidAmount,
                    'paid_amount_currency' => $paidCurrency,
                    'plan_interval' => $planInterval,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'created' => $created,
                    'plan_period_start' => $current_period_start,
                    'plan_period_end' => $current_period_end,
                    'status' => $payment_status
                ),
                array(
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                )
            );

            $payment_id = $wpdb->insert_id;

            // Update subscription ID in users table
            $wpdb->update(
                $users_table,
                array('subscription_id' => $payment_id),
                array('id' => $userID),
                array('%d'),
                array('%d')
            );
        }

        $output = [
            'payment_id' => base64_encode($payment_id)
        ];
        echo json_encode($output);
    } else {
        echo json_encode(['error' => 'Transaction has been failed!']);
    }
}
?>