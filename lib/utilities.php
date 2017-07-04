<?php

namespace WPDiscourse\Shortcodes;

use WPDiscourse\Utilities\Utilities as DiscourseUtilities;

trait Utilities {
	public function get_options() {

		return DiscourseUtilities::get_options();
	}

	public function validate( $response ) {

		return DiscourseUtilities::validate( $response );
	}

	public function get_discourse_categories() {

		return DiscourseUtilities::get_discourse_categories();
	}
}