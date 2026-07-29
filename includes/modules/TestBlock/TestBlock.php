<?php
/**
 * Test Block module.
 *
 * Proves the plugin's registration pipeline end to end and demonstrates the
 * three field mechanics the real modules will reuse: a plain text field, a
 * tiny_mce content field, and a select.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Test_Block extends ET_Builder_Module {

	public $slug = 'honest_test_block';

	/**
	 * 'partial' rather than 'on'.
	 *
	 * 'on' tells the Visual Builder a React component exists for this slug. This
	 * module is PHP only, so the VB falls back and its _getContent() still takes
	 * the 'on' branch, which returns a React component factory instead of a
	 * content string -- the factory then gets stringified into the canvas.
	 * Any non-'off' value keeps full builder support (see has_vb_support()) while
	 * routing content down the string branch.
	 */
	public $vb_support = 'partial';

	protected $module_credits = array(
		'module_uri' => '',
		'author'     => 'Honest Health',
		'author_uri' => '',
	);

	public function init() {
		$this->name   = esc_html__( 'Test Block', 'honest-divi-modules' );
		$this->plural = esc_html__( 'Test Blocks', 'honest-divi-modules' );

		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'general'  => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Content', 'honest-divi-modules' ),
				),
			),
			'advanced' => array(
				'toggles' => array(
					'heading' => esc_html__( 'Heading', 'honest-divi-modules' ),
					'body'    => esc_html__( 'Body', 'honest-divi-modules' ),
				),
			),
		);

		$this->advanced_fields = array(
			'fonts'          => array(
				'heading' => array(
					'label'       => esc_html__( 'Heading', 'honest-divi-modules' ),
					'css'         => array(
						'main' => "{$this->main_css_element} .honest-test-block__heading",
					),
					'font_size'   => array(
						'default' => '28px',
					),
					'toggle_slug' => 'heading',
				),
				'body'    => array(
					'label'       => esc_html__( 'Body', 'honest-divi-modules' ),
					'css'         => array(
						'main' => "{$this->main_css_element} .honest-test-block__body",
					),
					'toggle_slug' => 'body',
				),
			),
			'background'     => array(),
			'margin_padding' => array(),
			'borders'        => array(
				'default' => array(),
			),
			'box_shadow'     => array(
				'default' => array(),
			),
			'button'         => false,
		);
	}

	public function get_fields() {
		return array(
			'heading' => array(
				'label'           => esc_html__( 'Heading', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Text shown as the block heading.', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'content' => array(
				'label'           => esc_html__( 'Body', 'honest-divi-modules' ),
				'type'            => 'tiny_mce',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Body content shown beneath the heading.', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'variant' => array(
				'label'           => esc_html__( 'Style Variant', 'honest-divi-modules' ),
				'type'            => 'select',
				'option_category' => 'configuration',
				'description'     => esc_html__( 'Selects the block presentation.', 'honest-divi-modules' ),
				'options'         => array(
					'default'  => esc_html__( 'Default', 'honest-divi-modules' ),
					'boxed'    => esc_html__( 'Boxed', 'honest-divi-modules' ),
					'bordered' => esc_html__( 'Bordered', 'honest-divi-modules' ),
				),
				'default'         => 'default',
				'toggle_slug'     => 'main_content',
			),
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		$heading = $this->props['heading'];
		$variant = $this->props['variant'];

		$this->add_classname(
			array(
				'honest-test-block',
				'honest-test-block--' . sanitize_html_class( $variant ),
			)
		);

		$heading_html = '' !== $heading
			? sprintf(
				'<h2 class="honest-test-block__heading">%s</h2>',
				esc_html( $heading )
			)
			: '';

		$body_html = '' !== trim( (string) $this->content )
			? sprintf(
				'<div class="honest-test-block__body">%s</div>',
				et_core_esc_previously( $this->content )
			)
			: '';

		return sprintf(
			'<div%2$s class="%1$s">%3$s%4$s</div>',
			$this->module_classname( $render_slug ),
			$this->module_id(),
			$heading_html,
			$body_html
		);
	}
}
