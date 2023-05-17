<?php
// // Include the configuration file  
// require_once 'config.php';

// // Include the database connection file  
// require_once 'dbConnect.php';
// Error Lines
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$payment_id = $statusMsg = '';
$status = 'error';

// Check whether the subscription ID is not empty 
if (!empty($_GET['sid'])) {
    // $subscr_id = base64_decode($_GET['sid']);

    // // Fetch subscription info from the database 
    // $sqlQ = "SELECT S.id, S.stripe_subscription_id, S.paid_amount, S.paid_amount_currency, S.plan_interval, S.plan_period_start, S.plan_period_end, S.customer_name, S.customer_email, S.status, P.name as plan_name, P.price as plan_amount FROM user_subscriptions as S LEFT JOIN plans as P On P.id = S.plan_id WHERE S.id = ?";
    // $stmt = $db->prepare($sqlQ);
    // $stmt->bind_param("s", $subscr_id);
    // $stmt->execute();
    // $stmt->store_result();

    // if ($stmt->num_rows > 0) {
    //     // Subscription and transaction details 
    //     $stmt->bind_result($subscription_id, $stripe_subscription_id, $paid_amount, $paid_amount_currency, $plan_interval, $plan_period_start, $plan_period_end, $customer_name, $customer_email, $subscr_status, $plan_name, $plan_amount);
    //     $stmt->fetch();

    //     $status = 'success';
    //     $statusMsg = 'Your Subscription Payment has been Successful!';
    // } else {
    //     $statusMsg = "Transaction has been failed!";
    // }
    $subscr_id = base64_decode($_GET['sid']);

    global $wpdb;

    // Fetch subscription info from the database
    $sqlQ = "SELECT S.id, S.stripe_subscription_id, S.paid_amount, S.paid_amount_currency, S.plan_interval, S.plan_period_start, S.plan_period_end, S.customer_name, S.customer_email, S.status, P.name as plan_name, P.price as plan_amount FROM {$wpdb->prefix}user_subscriptions as S LEFT JOIN {$wpdb->prefix}plans as P On P.id = S.plan_id WHERE S.id = %d";
    $subscription_info = $wpdb->get_row($wpdb->prepare($sqlQ, $subscr_id));

    if ($subscription_info) {
        $subscription_id = $subscription_info->id;
        $stripe_subscription_id = $subscription_info->stripe_subscription_id;
        $paid_amount = $subscription_info->paid_amount;
        $paid_amount_currency = $subscription_info->paid_amount_currency;
        $plan_interval = $subscription_info->plan_interval;
        $plan_period_start = $subscription_info->plan_period_start;
        $plan_period_end = $subscription_info->plan_period_end;
        $customer_name = $subscription_info->customer_name;
        $customer_email = $subscription_info->customer_email;
        $subscr_status = $subscription_info->status;
        $plan_name = $subscription_info->plan_name;
        $plan_amount = $subscription_info->plan_amount;

        $status = 'success';
        $statusMsg = 'Your Subscription Payment has been Successful!';
    } else {
        $statusMsg = "Transaction has been failed!";
    }
} else {
    header("Location: index.php");
    exit;
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="css/style.css">
<div class="container">
    <div class="row">
        <div class="col-8 mt-4 pt-3 border">
            <?php if (!empty($subscription_id)) { ?>

                <h1 class="text-success text-center <?php echo $status; ?>"><?php echo $statusMsg; ?></h1>

                <h4 class="text-success">Payment Information</h4>
                <p><b>Reference Number:</b>
                    <?php echo $subscription_id; ?>
                </p>
                <p><b>Subscription ID:</b>
                    <?php echo $stripe_subscription_id; ?>
                </p>
                <p><b>Paid Amount:</b>
                    <?php echo $paid_amount . ' ' . $paid_amount_currency; ?>
                </p>
                <p><b>Status:</b>
                    <?php echo $subscr_status; ?>
                </p>

                <h4 class="text-success">Subscription Information</h4>
                <p><b>Plan Name:</b>
                    <?php echo $plan_name; ?>
                </p>
                <p><b>Amount:</b>
                    <?php echo $plan_amount . ' ' . STRIPE_CURRENCY; ?>
                </p>
                <p><b>Plan Interval:</b>
                    <?php echo $plan_interval; ?>
                </p>
                <p><b>Period Start:</b>
                    <?php echo $plan_period_start; ?>
                </p>
                <p><b>Period End:</b>
                    <?php echo $plan_period_end; ?>
                </p>

                <h4 class="text-success">Customer Information</h4>
                <p><b>Name:</b>
                    <?php echo $customer_name; ?>
                </p>
                <p><b>Email:</b>
                    <?php echo $customer_email; ?>
                </p>
            <?php } else { ?>
                <h1 class="text-success error">Your Transaction been failed!</h1>
                <p class="error">
                    <?php echo $statusMsg; ?>
                </p>
            <?php } ?>
        </div>
    </div>
</div>