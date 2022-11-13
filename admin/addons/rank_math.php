<?php

$obj = new Orbisius_SEO_Editor_Plugin_Addon_rank_math();
$obj->init();

/**
 * @author Svetoslav Marinov (SLAVI) | https://orbisius.com
 */
class Orbisius_SEO_Editor_Plugin_Addon_rank_math extends Orbisius_SEO_Editor_Plugin_Addon_Base {
	protected $id = 'rank_math';

	/**
	 * The SEO plugin saves these data in meta fields.
	 * @var string[]
	 */
	protected $meta_mapping = [
		'meta_title' => 'rank_math_title',
		'meta_description' => 'rank_math_description',
	];
}