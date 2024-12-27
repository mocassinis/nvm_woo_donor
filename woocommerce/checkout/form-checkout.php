<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<?php if ( $checkout->get_checkout_fields() ) : ?>

		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<div class="" id="customer_details">
			<div class="col-1">
				<h3 class="nvm checkout billing">1. Στοιχεία χρέωσης</h3>
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
			</div>

			<div class="col-2">
				<h3 class="nvm checkout address">2. Διεύθυνση Αποστολής</h3>

				<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			</div>

			<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<h3 class="nvm checkout shipping">3. Τρόποι Αποστολής</h3>

			<?php do_action( 'nvm_woocommerce_checkout_payment_method' ); ?>

			<h3 class="nvm checkout shipping">4. Τρόποι Πληρωμής</h3>

			<?php do_action( 'nvm_woocommerce_checkout_shopping_method_action' ); ?>
			<?php do_action( 'nvm_woocommerce_after_checkout_shopping_method' ); ?>

		</div>

	<?php endif; ?>

	<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

	<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>


	<div id="order_review" class="woocommerce-checkout-review-order">
		<h3 id="order_review_heading" class="nvm"><?php esc_html_e( 'Η Παραγγελία σας', 'woocommerce' ); ?></h3>
		<div class="order_review_inside">
			<?php do_action( 'woocommerce_checkout_order_review' ); ?>
		</div>

	</div>

	<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
