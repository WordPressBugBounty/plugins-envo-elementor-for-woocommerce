<?php
namespace ETWW\Widgets;

// Elementor Classes
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Widget_Base;

if(! defined('ABSPATH')) exit; // Exit if accessed directly

class AnimatedHeading extends Widget_Base {

	public function get_name() {
		return 'etww-animated-heading';
	}

	public function get_title() {
		return __('Animated Heading', 'envo-elementor-for-woocommerce');
	}

	public function get_icon() {
		
		return 'etww-icon eicon-animated-headline';
	}

	public function get_categories() {
		return [ 'etww-elements' ];
	}

	public function get_script_depends() {
		return [ 'morphext', 'typed' ];
	}

	public function get_style_depends() {
		return [ 'etww-animated-heading' ];
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_animated_heading',
			[
				'label' 		=> __('Heading', 'envo-elementor-for-woocommerce'),
			]
		);

		$this->add_control(
			'heading_layout',
			[
				'label'   		=> __('Layout', 'envo-elementor-for-woocommerce'),
				'type'    		=> Controls_Manager::SELECT,
				'default' 		=> 'animated',
				'options' 		=> [
					'animated' => __('Animated', 'envo-elementor-for-woocommerce'),
					'typed'    => __('Typed', 'envo-elementor-for-woocommerce'),
				],
			]
		);

		$this->add_control(
			'pre_heading',
			[
				'label'       	=> __('Pre Heading', 'envo-elementor-for-woocommerce'),
				'type'        	=> Controls_Manager::TEXTAREA,
				'default'     	=> __('This is an', 'envo-elementor-for-woocommerce'),
				'placeholder' 	=> __('Enter your prefix heading', 'envo-elementor-for-woocommerce'),
				'dynamic' 		=> [ 'active' => true ],
			]
		);

		$this->add_control(
			'animated_heading',
			[
				'label'       	=> __('Heading', 'envo-elementor-for-woocommerce'),
				'description' 	=> __('Write animated heading here with comma separated. Such as Animated, Morphing, Awesome', 'envo-elementor-for-woocommerce'),
				'type'        	=> Controls_Manager::TEXTAREA,
				'default'     	=> __('Animated, Amazing, Awesome', 'envo-elementor-for-woocommerce'),
				'placeholder' 	=> __('Enter your animated heading', 'envo-elementor-for-woocommerce'),
				'dynamic' 		=> [ 'active' => true ],
			]
		);

		$this->add_control(
			'post_heading',
			[
				'label'       	=> __('Post Heading', 'envo-elementor-for-woocommerce'),
				'type'        	=> Controls_Manager::TEXTAREA,
				'default'     	=> __('Heading', 'envo-elementor-for-woocommerce'),
				'placeholder' 	=> __('Enter your suffix heading', 'envo-elementor-for-woocommerce'),
				'dynamic' 		=> [ 'active' => true ],
			]
		);

		$this->add_control(
			'link',
			[
				'label'       	=> __('Link', 'envo-elementor-for-woocommerce'),
				'type'        	=> Controls_Manager::URL,
				'placeholder' 	=> 'http://your-link.com',
			]
		);

		$this->add_control(
			'title_html_tag',
			[
				'label'   		=> __('HTML Tag', 'envo-elementor-for-woocommerce'),
				'type'    		=> Controls_Manager::SELECT,
				'options' 		=> etww_get_available_tags(),
				'default' 		=> 'h2',
				'condition' 	=> [
					'link[url]' => '',
				],
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label'   		=> __('Alignment', 'envo-elementor-for-woocommerce'),
				'type'    		=> Controls_Manager::CHOOSE,
				'options' 		=> [
					'left' => [
						'title' => __('Left', 'envo-elementor-for-woocommerce'),
						'icon'  => 'fa fa-align-left',
					],
					'center' => [
						'title' => __('Center', 'envo-elementor-for-woocommerce'),
						'icon'  => 'fa fa-align-center',
					],
					'right' => [
						'title' => __('Right', 'envo-elementor-for-woocommerce'),
						'icon'  => 'fa fa-align-right',
					],
				],
				'default'      	=> 'center',
				'prefix_class' 	=> 'elementor-align%s-',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_animation',
			[
				'label'     	=> __('Animation Options', 'envo-elementor-for-woocommerce'),
				'condition' 	=> [
					'heading_animation!' => '',
				],
			]
		);

		$this->add_control(
			'heading_animation',
			[
				'label'       	=> __('Animation', 'envo-elementor-for-woocommerce'),
				'type'        	=> Controls_Manager::ANIMATION,
				'default'     	=> 'fadeIn',
				'condition' 	=> [
					'heading_animation!' => '',
					'heading_layout' => 'animated',
				],
			]
		);

		$this->add_control(
			'heading_animation_duration',
			[
				'label'   		=> __('Duration', 'envo-elementor-for-woocommerce'),
				'type'    		=> Controls_Manager::SELECT,
				'default' 		=> '',
				'options' 		=> [
					''     => __('Normal', 'envo-elementor-for-woocommerce'),
					'slow' => __('Slow', 'envo-elementor-for-woocommerce'),
					'fast' => __('Fast', 'envo-elementor-for-woocommerce'),
				],
				'condition' 	=> [
					'heading_animation!' => '',
					'heading_layout' => 'animated',
				],
			]
		);

		$this->add_control(
			'heading_animation_delay',
			[
				'label'     	=> __('Delay (ms)', 'envo-elementor-for-woocommerce'),
				'type'      	=> Controls_Manager::NUMBER,
				'default'   	=> 2500,
				'min'       	=> 100,
				'max'       	=> 7000,
				'step'      	=> 100,
				'condition' 	=> [
					'heading_animation!' => '',
					'heading_layout' => 'animated',
				],
			]
		);

		$this->add_control(
			'type_speed',
			[
				'label'     	=> __('Type Speed', 'envo-elementor-for-woocommerce'),
				'type'      	=> Controls_Manager::NUMBER,
				'default'   	=> 50,
				'min'       	=> 10,
				'max'       	=> 100,
				'step'      	=> 5,
				'condition' 	=> [
					'heading_animation!' => '',
					'heading_layout' => 'typed',
				],
			]
		);

		$this->add_control(
			'start_delay',
			[
				'label'     	=> __('Start Delay', 'envo-elementor-for-woocommerce'),
				'type'      	=> Controls_Manager::NUMBER,
				'default'   	=> 1,
				'min'       	=> 1,
				'max'       	=> 100,
				'step'      	=> 1,
				'condition' 	=> [
					'heading_animation!' => '',
					'heading_layout' => 'typed',
				],
			]
		);

		$this->add_control(
			'back_speed',
			[
				'label'     	=> __('Back Speed', 'envo-elementor-for-woocommerce'),
				'type'      	=> Controls_Manager::NUMBER,
				'default'   	=> 30,
				'min'       	=> 0,
				'max'       	=> 100,
				'step'      	=> 2,
				'condition' 	=> [
					'heading_animation!' => '',
					'heading_layout' => 'typed',
				],
			]
		);

		$this->add_control(
			'back_delay',
			[
				'label'     	=> __('Back Delay', 'envo-elementor-for-woocommerce') . ' (ms)',
				'type'      	=> Controls_Manager::NUMBER,
				'default'   	=> 500,
				'min'       	=> 0,
				'max'       	=> 3000,
				'step'      	=> 50,
				'condition' 	=> [
					'heading_animation!' => '',
					'heading_layout' => 'typed',
				],
			]
		);

		$this->add_control(
			'loop',
			[
				'label'     	=> __('Loop', 'envo-elementor-for-woocommerce'),
				'type'      	=> Controls_Manager::SWITCHER,
				'default'   	=> 'yes',
				'condition' 	=> [
					'heading_animation!' => '',
					'heading_layout' => 'typed',
				],
			]
		);

		$this->add_control(
			'loop_count',
			[
				'label'     	=> __('Loop Count', 'envo-elementor-for-woocommerce'),
				'type'      	=> Controls_Manager::NUMBER,
				'default'   	=> 0,
				'min'       	=> 0,
				'condition' 	=> [
					'loop' => 'yes',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_pre_heading',
			[
				'label'     	=> __('Pre Heading', 'envo-elementor-for-woocommerce'),
				'tab'       	=> Controls_Manager::TAB_STYLE,
				'condition' 	=> [
					'pre_heading!' => '',
				]
			]
		);

		$this->add_control(
			'pre_heading_color',
			[
				'label'     	=> __('Pre Heading Color', 'envo-elementor-for-woocommerce'),
				'type'      	=> Controls_Manager::COLOR,
				'selectors' 	=> [
					'{{WRAPPER}} .etww-heading-wrap .etww-pre-heading' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     		=> 'pre_heading_typography',
				'selector' 		=> '{{WRAPPER}} .etww-heading-wrap .etww-pre-heading',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name'     		=> 'pre_heading_shadow',
				'selector' 		=> '{{WRAPPER}} .etww-heading-wrap .etww-pre-heading',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_animated_heading',
			[
				'label' 		=> __('Animated Heading', 'envo-elementor-for-woocommerce'),
				'tab'   		=> Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'animated_heading_color',
			[
				'label'     	=> __('Color', 'envo-elementor-for-woocommerce'),
				'type'      	=> Controls_Manager::COLOR,
				'selectors' 	=> [
					'{{WRAPPER}} .etww-heading-wrap .etww-heading-tag' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     		=> 'animated_heading_typography',
				'selector' 		=> '{{WRAPPER}} .etww-heading-wrap .etww-heading-tag',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name'     		=> 'animated_heading_shadow',
				'selector' 		=> '{{WRAPPER}} .etww-heading-wrap .etww-heading-tag',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_post_heading',
			[
				'label'     	=> __('Post Heading', 'envo-elementor-for-woocommerce'),
				'tab'       	=> Controls_Manager::TAB_STYLE,
				'condition' 	=> [
					'post_heading!' => '',
				]
			]
		);

		$this->add_control(
			'post_heading_color',
			[
				'label'     	=> __('Post Heading Color', 'envo-elementor-for-woocommerce'),
				'type'      	=> Controls_Manager::COLOR,
				'selectors' 	=> [
					'{{WRAPPER}} .etww-heading-wrap .etww-post-heading' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     		=> 'post_heading_typography',
				'selector' 		=> '{{WRAPPER}} .etww-heading-wrap .etww-post-heading',
			]
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			[
				'name'     		=> 'post_heading_shadow',
				'selector' 		=> '{{WRAPPER}} .etww-heading-wrap .etww-post-heading',
			]
		);

		$this->end_controls_section();

	}

	protected function render() {
		$settings   = $this->get_settings_for_display();
		$id         = $this->get_id();
		$title_tag  = $settings['title_html_tag'];

		$this->add_render_attribute('heading', 'class', 'etww-heading-tag');

		if(! empty($settings['link']['url'])) {
			$this->add_render_attribute('heading', 'href', esc_url($settings['link']['url']));

			if($settings['link']['is_external']) {
				$this->add_render_attribute('heading', 'target', '_blank');
			}

			if(! empty($settings['link']['nofollow'])) {
				$this->add_render_attribute('heading', 'rel', 'nofollow');
			}

			$title_tag = 'a';
		} ?>

		<div id="etww-animated-heading-<?php echo esc_attr($id); ?>" class="etww-heading-wrap">
			<<?php echo esc_html(etww_validate_html_tag( $title_tag )); ?> <?php $this->print_render_attribute_string('heading'); ?>>

				<?php
				if($settings['pre_heading']) { ?>
					<div class="etww-pre-heading"><?php echo esc_attr($settings['pre_heading']); ?></div>
				<?php
				}

				if($settings['animated_heading']
					&& 'animated' == $settings['heading_layout']) {
					$animation_duration = ($settings['heading_animation_duration']) ? ' etww-animated-'. $settings['heading_animation_duration'] : ''; ?>
			   		<div class="etww-animated-heading<?php echo esc_attr($animation_duration); ?>">
			   			<?php echo esc_html(rtrim($settings['animated_heading'], ',')); ?>
			   		</div>
				<?php
				} else if($settings['animated_heading']
					&&  'typed' == $settings['heading_layout']) { ?>
					<div class="etww-animated-heading"></div>
				<?php
				}

				if($settings['post_heading']) { ?>
					<div class="etww-post-heading"><?php echo esc_attr($settings['post_heading']); ?></div>
				<?php
				} ?>

			</<?php echo esc_html(etww_validate_html_tag( $title_tag )) ?>>
		</div>

		<?php
		$type_heading = explode(',', esc_html($settings['animated_heading']));
		$loopCount = isset($settings['loop_count']) ? $settings['loop_count'] : 0;
		$loop = ('yes' == $settings['loop']) ? 'true' : 'false';

		if($settings['animated_heading']) { ?>
			<script>
				jQuery(document).ready(function($) {
		    		"use strict";

		    		<?php if('animated' == $settings['heading_layout']) { ?>
						$('#etww-animated-heading-<?php echo esc_attr($id); ?> .etww-animated-heading').Morphext({
						    animation 	: '<?php echo esc_attr($settings['heading_animation']); ?>',
						    speed 		: <?php echo esc_attr($settings['heading_animation_delay']); ?>,
						});
					<?php } else if('typed' == $settings['heading_layout']) { ?>
						var typed 		= new Typed('#etww-animated-heading-<?php echo esc_attr($id); ?> .etww-animated-heading', {
							strings 	: <?php echo json_encode($type_heading); ?>,
							typeSpeed 	: <?php echo absint($settings['type_speed']); ?>,
							startDelay 	: <?php echo absint($settings['start_delay']); ?>,
							backSpeed 	: <?php echo absint($settings['back_speed']); ?>,
							backDelay 	: <?php echo absint($settings['back_delay']); ?>,
							loop 		: <?php echo esc_attr($loop) ?>,
							loopCount 	: <?php echo absint($loopCount); ?>,
						});
					<?php } ?>

				});
			</script>
		<?php
		}

	}
}