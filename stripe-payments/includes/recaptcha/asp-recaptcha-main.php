<?php
class ASPRECAPTCHA_main {

	public $asp_main;
	public $keys_entered = false;
	public $enabled      = false;
	private $max_tokens  = 4;

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	public function plugins_loaded() {
		$this->asp_main = AcceptStripePayments::get_instance();

		$this->enabled = $this->asp_main->get_setting( 'recaptcha_enabled' ) === 1;

		if ( ! empty( $this->asp_main->get_setting( 'recaptcha_secret_key' ) ) &&
			! empty( $this->asp_main->get_setting( 'recaptcha_site_key' ) ) ) {
			$this->keys_entered = true;
		}

			add_action( 'wp_ajax_asp_recaptcha_check', array( $this, 'ajax_recaptcha_check' ) );
			add_action( 'wp_ajax_nopriv_asp_recaptcha_check', array( $this, 'ajax_recaptcha_check' ) );

		if ( is_admin() ) {
			include_once WP_ASP_PLUGIN_PATH . 'includes/recaptcha/admin/asp-recaptcha-admin-menu.php';
			new ASPRECAPTCHA_admin_menu();
		}

		if ( $this->enabled ) {
				add_action( 'asp_ng_before_token_request', array( $this, 'ng_before_token_request' ) );
				add_action( 'asp_ng_before_payment_processing', array( $this, 'ng_before_payment_processing' ) );
			if ( ! is_admin() ) {
				add_filter( 'asp-button-output-data-ready', array( $this, 'data_ready' ), 10, 2 );
				add_filter( 'asp-button-output-additional-styles', array( $this, 'output_styles' ) );
				add_action( 'asp-button-output-register-script', array( $this, 'register_script' ) );
				add_action( 'asp-button-output-enqueue-script', array( $this, 'enqueue_script' ) );
				add_filter( 'asp-button-output-after-button', array( $this, 'after_button' ), 10, 3 );
				add_filter( 'asp_before_payment_processing', array( $this, 'before_payment_processing' ), 10, 2 );

				add_filter( 'asp_ng_pp_data_ready', array( $this, 'ng_data_ready' ), 10, 2 );
				add_filter( 'asp_ng_pp_output_before_buttons', array( $this, 'ng_before_buttons' ), 10, 2 );
				add_action( 'asp_ng_pp_output_add_scripts', array( $this, 'ng_add_scripts' ) );
				add_filter( 'asp_ng_button_output_after_button', array( $this, 'ng_button_output_after_button' ), 10, 3 );
			}
		}
	}

	private function check_recatpcha( $response ) {
		$ret = array();
		$res = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret'   => $this->asp_main->get_setting( 'recaptcha_secret_key' ),
					'response' => $response,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			$ret['error'] = __( 'reCaptcha: error occurred during API request.', 'stripe-payments' ) . ' ' . $res->get_error_message();
			return $ret;
		}

		if ( $res['response']['code'] !== 200 ) {
			$ret['error'] = __( 'reCaptcha: error occurred during API request. HTTP Error code:', 'stripe-payments' ) . ' ' . $res['response']['code'];
			return $ret;
		}

		$response = json_decode( $res['body'], true );

		if ( is_null( $response ) ) {
			$ret['error'] = __( 'reCaptcha: error occured parsing API response, invalid JSON data.', 'stripe-payments' );
			return $ret;
		}

		if ( $response['success'] !== true ) {
			$err_codes_str = '';
			if ( is_array( $response['error-codes'] ) ) {
				foreach ( $response['error-codes'] as $error_code ) {
					switch ( $error_code ) {
						case 'invalid-input-response':
							$err_codes_str .= __( 'The response parameter is invalid or malformed.', 'stripe-payments' );
							break;
						case 'missing-input-secret':
							$err_codes_str .= __( 'Secret key is missing.', 'stripe-payments' );
							break;
						case 'invalid-input-secret':
							$err_codes_str .= __( 'Secret key is invalid or malformed.', 'stripe-payments' );
							break;
						case 'missing-input-response':
							$err_codes_str .= __( 'The response parameter is missing.', 'stripe-payments' );
							break;
						default:
							$err_codes_str .= $error_code;
							break;
					}
				}
			}
			$ret['error'] = __( 'reCaptcha: check failed. Following error(s) occurred:', 'stripe-payments' ) . ' ' . $err_codes_str;
			return $ret;
		}
		return $ret;
	}

	public function ajax_recaptcha_check() {
		$out     = array();
		$payload = filter_input( INPUT_POST, 'recaptcha_response', FILTER_SANITIZE_STRING );
		if ( empty( $payload ) ) {
			$out['error'] = __( 'Empty reCaptcha response received.', 'stripe-payments' );
			wp_send_json( $out );
		}

		$out = $this->check_recatpcha( $payload );

		$sess = ASP_Session::get_instance();

		if ( isset( $out['error'] ) ) {
			$sess->set_transient_data( 'reCaptcha_checked', false );
			$sess->set_transient_data( 'reCaptcha_error', $out['error'] );
		} else {
			$sess->set_transient_data( 'reCaptcha_checked', true );
			$sess->set_transient_data( 'reCaptcha_tokens', $this->max_tokens );
		}

		wp_send_json( $out );
	}

	public function ng_before_token_request( $item ) {

		$sess = ASP_Session::get_instance();

		$checked = $sess->get_transient_data( 'reCaptcha_checked' );

		if ( ! $checked ) {
			$err = $sess->get_transient_data( 'reCaptcha_error' );
			if ( empty( $err ) ) {
				$err = __( 'reCaptcha check failed.', 'stripe-payments' );
			}
			wp_send_json(
				array(
					'success' => false,
					'err'     => $err,
				)
			);
		}

		$tokens = $sess->get_transient_data( 'reCaptcha_tokens', 0 );

		if ( $tokens <= 0 ) {
			wp_send_json(
				array(
					'success' => false,
					'err'     => __( 'reCaptcha tokens expired. Please refresh the page and try again.', 'stripe-payments' ),
				)
			);
		}

		$tokens--;
		$sess->set_transient_data( 'reCaptcha_tokens', $tokens );

		return true;
	}

	public function ng_before_payment_processing( $post_data ) {
		$sess = ASP_Session::get_instance();
		$sess->set_transient_data( 'reCaptcha_checked', false );
		$sess->set_transient_data( 'reCaptcha_error', false );
	}

	public function ng_before_buttons( $out, $data ) {
		$invisible = $this->asp_main->get_setting( 'recaptcha_invisible' );
		ob_start();
		?>
<style>
.asp-recaptcha-container {
	height: 78px;
	margin: 15px 0;
	width: auto;
	padding: 0 5px;
}

.asp-recaptcha-invisible {
	height: 0;
	margin: 0 auto;
}

.asp-recaptcha-container div {
	margin: 0 auto;
	height: 78px;
}

#recaptcha-error {
	text-align: center;
}

#asp-recaptcha-google-notice {
	font-size: 75%;
	color: gray;
	text-align: center;
	margin: 5px 0;
	opacity: 0.75;
}
		<?php echo $invisible ? '.grecaptcha-badge {visibility: hidden;}' : ''; ?>
</style>
<div id="asp-recaptcha-container" class="asp-recaptcha-container<?php echo $invisible ? ' asp-recaptcha-invisible' : ''; ?>"></div>
		<?php if ( $invisible ) { ?>
<div id="asp-recaptcha-google-notice">This site is protected by reCAPTCHA and the Google
	<a href="https://policies.google.com/privacy" target="_blank">Privacy Policy</a> and
	<a href="https://policies.google.com/terms" target="_blank">Terms of Service</a> apply.</div>
<?php } ?>
<div id="recaptcha-error" class="form-err" role="alert">
</div>
		<?php
		$out .= ob_get_clean();
		return $out;
	}

	public function data_ready( $data, $atts ) {
		$data['addonHooks'] = isset( $data['addonHooks'] ) ? $data['addonHooks'] : array();
		array_push( $data['addonHooks'], 'recaptcha' );

		$data['recaptchaSiteKey']   = $this->asp_main->get_setting( 'recaptcha_site_key' );
		$data['recaptchaInvisible'] = $this->asp_main->get_setting( 'recaptcha_invisible' );
		return $data;
	}

	public function ng_data_ready( $data, $atts ) {
		if ( $this->enabled && ! $this->keys_entered ) {
			$data['fatal_error'] = __( 'Please enter reCaptcha keys.', 'stripe-payments' );
		}
		$addon                    = array(
			'name'    => 'reCaptcha',
			'handler' => 'reCaptchaHandlerNG',
		);
		$data['addons'][]         = $addon;
		$data['recaptchaSiteKey'] = $this->asp_main->get_setting( 'recaptcha_site_key' );
		return $data;
	}

	public function ng_add_scripts( $scripts ) {
		$scripts[] = array(
			'footer' => true,
			'src'    => 'https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit',
		);
		$scripts[] = array(
			'footer' => true,
			'src'    => WP_ASP_PLUGIN_URL . '/includes/recaptcha/public/js/asp-recaptcha-ng.js?ver=' . WP_ASP_PLUGIN_VERSION,
		);
		return $scripts;
	}

	public function ng_button_output_after_button( $output, $data, $class ) {
		$prefetch = $this->asp_main->get_setting( 'frontend_prefetch_scripts' );
		if ( $prefetch ) {
			if ( empty( $this->asp_main->sc_scripts_prefetched ) ) {
				if ( ! isset( $this->asp_main->footer_scripts ) ) {
					$this->asp_main->footer_scripts = '';
				}
				$this->asp_main->footer_scripts .= '<link rel="prefetch" href="https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit" />';
				$this->asp_main->footer_scripts .= '<link rel="prefetch" href="' . WP_ASP_PLUGIN_URL . '/includes/recaptcha/public/js/asp-recaptcha-ng.js?ver=' . WP_ASP_PLUGIN_VERSION . '" />';
			}
		}
		return $output;
	}

	public function before_payment_processing( $ret, $post ) {
		if ( ! isset( $post['recaptchaKey'] ) || empty( $post['recaptchaKey'] ) ) {
			$ret['error'] = __( 'reCaptcha: missing user response data.', 'stripe-payments' );
			return $ret;
		}
		$payload = sanitize_text_field( $post['recaptchaKey'] );

		$ret = $this->check_recatpcha( $payload );

		return $ret;
	}

	public function output_styles( $output ) {
		ob_start();
		?>
<style>
.asp-recaptcha-modal {
	display: none;
	max-width: 350px !important;
	min-width: 314px;
}

.asp-recaptcha-container {
	height: 100px;
	margin: 0 auto;
	margin-top: 15px;
	width: auto;
	padding: 0 5px;
}

.asp-recaptcha-container div {
	margin: 0 auto;
	height: 78px;
}

div.asp-recaptcha-modal div.iziModal-header {
	background: #3795cb none repeat scroll 0% 0% !important;
}
</style>
		<?php
		$output .= ob_get_clean();
		return $output;
	}

	public function after_button( $output, $data, $class ) {
		ob_start();
		?>
<div id="asp-recaptcha-modal-<?php echo $data['uniq_id']; ?>" class="asp-recaptcha-modal">
	<div id="asp-recaptcha-container-<?php echo $data['uniq_id']; ?>" class="asp-recaptcha-container"></div>
</div>
		<?php
		$output .= ob_get_clean();
		return $output;
	}

	public function register_script() {
		wp_register_script( 'asp-recaptcha-recaptcha', 'https://www.google.com/recaptcha/api.js?render=explicit', array(), null, true );
		wp_register_script( 'asp-recaptcha-handler', WP_ASP_PLUGIN_URL . '/includes/recaptcha/public/js/asp-recaptcha-script.js', array( 'asp-recaptcha-recaptcha', 'jquery' ), WP_ASP_PLUGIN_VERSION, true );
		wp_register_script( 'aspapm-iziModal', WP_ASP_PLUGIN_URL . '/includes/recaptcha/public/js/iziModal.min.js', 'jquery', WP_ASP_PLUGIN_VERSION, true );
		wp_register_style( 'aspapm-iziModal-css', WP_ASP_PLUGIN_URL . '/includes/recaptcha/public/css/iziModal.min.css', null, WP_ASP_PLUGIN_VERSION );
	}

	public function enqueue_script() {
		wp_enqueue_script( 'asp-recaptcha-recaptcha' );
		wp_enqueue_script( 'asp-recaptcha-handler' );
		wp_enqueue_script( 'aspapm-iziModal' );
		wp_enqueue_style( 'aspapm-iziModal-css' );
	}

}

new ASPRECAPTCHA_main();
