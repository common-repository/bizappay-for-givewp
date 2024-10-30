<?php

/**
 * Plugin Name: Bizappay for GiveWP
 * Plugin URI: https://www.bizappay.my
 * Description: Enable online payments using online banking for GiveWP donation. Please read carefully and understand the plugin details before use this plugin. Thank you.
 * Version: 1.0.0
 * Author: Laratech Systems
 * Author URI: https://laratechsystems.my
 * GiveWP tested up to: 2.15.0
 **/

use Give\Helpers\Form\Utils as FormUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if (!function_exists('write_log')) {

    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

}

/**
 * Register payment method.
 * 
 * @since 1.0.0
 * 
 * @return array
 */

function bizappay_for_give_register_payment_method( $gateways ) {

	$gateways['bizappay'] = array(
		'admin_label'		=> __('Bizappay - www.bizappay.my', 'bizappay-for-give'),
		'checkout_label'	=> __('Bizappay', 'bizappay-for-give'),
	);

	return $gateways;
}

add_filter('give_payment_gateways', 'bizappay_for_give_register_payment_method');

/**
 * Register section for Payment Gateway Settings.
 * 
 * @param array $sectons List of payment gateway sections.
 * 
 * @since 1.0.0
 * 
 * @return array
 */ 

function bizappay_for_give_register_payment_gateway_sections( $sections ) {

	$sections['bizappay-settings'] = __('Bizappay', 'bizappay-for-give');

	return $sections;
}

add_filter('give_get_sections_gateways', 'bizappay_for_give_register_payment_gateway_sections');


/**
 * Register Bizappay Admin settings.
 * 
 * @param array $settings List of admin setting options.
 * 
 * @since 1.0.0
 * 
 * @return array
 */

 function bizappay_for_give_register_payment_gateway_setting_fields( $settings )  {

 	switch( give_get_current_setting_section() ) {
 		case 'bizappay-settings':

	 		$settings = array(
	 			array(
	 				'id'	=> 'give_title_bizappay',
	 				'type'	=> 'title'
	 			),
	 		);

	 		$settings[] = array(
	 			'name'		=> __( 'Sandbox Mode?', 'bizappay-sandbox-mode' ),
	 			'desc'		=> __( 'Register your sandbox account at stg.bizappay.my/merchant.', 'bizappay-for-give' ),
	 			'id'		=> 'bizappay_for_give_sandbox_mode',
	 			'type'		=> 'checkbox',
	 			'default'	=> 'no',
	 		);

	 		$settings[] = array(
	 			'name'	=> __( 'Username / Email', 'bizappay-email-username' ),
	 			'desc'	=> __( 'Enter your Bizappay\'s username/email', 'bizappay-for-give' ),
	 			'id'	=> 'bizappay_for_give_email_username',
	 			'type'	=> 'text',
	 		);

	 		$settings[] = array(
	 			'name'	=> __( 'API Key', 'bizappay-api-key' ),
	 			'desc'	=> __( 'Enter your API Key, found in your Bizappay profile', 'bizappay-for-give' ),
	 			'id'	=> 'bizappay_for_give_api_key',
	 			'type'	=> 'text',
	 		);

	 		$settings[] = array(
	 			'name'	=> __( 'Category code', 'bizappay-category-code' ),
	 			'desc'	=> __( 'Category code can be found at your Bizappay account > Category section', 'bizappay-for-give' ),
	 			'id'	=> 'bizappay_for_give_category_code',
	 			'type'	=> 'text',
	 		);

	 		$settings[] = array(
	 			'id'	=> 'give_title_bizappay',
	 			'type'	=> 'sectionend',
	 		);

 		break;
 	}

 	return $settings;
 }

 add_filter( 'give_get_settings_gateways', 'bizappay_for_give_register_payment_gateway_setting_fields' );

 /**
  * Add our payment instruction on checkout
  * 
  * @since 1.0.0
  * 
  * @param int $form_id Give Form id.
  * 
  * @return bool
  */

function give_bizappay_checkout_form( $form_id )  {


	if ( FormUtils::isLegacyForm( $form_id ) ) {
		return false;
	}

	printf(
		'
		<fieldset class="no-fields">
			<div style="display: flex; justify-content: center; margin-top: 20px;">
				<img src="'.plugins_url( 'assets/images/bizappay.png' , __FILE__ ).'" height="66" />
			</div>
			<p style="text-align: center;"><b>%1$s</b></p>
			<p style="text-align: center;">
				<b>%2$s</b> %3$s
			</p>
		</fieldset>
	',
		__( 'Pay your donation securely with Bizappay', 'bizappay-for-give' ),
		__( 'How it works:', 'bizappay-for-give' ),
		__( 'You will be redirected to Bizappay payment page. You will then be brought back to this page to view your receipt.', 'bizappay-for-give' )
	);

	return true;

}

add_action( 'give_bizappay_cc_form', 'give_bizappay_checkout_form' );


/**
 * Submit donation to Payment Gateway
 * 
 * @param array $posted_data List of posted data.
 * 
 * @since 1.0.0
 * @access public
 * 
 * @return void
 */

function bizappay_for_give_process_donation( $posted_data ) {
	
	give_clear_errors();
	$errors = give_get_errors();

	// No errors, proceed.
	if ( ! $errors ) {

		$form_id         = intval( $posted_data['post_data']['give-form-id'] );
		$price_id        = ! empty( $posted_data['post_data']['give-price-id'] ) ? $posted_data['post_data']['give-price-id'] : 0;
		$donation_amount = ! empty( $posted_data['price'] ) ? $posted_data['price'] : 0;

		$email = sanitize_email($posted_data['user_email']);

		$hash = md5(give_get_option('bizappay_for_give_api_key').$form_id.$donation_amount.$email.$posted_data['purchase_key']);

		// Setup the payment details.
		$donation_data = array(
			'price'           => $donation_amount,
			'give_form_title' => $posted_data['post_data']['give-form-title'],
			'give_form_id'    => $form_id,
			'give_price_id'   => $price_id,
			'date'            => $posted_data['date'],
			'user_email'      => $email,
			'purchase_key'    => $posted_data['purchase_key'],
			'currency'        => give_get_currency( $form_id ),
			'user_info'       => $posted_data['user_info'],
			'category'	  	  => give_get_option('bizappay_for_give_category_code'),
			'status'          => 'pending',
			'gateway'         => 'bizappay',
			'merchant'		  => give_get_option('bizappay_for_give_email_username'),
			'hash'			  => $hash,
			'checkout'		  => give_get_success_page_uri(),
			'user_first_last_name' => $posted_data['user_info']['first_name'].' '.$posted_data['user_info']['last_name'],
		);

		// Record the pending donation.
		$donation_id = give_insert_payment( $donation_data );

		if ( ! $donation_id ) {

			// Record Gateway Error as Pending Donation in Give is not created.
			give_record_gateway_error( __( 'Payment Error', 'bizappay-for-give' ), sprintf( __( 'Unable to create a pending donation with Give.', 'bizappay-for-give' ) ) );

			// Send user back to checkout.
			give_send_back_to_checkout( '?payment-mode=bizappay' );
			return;
		}

		// Do the actual payment processing using the custom payment gateway API.
        give_call_bizappay_api( $donation_data, give_get_option('bizappay_for_give_sandbox_mode'), $donation_id );

	} else {

		// Send user back to checkout.
		give_send_back_to_checkout( '?payment-mode=bizappay' );
	} // End if().
}

add_action( 'give_gateway_bizappay', 'bizappay_for_give_process_donation' );

/**
 * Call Bizappay API and redirect to the API url with $_GET prams
 * 
 * @since 1.0.0
 */

 function give_call_bizappay_api( $postdata, $gateway_settings, $donationid )  {

 	if( $gateway_settings == true ) {
 		$url = 'https://stg.bizappay.my/api/givewp?did='.$donationid.'&';
 	} else {
 		$url = 'https://www.bizappay.my/api/givewp?did='.$donationid.'&';
 	}

 	# Format it properly using get
	$givewp_args = '';
	foreach ( $postdata as $key => $value ) {
		if ( $givewp_args != '' ) {
			$givewp_args .= '&';
		}
		$givewp_args .= $key . "=" . $value;
	}

	wp_redirect($url . $givewp_args);

 }


# for users not logged in
add_action('wp_ajax_nopriv_payment_callback_action', 'give_bizappay_callback_action');

# for users logged in
add_action('wp_ajax_payment_callback_action', 'give_bizappay_callback_action');

# Your callback
function give_bizappay_callback_action() { 
	
	$paymentStatus = intval($_POST['payStatus']) == 0 ? false : intval($_POST['payStatus']);
	$donationId = intval($_POST['donationId']) == 0 ? false : intval($_POST['donationId']);
	$invoiceNo = filter_input(INPUT_POST, "invoiceNo", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	$theirHash = sanitize_key($_POST['hash']);

	if( $paymentStatus && $donationId && !empty($invoiceNo) && !empty($theirHash) ) {
	// 	echo Give()->seq_donation_number->get_serial_code( $_POST['donation_id'] );
		$ourHash = md5(give_get_option('bizappay_for_give_api_key').$paymentStatus.$donationId.$invoiceNo.'ok');
		
		if($ourHash == $theirHash) {

			switch($paymentStatus) {
				case 1:
					give_update_payment_status($donationId, 'publish');
				break;
					
				case 3:
					give_update_payment_status($donationId, 'failed');
				break;				
			}
			
			give_insert_payment_note($donationId, 'Payment via Bizappay Inv. # :'.$invoiceNo);
		}

	} else {
		write_log('unable to update donation status');
	}	
}

?>