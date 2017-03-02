<?php

namespace WPDiscourse\LatestTopics;

use WPDiscourse\Utilities\Utilities as DiscourseUtilities;

class LatestTopics {

	/**
	 * The key for the plugin's options array.
	 *
	 * @access protected
	 * @var string
	 */
	protected $option_key = 'dclt_options';

	/**
	 * The merged options from WP Discourse and WP Discourse Latest Topics.
	 *
	 * All options are held in a single array, use a custom plugin prefix to avoid naming collisions with wp-discourse.
	 *
	 * @access protected
	 * @var array
	 */
	protected $options;

	/**
	 * An instance of the TopicFormatter class.
	 *
	 * @access protected
	 * @var TopicFormatter
	 */
	protected $topic_formatter;

	/**
	 * The Discourse forum url.
	 *
	 * @access protected
	 * @var string
	 */
	protected $discourse_url;

	/**
	 * The options array added by this plugin.
	 *
	 * @access protected
	 * @var array
	 */
	protected $dclt_options = array(
		'dclt_cache_duration'     => 10,
		'dclt_webhook_refresh'    => 0,
		'dclt_webhook_secret'     => '',
		'dclt_clear_topics_cache' => 0,
		'dclt_use_default_styles' => 1,
	);

	/**
	 * LatestTopics constructor.
	 */
	public function __construct( $topic_formatter ) {
		$this->topic_formatter = $topic_formatter;

		add_action( 'init', array( $this, 'initialize_plugin' ) );
		add_filter( 'wpdc_utilities_options_array', array( $this, 'add_options' ) );
		add_action( 'rest_api_init', array( $this, 'initialize_topic_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'plugin_scripts' ) );
	}

	/**
	 * Adds the plugin options, gets the merged wp-discourse/wp-discourse-latest-topics options, sets the discourse_url.
	 */
	public function initialize_plugin() {
		add_option( 'dclt_options', $this->dclt_options );
		$this->options       = DiscourseUtilities::get_options();
		$this->discourse_url = ! empty( $this->options['url'] ) ? $this->options['url'] : null;
	}

	/**
	 * Enqueue styles.
	 */
	public function plugin_scripts() {
		if ( ! empty( $this->options['dclt_use_default_styles'] ) && 1 === intval( $this->options['dclt_use_default_styles'] ) ) {
			wp_register_style( 'dclt_styles', plugins_url( '/css/styles.css', __FILE__ ) );
			wp_enqueue_style( 'dclt_styles' );
			wp_register_script( 'dclt_js', plugins_url( '/js/discourse-latest.js', __FILE__ ), array( 'jquery' ), true );
			$data = array(
				'latestURL' => home_url( '/wp-json/wp-discourse/v1/latest-topics' ),
			);

			wp_enqueue_script( 'dclt_js' );
			wp_localize_script( 'dclt_js', 'dclt', $data );
		}
	}

	/**
	 * Hooks into 'wpdc_utilities_options_array'.
	 *
	 * This function merges the plugins options with the options array that is created in
	 * WPDiscourse\Utilities\Utilities::get_options. Doing this makes it possible to use the FormHelper function in the plugin.
	 * If you aren't using the FormHelper function, there is no need to do this.
	 *
	 * @param array $wpdc_options The unfiltered Discourse options.
	 *
	 * @return array
	 */
	public function add_options( $wpdc_options ) {
		static $merged_options = [];

		if ( empty( $merged_options ) ) {
			$added_options = get_option( $this->option_key );
			if ( is_array( $added_options ) ) {
				$merged_options = array_merge( $wpdc_options, $added_options );
			} else {
				$merged_options = $wpdc_options;
			}
		}

		return $merged_options;
	}

	/**
	 * Initializes a WordPress Rest API route and endpoint.
	 */
	public function initialize_topic_route() {
		register_rest_route( 'wp-discourse/v1', 'latest-topics', array(
			array(
				'methods'  => \WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'create_latest_topics' ),
			),
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_latest_topics' ),
			)
		) );
	}

	/**
	 * Create latest topics.
	 *
	 *
	 * @param \WP_REST_Request $data
	 *
	 * @return null
	 */
	public function create_latest_topics( $data ) {
		$api_enabled = ! empty( $this->options['dclt_webhook_refresh'] ) && 1 === intval( $this->options['dclt_webhook_refresh'] );
		if ( ! $api_enabled ) {

			return 0;
		}

		$data = $this->verify_discourse_request( $data );

		if ( is_wp_error( $data ) ) {
			error_log( $data->get_error_message() );

			return null;
		}

		$latest = $this->latest_topics();

		set_transient( 'dclt_latest_topics', $latest, DAY_IN_SECONDS );

		return 1;
	}

	/**
	 * Get the latest topics from either from the stored transient, or from Discourse.
	 *
	 * @return array
	 */
	public function get_latest_topics() {
		$discourse_topics = get_transient( 'dclt_latest_topics' );
		$plugin_options   = get_option( $this->option_key );
		$force            = ! empty( $plugin_options['dclt_clear_topics_cache'] ) ? $plugin_options['dclt_clear_topics_cache'] : 0;

		if ( empty( $discourse_topics ) || $force ) {
			$discourse_topics = $this->latest_topics();
		}

		$cache_duration = ! empty( $plugin_options['dclt_cache_duration'] ) ? $plugin_options['dclt_cache_duration'] : 10;

		// Todo: This could be set to null. Something needs to happen here.
		set_transient( 'dclt_latest_topics', $discourse_topics, $cache_duration * MINUTE_IN_SECONDS );

		// Allow this to be set by the GET request or the shortcode.
		$formatted_topics = $this->topic_formatter->format_topics( $discourse_topics, array( 'max_topics'      => 5,
		                                                                                     'display_avatars' => 'true',
		) );
		if ( $force ) {
			$plugin_options['dclt_clear_topics_cache'] = 0;

			update_option( $this->option_key, $plugin_options );
		}

		return $formatted_topics;
	}

	/**
	 * Gets the latest topics from Discourse.
	 *
	 * @return array|mixed|null|object
	 */
	protected function latest_topics() {
		if ( empty( $this->discourse_url ) ) {

			return null;
		}

		$latest_url = esc_url( $this->discourse_url . '/latest.json' );

		$remote = wp_remote_get( $latest_url );

		if ( ! DiscourseUtilities::validate( $remote ) ) {

			return null;
		}

		return json_decode( wp_remote_retrieve_body( $remote ), true );
	}

	/**
	 * Verify that the request originated from a Discourse webhook and the the secret keys match.
	 *
	 * @param \WP_REST_Request $data
	 *
	 * @return \WP_Error|\WP_REST_Request
	 */
	protected function verify_discourse_request( $data ) {
		// The X-Discourse-Event-Signature consists of 'sha256=' . hamc of raw payload.
		// It is generated by computing `hash_hmac( 'sha256', $payload, $secret )`
		if ( $sig = substr( $data->get_header( 'X-Discourse-Event-Signature' ), 7 ) ) {
			$payload = $data->get_body();
			// Key used for verifying the request - a matching key needs to be set on the Discourse webhook.
			$secret = ! empty( $this->options['dclt_webhook_secret'] ) ? $this->options['dclt_webhook_secret'] : null;

			if ( ! $secret ) {
				return new \WP_Error( 'Webhook Secret Missing', 'The webhook secret key has not been set.' );
			}

			if ( $sig === hash_hmac( 'sha256', $payload, $secret ) ) {

				return $data;
			} else {

				return new \WP_Error( 'Authentication Failed', 'Discourse Webhook Request Error: signatures did not match.' );
			}
		} else {
			return new \WP_Error( 'Access Denied', 'Discourse Webhook Request Error: the X-Discourse-Event-Signature was not set for the request.' );
		}
	}
}
