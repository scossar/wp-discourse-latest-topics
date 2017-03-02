<?php

namespace WPDiscourse\LatestTopics;

use WPDiscourse\Utilities\Utilities as DiscourseUtilities;

class Shortcode {
	/**
	 * The plugin options.
	 *
	 * @access protected
	 * @var array
	 */
	protected $options;

	/**
	 * An instance of the LatestTopics class.
	 *
	 * @access protected
	 * @var LatestTopics
	 */
	protected $latest_topics;

	/**
	 * DiscourseLatestShortcode constructor.
	 *
	 * @param LatestTopics $latest_topics An instance of the LatestTopics class.
	 */
	public function __construct( $latest_topics ) {
		$this->latest_topics = $latest_topics;

		add_action( 'init', array( $this, 'setup_options' ) );
		add_shortcode( 'discourse_latest', array( $this, 'discourse_latest' ) );
	}

	/**
	 * Set the plugin options.
	 */
	public function setup_options() {
		$this->options = DiscourseUtilities::get_options();
	}

	/**
	 * Create the shortcode.
	 *
	 * @param array $atts The shortcode attributes.
	 *
	 * @return string
	 */
	public function discourse_latest( $atts ) {

		$attributes = shortcode_atts( array(
			'max_topics' => 5,
			'display_avatars' => 'true',
		), $atts );

		return $discourse_topics = $this->latest_topics->get_latest_topics();
	}
}