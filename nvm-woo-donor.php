<?php //phpcs:ignore - \r\n issue

/*
 * Plugin Name: WooCommerce Donor plugin by Nevma
 * Plugin URI:
 * Description: A plugin to handle donations via WooCommerce by nevma team
 * Version: 1.1.2
 * Author: Nevma Team
 * Author URI: https://woocommerce.com/vendor/nevma/
 * Text Domain: nevma
 *
 * Woo:
 * WC requires at least: 4.0
 * WC tested up to: 9.4
*/

/**
 * Set namespace.
 */
namespace Nvm;

/**
 * Check that the file is not accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

/**
 * Class Donor.
 */
class Donor {
	/**
	 * The plugin version.
	 *
	 * @var string $version
	 */
	public static $plugin_version;

	/**
	 * Set namespace prefix.
	 *
	 * @var string $namespace_prefix
	 */
	public static $namespace_prefix;

	/**
	 * The plugin directory.
	 *
	 * @var string $plugin_dir
	 */
	public static $plugin_dir;

	/**
	 * The plugin temp directory.
	 *
	 * @var string $plugin_tmp_dir
	 */
	public static $plugin_tmp_dir;

	/**
	 * The plugin url.
	 *
	 * @var string $plugin_url
	 */
	public static $plugin_url;

	/**
	 * The plugin instance.
	 *
	 * @var null|Donor $instance
	 */
	private static $instance = null;

	/**
	 * Gets the plugin instance.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {

		// Set the plugin version.
		self::$plugin_version = '0.0.2';

		// Set the plugin namespace.
		self::$namespace_prefix = 'Nvm\\Donor';

		// Set the plugin directory.
		self::$plugin_dir = wp_normalize_path( plugin_dir_path( __FILE__ ) );

		// Set the plugin url.
		self::$plugin_url = plugin_dir_url( __FILE__ );

		// Autoload.
		self::autoload();

		// Scripts & Styles.
		add_action( 'acf/init', array( $this, 'sync_acf_fields_from_json' ) );

		add_action( 'wp_head', array( $this, 'initiate_redirect_template' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_donor_script' ), 10 );

		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_donation_fields_to_product' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'save_donation_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_donation_to_order_items' ), 10, 4 );

		add_filter( 'woocommerce_is_sold_individually', array( $this, 'remove_quantity_input_field' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'adjust_product_price_based_on_choice' ) );

		add_filter( 'woocommerce_checkout_fields', array( $this, 'nvm_customize_checkout_fields' ), 10 );

		add_action( 'woocommerce_add_to_cart', array( $this, 'redirect_to_checkout_for_specific_product' ), 50, 6 );
	}

	/**
	 * Autoload.
	 */
	public static function autoload() {
		spl_autoload_register(
			function ( $class ) {

				$prefix = self::$namespace_prefix;
				$len    = strlen( $prefix );

				if ( 0 !== strncmp( $prefix, $class, $len ) ) {
					return;
				}

				$relative_class = substr( $class, $len );
				$path           = explode( '\\', strtolower( str_replace( '_', '-', $relative_class ) ) );
				$file           = array_pop( $path );
				$file           = self::$plugin_dir . 'classes/class-' . $file . '.php';

				if ( file_exists( $file ) ) {
					require $file;
				}

				// add the autoload.php file for the prefixed vendor folder.
				require self::$plugin_dir . '/prefixed/vendor/autoload.php';
			}
		);
	}

	public function declare_hpos_compatibility() {

		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Check plugin dependencies.
	 *
	 * Verifies if WooCommerce is active without relying on the folder structure.
	 */
	public static function check_plugin_dependencies() {
		// Check if the WooCommerce class exists.
		if ( ! class_exists( 'WooCommerce' ) ) {
			// Display an admin error message and terminate the script.
			wp_die(
				esc_html__( 'Sorry, but this plugin requires the WooCommerce plugin to be active.', 'your-text-domain' ) .
				' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' .
				esc_html__( 'Return to Plugins.', 'nevma' ) . '</a>'
			);
		}
	}

	public function redirect_to_checkout_for_specific_product( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

		// Target specific product for donations.
		$target_product_id = $this->get_donor_product();

		if ( $product_id === $target_product_id ) {
			// Get the checkout URL
			$checkout_url = wc_get_checkout_url();

			// Redirect to the checkout page
			wp_safe_redirect( $checkout_url );
			exit;
		}
	}

	/**
	 * Load and sync ACF fields from a JSON file.
	 */
	public function sync_acf_fields_from_json() {
		// Path to your JSON file within the plugin directory.
		$json_file_path = plugin_dir_path( __FILE__ ) . '/acf/donor-acf.json';

		// Check if the file exists.
		if ( ! file_exists( $json_file_path ) ) {
			return;
		}

		// Decode the JSON file.
		$json_content = file_get_contents( $json_file_path );
		$field_groups = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return;
		}

		// Ensure ACF is active before syncing fields.
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// Register each field group with ACF.
		foreach ( $field_groups as $field_group ) {
			acf_add_local_field_group( $field_group );
		}
	}

	public function enqueue_donor_script() {
		// if ( is_checkout() ) {
		// wp_enqueue_style(
		// 'nvm-donor',
		// plugin_dir_url( __FILE__ ) . 'css/style.css',
		// array(),
		// self::$plugin_version
		// );
		// }
	}


	// Add a custom donation form to the checkout page
	public function add_donation_type() {
		?>
		<!-- Donation Type -->
		<p>
			<label for="donation-type"><?php esc_html_e( 'Donation Type', 'nevma' ); ?></label>
			<select id="donation-type" name="donation_type">
				<option value="individual"><?php esc_html_e( 'Individual', 'nevma' ); ?></option>
				<option value="corporate"><?php esc_html_e( 'Corporate', 'nevma' ); ?></option>
				<option value="memoriam"><?php esc_html_e( 'In Memoriam', 'nevma' ); ?></option>
			</select>
		</p>
		<?php
	}

	public function remove_quantity_input_field( $return, $product ) {

		if ( is_product() ) {

			$target_product_id = $this->get_donor_product();

			if ( $product->get_id() === $target_product_id ) {
				return true;
			}
		}
		return $return;
	}


	public function get_donor_product() {
		if ( class_exists( 'ACF' ) ) {
			$donor_product_id = get_field( 'product', 'options' );

			return $donor_product_id;
		}

		return false;
	}

	public function get_donor_prices() {
		$chosen  = WC()->session->get( 'radio_chosen' );
		$chosen  = empty( $chosen ) ? WC()->checkout->get_value( 'nvm_radio_choice' ) : $chosen;
		$options = array();
		$minimum = 1;

		if ( class_exists( 'ACF' ) ) {
			$minimum = get_field( 'minimun_amount', 'options' );

			$array_donor = get_field( 'donor_prices', 'options' );
			if ( ! empty( $array_donor ) ) {
				foreach ( $array_donor as $donor ) {
					$donor_amount             = $donor['amount'];
					$options[ $donor_amount ] = $donor_amount . '€';
				}
			}
		}

		if ( empty( $options ) ) {
			$options = array(
				'5'  => '5€',
				'10' => '10€',
				'25' => '25€',
				'50' => '50€',
			);
		}

		$options['custom'] = esc_html__( 'Custom Amount', 'nevma' );
		$chosen            = empty( $chosen ) ? array_key_first( $options ) : $chosen;

		$args = array(
			'type'    => 'radio',
			'class'   => array( 'form-row-wide' ),
			'options' => $options,
			'default' => $chosen,
		);

		echo '<div id="donation-choices">';
		woocommerce_form_field( 'nvm_radio_choice', $args, $chosen );
		echo '</div>';

		echo '<div class="donation-fields">';
		woocommerce_form_field(
			'donation_amount',
			array(
				'type'        => 'number',
				'label'       => __( 'Donation Amount (€)', 'nevma' ),
				'required'    => false,
				'class'       => array( 'form-row-wide' ),
				'placeholder' => __( 'Enter an amount', 'nevma' ),
			)
		);
		echo '</div>';

		wp_nonce_field( 'donation_form_nonce', 'donation_form_nonce_field' );
	}

	/**
	 * Add donation fields to the product page.
	 */
	public function add_donation_fields_to_product() {
		global $product;

		// Target specific product for donations.
		$target_product_id = $this->get_donor_product();

		if ( $product->get_id() !== $target_product_id ) {
			return;
		}

		$this->get_donor_prices();
		?>
		<style>
			.woocommerce form .form-row label,
			.woocommerce-page form .form-row label {
				display: inline-block;
			}
		</style>
		<?php
	}

	/**
	 * Save donation data to cart item.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id Product ID.
	 */
	public function save_donation_data( $cart_item_data, $product_id ) {

		if ( ! isset( $_POST['donation_form_nonce_field'] ) || ! wp_verify_nonce( $_POST['donation_form_nonce_field'], 'donation_form_nonce' ) ) {
			wp_die();
		}

		if ( isset( $_POST['nvm_radio_choice'] ) ) {

			if ( 'custom' === $_POST['nvm_radio_choice'] ) {
				if ( isset( $_POST['donation_amount'] ) && is_numeric( $_POST['donation_amount'] ) ) {
					$cart_item_data['nvm_radio_choice'] = floatval( $_POST['donation_amount'] );
				}
			}

			if ( 'custom' !== $_POST['nvm_radio_choice'] ) {

				$cart_item_data['nvm_radio_choice'] = floatval( sanitize_text_field( $_POST['nvm_radio_choice'] ) );
			}
		}

		return $cart_item_data;
	}

	/**
	 * Adjust product price based on radio choice.
	 *
	 * @param WC_Cart $cart The WooCommerce cart object.
	 */
	public function adjust_product_price_based_on_choice( $cart ) {

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! WC()->cart->is_empty() ) {

			foreach ( $cart->get_cart() as $cart_item ) {

				if ( isset( $cart_item['nvm_radio_choice'] ) ) {
					$new_price = $cart_item['nvm_radio_choice']; // The new price from radio choice.
					$cart_item['data']->set_price( $new_price );
				}
			}
		}
	}

	/**
	 * Add donation data to order items.
	 *
	 * @param \WC_Order_Item $item Order item.
	 * @param string         $cart_item_key Cart item key.
	 * @param array          $values Cart item data.
	 * @param \WC_Order      $order Order object.
	 */
	public function add_donation_to_order_items( $item, $cart_item_key, $values, $order ) {

		if ( isset( $values['nvm_radio_choice'] ) ) {

			$item->add_meta_data( __( 'nvm_radio_choice', 'nevma' ), $values['nvm_radio_choice'] );
		}
	}


	public function initiate_redirect_template() {

		$has_donor_product = false;
		$target_product_id = $this->get_donor_product();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['data'] ) && $cart_item['data']->get_id() === $target_product_id ) {
				$has_donor_product = true;
				break;
			}
		}

		// If the cart contains the "donor" product, remove specific billing fields
		if ( $has_donor_product ) {

			add_filter( 'woocommerce_locate_template', array( $this, 'redirect_wc_template' ), 10, 3 );

			// remove coupon field on donor checkout
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		}
	}

	/**
	 * Filter the cart template path to use cart.php in this plugin instead of the one in WooCommerce.
	 *
	 * @param string $template      Default template file path.
	 * @param string $template_name Template file slug. @phpcs:ignore
	 * @param string $template_path Template file name.
	 *
	 * @return string The new Template file path.
	 */
	public function redirect_wc_template( $template, $template_name, $template_path ) { // phpcs:ignore WordPress.UnusedFunctionParameter.Found

		if ( 'form-checkout.php' === basename( $template ) ) {
			$template = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'woocommerce/checkout/form-checkout.php';
		} elseif ( 'payment.php' === basename( $template ) ) {
			$template = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'woocommerce/checkout/payment.php';
		} elseif ( 'review-order.php' === basename( $template ) ) {
			$template = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'woocommerce/checkout/review-order.php';
		}

		return $template;
	}

	public function nvm_customize_checkout_fields( $fields ) {

			unset( $fields['billing']['billing_company'] );
			unset( $fields['billing']['billing_address_1'] );
			unset( $fields['billing']['billing_address_2'] );
			unset( $fields['billing']['billing_postcode'] );
			unset( $fields['billing']['billing_state'] );
	}

	public function render_donation_form() {

		ob_start();
		do_action( 'donor_before' );
		do_action( 'donor_product' );
		echo do_shortcode( '[woocommerce_checkout]' );
		?>
		<?php
		do_action( 'nvm_donor_after' );
		return ob_get_clean();
	}

	/**
	 * Runs on plugin activation.
	 */
	public static function on_plugin_activation() {

		self::check_plugin_dependencies();
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function on_plugin_deactivation() {
	}

	/**
	 * Runs on plugin uninstall.
	 */
	public static function on_plugin_uninstall() {
	}

	/**
	 * Φόρτωση των μεταφράσεων
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'nevma',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}
}


/**
 * Activation Hook.
 */
register_activation_hook( __FILE__, array( '\\Nvm\\Donor', 'on_plugin_activation' ) );

/**
 * Dectivation Hook.
 */
register_deactivation_hook( __FILE__, array( '\\Nvm\\Donor', 'on_plugin_deactivation' ) );


/**
 * Uninstall Hook.
 */
register_uninstall_hook( __FILE__, array( '\\Nvm\\Donor', 'on_plugin_uninstall' ) );

/**
 * Load plugin.
 */
add_action( 'plugins_loaded', array( '\\Nvm\\Donor', 'get_instance' ) );