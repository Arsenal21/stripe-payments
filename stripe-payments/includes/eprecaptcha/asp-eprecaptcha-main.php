<?php
class ASP_EPRECAPTCHA_Main {

	public $asp_main;
	public $keys_entered = false;
	public $enabled      = false;
	private $max_tokens  = 4;

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 100 );
	}

	public function plugins_loaded() {
		$this->asp_main = AcceptStripePayments::get_instance();

		$this->enabled = $this->asp_main->get_setting( 'captcha_type' ) === 'eprecaptcha';

		if ( ! empty( $this->asp_main->get_setting( 'eprecaptcha_project_id' ) ) &&
			! empty( $this->asp_main->get_setting( 'eprecaptcha_site_key' ) )  &&
			! empty( $this->asp_main->get_setting( 'eprecaptcha_api_key' ) )  
			) {
			$this->keys_entered = true;
		}

			add_action( 'wp_ajax_asp_eprecaptcha_check', array( $this, 'ajax_eprecaptcha_check' ) );
			add_action( 'wp_ajax_nopriv_asp_eprecaptcha_check', array( $this, 'ajax_eprecaptcha_check' ) );

		if ( is_admin() ) {
			include_once WP_ASP_PLUGIN_PATH . 'includes/eprecaptcha/admin/asp-eprecaptcha-admin-menu.php';
			new ASP_EPRECAPTCHA_Admin_Menu();
		}

		if ( $this->enabled ) {
                        add_action( 'asp_ng_before_token_request', array( $this, 'ng_before_token_request' ) );
                        add_action( 'asp_ng_before_payment_processing', array( $this, 'ng_before_payment_processing' ) );
                        add_action( 'asp_ng_do_additional_captcha_response_check', array( $this, 'ng_do_additional_captcha_response_check' ), 10, 2 );
                        
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

	private function check_eprecatpcha( $token ) {
		$ret = array();
		$projectId = $this->asp_main->get_setting( 'eprecaptcha_project_id' );
		$apiKey = $this->asp_main->get_setting( 'eprecaptcha_api_key' );
		$obj = new stdClass();
		$obj->event = new stdClass();

		$obj->event->siteKey = $this->asp_main->get_setting( 'eprecaptcha_site_key' );
		$obj->event->token=$token;
		$body = json_encode($obj);
		
		$res = wp_remote_post(
			'https://recaptchaenterprise.googleapis.com/v1/projects/'.$projectId.'/assessments?key='.$apiKey,
			array(
				'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
				'data_format' => 'body',
				'body' => $body,
			)
		);
		
		if ( is_wp_error( $res ) ) {
			$ret['error'] = __( 'Enterprice reCaptcha: error occurred during API request.', 'stripe-payments' ) . ' ' . $res->get_error_message();
			return $ret;
		}

		if ( $res['response']['code'] !== 200 ) {
			$ret['error'] = __( 'Enterprise reCaptcha: error occurred during API request. HTTP Error code:', 'stripe-payments' ) . ' ' . $res['response']['code'];
			return $ret;
		}

		$response = json_decode( $res['body'], true );

		if ( is_null( $response ) ) {
			$ret['error'] = __( 'Enterprise reCaptcha: error occured parsing API response, invalid JSON data.', 'stripe-payments' );
			return $ret;
		}

		if ( $response["tokenProperties"]['valid'] !== true ) {			
			$err_codes_str = __($response["tokenProperties"]['invalidReason'] , 'stripe-payments' );			
			$ret['error'] = __( 'Enterprise reCaptcha: check failed. Following error(s) occurred:', 'stripe-payments' ) . ' ' . $err_codes_str;
			return $ret;
		}
		return $ret;
	}

	public function ajax_eprecaptcha_check() {
		$out     = array();
		$payload = filter_input( INPUT_POST, 'eprecaptcha_response', FILTER_SANITIZE_STRING );
		if ( empty( $payload ) ) {
			$out['error'] = __( 'Empty Enterprise reCaptcha response received.', 'stripe-payments' );
			wp_send_json( $out );
		}

		$out = $this->check_eprecatpcha( $payload );

		$sess = ASP_Session::get_instance();

		if ( isset( $out['error'] ) ) {
			$sess->set_transient_data( 'epreCaptcha_checked', false );
			$sess->set_transient_data( 'epreCaptcha_error', $out['error'] );
		} else {
                        ASP_Utils_Bot_Mitigation::record_captcha_solve_ip_time_data();
                    
			$sess->set_transient_data( 'epreCaptcha_checked', true );
			$sess->set_transient_data( 'epreCaptcha_tokens', $this->max_tokens );
		}

		wp_send_json( $out );
	}

        public function ng_do_additional_captcha_response_check( $item, $params ) {

                if ( !ASP_Utils_Bot_Mitigation::is_captcha_solve_ip_data_time_valid() ){
                    ASP_Debug_Logger::log( 'Enterprise reCAPTCHA - Additional captcha response check failed!', false );
                    //Exit out silently. Do not go ahead with the request processing. 
                    //If some sites want more relaxed option, we can add an option to extend the expiry timer maybe.
                    exit;
                }
                
                ASP_Debug_Logger::log( 'Enterprise reCAPTCHA - Additional captcha response check done.', true );
                return true;
        }
        
	public function ng_before_token_request( $item ) {

		$sess = ASP_Session::get_instance();

		$checked = $sess->get_transient_data( 'epreCaptcha_checked' );

		if ( ! $checked ) {
			$err = $sess->get_transient_data( 'epreCaptcha_error' );
			if ( empty( $err ) ) {
				$err = __( 'Enterprise reCaptcha check failed.', 'stripe-payments' );
			}
			wp_send_json(
				array(
					'success' => false,
					'err'     => $err,
				)
			);
		}

		$tokens = $sess->get_transient_data( 'epreCaptcha_tokens', 0 );

		if ( $tokens <= 0 ) {
			wp_send_json(
				array(
					'success' => false,
					'err'     => __( 'epreCaptcha tokens expired. Please refresh the page and try again.', 'stripe-payments' ),
				)
			);
		}

		$tokens--;
		$sess->set_transient_data( 'epreCaptcha_tokens', $tokens );

		return true;
	}

	public function ng_before_payment_processing( $post_data ) {
		$sess = ASP_Session::get_instance();
		$sess->set_transient_data( 'epreCaptcha_checked', false );
		$sess->set_transient_data( 'epreCaptcha_error', false );
	}

	public function ng_before_buttons( $out, $data ) {		
		ob_start();
		?>
<style>
.asp-eprecaptcha-container {
	height: 78px;
	margin: 15px 0;
	width: auto;
	padding: 0 5px;
}

.asp-eprecaptcha-invisible {
	height: 0;
	margin: 0 auto;
}

.asp-eprecaptcha-container div {
	margin: 0 auto;
	height: 78px;
}

#eprecaptcha-error {
	text-align: center;
}

#asp-eprecaptcha-google-notice {
	font-size: 75%;
	color: gray;
	text-align: center;
	margin: 5px 0;
	opacity: 0.75;
}

</style>
<div id="asp-eprecaptcha-container" class="asp-eprecaptcha-container"></div>
<div id="eprecaptcha-error" class="form-err" role="alert">
</div>
		<?php
		$out .= ob_get_clean();
		return $out;
	}

	public function data_ready( $data, $atts ) {
		$data['addonHooks'] = isset( $data['addonHooks'] ) ? $data['addonHooks'] : array();
		array_push( $data['addonHooks'], 'recaptcha' );

		$data['eprecaptchaSiteKey']   = $this->asp_main->get_setting( 'eprecaptcha_site_key' );		
		return $data;
	}

	public function ng_data_ready( $data, $atts ) {
		if ( $this->enabled && ! $this->keys_entered ) {
			$data['fatal_error'] = __( 'Please enter Enterprise reCaptcha keys.', 'stripe-payments' );
		}
		$addon                    = array(
			'name'    => 'epreCaptcha',
			'handler' => 'epreCaptchaHandlerNG',
		);
		$data['addons'][]         = $addon;
		$data['eprecaptchaSiteKey'] = $this->asp_main->get_setting( 'eprecaptcha_site_key' );
		return $data;
	}

	public function ng_add_scripts( $scripts ) {
		$scripts[] = array(
			'footer' => true,
			'src'    => 'https://www.google.com/recaptcha/enterprise.js?onload=onloadCallback&render=explicit',
		);
		$scripts[] = array(
			'footer' => true,
			'src'    => WP_ASP_PLUGIN_URL . '/includes/eprecaptcha/public/js/asp-eprecaptcha-ng.js?ver=' . WP_ASP_PLUGIN_VERSION,
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
				$this->asp_main->footer_scripts .= '<link rel="prefetch" as="script" href="https://www.google.com/recaptcha/enterprise.js?onload=onloadCallback&render=explicit" />';
				$this->asp_main->footer_scripts .= '<link rel="prefetch" as="script" href="' . WP_ASP_PLUGIN_URL . '/includes/eprecaptcha/public/js/asp-eprecaptcha-ng.js?ver=' . WP_ASP_PLUGIN_VERSION . '" />';
			}
		}
		return $output;
	}

	public function before_payment_processing( $ret, $post ) {
		if ( ! isset( $post['eprecaptchaKey'] ) || empty( $post['eprecaptchaKey'] ) ) {
			$ret['error'] = __( 'Enterprise reCaptcha: missing user response data.', 'stripe-payments' );
			return $ret;
		}
		$payload = sanitize_text_field( $post['eprecaptchaKey'] );

		$ret = $this->check_eprecatpcha( $payload );

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
<div id="asp-eprecaptcha-modal-<?php echo esc_attr( $data['uniq_id'] ); ?>" class="asp-eprecaptcha-modal">
	<div id="asp-eprecaptcha-container-<?php echo esc_attr( $data['uniq_id'] ); ?>" class="asp-eprecaptcha-container"></div>
</div>
		<?php
		$output .= ob_get_clean();
		return $output;
	}

	public function register_script() {
		wp_register_script( 'asp-eprecaptcha-eprecaptcha', 'https://www.google.com/recaptcha/enterprise.js?render=explicit', array(), null, true );
		wp_register_script( 'asp-eprecaptcha-handler', WP_ASP_PLUGIN_URL . '/includes/eprecaptcha/public/js/asp-eprecaptcha-script.js', array( 'asp-eprecaptcha-recaptcha', 'jquery' ), WP_ASP_PLUGIN_VERSION, true );
		wp_register_script( 'aspapm-iziModal', WP_ASP_PLUGIN_URL . '/includes/eprecaptcha/public/js/iziModal.min.js', 'jquery', WP_ASP_PLUGIN_VERSION, true );
		wp_register_style( 'aspapm-iziModal-css', WP_ASP_PLUGIN_URL . '/includes/eprecaptcha/public/css/iziModal.min.css', null, WP_ASP_PLUGIN_VERSION );
	}

	public function enqueue_script() {
		wp_enqueue_script( 'asp-eprecaptcha-eprecaptcha' );
		wp_enqueue_script( 'asp-eprecaptcha-handler' );
		wp_enqueue_script( 'aspapm-iziModal' );
		wp_enqueue_style( 'aspapm-iziModal-css' );
	}

}

new ASP_EPRECAPTCHA_Main();
