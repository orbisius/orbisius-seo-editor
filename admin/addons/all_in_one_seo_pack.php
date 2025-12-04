<?php

$obj = new Orbisius_SEO_Editor_Plugin_Addon_All_In_One_Seo_Pack();
$obj->init();

/**
 * @author Svetoslav Marinov (SLAVI) | https://orbisius.com
 */
class Orbisius_SEO_Editor_Plugin_Addon_All_In_One_Seo_Pack extends Orbisius_SEO_Editor_Plugin_Addon_Base {
	protected $id = 'all_in_one_seo_pack';

	/**
	 * The SEO plugin saves these data in meta fields.
	 * @var string[]
	 */
	protected $meta_mapping = [
		'meta_title' => '_aioseo_title',
		'meta_description' => '_aioseo_description',
	];
}