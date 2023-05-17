<?php
/**
 * Plugin Name: Payment
 * Description: Stripe-Payment is used for payment integration
 * Plugin URI: https://www.google.com
 * Version: 1.0.0
 * Author: Ahmad Bin Waris
 * Author URI: https://www.google.com
 */

define("PLUGIN_DIR", plugin_dir_path(__FILE__));
define("PLUGIN_URL", plugin_dir_url(__FILE__));
define('STRIPE_API_KEY', 'sk_test_51N3EzBIH1RmHhmxTeNAderlZ35sJmi55eKuq6gretG40kVDFySnI46lqDipGTcx5YEZ0lpzcyflq6rJ56wEY4CuG00G05x1N6n');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51N3EzBIH1RmHhmxTYmpSwIHk1M4kdVt0VgY8cDZBZ1CGJWWVAvsJQfU5K4dwlPwvla2G89kJofWEFQPeplVkdhPE00eqd5bamg');
define('STRIPE_CURRENCY', 'USD');
// include PLUGIN_DIR . "Payment/config.php";

?>
<?php

function custom_scripts()
{
    wp_enqueue_style('bootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css');

    wp_enqueue_style('Style', PLUGIN_URL . 'assets/css/style.css');

    wp_enqueue_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js', array(), '3.2.1', true);

    wp_enqueue_script('Stripe', 'https://js.stripe.com/v3/', array(), '1.0', true);

    wp_enqueue_script('checkout', PLUGIN_URL . 'Payment/js/checkout.js', array(), '1.0', true);


}
add_action('admin_enqueue_scripts', 'custom_scripts');

function payment_menu()
{
    add_menu_page('Strip Payment', 'Strip Payment', 'manage_options', 'payment', 'payment_callback');
}

add_action('admin_menu', 'payment_menu');
function payment_callback()
{
    global $wpdb;
    global $plans_table;
    $plans_table = $wpdb->prefix . 'membership_plans';
    $plans = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM " . $plans_table . " ORDER BY id ASC"
        ),
        ARRAY_A
    );
    print_r($plans);
    global $ab;
    print_r($ab);
    ?>
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css"> -->
    <!-- <link rel="stylesheet" href="css/style.css"> -->
    <div class="container">
        <div class="row">
            <div class="col-8">

                <div class="panel">
                    <div class="panel-heading">
                        <h3 class="panel-title">Subscription with Stripe</h3>

                        <!-- Plan Info -->
                        <div>
                            <b>Select Plan:</b>
                            <select id="subscr_plan" class="form-control">
                                <?php
                                foreach ($plans as $key => $value) {
                                    ?>
                                    <option value="<?php echo $value['id']; ?>"><?php echo $value['name'] . ' [' . '$' . $value['price'] . ']'; ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="panel-body">
                        <!-- Display status message -->
                        <div id="paymentResponse" class="hidden"></div>

                        <!-- Display a subscription form -->
                        <form id="subscrFrm">
                            <div class="form-group">
                                <label>NAME</label>
                                <input type="text" id="name" class="form-control" placeholder="Enter name" required=""
                                    autofocus="">
                            </div>
                            <div class="form-group">
                                <label>EMAIL</label>
                                <input type="email" id="email" class="form-control" placeholder="Enter email" required="">
                            </div>

                            <div class="form-group">
                                <label>CARD INFO</label>
                                <div id="card-element">
                                    <!-- Stripe.js will create card input elements here -->
                                </div>
                            </div>

                            <!-- Form submit button -->
                            <button id="submitBtn" class="btn btn-success">
                                <div class="spinner hidden" id="spinner"></div>
                                <span id="buttonText">Proceed</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- <script src="https://js.stripe.com/v3/"></script> -->
    <!-- <script src="assets/js/checkout.js" STRIPE_PUBLISHABLE_KEY="<?php echo STRIPE_PUBLISHABLE_KEY; ?>" defer></script> -->
    <?php
}
// Error Lines
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>