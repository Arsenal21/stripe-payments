<?php
class ASP_HCAPTCHA_Main {

	public $asp_main;
	public $keys_entered = false;
	public $enabled      = false;
	private $max_tokens  = 4;

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 100 );
	}

	public function plugins_loaded() {
		$this->asp_main = AcceptStripePayments::get_instance();

		$this->enabled = $this->asp_main->get_setting( 'captcha_type' ) === 'hcaptcha';

		if ( ! empty( $this->asp_main->get_setting( 'hcaptcha_secret_key' ) ) &&
			! empty( $this->asp_main->get_setting( 'hcaptcha_site_key' ) ) ) {
			$this->keys_entered = true;
		}

			add_action( 'wp_ajax_asp_hcaptcha_check', array( $this, 'ajax_hcaptcha_check' ) );
			add_action( 'wp_ajax_nopriv_asp_hcaptcha_check', array( $this, 'ajax_hcaptcha_check' ) );

		if ( is_admin() ) {
			require_once WP_ASP_PLUGIN_PATH . 'includes/hcaptcha/admin/asp-hcaptcha-admin-menu.php';
			new ASP_HCAPTCHA_Admin_Menu();
		}

		if ( $this->enabled ) {
			add_action( 'asp_ng_before_token_request', array( $this, 'ng_before_token_request' ) );
			add_action( 'asp_ng_before_payment_processing', array( $this, 'ng_before_payment_processing' ) );
                        add_action( 'asp_ng_do_additional_captcha_response_check', array( $this, 'ng_do_additional_captcha_response_check' ), 10, 2 );
                                                        
			if ( ! is_admin() ) {
				add_filter( 'asp_ng_pp_data_ready', array( $this, 'ng_data_ready' ), 10, 2 );
				add_filter( 'asp_ng_pp_output_before_buttons', array( $this, 'ng_before_buttons' ), 10, 2 );
				add_action( 'asp_ng_pp_output_add_scripts', array( $this, 'ng_add_scripts' ) );
				add_filter( 'asp_ng_button_output_after_button', array( $this, 'ng_button_output_after_button' ), 10, 3 );
			}
		}
	}

	private function check_hcaptcha( $response ) {
		$ret = array();
		$res = wp_remote_post(
			'https://hcaptcha.com/siteverify',
			array(
				'body' => array(
					'secret'   => $this->asp_main->get_setting( 'hcaptcha_secret_key' ),
					'response' => $response,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			$ret['error'] = __( 'hCaptcha: error occurred during API request.', 'stripe-payments' ) . ' ' . $res->get_error_message();
			return $ret;
		}

		if ( $res['response']['code'] !== 200 ) {
			$ret['error'] = __( 'hCaptcha: error occurred during API request. HTTP Error code:', 'stripe-payments' ) . ' ' . $res['response']['code'];
			return $ret;
		}

		$response = json_decode( $res['body'], true );

		if ( is_null( $response ) ) {
			$ret['error'] = __( 'hCaptcha: error occured parsing API response, invalid JSON data.', 'stripe-payments' );
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
			$ret['error'] = __( 'hCaptcha: check failed. Following error(s) occurred:', 'stripe-payments' ) . ' ' . $err_codes_str;
			return $ret;
		}
		return $ret;
	}

	public function ajax_hcaptcha_check() {
		$out     = array();
		$payload = filter_input( INPUT_POST, 'hcaptcha_response', FILTER_SANITIZE_STRING );
		if ( empty( $payload ) ) {
			$out['error'] = __( 'Empty hCaptcha response received.', 'stripe-payments' );
			wp_send_json( $out );
		}

		$out = $this->check_hcaptcha( $payload );

		$sess = ASP_Session::get_instance();

		if ( isset( $out['error'] ) ) {
			$sess->set_transient_data( 'hCaptcha_checked', false );
			$sess->set_transient_data( 'hCaptcha_error', $out['error'] );
		} else {
                        ASP_Utils_Bot_Mitigation::record_captcha_solve_ip_time_data();
                    
			$sess->set_transient_data( 'hCaptcha_checked', true );
			$sess->set_transient_data( 'hCaptcha_tokens', $this->max_tokens );
		}

		wp_send_json( $out );
	}

        public function ng_do_additional_captcha_response_check( $item, $params ) {

                if ( !ASP_Utils_Bot_Mitigation::is_captcha_solve_ip_data_time_valid() ){
                    ASP_Debug_Logger::log( 'hCaptcha - Additional captcha response check failed!', false );
                    //Exit out to be safe. If some sites want more relaxed option, we can add an option to extend the timer.
                    exit;
                }
                
                ASP_Debug_Logger::log( 'hCaptcha - Additional captcha response check done.', true );
                return true;
        }
        
	public function ng_before_token_request( $item ) {

		$sess = ASP_Session::get_instance();

		$checked = $sess->get_transient_data( 'hCaptcha_checked' );

		if ( ! $checked ) {
			$err = $sess->get_transient_data( 'hCaptcha_error' );
			if ( empty( $err ) ) {
				$err = __( 'hCaptcha check failed.', 'stripe-payments' );
			}
			wp_send_json(
				array(
					'success' => false,
					'err'     => $err,
				)
			);
		}

		$tokens = $sess->get_transient_data( 'hCaptcha_tokens', 0 );

		if ( $tokens <= 0 ) {
			wp_send_json(
				array(
					'success' => false,
					'err'     => __( 'hCaptcha tokens expired. Please refresh the page and try again.', 'stripe-payments' ),
				)
			);
		}

		$tokens--;
		$sess->set_transient_data( 'hCaptcha_tokens', $tokens );

		return true;
	}

	public function ng_before_payment_processing( $post_data ) {
		$sess = ASP_Session::get_instance();
		$sess->set_transient_data( 'hCaptcha_checked', false );
		$sess->set_transient_data( 'hCaptcha_error', false );
	}

	public function ng_before_buttons( $out, $data ) {
		$invisible = $this->asp_main->get_setting( 'hcaptcha_invisible' );
		ob_start();
		?>
<style>
.asp-hcaptcha-container {
	text-align: center;
	height: 78px;
	margin: 15px 0;
	width: auto;
	padding: 0 5px;
}

.asp-hcaptcha-invisible {
	height: 0;
	margin: 0 auto;
}

.asp-hcaptcha-container div {
	margin: 0 auto;
	height: 78px;
}

#hcaptcha-error {
	text-align: center;
}

#asp-hcaptcha-privacy-notice {
	font-size: 75%;
	color: gray;
	text-align: center;
	margin: 5px 0;
	opacity: 0.75;
}
		<?php echo $invisible ? '.hcaptcha-badge {visibility: hidden;}' : ''; ?>
</style>
<div id="asp-hcaptcha-container" class="asp-hcaptcha-container<?php echo $invisible ? ' asp-hcaptcha-invisible" data-size="invisible' : ''; ?>"></div>
		<?php if ( $invisible ) { ?>
<div id="asp-hcaptcha-privacy-notice">This site is protected by hCaptcha and its 
<a href="https://hcaptcha.com/privacy" target="_blank">Privacy Policy</a> and 
<a href="https://hcaptcha.com/terms" target="_blank">Terms of Service</a> apply.</div>
<?php } ?>
<div id="hcaptcha-error" class="form-err" role="alert">
</div>
		<?php
		$out .= ob_get_clean();
		return $out;
	}

	public function ng_data_ready( $data, $atts ) {
		if ( $this->enabled && ! $this->keys_entered ) {
			$data['fatal_error'] = __( 'Please enter hCaptcha keys.', 'stripe-payments' );
		}
		$addon                     = array(
			'name'    => 'hCaptcha',
			'handler' => 'hCaptchaHandlerNG',
		);
		$data['addons'][]          = $addon;
		$data['hcaptchaSiteKey']   = $this->asp_main->get_setting( 'hcaptcha_site_key' );
		$data['hcaptchaInvisible'] = $this->asp_main->get_setting( 'hcaptcha_invisible' );
		return $data;
	}

	public function ng_add_scripts( $scripts ) {
		$scripts[] = array(
			'footer' => true,
			'src'    => 'https://js.hcaptcha.com/1/api.js?onload=onloadCallback&render=explicit',
		);
		$scripts[] = array(
			'footer' => true,
			'src'    => WP_ASP_PLUGIN_URL . '/includes/hcaptcha/public/js/asp-hcaptcha-ng.js?ver=' . WP_ASP_PLUGIN_VERSION,
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
				$this->asp_main->footer_scripts .= '<link rel="prefetch" as="script" href="https://js.hcaptcha.com/1/api.js?onload=onloadCallback&render=explicit" />';
				$this->asp_main->footer_scripts .= '<link rel="prefetch" as="script" href="' . WP_ASP_PLUGIN_URL . '/includes/hcaptcha/public/js/asp-hcaptcha-ng.js?ver=' . WP_ASP_PLUGIN_VERSION . '" />';
			}
		}
		return $output;
	}

}

new ASP_HCAPTCHA_Main();
