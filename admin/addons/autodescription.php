<?php

$obj = new Orbisius_SEO_Editor_Plugin_Addon_Autodescription(); // The SEO Framework
$obj->init();

/**
 * @author Svetoslav Marinov (SLAVI) | https://orbisius.com
 */
class Orbisius_SEO_Editor_Plugin_Addon_Autodescription extends Orbisius_SEO_Editor_Plugin_Addon_Base {
	protected $id = 'autodescription';

	/**
	 * The SEO plugin saves these data in meta fields.
	 * @var string[]
	 */
	protected $meta_mapping = [
		'meta_title' => '_genesis_title',
		'meta_description' => '_genesis_description',
	];
}
