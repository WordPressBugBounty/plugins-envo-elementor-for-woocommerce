<?php
/**
 * Elementor's own preparation of imported template content: new element IDs and the "on_import" step of every
 * control (the images are copied into the media library).
 *
 * @package ETWW
 */

namespace ETWW\Library;

defined( 'ABSPATH' ) || exit;

class Elementor_Source extends \Elementor\TemplateLibrary\Source_Local {

	/**
	 * No parent constructor: the local source is already registered by Elementor (post type, hooks).
	 */
	public function __construct() {} // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found

	/**
	 * @param array $content Elementor elements.
	 * @return array
	 */
	public function prepare( array $content ) {
		$content = $this->replace_elements_ids( $content );
		return $this->process_export_import_content( $content, 'on_import' );
	}
}
