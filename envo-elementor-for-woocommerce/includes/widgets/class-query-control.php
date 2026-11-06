<?php
namespace ETWW\Widgets;

use Elementor\Base_Data_Control;

if(! defined('ABSPATH')) exit; // Exit if accessed directly

class Query_Control extends Base_Data_Control {
	const CONTROL_ID = 'etww-query-posts';

	public function get_type() {
		return self::CONTROL_ID;
	}

	protected function get_default_settings() {
		return [
			'label_block' => true,
			'multiple'    => false,
			'options'     => [],
			'post_type'   => 'all',
		];
	}

	public function enqueue() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script('etww-query-post',
			ETWW_URL . 'assets/js/query-post.js',
			[ 'jquery' ],
			ETWW_VERSION
		);
		wp_enqueue_script('etww-query-post');
		// Nonce of the two AJAX actions behind the control (Manager::ajax_posts_*).
		wp_add_inline_script( 'etww-query-post', 'window.etwwQueryNonce = ' . wp_json_encode( wp_create_nonce( 'etww_query_posts' ) ) . ';', 'before' );
	}

	public function content_template() {
		$control_uid = $this->get_control_uid(); ?>
		<div class="elementor-control-field">
			<label for="<?php echo esc_attr( $control_uid ); ?>" class="elementor-control-title">{{{ data.label }}}</label>
			<div class="elementor-control-input-wrapper">
				<# var multiple = (data.multiple) ? 'multiple' : ''; #>
				<select id="<?php echo esc_attr( $control_uid ); ?>" class="elementor-select2" type="select2" {{ multiple }} data-setting="{{ data.name }}">
					<# _.each(data.options, function(option_title, option_value) {
						var value = data.controlValue;
						if(typeof value == 'string') {
							var selected = (option_value === value) ? 'selected' : '';
						} else if(null !== value) {
							var value = _.values(value);
							var selected = (-1 !== value.indexOf(option_value)) ? 'selected' : '';
						}
						#>
					<option {{ selected }} value="{{ option_value }}">{{{ option_title }}}</option>
					<# }); #>
				</select>
			</div>
		</div>
		<# if(data.description) { #>
			<div class="elementor-control-field-description">{{{ data.description }}}</div>
		<# } #>
		<?php
	}
}