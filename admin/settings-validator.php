<?php

namespace WPDiscourse\LatestTopics;

/**
 * Class SettingsValidator
 *
 * This class is adding filters for each of the settings fields. The filters are being applied by
 * the WPDiscourse\Admin\FormHelper::validate_options function. Using that function is optional. If
 * it's not used, a validation method will have to be supplied to the register_setting function for
 * your options.
 *
 * @package WPDiscourse\LatestTopics
 */
class SettingsValidator {
	protected $webhook_refresh = false;

	/**
	 * SettingsValidator constructor.
	 */
	public function __construct() {
		add_filter( 'wpdc_validate_dclt_cache_duration', array( $this, 'validate_int' ) );
		add_filter( 'wpdc_validate_dclt_webhook_refresh', array( $this, 'validate_webhook_request' ) );
		add_filter( 'wpdc_validate_dclt_webhook_secret', array( $this, 'validate_webhook_secret' ) );
		add_filter( 'wpdc_validate_dclt_ajax_load', array( $this, 'validate_checkbox' ) );
		add_filter( 'wpdc_validate_dclt_ajax_timeout', array( $this, 'validate_int' ) );
		add_filter( 'wpdc_validate_dclt_use_default_styles', array( $this, 'validate_checkbox' ) );
		add_filter( 'wpdc_validate_dclt_clear_topics_cache', array( $this, 'validate_checkbox' ) );
	}

	public function validate_checkbox( $input ) {
		return 1 === intval( $input ) ? 1 : 0;
	}

	// This should be improved.
	public function validate_int( $input ) {
		return intval( $input );
	}

	public function validate_webhook_request( $input ) {
		$val = $this->validate_checkbox( $input );
		$this->webhook_refresh = 1 === $val ? true : false;

		return $val;
	}

	public function validate_webhook_secret( $input ) {
		if ( empty( $input) && true === $this->webhook_refresh ) {
			add_settings_error( 'dclt', 'webhook_secret', __( 'To use Discourse webhooks you must provide a webhook secret key.', 'dclt') );

			return '';
		}

		return sanitize_text_field( $input );
	}
}