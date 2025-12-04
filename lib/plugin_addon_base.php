<?php

/**
 * @author Svetoslav Marinov (SLAVI) | https://orbisius.com
 */
class Orbisius_SEO_Editor_Plugin_Addon_Base {
	/**
	 * that's the id of the supported plugin (lowercased camel_case)
	 * @var string
	 */
	protected $id = '';

	// @todo allow some WP fields to also be edited.
	// The idea could be that if it's a WP field then collect them in an array and if at least one
	// run update post
	protected $wp_fields = [
		'excerpt' => 'excerpt',
		'post_title' => 'post_title',
	];

	/**
	 * The SEO plugin saves these data in meta fields.
	 * each plugin addon must define these
	 * My plugin meta key -> target seo plugin meta field
	 * @var array
	 */
	protected $meta_mapping = [
		'meta_title' => 'meta_title',
		'meta_description' => 'meta_description',
		'focus_keyword' => 'focus_keyword',
		'facebook_title' => 'facebook_title',
		'facebook_description' => 'facebook_description',
		'facebook_custom_image_url' => 'facebook_custom_image_url',
		'twitter_title' => 'twitter_title',
		'twitter_description' => 'twitter_description',
		'twitter_custom_image_url' => 'twitter_custom_image_url',
		'breadcrumb_title' => 'breadcrumb_title',
	];

	/**
	 * These apply to app addons
	 * @param array $filters
	 * @return void
	 */
	public function init($filters = []) {
		add_filter('orbisius_seo_editor_filter_supported_addon_fields', [ $this, 'getMetaMapping', ]);
		add_filter('orbisius_seo_editor_filter_single_record_data_loaded', [ $this, 'appendMetaDataToRec', ], 10, 2);
		add_filter('orbisius_seo_editor_filter_save_single_record_data', [ $this, 'updateMetaData', ], 10, 2);
	}

	/**
	 * Reads meta fields
	 * @param array $post_rec
	 * @param array $filters
	 * @return array
	 */
	public function appendMetaDataToRec($post_rec, $filters = []) : array {
		// Let's have a check just in case multiple addons were loaded.
		if (empty($filters['src_seo_plugin']) || $filters['src_seo_plugin'] != $this->getId()) {
			return $post_rec;
		}

		$fields = $this->getMetaMapping();
		$wanted_meta_fields = $fields;

		// Ok the user wants to read/update only certain fields and not all supported fields.
		// the format is key (meta_title, val (human readable) Meta Title.
		// In order to find the current addon's exact field we have to flip the regular mapping.
		// it is _yoast_wpseo_title -> meta_title
		if (!empty($filters['query_filters']['sel_cols'])) {
			$wanted_meta_fields = [];

			foreach ($filters['query_filters']['sel_cols'] as $seo_ed_meta_field => $label) {
				// sometimes during caching a field may be passed that the current addon
				// doesn't have so skip this.
				if (empty($fields[$seo_ed_meta_field])) {
					continue;
				}

				$target_seo_plugin_meta_field = $fields[$seo_ed_meta_field];
				$wanted_meta_fields[$seo_ed_meta_field] = $target_seo_plugin_meta_field;
			}
		}

		foreach ($wanted_meta_fields as $seo_editor_field_id => $target_seo_plugin_meta_key) {
			$post_rec[$seo_editor_field_id] = get_post_meta($post_rec['id'], $target_seo_plugin_meta_key, true);
		}

		return $post_rec;
	}

	/**
	 * Updates meta
	 * @param array $post_rec
	 * @param array $filters
	 * @return Orbisius_SEO_Editor_Result
	 */
	public function updateMetaData($post_rec, $filters = []) {
		$res_obj = empty($filters['res_obj']) ? new Orbisius_SEO_Editor_Result() : $filters['res_obj'];

		// We'll check first if target plugin is selected because we might be able to update the records of another plugin
		if (!empty($filters['target_seo_plugin']) && $filters['target_seo_plugin'] == $this->getId()) {
			// ok
		} elseif (!empty($filters['src_seo_plugin']) && $filters['src_seo_plugin'] == $this->getId()) {
			// ok; The user wants to update the meta of the selected source plugin
		} else {
			$res_obj->processed = 0;
			return $res_obj; // this is not for us to process
		}

		$cnt = 0;
		$updated_fields = [];
		$res_obj->processed = 1;

		$fields = $this->getMetaMapping();
		$wanted_meta_fields = $fields;

		// Ok the user wants to read/update only certain fields and not all supported fields.
		// the format is key (meta_title, val (human readable) Meta Title.
		// In order to find the current addon's exact field we have to flip the regular mapping.
		// it is _yoast_wpseo_title -> meta_title
		if (!empty($filters['query_filters']['sel_cols'])) {
			$wanted_meta_fields = [];

			foreach ($filters['query_filters']['sel_cols'] as $seo_ed_meta_field => $label) {
				// sometimes during caching a field may be passed that the current addon
				// doesn't have so skip this.
				if (empty($fields[$seo_ed_meta_field])) {
					continue;
				}

				$target_seo_plugin_meta_field = $fields[$seo_ed_meta_field];
				$wanted_meta_fields[$seo_ed_meta_field] = $target_seo_plugin_meta_field;
			}
		}

		foreach ($wanted_meta_fields as $seo_editor_field_id => $target_seo_plugin_meta_key) {
			if (empty($post_rec[$seo_editor_field_id])) {
				continue;
			}

			$val = wp_strip_all_tags($post_rec[$seo_editor_field_id]);
			$val = trim($val);

			if (empty($val)) {
				continue;
			}

			$res = update_post_meta($post_rec['id'], $target_seo_plugin_meta_key, $val);

			if ($res === false) {
				continue;
			}

			$cnt++;
			$updated_fields[] = $seo_editor_field_id;
		}

		$res_obj->status($cnt > 0);
		$res_obj->data['updated_fields'] = $updated_fields;

		return $res_obj;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	const FMT_HUMAN_READABLE = 'hr';

	/**
	 * Returns the fields that are mapped.
	 * plugin's meta keys -> more cool.
	 * If param is passed the mapped keys look presentable.
	 * e.g. Meta Title or Meta Description and not meta_title
	 * @return array
	 */
	public function getMetaMapping($fmt = ''): array {
		$mapping = $this->meta_mapping;

		if ($fmt == self::FMT_HUMAN_READABLE) {
			$mapping = Orbisius_SEO_Editor_Util::toHumanReadable($mapping);
		}

		$ctx = [
			'addon_id' => $this->getId(),
		];

		$mapping = apply_filters('orbisius_seo_editor_filter_addon_fields', $mapping, $ctx);

		return $mapping;
	}

	/**
	 * @param string[] $meta_mapping
	 */
	public function setMetaMapping( array $meta_mapping ): void {
		$this->meta_mapping = $meta_mapping;
	}

	/**
	 * Singleton pattern i.e. we have only one instance of this obj
	 * @staticvar static $instance
	 * @return static
	 */
	/*public static function getInstance() {
		static $instance = null;

		// This will make the calling class to be instantiated.
		// no need each sub class to define this method.
		if (is_null($instance)) {
			$instance = new self();
		}

		return $instance;
	}

	private function __construct() {}*/
}
