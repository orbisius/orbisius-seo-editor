<?php

$obj = new Orbisius_SEO_Editor_Plugin_Addon_wpseo(); // Yoast SEO
$obj->init();

/**
 * @author Svetoslav Marinov (SLAVI) | https://orbisius.com
 */
class Orbisius_SEO_Editor_Plugin_Addon_wpseo extends Orbisius_SEO_Editor_Plugin_Addon_Base {
	protected $id = 'wp_seo';

	/**
	 * The SEO plugin saves these data in meta fields.
	 * @var string[]
	 */
	protected $meta_mapping = [
		'meta_title' => '_yoast_wpseo_title',
		'meta_description' => '_yoast_wpseo_metadesc',
        'focus_keyword' => '_yoast_wpseo_focuskw',
	];
}
