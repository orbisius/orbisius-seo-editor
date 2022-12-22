<?php

class Orbisius_SEO_Editor_Util {
	/**
	 * Orbisius_SEO_Editor_Util::getCount();
	 *
	 * @global obj $wpdb
	 * @return int
	 */
	public static function getCount($post_type = 'product') {
		global $wpdb;

		$post_status = $post_type == 'attachment' ? 'inherit' : 'publish';

		$post_type = esc_sql($post_type);
		$post_status = esc_sql($post_status);

		$count = $wpdb->get_var("SELECT COUNT(id) as cnt FROM {$wpdb->posts} WHERE post_type = '$post_type' AND post_status = '$post_status' ");
		$count = empty($count) ? 0 : $count;

		return $count;
	}

	/**
	 * This function loops through the selected items and calls some filters so other plugins/addons can
	 * do their job.
	 * @param array $prod_arr
	 */
	public static function processRecords($prod_arr = array(), $filter_params = array()) {
		$status = 1;
		$work_log = array();
		$status_rec = array();
		$prod_arr = (array) $prod_arr;

		$admin_cherry_picks_items = !empty($filter_params['admin_cherry_picks_products']);

		/*
		$sess_tag_yyyy_mm_dd = 'orb_seo_ed_'
				. date('Ymd_His') . '_'
				. (isset($filter_params['revert']) ? '_revert_' : '') // the user can be reverting price changes
				. sha1(mt_rand(10000, 1000000) . $_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_PORT'] . uniqid()); // get a super unique sha

		$file_prefix = ORBISIUS_SEO_EDITOR_DATA_DIR . $sess_tag_yyyy_mm_dd;
		$file_prefix_url = ORBISIUS_SEO_EDITOR_DATA_DIR_URL . $sess_tag_yyyy_mm_dd;

		$target_txt_file = $file_prefix . '.txt';

		if (!is_dir(dirname($file_prefix))) {
			mkdir(dirname($file_prefix), 0775, 1);
		}*/

		$ctx = $filter_params;

		foreach ($prod_arr as $id => $rec) {
			if ($admin_cherry_picks_items && empty($rec['user_selected'])) {
				$work_log[] = sprintf( "Item ID [%d] skipped (user did not select it).", $id);
				continue;
			} elseif (0&&empty($rec['alt'])) { // A client needed to update other records but not alt, why stop him.
				$work_log[] = sprintf( "Alt not specified: [%d] skipped.", $id);
				continue;
			}

			if (empty($rec['id'])) { // this is probably a csv row so override the $id which is loop index
				$rec['id'] = $id;
			} else {
				$id = $rec['id'];
			}

			// does the record has a hash? if yes, we'll calculated the hash of the passed data and compare
			// if the hashes match we'll skip the record -> no changes to the fields we care about
			if (!empty($rec['hash'])) {
				$local_hash = apply_filters( 'orbisius_seo_editor_filter_calc_record_hash', '', $rec, $ctx );

				if (!empty($local_hash) && hash_equals($local_hash, $rec['hash'])) {
					$work_log[] = sprintf( "Item ID [%d] skipped not modified", $id);
					continue;
				}
			}

			// @todo use a filter to try/catch
			$local_ctx = $ctx;

			// We're using this because we want to know the status
			do_action('orbisius_seo_editor_action_save_single_record_data', $rec, $local_ctx);
			$res_obj = apply_filters('orbisius_seo_editor_filter_save_single_record_data', $rec, $local_ctx);
			// $res = Orbisius_SEO_Editor_Media::setInfo($id, $rec);

			// if it's empty or an array that means that there wasn't a filter to handle it.
			if (empty($res_obj) || !is_object($res_obj)) {
				$work_log[] = sprintf( "Item ID [%d] not processed. Update action not handled by one of our SEO addons. Please contact support with details what you did so we can reproduce it", $id);
			} else {
				$work_log[] = sprintf( "Item ID [%d] processed, success: [%s].", $id, $res_obj->isSuccess() ? 'Yes' : 'No');
			}
		}

		$status_rec['status'] = $status;
		$status_rec['work_log'] = $work_log;

		/*file_put_contents($target_txt_file, join("\n", $work_log), FILE_APPEND | LOCK_EX);

		$status_rec['work_log_csv_file_url'] = $file_prefix_url  . '.csv';
		$status_rec['work_log_txt_file_url'] = $file_prefix_url  . '.txt';*/

		return $status_rec;
	}

	/**
	 * This shortcode parses [orbisius_seo_ed] shortcode and replaces it with code that was
	 * entered in the settings page.
	 * Orbisius_SEO_Editor_Util::generate_product_table
	 *
	 * @param array $attr
	 * @return string
	 */
	public static function generateResultsTable($records, $filters = array(), $full_params = []) {
		$buff = '';

		/* $buff .= sprintf("<div class='updated'><p>Loading products from CSV file [<a href='%s' target='_blank'>%s</a>]  "
						  . "| Download: <a href='%s' target='_blank'>Report file</a> (txt)."
						  . "<br/><strong>Old prices will be preloaded. The actual/current prices will be shown in brackets.</strong></p></div>\n",
			 ORBISIUS_SEO_EDITOR_DATA_DIR_URL . basename($search_items_params['old_price_file']), // csv
			 basename($search_items_params['old_price_file']), // just filename
			 ORBISIUS_SEO_EDITOR_DATA_DIR_URL . str_replace('.csv', '.txt', basename($search_items_params['old_price_file'])) // .txt
		 );*/

		$help_info_export = "You can export the current search into CSV (excel) and exit it that way. After you are done import the modified csv file.";
		$help_info_export_esc = esc_attr($help_info_export);
		$export_btn_html = "<span title='$help_info_export_esc'><button type='submit' id='orbisius_seo_editor_csv_export' "
		                   . "name='orbisius_seo_editor_csv_export' value='orbisius_seo_editor_csv_export' class='orb_seo_ed_save_button button'>"
		                   . __('Export (CSV)' ) . "</button>\n</span>";

		$save_changes_btn_html = "<div style='float:right;margin-top:-30px;margin-bottom:10px;'><button type='submit' id='save_changes' "
		                         . "name='save_changes' value='save_changes' class='orb_seo_ed_save_button button-primary'>"
		                         . __('Save Changes', 'wordpress' ) . "</button>\n | $export_btn_html</div>";

		$save_changes_btn_bottom_html = "<div style='margin-top:10px;margin-bottom:0px;text-align:right;'><button type='submit' id='save_changes' "
		                                . "name='save_changes' value='save_changes' class='orb_seo_ed_save_button button-primary'>"
		                                . __('Save Changes', 'wordpress' ) . "</button>\n | $export_btn_html</div>";

		/*$max_input_vars = ini_get('max_input_vars');
		$max_input_vars = intval($max_input_vars);
		$max_rows = floor($max_input_vars / 5);

		$link = "https://orbisius.com/blog/change-php-max_input_vars-limit-p3799?utm_source=orbisius-seo-editor";

		$buff .= "<div class='orbisius_seo_editor_info'>\n";
		$buff .= "max_input_vars: $max_input_vars, You can edit approx.: $max_rows items at a time "
				. "| <a href='$link' target='_blank' "
				. "title='Read Orbisius blog post on how to increase max_input_vars limit (opens in a new tab/window)'>How to increase it</a>\n";
		$buff .= "</div>\n";*/

		$buff .= "<div class='orbisius_seo_editor_container'>\n";
		$render_id_col = !empty($filters['render_id_col']); //$opts['render_id_col'] != 'none';

		if (!empty($records)) {
			$buff .= $save_changes_btn_html;
			$buff .= "<table class='orbisius_seo_editor_table widefat'>\n";
			$buff .= "<thead><tr>\n";

			$header_columns = array();

			if ($render_id_col) {
				$header_columns['sel_col'] = "\tSelect: "
				                      . "<a href='javascript:void(0);' class='sel_all' "
				                      . "data-cmd='select_all'>All</a>\n"
				                      . "/ <a href='javascript:void(0);' class='sel_all' "
				                      . "data-cmd='select_none'>None</a>\n";

				$header_columns['id'] = "ID / SKU";
			}

			$header_columns['id_col'] = 'ID';
			$header_columns['post_title'] = 'Title';
			$sel_cols = [];

			// must be assoc array
			if (empty($filters['sel_cols']) || !is_array($filters['sel_cols'])) {
				$sel_cols['meta_title']       = 'Meta Title';
				$sel_cols['meta_description'] = 'Meta Description';
			} else {
				$sel_cols = $filters['sel_cols'];
			}

			$header_columns += $sel_cols;
			$header_columns = apply_filters('orbisius_seo_editor_filter_header_cols', $header_columns);

			// We'll generate header columns and each will have a class
			// e.g. product-price
			foreach ($header_columns as $key => $col) {
				$cls = $key;
				$col_esc = esc_html($col);
				$buff .= "\t<th class='$cls'>$col_esc</th>\n";
			}

			$buff .= "</tr></thead><tbody>\n";

			$cnt = 0;

			foreach ($records as $idx => $post_rec) {
				$cnt++;
				$cls = $cnt % 2 == 0 ? 'even' : 'odd';
				$post_rec = (array) $post_rec;
				$columns = array();

				// init
				foreach ($header_columns as $key => $label) {
					$columns[$key] = empty($post_rec[$key]) ? '' : $post_rec[$key];
				}

				//$attachment_data = Orbisius_SEO_Editor_Media::getInfo($post_rec);
				$id = $post_rec['id'];
				$id_esc = (int) $id;

				if ($render_id_col) {
					// Hidden checkbox which appears only if the admin wants to cherry pick products
					$columns['sel_col'] = "<input type='checkbox' name='orbisius_seo_editor_data[$id_esc][user_selected]' value='1' />";

					// ID content
					$id_col = "$cnt) #" . $id_esc;
					$columns['id_col'] = "<a href='javascript:void(0);' title='Click to select (works if individual product selection is enabled).'>$id_col</a>";
				}

				// Thumbnail content
				$t = 'ID: ' . $id_esc;
				//$thumbnail = $attachment_data['src'];
				/*$columns['thumbnail'] = "<a href='javascript:void(0);' "
						. "title=''><img style='width:100%;' src='$thumbnail' alt=''/></a>";*/
				//$columns['thumbnail'] = "<img style='width:100%;' src='$thumbnail' alt='$t' title='$t' />";

				/*if (!empty($prod_params['csv_export_prefill_empty_field'])) {
					$attachment_data = Orbisius_SEO_Editor_Media::setDefaults($attachment_data);
				}*/

				// This is post.php?post=123&action=edit
				$edit_post_link = add_query_arg( [ 'post' => $id, 'action' => 'edit', ], admin_url('post.php') );
				$edit_post_link_esc = esc_url($edit_post_link);

				// Let's add the view post link. We'll just link to the main site.com/?p=123 and let WP redirect
				// this should save us 1 call to get permalink function.
				$view_post_link = add_query_arg( [ 'p' => $id ], site_url() );
				$view_post_link_esc = esc_url($view_post_link);

				$columns['id_col'] = "#$cnt | ID: $id_esc";
				$columns['id_col'] .= " | <a href='$edit_post_link_esc' target='_blank' title='Edit this item (new tab/window)'>Edit</a>";
				$columns['id_col'] .= " | <a href='$view_post_link_esc' target='_blank' title='View the item on public side (new tab/window)'>View</a>";

				// Just displaying the title along with a slug (so we can have more fields to edit see max_input vars for php)
				$columns['post_title'] = esc_html($post_rec['post_title'] . ' | link: ' . $post_rec['post_name']); // let's just show the title to save inputs
				//$columns['post_title'] = Orbisius_SEO_Editor_HTML::text("orbisius_seo_editor_data[$id_esc][title]", $post_rec['post_title'], 'textarea class="widefat" placeholder="Enter title" ');
//				$columns['meta_title'] = Orbisius_SEO_Editor_HTML::text("orbisius_seo_editor_data[$id_esc][meta_title]", $post_rec['meta_title'], 'textarea class="widefat" placeholder="Enter Alt text" ');
//				$columns['meta_description'] = Orbisius_SEO_Editor_HTML::text("orbisius_seo_editor_data[$id_esc][meta_description]", $post_rec['meta_description'], 'textarea class="widefat" placeholder="Enter description" ');

				$ctx = array(
					'product_id' => $id,
				);

				$ctx = array_replace_recursive($ctx, $post_rec);

				$buff .= "<tr class='$cls' data-record_id='$id_esc'>\n";
				$columns = apply_filters('orbisius_seo_editor_filter_body_cols', $columns, $ctx);

				if (!empty($post_rec['hash'])) {
					$hash_esc = esc_attr($post_rec['hash']);
					$hash_hid_field = "<input type='hidden' name='orbisius_seo_editor_data[$id_esc][hash]' value='$hash_esc' />";
					$columns['post_title'] .= $hash_hid_field;
				}

				// We'll generate header columns and each will have a class
				// e.g. product-price
				foreach ($header_columns as $key => $col) {
					// do we need to skip a col? e.g. editing only meta_title
					if ( ! empty( $filters['skip_cols'] ) && in_array($key, $filters['skip_cols']) ) {
						continue;
					}

					$val = empty($columns[$key]) ? '' : $columns[$key];
					$key_esc = esc_attr($key);

					if ($key == 'id' || $key == 'id_col' || $key == 'post_title') {
						$val_html = $val;
					} else {
						$val_html = Orbisius_SEO_Editor_HTML::text(
							"orbisius_seo_editor_data[$id_esc][$key_esc]",
							$val,
							"textarea class='widefat' "
						);
					}

					$cls = sanitize_title($key);
					$buff .= "\t<td class='$cls'>$val_html</td>\n";
				}

				$buff .= "</tr>\n";
			} // foreach

			$buff .= "</tbody></table>\n";
			$buff .= $save_changes_btn_bottom_html;
		} else {
			$buff .= "<div class='app_no_results orbisius_seo_editor_notice'>Nothing found.</div>";
		}

		$buff .= "</div>\n";

		return $buff;
	}

	/**
	 * Retrieves all the posts matching given post type.
	 * If the type is attachment we set the publish status to inherit because
	 * the post status is set to published if not specified.
	 * @param array $filters
	 * @return array
	 */
	public static function getItems($filters = array()) : array {
		try {
			$limit = 100;
			$cur_cache_suspend_flag = wp_suspend_cache_addition();

			$args = array(
				'post_type'           => 'post',
				'suppress_filters'    => true,
				'ignore_sticky_posts' => 1,
			);

			$expected_wp_fields = array( 'post_type', 'post_parent', 'post_mime_type', 'author', 'date_query' );

			foreach ( $expected_wp_fields as $field ) {
				if ( ! empty( $filters[ $field ] ) ) {
					$args[ $field ] = $filters[ $field ];
				}
			}

			if ( ! empty( $filters['post_status'] ) ) {
				$args['post_status'] = $filters['post_status'];
			} elseif ( $args['post_type'] == 'attachment' ) {
				$args['post_status'] = 'inherit';
			} else {
				$args['post_status'] = 'publish';
			}

			$kwd = '';

			if ( ! empty( $filters['keyword'] ) ) {
				$kwd = $filters['keyword'];
			} elseif ( ! empty( $filters['search_kwd'] ) ) {
				$kwd = $filters['search_kwd'];
			}

			// By passing this parameter we do a search
			if ( ! empty( $kwd ) ) {
				$kwd = trim( $kwd );

				// Numerics? Oh that's an ID or comma separated IDs
				if ( is_numeric( $kwd ) || ( strpos( $kwd, ',' ) !== false ) ) {
					$id_array = preg_split( '#\s*[,\|\;]+\s*#si', $kwd ); // splits on multiple commas or pipes in case of an error
					$id_array = array_map( 'intval', $id_array ); // we care about ints
					$id_array = array_unique( $id_array );
					$id_array = array_filter( $id_array ); // rm non-empty

					if ( ! empty( $id_array ) ) {
						$args['post__in'] = $id_array;
					}
				} else {
					$args['s'] = $kwd;
				}
			}

			if ( ! empty( $filters['orderby'] ) ) { // field
				$args['orderby'] = $filters['orderby'];

				if ( ! empty( $filters['order'] ) ) { // asc or desc
					$args['order'] = $filters['order'];
				}
			} elseif ( 0 ) { // this confusing when media items change their spots after update
				$args['orderby'] = 'title';
				$args['order']   = 'asc';
			}

			if ( ! empty( $filters['user_id'] ) ) {
				$args['author'] = $filters['user_id'];
			}

			// The user wants products from a given category
			if ( ! empty( $filters['product_category'] ) ) {
				$cat          = trim( $filters['product_category'] );
				$search_field = is_numeric( $cat ) ? 'id' : 'slug';
				$terms        = array( $cat );

				$args['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'terms'    => $terms, // actual ID or the slug
						'field'    => $search_field,
						'operator' => 'IN'
					),
				);
			}

			// The user wants products from a given category
			if ( ! empty( $filters['category'] ) ) {
				$cat          = trim( $filters['category'] );
				$search_field = is_numeric( $cat ) ? 'id' : 'slug';
				$terms        = array( $cat );

				$args['tax_query'] = array(
					array(
						'taxonomy' => 'category',
						'terms'    => $terms, // actual ID or the slug
						'field'    => $search_field,
						'operator' => 'IN'
					),
				);
			}

			// apply limits if supplied present. -1 means no limit.
			$args['posts_per_page'] = empty( $filters['limit'] ) || $filters['limit'] < - 1 || $filters['limit'] > 100000 ? $limit : $filters['limit'];

			if ( ! empty( $filters['meta'] ) ) {
				$args['meta_query'][] = array(
					array(
						'key'     => $filters['meta']['key'],
						'value'   => $filters['meta']['value'],
						'compare' => '='
					),
				);
			}

			// A transient that doesn't expire has a max name length of 53 characters yet a transient that does expire has a max name length of 45 characters.
			// see https://wordpress.stackexchange.com/questions/20196/transient-object-cache-maximum-key-length
			$cache_id = '';

			if ( 0&&empty( $filters['skip_cache'] ) ) {
				$cache_id = 'orb_seo_ed' . md5( serialize( $args ) ); // less than 45 chars; 10 + 32 (md5)
				$results  = get_transient( $cache_id );

				if ( $results !== false ) {
					return $results;
				}
			} else {
				/**
				 * Don't save stuff in cache.
				 * @see https://www.gubatron.com/blog/2012/11/13/how-to-process-thousands-of-wordpress-posts-without-hitting-or-raising-memory-limits/
				 */
				if ( function_exists( 'wp_suspend_cache_addition' ) ) {
					wp_suspend_cache_addition( true );
				}
			}

			set_time_limit( 15 * 60 );

			$ctx           = [];
			$ctx           = array_replace_recursive( $ctx, $filters );
			$args          = apply_filters( 'orbisius_seo_editor_filter_get_items_args', $args, $ctx );
			$posts_obj_arr = get_posts( $args );
			$desired_cols  = [ 'id', 'title', 'hash', 'post_title', 'post_name', 'post_type', 'meta_title', 'meta_description', ];
			$desired_cols  = apply_filters( 'orbisius_seo_editor_filter_items_fields', $desired_cols, $ctx );
			$posts         = [];

			// Convert to array
			foreach ( $posts_obj_arr as $idx => $obj ) {
				$post_rec_raw = (array) $obj;
				$post_rec = [];

				// We'll pick only the fields we want because we'll save that later in the cache
				// we want minimal data there.
				foreach ($desired_cols as $field) {
					$post_rec[$field] = empty( $post_rec_raw[$field] ) ? '' : $post_rec_raw[$field];
				}

				$post_rec['id'] = $post_rec_raw['ID'];

				// Let's initialize these
				$post_rec['title'] = empty( $post_rec['title'] ) ? '' : $post_rec['title'];

				if ( empty( $post_rec['title'] ) && ! empty( $post_rec['post_title'] ) ) {
					$post_rec['title'] = $post_rec['post_title'];
				}

//				$post_rec['meta_title']       = empty( $post_rec['meta_title'] ) ? '' : $post_rec['meta_title'];
//				$post_rec['meta_description'] = empty( $post_rec['meta_description'] ) ? '' : $post_rec['meta_description'];

				$local_ctx = $ctx;
				$post_rec  = apply_filters( 'orbisius_seo_editor_filter_single_record_data_loaded', $post_rec, $local_ctx );

				if ( empty( $post_rec['hash'] ) ) {
					$post_rec['hash'] = apply_filters( 'orbisius_seo_editor_filter_calc_record_hash', '', $post_rec, $ctx );
				}

				$posts[ $post_rec['id'] ] = $post_rec;
			}

			$posts = apply_filters( 'orbisius_seo_editor_filter_after_get_posts', $posts, $ctx );

			if ( ! empty( $cache_id ) ) {
				set_transient( $cache_id, $posts, 4 * 3600 ); // added cache expiration to 4h
			}
		} finally {
			if ( function_exists( 'wp_suspend_cache_addition' ) ) {
				wp_suspend_cache_addition( $cur_cache_suspend_flag );
			}
		}

		return $posts;
	}

	/**
	 * generates an HTML select
	 * Orbisius_SEO_Editor_Util::htmlSelect();
	 * @param string $name
	 * @param string $sel
	 * @param array $options
	 * @param string|array $attr
	 *
	 * @return string
	 */
	public static function htmlSelect($name = '', $sel = null, $options = array(), $attr = '') {
		$id = preg_replace('#[^\w\-]+#si', '_', $name);
		$id = trim($id, '_');
		$id = sanitize_title($id);

		// if the class wasn't passed we'll add it.
		// What if we want to append id?
		if (stripos($attr, 'class') === false) {
			$css = "$id orbisius_seo_editor_dropdown";
			$attr .= sprintf(' class="%s" ', $css);
		} else {
			$attr = preg_replace('#(\s*class\s*=[\'\"\s]*)#si', '${1}orbisius_seo_editor_dropdown ', $attr);
		}

		$html = "\n" . '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" ' . $attr . '>' . "\n";

		foreach ($options as $key => $label) {
			$key_esc = esc_attr($key);
			$label_esc = esc_html($label);
			$selected = $sel == $key ? ' selected="selected"' : '';
			$html .= "\t<option value='$key_esc' $selected>$label_esc</option>\n";
		}

		$html .= '</select>';
		$html .= "\n";

		return $html;
	}


	/**
	 * Generates radio or checkboxes. If $sel is empty the first element from the $options array
	 * will be used as default. This supports checkboxes if [ is used in the name -> array values so it switches to checkboxes instead.
	 * Multiple values will be selected.
	 * $buff .= 'License: ' . Orbisius_SEO_Editor_Util::generateHtmlBoxes('license', empty($_REQUEST['license']) ? '' : $_REQUEST['license'], $license_types);
	 *
	 * @param type $name
	 * @param type $sel
	 * @param type $options
	 * @param type $attr
	 * @return string
	 */
	public static function generateHtmlBoxes($name = '', $sel = null, $options = array(), $attr = '') {
		$id = empty($options['_id']) ? strtolower($name) : $options['_id'];
		$id = preg_replace('#[^\w-]#si', '_', $id);
		$id = trim($id, '_ ');

		$id = esc_attr($id);
		$name = esc_attr($name);
		$html = "\n<div id='{$id}_container' $attr>\n";

		// if we have [ that means that the user is supposed to select multiple values
		// therefore we'll use checkboxes. Smart, eh?
		$type = (strpos($name, '[') === false) ? 'radio' : 'checkbox';

		if (isset($options['_type'])) { // user has set the type
			$type = $options['_type'];
			unset($options['_type']);
		}

		if (isset($options['_id'])) { // already used at the top
			unset($options['_id']);
		}

		$sep = "<br/>\n";

		if (0&&!is_null($sel) && empty($sel)) {
			$first_key = key($options); // First Element's Key
			//$first_value = reset($options); // First Element's Value

			$sel = $first_key;
		}

		$sel_mod = (array) $sel;

		foreach ($options as $key => $label) {
			$checked = count(array_intersect($sel_mod, array($key, $label))) ? ' checked="checked"' : '';
			$html .= "\t<label> <input type='$type' id='$id' name='$name' value='$key' $checked /> $label</label>" . $sep;
		}

		$html .= '</div>';
		$html .= "\n";

		return $html;
	}

	// generates HTML select
	public static function html_checkbox($name, $val = null, $msg = '', $attr = '') {
		//                                        <?php echo empty($opts['form_new_window']) ? '' : 'checked="checked"';
		$sel = '';
		$name = esc_attr($name);
		$val = esc_attr($val);
		$msg = esc_attr($msg);
		$html = "\n<label for='$name'><input type='checkbox' id='$name' name='$name' value='$val' $sel $attr /> $msg</label>\n";

		return $html;
	}

	/**
	 * a simple status message, no formatting except color.
	 * Orbisius_SEO_Editor_Util::msg()
	 */
	public static function msg($msg, $status = 0, $use_inline_css = 0) {
		$id = sanitize_title(ORBISIUS_SEO_EDITOR_PLUGIN_SLUG);
		$cls = '';
		$inline_css = '';

		if ($use_inline_css) {
			$inline_css = empty($status) ? 'color:red;' : 'color:green;';
			$inline_css .= 'padding:5px; margin: 5px auto;'; // text-align:center;
		} else {
			$cls = empty($status) ? 'app_error error' : 'app_success success';
		}

		$msg = str_ireplace(['<br/>', '<br>' ], '__ESC_BR__', $msg);
		$msg_esc = esc_html($msg);
		$msg_esc = str_ireplace('__ESC_BR__', '<br/>', $msg_esc);
		$str = "<div id='$id-notice' class='$cls' style='$inline_css'><strong>$msg_esc</strong></div>";

		return $str;
	}

	/**
	 * Returns a link to appearance. Taking into account multisite.
	 * Orbisius_SEO_Editor_Util::getPageLink();
	 * @param array $params
	 * @return string
	 */
	static public function getPageLink($page_type) {
		if ($page_type == 'settings') {
			$rel_path = 'options-general.php?page=' . plugin_basename(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);
		} elseif ($page_type == 'import') {
            $rel_path = 'tools.php?page=orbisius_seo_editor_action_page&tab=import';
        } elseif ($page_type == 'support') {
            return 'https://orbisius.com/support/';
        } elseif ($page_type == 'editor') {
			$rel_path = 'tools.php?page=orbisius_seo_editor_action_page';
		} else {
			$rel_path = 'tools.php?page=orbisius_seo_editor_action_page';
		}

		/*if (!empty($params)) {
			$rel_path = Orbisius_SEO_Editor_HTML::addUrlParams($rel_path, $params);
		}*/

		$full_page_link = is_multisite()
			? network_admin_url($rel_path)
			: admin_url($rel_path);

		return $full_page_link;
	}

	/**
	 * This is called by a filter to calculate the hash for the row but only using the fields we care about.
	 * Orbisius_SEO_Editor_Util::calcRecordHash();
	 * @param string $cur_hash
	 * @param array $rec
	 * @param array $ctx
	 * @return string
	 */
	public static function calcRecordHash($cur_hash, $rec = [], $ctx = []) {
		if (!empty($cur_hash)) {
			return $cur_hash;
		}

		$supported_addon_meta_fields = apply_filters('orbisius_seo_editor_filter_supported_addon_fields', [], []);

		$wanted_fields = [ 'id', ];
		$wanted_fields = apply_filters( 'orbisius_seo_editor_filter_header_cols', $wanted_fields, $ctx );
		$wanted_fields = array_merge($wanted_fields, array_keys($supported_addon_meta_fields));

		$cur_hash = Orbisius_SEO_Editor_Util::calcHash($rec, $wanted_fields);

		return $cur_hash;
	}

	/**
	 * Checks if the user has access to certain stuff.
	 * For now we'll require admin access.
	 * Orbisius_SEO_Editor_Util::getRequiredCap();
	 * @return string
	 */
	public static function getRequiredCap() {
		return 'edit_others_posts';
	}

	/**
	 * Checks if the user has access to certain stuff.
	 * For now we'll require admin access.
	 * Orbisius_SEO_Editor_Util::hasAccess();
	 * @return bool
	 */
	public static function hasAccess() {
		if (current_user_can( 'edit_others_posts' )) { // editor
			return true;
		}

		if (current_user_can( 'manage_options' )) { // admin
			return true;
		}

		return false;
	}

	/**
	 * Checks if the user has access to certain stuff.
	 * Orbisius_SEO_Editor_Util::extendRunningTime();
	 * @return void
	 */
	public static function extendRunningTime() {
		$max_life = 15 * 60; // Allow it to run a long time.
		set_time_limit($max_life);
		ignore_user_abort(true); // Give 0 f*** if the browser interrupts the connection we will continue to update prices
	}

	/**
	 * Converts the data to human readable. If it's a string processes or if an array updates the values.
	 * Orbisius_SEO_Editor_Util::toHumanReadable();
	 * @param string|array $data
	 * @return string|array
	 */
	public static function toHumanReadable($data) {
		if (is_array($data)) {
			$new_mapping = [];

			// Let's make my plugin's human readable
			foreach ($data as $plugin_seo_key => $my_key) {
				$my_key_fmt                     = self::toHumanReadable( $my_key );
				$new_mapping[ $plugin_seo_key ] = $my_key_fmt;
			}

			return $new_mapping;
		} elseif (is_scalar($data)) {
			$my_key_fmt                     = str_replace( [ '-', '_', ], ' ', $data );
			$my_key_fmt                     = trim( $my_key_fmt );
			$my_key_fmt                     = ucwords( $my_key_fmt );
			return $my_key_fmt;
		} else {
			return $data;
		}
	}

	/**
	 * Orbisius_SEO_Editor_Util::calcHash(array( 'title' => 'aaaa' ));
	 * @param array $data
	 * @param array $wanted_hash_fields
	 * @return string
	 */
	public static function calcHash( $data, $wanted_hash_fields = [] ) {
		$data = (array) $data;

		// we skip hash because if we keep it it will 100% mess with the final hash
		// we skip src because upon submission we don't send src.
		// We're skipping file_name from the hash calc because because the user may have only changed the file names
		// and not the other fields so we'll save some db requests.
		// We don't need the hash for the file names because we have current file name and the new one
		$skip_fields = array('hash', 'src', 'file_name', 'new_file_name');
		$skip_fields = apply_filters('orbisius_seo_editor_filter_calc_hash_skip_fields', $skip_fields, $data);

		foreach ($skip_fields as $field) {
			unset($data[$field]);
		}

		// If the existing fields is not in the wanted fields remove it or if it's empty
		if (!empty($wanted_hash_fields)) {
			foreach ($data as $field => $val) {
				if (!in_array($field, $wanted_hash_fields) || empty($data[$field])) {
					unset( $data[ $field ] );
				}
			}
		}

		// Ensure that all variables are strings including ints.
		// Consistency is key because php's serialize function serializes different variables differently
		$data = array_map('strval', $data);
		$data = array_filter($data);
		ksort($data);
		$hash = sha1(serialize($data));

		return $hash;
	}

	/**
	 * Orbisius_SEO_Editor_Util::getField('field');
	 * @param string|array $inp_field
	 * @param mixed $default_val
	 * @param mixed $opts
	 * @return mixed|string
	 */
	public function getField($inp_field, $default_val = '', $opts = []) {
		if (is_array($inp_field)) {
			$inp_field = join(',', $inp_field);
		}

		$fields = preg_split('#[\s\;\|\/,]+#si', $inp_field);
		$fields = (array) $fields;

		foreach ( $fields as $field ) {
			if (isset($opts[$field])) {
				return $opts[$field];
			}
		}

		return $default_val;
	}
}
