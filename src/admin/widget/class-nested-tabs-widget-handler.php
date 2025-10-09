<?php
/**
 * Widget handler for Elementor Nested tab widget.
 *
 * @package Progressus\Gutenberg
 */
namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Elementor_Elements_Parser;

defined( 'ABSPATH' ) || exit;

class Nested_Tabs_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Convert Elementor nested tabs widget to Gutenberg columns + custom HTML.
	 *
	 * Each tab is represented by a child element container whose content is
	 * recursively parsed.
	 *
	 * @param array $element Elementor widget data.
	 * @return string Gutenberg block markup.
	 */
	public function handle( array $element ): string {
		$tabs = $element['elements'] ?? array();
		if ( empty( $tabs ) || ! is_array( $tabs ) ) {
			return '';
		}

		$tab_titles   = array();
		$tab_contents = array();

		foreach ( $tabs as $index => $tab ) {
			$tab_id = 'tab-' . $index;
			$title  = $tab['settings']['_title'] ?? 'Tab ' . ( $index + 1 );

			$content = '';
			if ( ! empty( $tab['elements'] ) ) {
				$content = Elementor_Elements_Parser::parse( $tab['elements'] );
			} elseif ( isset( $tab['settings']['content'] ) ) {
				$content = wp_kses_post( $tab['settings']['content'] );
			}

			$tab_titles[] = sprintf(
				'<button class="gb-tab-title" data-tab="%s">%s</button>',
				esc_attr( $tab_id ),
				esc_html( $title )
			);

			$tab_contents[] = sprintf(
				'<div class="gb-tab-content" id="%s" style="display:%s;">%s</div>',
				esc_attr( $tab_id ),
				0 === $index ? 'block' : 'none',
				$content
			);
		}

		$tabs_html  = '<div class="gb-tabs">';
		$tabs_html .= '<div class="gb-tabs-nav">' . implode( '', $tab_titles ) . '</div>';
		$tabs_html .= '<div class="gb-tabs-contents">' . implode( '', $tab_contents ) . '</div>';
		$tabs_html .= '</div>';

		$block_content  = '<!-- wp:columns -->';
		$block_content .= '<div class="wp-block-columns">';
		$block_content .= '<!-- wp:column {"width":"100%"} --><div class="wp-block-column" style="flex-basis:100%">';
		$block_content .= $tabs_html;
		$block_content .= '</div><!-- /wp:column -->';
		$block_content .= '</div><!-- /wp:columns -->';

		$block_content .= '<script>' .
		                  'document.addEventListener("DOMContentLoaded",function(){' .
		                  'var buttons=document.querySelectorAll(".gb-tab-title");' .
		                  'var contents=document.querySelectorAll(".gb-tab-content");' .
		                  'buttons.forEach(function(btn){' .
		                  'btn.addEventListener("click",function(){' .
		                  'buttons.forEach(function(b){b.classList.remove("active");});' .
		                  'btn.classList.add("active");' .
		                  'contents.forEach(function(c){c.style.display=(c.id===btn.getAttribute("data-tab"))?"block":"none";});' .
		                  '});' .
		                  '});' .
		                  'if(buttons.length)buttons[0].classList.add("active");' .
		                  '});' .
		                  '</script>';

		$block_content .= '<style>' .
		                  '.gb-tabs-nav { display: flex; gap: 10px; margin-bottom: 10px; }' .
		                  '.gb-tab-title { background: #f3f3f3; border: 1px solid #ccc; padding: 8px 16px; cursor: pointer; }' .
		                  '.gb-tab-title.active { background: #e0e0e0; font-weight: bold; }' .
		                  '.gb-tab-content { padding: 10px; border: 1px solid #eee; }' .
		                  '</style>';

		return $block_content;
	}
}