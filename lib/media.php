<?php

/**
 * This class is used in Orbisius SEO Editor
 * This premium WooCommerce extension allows you to change product prices (up/down) for all products or for a selected category and its subcategories.
 * You can review them before actually making the changes.
 * 
 * @see https://orbisius.com/products/wordpress-plugins/woocommerce-extensions/orbisius-seo-editor/
 * @author jaywilliams | myd3.com | https://gist.github.com/jaywilliams
 * @author Svetoslav Marinov (SLAVI) | https://orbisius.com
 */
class Orbisius_SEO_Editor_Media {
	const INFO_GUID = 2;
	const INFO_MEDIA_ATTRIBS = 4;
	const INFO_MEDIA_LINK = 8;
	const ATTACHMENT_LINK = 16;
	const SERIALIZE_NON_SCALAR_META = 2;

	/**
     * Gets attaachment data from ID or by parsing the passed object.
     * Usage: Orbisius_SEO_Editor_Media::getInfo(123);
     * @param id/array $name Description
	 * @see https://wordpress.stackexchange.com/questions/125554/get-image-description
	*/
	public static function getInfo( $attachment_id, $filters = array() ) {
		$data = array();

        if (is_numeric($attachment_id)) {
            $attachment = get_post( $attachment_id );
        } elseif (is_object($attachment_id)) {
	        $attachment = $attachment_id;
        } elseif (is_array($attachment_id)) {
	        $attachment = $attachment_id;
        } else {
        	return $data;
        }

        if (empty($attachment->ID)) {
        	return $data;
		}

		$file_name_without_ext = '';

		// Get file name with extension so the user doesn't
		$attachment_metadata = wp_get_attachment_metadata( $attachment->ID, true );

		if ( !empty( $attachment_metadata['file']) ) {
			$file_name = $attachment_metadata['file'];
			$file_name_without_ext = pathinfo($file_name, PATHINFO_FILENAME);
		}

		$data = array(
            'id' => $attachment->ID,
			'alt' => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
			'src' => '',
			'title' => $attachment->post_title,
			// we're keeping both so we can easily compare if the name was changed or not so we don't query the db again.
			'file_name' => $file_name_without_ext,
			'new_file_name' => $file_name_without_ext,
			'caption' => $attachment->post_excerpt,
			'description' => $attachment->post_content,
			// exif?
		);

		$flags = empty($filters['flags']) ? 0 : (int) $filters['flags'];

        if ($flags & self::INFO_GUID) {
	        $data['guid'] = $attachment->guid; // refactor this could change if switched to another hosting
        }

        if ($flags & self::INFO_MEDIA_ATTRIBS) {
	        $data['image_attributes'] = wp_get_attachment_image_src( $attachment->ID );
        }

        if ($flags & self::INFO_MEDIA_LINK) {
	        $data['link'] = get_permalink( $attachment->ID );
        }

        if (1 || $flags & self::ATTACHMENT_LINK) { // get it for back compat
	        $data['src'] = wp_get_attachment_url($attachment->ID);
        }

		// calc hash of what we have here
		$data['hash'] = Orbisius_SEO_Editor_Util::calcHash($data);
		$data['default'] = Orbisius_SEO_Editor_Media::extract_title($data);

		if (!empty($filters['meta_fields'])) {
			foreach ($filters['meta_fields'] as $meta_key) {
				$meta_val = get_post_meta($data['id'], $meta_key, true);

				if (!empty($meta_val)
				    && !is_scalar($meta_val)
				) { // it could be too large serialized data
					if ($flags & self::SERIALIZE_NON_SCALAR_META) {
						$ser      = serialize( $meta_val );
						$meta_val = self::ENCODED_SER_DATA_VAL_PRFIX . base64_encode( $ser );
					} else {
						$meta_val = self::ENCODED_SER_DATA_VAL_NO_ENC;
					}
				}

				$prefixed_key = Orbisius_SEO_Editor_Media::META_KEY_PRFIX . $meta_key;
				$data[$prefixed_key] = $meta_val;
			}
		}

		return $data;
	}

    /**
     * mapping a key that makes sense -> actual field in the posts table.
     * @var array
     */
    public static $expected_fields2wp = array(
    	'id' => 'ID',
        'title' => 'post_title',
        'caption' => 'post_excerpt',
        'description' => 'post_content',

        // post meta
        'alt' => '_wp_attachment_image_alt',
        'file_name' => '_wp_attached_file',
        'new_file_name' => '_wp_attached_file',
    );

	/**
	 * @return array
	 */
	public static function get_expected_fields2wp() {
		return self::$expected_fields2wp;
	}

	/**
	 * Orbisius_SEO_Editor_Media::setDefaults();
	 * @param array $data
	 * @return array
	 */
	public static function setDefaults($attachment_data) {
		$default = empty($attachment_data['default']) ? self::extract_title($attachment_data) : $attachment_data['default'];

		foreach (self::get_expected_fields2wp() as $field => $wp_var) {
			$attachment_data[$field] = empty($attachment_data[$field]) ? $default : $attachment_data[$field];
		}

		return $attachment_data;
	}

    /**
     * Updates single attachment info
     * Orbisius_SEO_Editor_Media::setInfo(123, array( 'title' => 'aaaa' ));
     * @param int $attachment_id
     * @param array $data
     * @return bool
     */
	public static function setInfo( $attachment_id, $data ) {
		include_once( ABSPATH . 'wp-admin/includes/image.php' );
		$file_api = Orbisius_seo_editor_File::getInstance();

        $post_data = array(
            'ID' => $attachment_id,
        );

        $exp_fields = self::get_expected_fields2wp();
		$wp_db_fields = array_flip($exp_fields);

		$our_meta_key_prefix_regex = '#^' . preg_quote(self::META_KEY_PRFIX, '#') . '#si';
		$user_custom_meta_key_prefix_regex = '#^' . preg_quote(self::USER_META_KEY_PRFIX, '#') . '#si';

		$has_new_name = false;
		$new_name_changed = false;
		$replace_db_keywords = [];

		try {
			// The user wants to change the file name.
			if ( ! empty( $data['file_name'] )
			     && ! empty( $data['new_file_name'] )
			     && $data['file_name'] != $data['new_file_name'] ) {
				$has_new_name = true;

				$data['new_file_name'] = $file_api->sanitize( $data['new_file_name'] );

				// Get path to existing attachment
				$file = get_attached_file( $attachment_id );

				if (empty($file)) {
					throw new Exception("No data for attachment [$attachment_id]");
				}

				/* ‌array (
					  'dirname' => 'C:\\Copy\\Dropbox\\cloud\\projects\\clients\\wp-2018.com\\htdocs/wp-content/uploads/2018/04',
					  'basename' => 'Svetlio_big_IMG_20190907_152902.jpg',
					  'extension' => 'jpg',
					  'filename' => 'Svetlio_big_IMG_20190907_152902',
				)*/
				$path_details = pathinfo( $file );

				// Create new attachment name
				$file_updated_abs_path = $path_details['dirname'] . '/' . $data['new_file_name'];

				$ext = '';

				if ( ! empty( $path_details['extension'] ) ) {
					$ext = $path_details['extension'];
					$file_updated_abs_path .= '.' . $path_details['extension'];
				}

				// we need the prefix so the replace only what we need
				$search_rel = preg_replace('#^.*?(/upload.*)#is', '$1', $file);
				$replace_rel = preg_replace('#^.*?(/upload.*)#is', '$1', $file_updated_abs_path);

				if (!empty($ext)) {
					$ext_q = preg_quote( $ext );

					// This will be like a prefix and we'll be able to replace the resized copies.
					$search_rel_no_ext  = preg_replace( '/\.' . $ext_q . '$/si', '', $search_rel );
					$replace_rel_no_ext  = preg_replace( '/\.' . $ext_q . '$/si', '', $replace_rel );
					$replace_db_keywords[$search_rel_no_ext] = $replace_rel_no_ext;
				}

				$replace_db_keywords[$search_rel] = $replace_rel; // orig

				// Update the attachment name
				$ren_res = rename( $file, $file_updated_abs_path );

				if (empty($ren_res)) {
					throw new Exception("Rename didn't succeed: [$file => $file_updated_abs_path]");
				}

				$upd_res = update_attached_file( $attachment_id, $file_updated_abs_path );

				if (empty($upd_res)) {
					throw new Exception("Update attachment [$attachment_id] didn't succeed.");
				}

				if ( $ren_res && $upd_res ) {
					$new_name_changed = true;

					$meta = wp_get_attachment_metadata( $attachment_id ); // get the data structured

					// delete old images
					wp_delete_attachment_files( $attachment_id, $meta, [], $file );

					// Update attachment meta data
					$file_updated_meta = wp_generate_attachment_metadata( $attachment_id, $file_updated_abs_path );
					wp_update_attachment_metadata( $attachment_id, $file_updated_meta );

					foreach ($replace_db_keywords as $s => $r) {
						$replace_cnt = Orbisius_SEO_Editor_Media::replace_content_text($s, $r);
					}
				}
			}
		} catch (Exception $e) {
			trigger_error($e->getMessage(), E_USER_NOTICE);
		}

        foreach ($data as $key => $val) {
        	if (strcasecmp($key , 'id') == 0 && $val <= 0) { // id=0
        		continue;
	        }

        	// Skip val non-scalar values or if we have our special value
        	if (!is_scalar($val) || $val == self::ENCODED_SER_DATA_VAL_NO_ENC) {
		        continue;
	        }

        	$wp_db_key = '';

        	// Let's get to wp db field. Then we'll check for meta
	        if (isset($exp_fields[$key])) {
		        $wp_db_key = $exp_fields[$key];
	        } elseif (isset($wp_db_fields[$key])) {
		        $wp_db_key = $wp_db_fields[$key];
	        }

        	// known field
	        if (!empty($wp_db_key)) {
                if ($key == 'alt') { // is this a column prefixed by the user?
	                $post_meta[ $wp_db_key ] = $val;
                } else {
	                $post_data[ $wp_db_key ] = $val;
                }
	        }

	        // It's time to check for custom meta stuff.
	        elseif (preg_match($our_meta_key_prefix_regex, $key)) { // is this a column prefixed by the user?
		        $no_prefix_key = preg_replace($our_meta_key_prefix_regex, '', $key);

		        if (!isset($post_meta[ $no_prefix_key ])) {
			        $post_meta[ $no_prefix_key ] = $val;
		        }
	        } elseif (preg_match($user_custom_meta_key_prefix_regex, $key)) { // is this a column prefixed by the user?
		        $no_prefix_key = preg_replace($user_custom_meta_key_prefix_regex, '', $key);

		        if (!isset($post_meta[ $no_prefix_key ])) {
			        $post_meta[ $no_prefix_key ] = $val;
		        }
	        } else {
	        	continue;
	        }
        }

		// We allow empty field in case the user wants to remove a field or set an empty value for it
		$post_data = Orbisius_SEO_Editor_HTML::sanitize($post_data);
		$post_meta = Orbisius_SEO_Editor_HTML::sanitize($post_meta);

        $res1 = false;

        if (!empty($data['hash'])) {
	        $data['id'] = $attachment_id; // hash uses it
	        $new_hash = Orbisius_SEO_Editor_Util::calcHash( $data );

	        if (hash_equals($new_hash, $data['hash'])) { // no change
		        $res1 = true;
		        $res2 = true;
		        $post_data = array();
		        $post_meta = array();
	        }
        }

        // Update the post into the database if at least 1 param is there.
        if (count($post_data) >= 2) {
            $res1 = wp_update_post( $post_data );
        }

        foreach ($post_meta as $k => $v) {
	        $res2 = update_post_meta( $attachment_id, $k, $v);
        }

        return ($res1 !== false) || ($has_new_name && $new_name_changed);
    }

	/**
	 * Orbisius_SEO_Editor_Media::extract_title(array( 'title' => 'aaaa' ));
	 * @param array $data
	 * @return string
	 */
	public static function extract_title( $attachment_data ) {
		if (is_scalar($attachment_data)) {
			$attachment_data = array();
			$attachment_data['src'] = $attachment_data;
		} elseif (is_object($attachment_data)) {
			$attachment_data = (array) $attachment_data;
		}

		if (is_array($attachment_data) && !empty($attachment_data['src'])) {
			$src = $attachment_data['src'];
		} else {
			return '';
		}

		$default_val = parse_url($src, PHP_URL_PATH);
		$default_val = preg_replace('#\?.*#si', '', $default_val);
		$default_val = basename($default_val);
		$default_val = preg_replace('#[\-\_]*\.\w+$#si', '', $default_val);
		$default_val = preg_replace('#[^\w\-]+#si', ' ', $default_val);
		$default_val = preg_replace('#\s+#si', ' ', $default_val);
		$default_val = trim($default_val);
		$default_val = ucfirst($default_val);
		return $default_val;
    }

    const META_SEARCH_FIELD_ALL = 'all';
    const META_SEARCH_FIELD_NONE = 'none';
    const META_SEARCH_FIELD_PREFIXED_BY = 'prefixed_by';
    const META_SEARCH_FIELD_EXACT_META = 'exact_meta';

	const META_KEY_PRFIX = '_orb_seo_ed_meta_';
	const USER_META_KEY_PRFIX = '_orb_media_ed_user_meta_';

	const ENCODED_SER_DATA_VAL_PRFIX = '_orb_seo_ed_enc_meta_base64_';
	const ENCODED_SER_DATA_VAL_NO_ENC = '_orb_seo_ed_enc_none_skipped_';

	/**
	 * Orbisius_SEO_Editor_Media::get_meta_fields(array( 'title' => 'aaaa' ));
	 * @param array $data
	 * @return array
	 */
	public static function get_meta_fields( $what_meta = self::META_SEARCH_FIELD_NONE, $search = '' ) {
		global $wpdb;

		$search = Orbisius_SEO_Editor_HTML::sanitize($search);
		$what_meta = Orbisius_SEO_Editor_HTML::sanitize($what_meta);
		$where_sql = '1=1';
		$search_arr = array();

		switch ($what_meta) {
			case self::META_SEARCH_FIELD_ALL:
				// we're not limiting the query
				break;

			case self::META_SEARCH_FIELD_EXACT_META:
				$search_arr = preg_split('#[\s\;\,\|]+#si', $search);

				foreach ($search_arr as & $val) {
					$val = preg_replace('#[^\w\-]+#si', '', $val);
					$val = Orbisius_SEO_Editor_HTML::sanitize($val);
				}

				$search_arr = array_filter($search_arr);
				$search_arr = array_unique($search_arr);
				break;

			case self::META_SEARCH_FIELD_PREFIXED_BY:
				if (empty($search)) {
					return array();
				}

				$search_esc = esc_sql($search);
				$where_sql = "meta_key LIKE '$search_esc%'";
				break;

			default:
				return array();
				break;
		}

		if (empty($search_arr)) {
			//SELECT meta_key FROM `wp_79666bf8_postmeta` WHERE meta_key like '_wp_page_%' GROUP by meta_key;
			// we're trying to be as efficient as possible and requesting only the meta_key.
			// We're grouping the keys because we can have only one column with the same name.
			// we're gtting only the meta for attachments
			$sql = "SELECT 
					meta_key FROM $wpdb->posts p,$wpdb->postmeta pm
                WHERE
                	p.ID = pm.post_id
                	AND p.post_type = 'attachment'
                	AND INSTR(p.post_mime_type, 'image/') > 0
                	AND $where_sql
                GROUP BY
                 	meta_key
                LIMIT 500
            ";
			$results = $wpdb->get_col($sql);
			$results = empty($results) ? array() : $results;
		} else {
			$results = $search_arr;
		}

		// We'll treat this as ALT tag and will not list it under meta
		$pos = array_search('_wp_attachment_image_alt', $results );

		if ($pos !== false) {
			unset($results[$pos]);
		}

		return $results;
	}

	/**
	 * Replaces text in db directly in post_content.
	 * ideas: replace in post_title
	 * Orbisius_SEO_Editor_Media::replace_content_text_wp_cli();
	 * see https://wordpress.stackexchange.com/questions/285493/wpcli-search-and-replace-variants-for-all-tables
	 * check for php bin and wp-cli
	 * @param string $search_text
	 * @param string $replace_text
	 * @param array $params
	 * @return int
	 */
	static public function replace_content_text_wp_cli($search_text, $replace_text, $params = []) {

	}

	/**
	 * Replaces text in db directly in post_content.
	 * ideas: replace in post_title
	 * Orbisius_SEO_Editor_Media::replace_content_text();
	 *
	 * @param string $search_text
	 * @param string $replace_text
	 * @param array $params
	 * @return int
	 */
	static public function replace_content_text($search_text, $replace_text, $params = []) {
		global $wpdb;

		$search_text = trim($search_text);
		$replace_text = trim($replace_text);

		if (empty($search_text) || !is_scalar($search_text)) {
			return false;
		}

		if (empty($replace_text) || !is_scalar($replace_text)) {
			return false;
		}

		$search_text_esc = esc_sql( $search_text );
		$replace_text_esc = esc_sql( $replace_text );

		// https://wordpress.stackexchange.com/questions/38592/how-to-replace-post-image-url-before-posting-using-api
		// https://premium.wpmudev.org/blog/replacing-image-links/
		// content
		$replace_sql = "UPDATE $wpdb->posts SET post_content = replace(post_content, '$search_text_esc', '$replace_text_esc')";

		// meta featured image
		$replace_featured_image_sql = "UPDATE $wpdb->postmeta SET meta_value = replace(meta_value, '$search_text_esc', '$replace_text_esc') WHERE meta_key='_wp_attached_file'";

		$id = 0;
		$limit = 0;

		if (!empty($params['id'])) {
			$id = $params['id'];
		}

		if (!empty($params['limit'])) {
			$limit = $params['limit'];
		}

		if (!empty($params['meta_key'])) {
			//$limit = $params['limit'];
		}

		$id = intval($id);
		$limit = intval($limit);

		if ($id > 0) {
			$limit = 1;
			$replace_sql .= " WHERE ID = $id";
			$replace_featured_image_sql .= " AND ID = $id";
		}

		if ($limit > 0) {
			$replace_sql .= " LIMIT $limit";
			$replace_featured_image_sql .= " LIMIT $limit";
		}

		$res = $wpdb->query($replace_sql);
		$featured_replace_res = $wpdb->query($replace_featured_image_sql);

		return $res;
	}
}
