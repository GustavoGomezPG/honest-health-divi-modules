<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Honest_Divi_Module_Base extends ET_Builder_Module {

	public $vb_support = 'partial';

	protected $module_credits = array(
		'module_uri' => '',
		'author'     => 'Honest Health',
		'author_uri' => '',
	);

	/**
	 * Emit the outer module wrapper. Required because vb_support='partial'
	 * means Divi does not wrap third-party module output.
	 */
	protected function wrap( $render_slug, $inner, $extra_classes = array() ) {
		foreach ( (array) $extra_classes as $class ) {
			if ( '' !== $class ) {
				$this->add_classname( $class );
			}
		}

		return sprintf(
			'<div%2$s class="%1$s">%3$s</div>',
			$this->module_classname( $render_slug ),
			$this->module_id(),
			$inner
		);
	}

	/**
	 * Standard design-tab options every module gets.
	 *
	 * @param array $selectors Optional font groups keyed by slug.
	 */
	protected function base_advanced_fields( $selectors = array() ) {
		return array_merge(
			array(
				'fonts'          => $selectors,
				'background'     => array(),
				'margin_padding' => array(),
				'borders'        => array( 'default' => array() ),
				'box_shadow'     => array( 'default' => array() ),
				'button'         => false,
			)
		);
	}
}
