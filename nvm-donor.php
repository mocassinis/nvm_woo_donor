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

use Nvm\Donor\Acf as Nvm_Acf;
use Nvm\Donor\Product as Nvm_Product;


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
		self::initiate_acf_options();
		self::initiate_product_donor();

		// Declare HPOS Compability
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_donor_script' ), 10 );

		// Checkout settings
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'add_donors_ways' ) );
	}

	/**
	 * Autoload.
	 */
	public static function autoload() {
		spl_autoload_register(
			function ( $classes ) {

				$prefix = self::$namespace_prefix;
				$len    = strlen( $prefix );

				if ( 0 !== strncmp( $prefix, $classes, $len ) ) {
					return;
				}

				$relative_class = substr( $classes, $len );
				$path           = explode( '\\', strtolower( str_replace( '_', '-', $relative_class ) ) );
				$file           = array_pop( $path );
				$file           = self::$plugin_dir . 'classes/class-' . $file . '.php';

				if ( file_exists( $file ) ) {
					require $file;
				}

				// add the autoload.php file for the prefixed vendor folder.
				require self::$plugin_dir . 'prefixed/autoload.php';
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

	public function initiate_acf_options() {
		$init = new Nvm_Acf();
	}

	public function initiate_product_donor() {
		$init = new Nvm_Product();
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




	public function customize_checkout_fields( $fields ) {

			unset( $fields['billing']['billing_company'] );
			unset( $fields['billing']['billing_address_1'] );
			unset( $fields['billing']['billing_address_2'] );
			unset( $fields['billing']['billing_postcode'] );
			unset( $fields['billing']['billing_state'] );

		return $fields;
	}

	public function add_donors_ways() {

		$chosen = WC()->session->get( 'donation' );
		$chosen = empty( $chosen ) ? WC()->checkout->get_value( 'donation' ) : $chosen;

		$options = array(
			'individual' => __( 'ΑΤΟΜΙΚΗ ΔΩΡΕΑ', 'nevma' ),
			'corporate'  => __( 'ΕΤΑΙΡΙΚΗ ΔΩΡΕΑ', 'nevma' ),
			'memoriam'   => __( 'ΔΩΡΕΑ ΕΙΣ ΜΝΗΜΗ', 'nevma' ),
		);

		$args = array(
			'type'    => 'radio',
			'class'   => array( 'form-row-wide', 'donation-type' ),
			'options' => $options,
			'default' => array_key_first( $options ),
		);

		echo '<div id="donation-choices">';
		woocommerce_form_field( 'type_of_donation', $args, $chosen );
		echo '</div>';

		?>

		<style>
			.woocommerce form .form-row label,
			.woocommerce-page form .form-row label {
				display: inline-block;
			}
		</style>

	<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function () {
			const orderTypeRadios = document.querySelectorAll('input[name="type_of_donation"]');

			function updateDisplay() {
				const selectedValue = document.querySelector('input[name="type_of_donation"]:checked').value;
				const displayStyle = selectedValue === 'timologio' ? 'block' : 'none';
				document.querySelectorAll('.timologio').forEach(el => el.style.display = displayStyle);
			}

			// Check initially before any clicks
			updateDisplay();

			// Update display on radio button change
			orderTypeRadios.forEach(radio => radio.addEventListener('click', updateDisplay));
		});
	</script>
		<?php
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