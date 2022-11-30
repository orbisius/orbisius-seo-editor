<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$admin_obj = Orbisius_SEO_Editor_Admin::getInstance();
$admin_obj->installHooks();

class Orbisius_SEO_Editor_Admin {
    private $tabs = [
	    'seo_editor' => [
		    'title' => 'SEO Editor',
	    ],

	    'import' => [
		    'title' => 'Import',
	    ],

	    'support' => [
		    'title' => 'Support',
	    ],

	    'about' => [
		    'title' => 'About',
	    ],
    ];

	public function installHooks() {
        if (!defined('ORBISIUS_SEO_EDITOR_DATA_DIR')) {
	        /*
			 * Array (
				[path] => C:\path\to\wordpress\wp-content\uploads\2010\05
				[url] => https://example.com/wp-content/uploads/2010/05
				[subdir] => /2010/05
				[basedir] => C:\path\to\wordpress\wp-content\uploads
				[baseurl] => https://example.com/wp-content/uploads
				[error] =>
			)
			// Descriptions
			[path] - base directory and sub directory or full path to upload directory.
			[url] - base url and sub directory or absolute URL to upload directory.
			[subdir] - sub directory if uploads use year/month folders option is on.
			[basedir] - path without subdir.
			[baseurl] - URL path without subdir.
			[error] - set to false.
			*/
	        $upload_dir_rec = wp_upload_dir();

	        $data_dir     = $upload_dir_rec['basedir'] . '/.ht_orbisius_seo_editor/';
	        $data_dir_url = $upload_dir_rec['baseurl'] . '/.ht_orbisius_seo_editor/';

	        define( 'ORBISIUS_SEO_EDITOR_DATA_DIR', $data_dir );
	        define( 'ORBISIUS_SEO_EDITOR_DATA_DIR_URL', $data_dir_url );
        }

		add_action( 'init', [ $this, 'init' ], 11 );
		add_action( 'admin_menu', [ $this, 'setupAdminLinks' ] );
		add_action( 'admin_init', [ $this, 'onAdminInit' ]);
		add_action( 'admin_init', [ $this, 'registerSettings' ]);
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ]);
		add_action( 'wp_before_admin_bar_render', [ $this, 'addLinksToWPAdminBar' ], 100);

		add_filter( 'orbisius_seo_editor_filter_calc_record_hash', 'Orbisius_SEO_Editor_Util::calcRecordHash', 10, 3 );
		add_filter( 'orbisius_seo_editor_action_render_tab_content', [ $this, 'renderTabContent' ]);
	}

	/**
     * This checks the current tab and loads the file automatically. 
	 * @return void
	 */
    public function onAdminInit() {
	    add_action( "wp_ajax_orbisius_seo_editor_search_load_supported_addon_fields", [ $this, 'loadCurrentSEOAddonsSupportedFieldsAjax' ] );
	    add_action( "wp_ajax_nopriv_orbisius_seo_editor_search_load_supported_addon_fields", [ $this, 'loadCurrentSEOAddonsSupportedFieldsAjax' ] );
    }

	/**
     * Outputs json
	 * @return void
	 */
    public function loadCurrentSEOAddonsSupportedFieldsAjax() {
        try {
            $src_field = '';
	        $meta_fields = [];
	        $res_obj = new Orbisius_SEO_Editor_Result();

            if (!Orbisius_SEO_Editor_Util::hasAccess()) { // could have been logged out
                throw new Exception("Cannot proceed. Please refresh the page and try again.");
            }

	        $filter_options = $this->getSearchFilter();

	        // The currently loaded addon (because of the orbisius_seo_editor_search[src_seo_plugin] param) should fill in this array's values.
	        // the returned array will be my_seo plugin meta => seo addon fields.
	        // We'll take the keys and create a new array which will contain my plugin's keys.
	        // e.g. meta_title => Meta Title
	        // When the save happens we are looking for my plugin's meta fields which later will be mapped
	        // to the specific target plugin we have.
	        $supported_addon_meta_fields = apply_filters('orbisius_seo_editor_filter_supported_addon_fields', [], []);
	        $keys = array_keys($supported_addon_meta_fields);
	        $meta_fields = empty($supported_addon_meta_fields)
                ? [ '' => 'Nothing found', ]
                : [ '' => '== Select ==', 'meta_title_and_description' => 'Meta Title and Description' ] + array_combine($keys, $keys);
	        $meta_fields = Orbisius_SEO_Editor_Util::toHumanReadable($meta_fields);
	        $src_field = $filter_options['src_field'];

            // Is this a supported field? if no do not return it.
            if (!empty($src_field) && empty($meta_fields[$src_field])) {
	            $src_field = '';
            }

	        $res_obj->status = 1;
        } catch (Exception $e) {
	        $res_obj->status = 0;
        } finally {
	        $res_obj->data['src_field'] = $src_field;
	        $res_obj->data['supported_fields'] = $meta_fields;
        }

	    wp_send_json( $res_obj );
    }

	/**
     * This checks the current tab and loads the file automatically.
	 * @return void
	 */
    public function renderTabContent() {
	    $tab_id = $this->getCurrentTabId();
	    $tab_file = ORBISIUS_SEO_EDITOR_BASE_DIR . "/admin/tabs/$tab_id.php";

        try {
	        ob_start();

	        if ( file_exists( $tab_file ) ) {
		        echo "\n<!-- tab_content_start-->\n";
		        include $tab_file;
		        echo "\n<!-- tab_content_end -->\n";
	        } else {
		        echo "\n<!--tab_content:none;-->\n";
	        }
        } finally {
	        echo ob_get_clean();
        }
    }

	/**
	 * @return void
	 */
	function init() {
		if (is_admin()) {
			$plugin_manager_obj = Orbisius_SEO_Editor_Plugin_Manager::getInstance();
			$search_items_params = $this->getSearchFilter();

			$ctx = [
				'filter_options' => $search_items_params,
			];

			do_action('orbisius_seo_editor_action_init', $ctx);

			// We need to include this here so load, import, save can work
			if (!empty($search_items_params['src_seo_plugin'])) {
				$fmt = $plugin_manager_obj->formatPluginSlug($search_items_params['src_seo_plugin']);
				$addon_file = ORBISIUS_SEO_EDITOR_BASE_DIR . "/admin/addons/{$fmt}.php";

				if (file_exists($addon_file)) {
					require_once $addon_file;
				} else {
					//wp_die('SEO Plugin Process not found: ' . $fmt);
				}
			}

			// We'll load the target addon as well if requested.
			// The idea is migration. The user may want to copy the meta info from one plugin to another.
			// Cool, eh?
			if (!empty($search_items_params['target_seo_plugin'])
			    && $search_items_params['target_seo_plugin'] != $search_items_params['src_seo_plugin']) {
				$fmt = $plugin_manager_obj->formatPluginSlug($search_items_params['target_seo_plugin']);
				$addon_file = ORBISIUS_SEO_EDITOR_BASE_DIR . "/admin/addons/{$fmt}.php";

				if (file_exists($addon_file)) {
					require_once $addon_file;
				} else {
					//wp_die('Target SEO Plugin Process not found: ' . $fmt);
                }
			}

			if ((!empty($_REQUEST['orbisius_seo_editor_csv_export']) || !empty($_REQUEST['orbisius_seo_editor_csv_export_and_set_defaults']))
			    && Orbisius_SEO_Editor_Util::hasAccess()
			) {
                // We need to query the records after the SEO addon was loaded.
                $search_items_params['skip_cache'] = 1;
				$records = Orbisius_SEO_Editor_Util::getItems($search_items_params);

				if (empty($records)) {
					wp_die( "No items found" );
				}

                $csv_obj = new Orbisius_SEO_Editor_CSV();
				$csv_obj->setDownloadFileName($this->generateCSVDownloadFileName($search_items_params));

				$supported_addon_meta_fields = apply_filters('orbisius_seo_editor_filter_supported_addon_fields', [], $ctx);

                $heading_cols = [
                    'id',
                    'title',
                ];

                $heading_cols2 = [ // later appended
	                'src_seo_plugin',
	                'post_type',
	                'post_name', // slug
	                'hash',
                ];

                $heading_cols = apply_filters( 'orbisius_seo_editor_filter_header_cols', $heading_cols, $ctx );
				$heading_cols = array_merge($heading_cols, array_keys($supported_addon_meta_fields), $heading_cols2);

                foreach ( $records as $idx => $rec ) {
                    $columns = array();

                    foreach ($heading_cols as $col_name) {
                        $columns[$col_name] = empty($rec[$col_name]) ? '' : $rec[$col_name];
                    }

                    // Let's insert what SEO plugin we've used but it must be in the heading column
	                if (in_array('src_seo_plugin', $heading_cols)
                        && empty($columns['src_seo_plugin'])
                        && !empty($search_items_params['src_seo_plugin'])
                    ) {
		                $columns['src_seo_plugin'] = $search_items_params['src_seo_plugin'];
                    }

                    // when no file is supplied, we'll send the CSV to browser directly.
                    $csv_obj->write( $columns, array_keys( $columns ) );
                }

                exit;
			}
		}
	}

	/**
	 * Setups loading of assets (css, js).
	 * for live servers we'll use the minified versions e.g. main.min.js otherwise .js or .css (dev)
	 * @see https://statopt.com - for JS and CSS compression
	 */
	function enqueueAdminAssets() {
		$dev = empty($_SERVER['DEV_ENV']) ? 0 : 1;
		$suffix = $dev ? '' : '.min';

		wp_enqueue_script( 'jquery' );

		$file_rel = '/assets/main.js';
		wp_enqueue_script(
			'orbisius_seo_editor',
			plugins_url( $file_rel, ORBISIUS_SEO_EDITOR_BASE_PLUGIN ), array( 'jquery', ),
			filemtime( plugin_dir_path( ORBISIUS_SEO_EDITOR_BASE_PLUGIN ) . $file_rel ),
			true
		);

        // Makes long dropdowns easily selectable
		$file_rel = '/share/select2/4.1.0-rc.0/select2.min.js';
		wp_enqueue_script(
			'orbisius_seo_editor_shared_select2',
			plugins_url( $file_rel, ORBISIUS_SEO_EDITOR_BASE_PLUGIN ),
            array( 'jquery', ),
			filemtime( plugin_dir_path( ORBISIUS_SEO_EDITOR_BASE_PLUGIN ) . $file_rel ),
			true
		);

		$file_rel = '/share/select2/4.1.0-rc.0/select2.min.css';
		wp_enqueue_style(
			'orbisius_seo_editor_shared_select2',
			plugins_url( $file_rel, ORBISIUS_SEO_EDITOR_BASE_PLUGIN ),
			filemtime( plugin_dir_path( ORBISIUS_SEO_EDITOR_BASE_PLUGIN ) . $file_rel ),
			true
		);

		$ctx = [];
		do_action('orbisius_seo_editor_admin_action_enqueue_assets', $ctx);
    }

	/**
	 * Adds admin bar items for easy access to the theme creator and editor
	 */
	function addLinksToWPAdminBar() {
		$this->addNodeToWPAdminBar('Orbisius SEO Editor', esc_url( Orbisius_SEO_Editor_Util::getPageLink('editor') ) );
		$this->addNodeToWPAdminBar('Editor', esc_url( Orbisius_SEO_Editor_Util::getPageLink('editor'), ORBISIUS_SEO_EDITOR_BASE_PLUGIN) );
		$this->addNodeToWPAdminBar('Settings', esc_url( Orbisius_SEO_Editor_Util::getPageLink('settings'), ORBISIUS_SEO_EDITOR_BASE_PLUGIN) );
		$this->addNodeToWPAdminBar('Support', esc_url( Orbisius_SEO_Editor_Util::getPageLink('support'), ORBISIUS_SEO_EDITOR_BASE_PLUGIN) );
	}

	/**
	 * Add's menu parent or submenu item.
	 * @param string $name the label of the menu item
	 * @param string $href the link to the item (settings page or ext site)
	 * @param string $parent Parent label (if creating a submenu item)
	 *
	 * @return void
	 * @author Slavi Marinov <https://orbisius.com>
	 * */
	function addNodeToWPAdminBar($name, $href = '', $parent = '', $custom_meta = array()) {
		global $wp_admin_bar;

		if (!is_super_admin()
		    || !is_admin_bar_showing()
		    || !is_object($wp_admin_bar)
		    || !function_exists('is_admin_bar_showing')) {
			return;
		}

		// Generate ID based on the current filename and the name supplied.
		$id = str_replace('.php', '', basename(ORBISIUS_SEO_EDITOR_BASE_PLUGIN));

		if (!empty($parent)) {
			$id .= '-submenu-' . $name;
		}

		$id = preg_replace('#[^\w\-]#si', '-', $id);
		$id = strtolower($id);
		$id = trim($id, '-');

		$parent = basename($parent); // jic
		$parent = trim($parent);

		// Generate the ID of the parent.
		if (!empty($parent)) {
			$parent = str_replace('.php', '', $parent);
			$parent = preg_replace('#[^\w\-]#si', '-', $parent);
			$parent = strtolower($parent);
			$parent = trim($parent, '-');
		}

		// links from the current host will open in the current window
		$site_url = site_url();

		$meta_default = array();
		$meta_ext = array( 'target' => '_blank' );

		// external links open in new tab/window automatically such as support
		$meta = (strpos($href, $site_url) !== false) ? $meta_default : $meta_ext;
		$meta = array_merge($meta, $custom_meta);

		$wp_admin_bar->add_node(array(
			'parent' => $parent,
			'id' => $id,
			'title' => $name,
			'href' => $href,
			'meta' => $meta,
		));
	}

	/**
	 * @return void
	 */
	public function setupAdminLinks() {
		// Settings
		add_filter( 'plugin_action_links', [ $this, 'addQuickSettingsLink' ], 10, 2 );

        // Settings > Orbisius SEO Editor
		$settings_hook = add_options_page(
            'Orbisius SEO Editor',
            'Orbisius SEO Editor',
			Orbisius_SEO_Editor_Util::getRequiredCap(),
            ORBISIUS_SEO_EDITOR_BASE_PLUGIN,
            [ $this, 'renderOptionsPage' ]
        );

		// Tools > Orbisius SEO Editor
		$tools_hook = add_submenu_page( 'tools.php',
            'Orbisius SEO Editor',
            'Orbisius SEO Editor',
            Orbisius_SEO_Editor_Util::getRequiredCap(),
			'orbisius_seo_editor_action_page',
            [ $this, 'renderSEOEditorMainPage' ],
            50
        );
	}

	/**
	 * Options page and this is shown under Products.
	 * For some reason the saved message doesn't show up on Products page
	 * that's why I had to display the message for edit.php page specifically.
	 *
	 * @package Orbisius SEO Editor
	 * @since 1.0
	 */
	function renderSEOEditorMainPage() {
		$ctx = [];
		$req_obj = Orbisius_SEO_Editor_Request::getInstance();

		?>
		<div id="orbisius_seo_editor_wrapper" class="wrap orbisius_seo_editor_wrapper">
			<h2>Orbisius SEO Editor</h2>

            <?php
            $tabs = $this->getTabs();
            $cur_tab = $this->getCurrentTabId();
            $cur_page_url = $req_obj->getRequestUrl();
            ?>
            <div class="qs_site_flex_list">
                <ul class="app_steps">
					<?php foreach ($tabs as $tab_id => $rec) : ?>
                        <li class="step <?php echo $tab_id == $cur_tab ? 'app_current_step' : ''; ?>">
							<?php if ($tab_id == $cur_tab) : ?>
                                <h3><?php echo esc_html($rec['title']); ?></h3>
							<?php else : ?>
								<?php
								$loop_page = $cur_page_url;
								$loop_page = add_query_arg('tab_id', $tab_id, $loop_page);
								?>
                                <a href='<?php echo esc_url($loop_page); ?>'><h3><?php echo esc_html($rec['title']); ?></h3></a>
							<?php endif; ?>
                        </li>
					<?php endforeach; ?>
                </ul>
            </div>

			<style>
                /*https://stackoverflow.com/questions/9171699/add-a-pipe-separator-after-items-in-an-unordered-list-unless-that-item-is-the-la*/
                .qs_site_flex_list {
                    position: relative;
                    /*margin: 1em;*/
                    overflow: hidden;
                }

                .qs_site_flex_list ul {
                    display: flex;
                    flex-direction: row;
                    flex-wrap: wrap;
                    justify-content: space-between;
                    margin-left: -1px;
                }

                .app_steps .step a {
                    text-decoration: none;
                }

                .app_steps .step a {
                    width: 100%;
                    height: 100%;
                    display: inline-block;
                }

                .app_steps .step a:hover {
                    color:#fff;
                    background: #fff3cd;
                }

                .app_hide {
                    display:none;
                }

                /*
                the whole tab must be clickable
                https://adrianroselli.com/2020/02/block-links-cards-clickable-regions-etc.html
                 */
                /*
                .app_steps .step a[href]::after {
                    content: "";
                    display: block;
                    position: absolute;
                    top: 0;
                    bottom: 0;
                    left: 0;
                    right: 0;
                }
                */

                .qs_site_flex_list li {
                    flex-grow: 1;
                    flex-basis: auto;
                    /*margin: .25em 0;*/
                    /*padding: 0 1em;*/
                    text-align: center;
                    border-left: 1px solid #ccc;
                    background-color: #fff;
                }

                .app_steps .app_current_step {
                    color: #fff !important;
                    font-weight: bold;
                    background: #00a699;
                }

                .app_steps .app_current_step h3, .app_steps .app_current_step h4 {
                    color: #fff;
                }

                .orbisius_seo_editor_download_session_data {
                    height: 125px;
                    overflow-y: auto;
                }

                .orbisius_seo_editor_download_session_data a {
                    display: block;
                }

                .orbisius_seo_editor_download_session_data a:hover {
                    background: yellow;
                }

                .orbisius_seo_editor_table .odd {
                    background: #eee;
                }

                .orbisius_seo_editor_table .highlight_row {
                    background: #FFFF99 !important;
                }

                .orbisius_seo_editor_table .highlight_box {
                    border: 1px solid red;
                    background: #FFFF99;
                    padding:3px;
                }

                .orbisius_seo_editor_notice {
                    padding:3px;
                    background: #FFFF99;
                }

                .orbisius_seo_editor_table .sel_col {
                    display: none;
                }

                .orbisius_seo_editor_table .quick_buttons {
                    float: right;
                }

                .orbisius_seo_editor_wrapper .app_success {
                    color: green;
                }
			</style>

			<div id="poststuff" class="orbisius_seo_editor_wrapper">
				<div id="post-body" class="metabox-holder columns-2">
						<!-- main content -->
						<div id="post-body-content">
							<div class="meta-box-sortables ui-sortable">
								<?php
								do_action('orbisius_seo_editor_action_render_tab_content', $ctx);
								do_action('orbisius_seo_editor_action_render_tab_content_' . $cur_tab, $ctx);
								?>

							</div> <!-- .meta-box-sortables .ui-sortable -->
						</div> <!-- #postbox-container-1 .postbox-container -->

					<!-- sidebar -->
					<div id="postbox-container-1" class="postbox-container">
						<div class="meta-box-sortables">
							<div class="postbox"> <!-- quick-contact -->
								<?php
								$current_user = wp_get_current_user();
								$email = empty($current_user->user_email) ? '' : $current_user->user_email;
								$quick_form_action = 'https://apps.orbisius.com/quick-contact/';

								if (!empty($_SERVER['DEV_ENV'])) {
									$quick_form_action = 'https://localhost/projects/quick-contact/';
								}
								?>
								<h3><span>Quick Question or Suggestion</span></h3>
								<div class="inside">
									<div>
										<form method="post" action="<?php echo esc_url($quick_form_action); ?>" target="_blank" enctype="multipart/form-data">
											<?php
											global $wp_version;
											$plugin_data = get_plugin_data(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);

											$hidden_data = array(
												'site_url' => site_url(),
												'wp_ver' => $wp_version,
												'first_name' => $current_user->first_name,
												'last_name' => $current_user->last_name,
												'product_name' => $plugin_data['Name'],
												'product_ver' => $plugin_data['Version'],
												'woocommerce_ver' => defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : 'n/a',
											);

                                            // Let's collect some plugin info so we can troubleshoot things easier later on
											$cnt = 0;
											$plugins_raw = get_plugins();
											$plugin_manager_obj = Orbisius_SEO_Editor_Plugin_Manager::getInstance();

											foreach ($plugins_raw as $plugin_dir_and_file => $rec) {
                                                $plugin_info_rec = [];
												$plugin_info_rec['name'] = empty($rec['Name']) ? '' : $rec['Name'];
												$plugin_info_rec['slug'] = $plugin_manager_obj->formatPluginSlug($plugin_dir_and_file);
												$plugin_info_rec['active'] = is_plugin_active( $plugin_dir_and_file );
												$plugin_info_rec['is_supported'] = $plugin_manager_obj->isPluginSupported($plugin_dir_and_file);
												$plugin_info_rec['plugin_url'] = empty($rec['PluginURI']) ? '' : $rec['PluginURI'];
												$plugin_info_rec['author'] = empty($rec['Author']) ? '' : $rec['Author'];
												$plugin_info_rec['author_name'] = empty($rec['AuthorName']) ? '' : $rec['AuthorName'];
												$plugin_info_rec['author_url'] = empty($rec['AuthorURI']) ? '' : $rec['AuthorURI'];
												$plugin_info_rec['ver'] = empty($rec['Version']) ? '' : $rec['Version'];
                                                $hidden_data[ 'plugin' . (++$cnt) ] = $plugin_info_rec;
											}

											$hid_data = http_build_query($hidden_data);
											$hid_data_esc = esc_attr($hid_data);

											echo "<input type='hidden' name='data[sys_info]' value='$hid_data_esc' />\n";


											?>
											<textarea class="widefat" id='orbisius_seo_editor_msg' name='data[msg]' required="required"></textarea>
											<br/>Your Email: <input type="text" class=""
											                        name='data[sender_email]' placeholder="Email" required="required"
											                        value="<?php echo esc_attr($email); ?>"
											/>
											<br/><input type="submit" class="button-primary" value="<?php _e('Send') ?>"
											            onclick="try { if (jQuery('#orbisius_seo_editor_msg').val().trim() == '') { alert('Enter your message.'); jQuery('#orbisius_seo_editor_msg').focus(); return false; } } catch(e) {};" />
											<br/>
											What data will be sent
											<a href='javascript:void(0);'
											   onclick='jQuery(".orbisius-price-changer-woocommerce-quick-contact-data-to-be-sent").toggle();'>(show/hide)</a>
											<div class="hide hide-if-js orbisius-price-changer-woocommerce-quick-contact-data-to-be-sent">
                                                <textarea class="widefat" rows="4" readonly="readonly" disabled="disabled"><?php
	                                                foreach ($hidden_data as $key => $val) {
		                                                if (is_array($val)) {
			                                                $val = var_export($val, 1);
		                                                }

		                                                $key_esc = esc_html($key);
		                                                $val_esc = esc_html($val);
		                                                echo "$key_esc: $val_esc\n";
	                                                }
	                                                ?></textarea>
											</div>
										</form>
									</div>
								</div> <!-- .inside -->
							</div> <!-- .postbox --> <!-- /quick-contact -->

							<!-- Hire Us -->
							<div class="postbox">
								<h3><span>Pro Addon</span></h3>
								<div class="inside">
                                    If you'd like more features and you should check the Pro version.
                                    It builds on top of the free version. It supports more SEO plugins, themes and more fields.
                                    <a href="<?php echo esc_url("https://orbisius.com/store/product/orbisius-seo-editor-pro/?utm_source=" . ORBISIUS_SEO_EDITOR_PLUGIN_SLUG);?>"
                                       target="_blank">Orbisius SEO Editor Pro</a>
                                    <br/>Use: <strong>seo-editor-pro-fan</strong> code to get 20% off
                                </div> <!-- .inside -->
							</div> <!-- .postbox -->
							<!-- /Hire Us -->

							<!-- Hire Us -->
							<div class="postbox">
								<h3><span>Hire Us</span></h3>
								<div class="inside">
									Hire us to create a plugin/web/mobile app
									<br/><a href="<?php echo esc_url("https://orbisius.com/page/free-quote/?utm_source=" . ORBISIUS_SEO_EDITOR_PLUGIN_SLUG . "&utm_medium=plugin-settings&utm_campaign=product"); ?>"
									        title="If you want a custom web/mobile app/plugin developed contact us. This opens in a new window/tab"
									        class="button-primary" target="_blank">Get a Free Quote</a>
								</div> <!-- .inside -->
							</div> <!-- .postbox -->
							<!-- /Hire Us -->

							<!-- Newsletter-->
							<div class="postbox">
								<h3><span>Newsletter</span></h3>
								<div class="inside">
									<!-- Begin MailChimp Signup Form -->
									<div id="mc_embed_signup">
										<?php
										$current_user = wp_get_current_user();
										$email = empty($current_user->user_email) ? '' : $current_user->user_email;
										?>

										<form action="//WebWeb.us2.list-manage.com/subscribe/post?u=005070a78d0e52a7b567e96df&amp;id=1b83cd2093" method="post"
										      id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank">
											<input type="hidden" value="settings" name="SRC2" />
											<input type="hidden" value="<?php echo esc_attr(ORBISIUS_SEO_EDITOR_PLUGIN_SLUG);?>" name="SRC" />

											<span>Get notified about cool plugins we release</span>
											<!--<div class="indicates-required"><span class="app_asterisk">*</span> indicates required
											</div>-->
											<div class="mc-field-group">
												<label for="mce-EMAIL">Email</label>
												<input type="email" value="<?php echo esc_attr($email); ?>" name="EMAIL" class="required email" id="mce-EMAIL">
											</div>
											<div id="mce-responses" class="clear">
												<div class="response" id="mce-error-response" style="display:none"></div>
												<div class="response" id="mce-success-response" style="display:none"></div>
											</div>	<div class="clear"><input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button-primary"></div>
										</form>
									</div>
									<!--End mc_embed_signup-->
								</div> <!-- .inside -->
							</div> <!-- .postbox -->
							<!-- /Newsletter-->

							<!-- Support options -->
							<div class="postbox">
								<h3><span>Support & Feature Requests</span></h3>
								<h3>
									<?php
									$plugin_data = get_plugin_data(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);
									$product_name = trim($plugin_data['Name']);
									$product_page = trim($plugin_data['PluginURI']);
									$product_descr = trim($plugin_data['Description']);
									$product_descr_short = substr($product_descr, 0, 50) . '...';
									$product_descr_short .= ' #WordPress #plugin';

									$base_name_slug = ORBISIUS_SEO_EDITOR_PLUGIN_SLUG;
									$product_page .= (strpos($product_page, '?') === false) ? '?' : '&';
									$product_page .= "utm_source=$base_name_slug&utm_medium=plugin-settings&utm_campaign=product";

									$product_page_tweet_link = $product_page;
									$product_page_tweet_link = str_replace('plugin-settings', 'tweet', $product_page_tweet_link);
									?>
									<!-- Twitter: code -->
									<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
									<!-- /Twitter: code -->

									<!-- Twitter: Orbisius_Follow:js -->
									<a href="https://twitter.com/orbisius" class="twitter-follow-button"
									   data-align="right" data-show-count="false">Follow @orbisius</a>
									<!-- /Twitter: Orbisius_Follow:js -->

									<!-- Twitter: Tweet:js -->
									<a href="https://twitter.com/share" class="twitter-share-button"
									   data-lang="en" data-text="Checkout <?php echo esc_attr($product_name);?> #WordPress #plugin.<?php echo esc_attr($product_descr_short); ?>"
									   data-count="none" data-via="orbisius" data-related="orbisius"
									   data-url="<?php echo esc_url($product_page_tweet_link);?>">Tweet</a>
									<!-- /Twitter: Tweet:js -->

									<br/>
									<span>
                                        <a href="<?php echo esc_url($product_page); ?>" target="_blank" title="[new window]">Product Page</a>
                                        |
                                        <a href="<?php echo esc_url("https://orbisius.com/forums/forum/community-support-forum/wordpress-plugins/$base_name_slug?utm_source=$base_name_slug&utm_medium=plugin-settings&utm_campaign=product");?>"
                                           target="_blank" title="[new window]">Support Forums</a>
										<!-- |
										<a href="//docs.google.com/viewer?url=https%3A%2F%2Fdl.dropboxusercontent.com%2Fs%2Fwz83vm9841lz3o9%2FOrbisius_LikeGate_Documentation.pdf" target="_blank">Documentation</a>-->
                                    </span>
								</h3>
							</div> <!-- .postbox -->
							<!-- /Support options -->

						</div> <!-- .meta-box-sortables -->
					</div> <!-- #postbox-container-1 .postbox-container -->

				</div> <!-- #post-body .metabox-holder .columns-2 -->

				<br class="clear" />
			</div> <!-- /poststuff -->
		</div> <!-- /orbisius_seo_editor_wrapper -->

		<?php
	}

	/**
	 * Retrieves the plugin options. It inserts some defaults.
	 * The saving is handled by the settings page. Basically, we submit to WP and it takes
	 * care of the saving.
	 *
	 * @return array
	 */
	function getOptions() {
		$defaults = array(
			'status' => 1,
			//'render_id_as' => 'id',
		);

		$opts = get_option('orbisius_seo_editor_opts');

		$opts = (array) $opts;
		$opts = array_merge($defaults, $opts);

		return $opts;
	}

	/**
	 * Options page
	 *
	 * @package Orbisius SEO Editor
	 * @since 1.0
	 */
	function renderOptionsPage() {
		$opts = $this->getOptions();
		?>

        <div class="wrap orbisius_seo_editor_admin_wrapper orbisius_seo_editor_container">

            <div id="icon-options-general" class="icon32"></div>
            <h2>Orbisius SEO Editor</h2>

            <div id="poststuff">

                <div id="post-body" class="metabox-holder columns-2">

                    <!-- main content -->
                    <div id="post-body-content">

                        <div class="meta-box-sortables ui-sortable">

                            <div class="postbox">

                                <h3><span>Settings</span></h3>
                                <div class="inside">
									<?php if (0) : ?>
                                        <form method="post" action="options.php">
											<?php settings_fields('orbisius_seo_editor_settings'); ?>
                                            <table class="form-table">

                                                <tr valign="top">
                                                    <th scope="row">Plugin Status</th>
                                                    <td>
                                                        <label for="radio1">
                                                            <input type="radio" id="radio1" name="orbisius_seo_editor_opts[status]"
                                                                   value="1" <?php echo empty($opts['status']) ? '' : 'checked="checked"'; ?> /> Enabled
                                                        </label>
                                                        <br/>
                                                        <label for="radio2">
                                                            <input type="radio" id="radio2" name="orbisius_seo_editor_opts[status]"
                                                                   value="0" <?php echo !empty($opts['status']) ? '' : 'checked="checked"'; ?> /> Disabled
                                                        </label>
                                                    </td>
                                                </tr>
                                            </table>

                                            <p class="submit">
                                                <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                                            </p>
                                        </form>
									<?php else : ?>
                                        <div>
                                            The plugin doesn't have settings options at the moment.<br/><br/>

                                            Go to: Tools &gt; <a href='<?php echo esc_url(Orbisius_SEO_Editor_Util::getPageLink('editor')); ?>'
                                                                 class="button-primary">Orbisius SEO Editor </a>
                                        </div>
									<?php endif; ?>
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->

                            <!-- Demo -->
                            <div class="postbox">
                                <h3><span>Demo</span></h3>
                                <div class="inside">

                                    <p>
                                        Link: <a href="https://youtu.be/DiO3zkZVruA" target="_blank"
                                                 title="[opens in a new and bigger tab/window]">youtube.com/watch?v=RsRBmCGuz1w&hd=1</a>
                                    </p>

                                    <p>
                                        <iframe width="560" height="315" src="https://www.youtube.com/embed/DiO3zkZVruA"
                                                title="YouTube video player"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                    </p>
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->
                            <!-- /Demo -->

                        </div> <!-- .meta-box-sortables .ui-sortable -->

                    </div> <!-- post-body-content -->

                    <!-- sidebar -->
                    <div id="postbox-container-1" class="postbox-container">

                        <div class="meta-box-sortables">
                            <!-- Hire Us -->
                            <div class="postbox">
                                <h3><span>Hire Us</span></h3>
                                <div class="inside">
                                    Hire us to create a plugin/web/mobile app
                                    <br/><a href="<?php echo url("https://orbisius.com/page/free-quote/?utm_source=". ORBISIUS_SEO_EDITOR_PLUGIN_SLUG . "&utm_medium=plugin-settings&utm_campaign=product");?>
                                            title="If you want a custom web/mobile app/plugin developed contact us. This opens in a new window/tab"
                                            class="button-primary" target="_blank">Get a Free Quote</a>
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->
                            <!-- /Hire Us -->

                            <!-- Newsletter-->
                            <div class="postbox">
                                <h3><span>Newsletter</span></h3>
                                <div class="inside">
                                    <!-- Begin MailChimp Signup Form -->
                                    <div id="mc_embed_signup">
										<?php
										$current_user = wp_get_current_user();
										$email = empty($current_user->user_email) ? '' : $current_user->user_email;
										?>

                                        <form action="//WebWeb.us2.list-manage.com/subscribe/post?u=005070a78d0e52a7b567e96df&amp;id=1b83cd2093" method="post"
                                              id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank">
                                            <input type="hidden" value="settings" name="SRC2" />
                                            <input type="hidden" value="<?php echo esc_attr(ORBISIUS_SEO_EDITOR_PLUGIN_SLUG);?>" name="SRC" />

                                            <span>Get notified about cool plugins we release</span>
                                            <!--<div class="indicates-required"><span class="app_asterisk">*</span> indicates required
											</div>-->
                                            <div class="mc-field-group">
                                                <label for="mce-EMAIL">Email</label>
                                                <input type="email" value="<?php echo esc_attr($email); ?>" name="EMAIL" class="required email" id="mce-EMAIL">
                                            </div>
                                            <div id="mce-responses" class="clear">
                                                <div class="response" id="mce-error-response" style="display:none"></div>
                                                <div class="response" id="mce-success-response" style="display:none"></div>
                                            </div>	<div class="clear"><input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button-primary"></div>
                                        </form>
                                    </div>
                                    <!--End mc_embed_signup-->
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->
                            <!-- /Newsletter-->

                            <!-- Support options -->
                            <div class="postbox">
                                <h3><span>Support & Feature Requests</span></h3>
                                <h3>
									<?php
									$plugin_data = get_plugin_data(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);
									$product_name = trim($plugin_data['Name']);
									$product_page = trim($plugin_data['PluginURI']);
									$product_descr = trim($plugin_data['Description']);
									$product_descr_short = substr($product_descr, 0, 50) . '...';
									$product_descr_short .= ' #WordPress #plugin';

									$base_name_slug = basename(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);
									$base_name_slug = str_replace('.php', '', $base_name_slug);
									$product_page .= (strpos($product_page, '?') === false) ? '?' : '&';
									$product_page .= "utm_source=$base_name_slug&utm_medium=plugin-settings&utm_campaign=product";

									$product_page_tweet_link = $product_page;
									$product_page_tweet_link = str_replace('plugin-settings', 'tweet', $product_page_tweet_link);
									?>
                                    <!-- Twitter: code -->
                                    <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
                                    <!-- /Twitter: code -->

                                    <!-- Twitter: Orbisius_Follow:js -->
                                    <a href="https://twitter.com/orbisius" class="twitter-follow-button"
                                       data-align="right" data-show-count="false">Follow @orbisius</a>
                                    <!-- /Twitter: Orbisius_Follow:js -->

                                    &nbsp;

                                    <!-- Twitter: Tweet:js -->
                                    <a href="https://twitter.com/share" class="twitter-share-button"
                                       data-lang="en" data-text="Checkout <?php echo $product_name;?> #WordPress #plugin.<?php echo $product_descr_short; ?>"
                                       data-count="none" data-via="orbisius" data-related="orbisius"
                                       data-url="<?php echo $product_page_tweet_link;?>">Tweet</a>
                                    <!-- /Twitter: Tweet:js -->

                                    <br/>
                                    <span>
                                    <a href="<?php echo $product_page; ?>" target="_blank" title="[new window]">Product Page</a>
                                    |
                                    <a href="https://orbisius.com/forums/forum/community-support-forum/wordpress-plugins/<?php echo $base_name_slug;?>/?utm_source=<?php echo $base_name_slug;?>&utm_medium=plugin-settings&utm_campaign=product"
                                       target="_blank" title="[new window]">Support Forums</a>

                                        <!-- |
										<a href="//docs.google.com/viewer?url=https%3A%2F%2Fdl.dropboxusercontent.com%2Fs%2Fwz83vm9841lz3o9%2FOrbisius_LikeGate_Documentation.pdf" target="_blank">Documentation</a>-->
                                </span>
                                </h3>
                            </div> <!-- .postbox -->
                            <!-- /Support options -->

                            <div class="postbox"> <!-- quick-contact -->
								<?php
								$current_user = wp_get_current_user();
								$email = empty($current_user->user_email) ? '' : $current_user->user_email;
								$quick_form_action = 'https://apps.orbisius.com/quick-contact/';

								if (!empty($_SERVER['DEV_ENV'])) {
									$quick_form_action = 'http://localhost/projects/quick-contact/';
								}
								?>
                                <h3><span>Quick Question or Suggestion</span></h3>
                                <div class="inside">
                                    <div>
                                        <form method="post" action="<?php echo $quick_form_action; ?>" target="_blank">
											<?php
											global $wp_version;
											$plugin_data = get_plugin_data(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);

											$hidden_data = array(
												'site_url' => site_url(),
												'wp_ver' => $wp_version,
												'first_name' => $current_user->first_name,
												'last_name' => $current_user->last_name,
												'product_name' => $plugin_data['Name'],
												'product_ver' => $plugin_data['Version'],
												'woocommerce_ver' => defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : 'n/a',
											);
											$hid_data = http_build_query($hidden_data);
											echo "<input type='hidden' name='data[sys_info]' value='$hid_data' />\n";
											?>
                                            <textarea class="widefat" id='orbisius_seo_editor_msg' name='data[msg]' required="required"></textarea>
                                            <br/>Your Email: <input type="text" class=""
                                                                    name='data[sender_email]' placeholder="Email" required="required"
                                                                    value="<?php echo esc_attr($email); ?>"
                                            />
                                            <br/><input type="submit" class="button-primary" value="<?php _e('Send') ?>"
                                                        onclick="try { if (jQuery('#orbisius_seo_editor_msg').val().trim() == '') { alert('Enter your message.'); jQuery('#orbisius_seo_editor_msg').focus(); return false; } } catch(e) {};" />
                                            <br/>
                                            What data will be sent
                                            <a href='javascript:void(0);'
                                               onclick='jQuery(".orbisius-price-changer-woocommerce-quick-contact-data-to-be-sent").toggle();'>(show/hide)</a>
                                            <div class="hide hide-if-js orbisius-price-changer-woocommerce-quick-contact-data-to-be-sent">
                                            <textarea class="widefat" rows="4" readonly="readonly" disabled="disabled"><?php
	                                            foreach ($hidden_data as $key => $val) {
		                                            if (is_array($val)) {
			                                            $val = var_export($val, 1);
		                                            }

		                                            echo "$key: $val\n";
	                                            }
	                                            ?></textarea>
                                            </div>
                                        </form>
                                    </div>
                                </div> <!-- .inside -->

                            </div> <!-- .postbox --> <!-- /quick-contact -->

                            <!-- Support options -->
                            <div class="postbox">
                                <h3><span>Support & Feature Requests</span></h3>
                                <h3>
									<?php
									$plugin_data = get_plugin_data(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);
									$product_name = trim($plugin_data['Name']);
									$product_page = trim($plugin_data['PluginURI']);
									$product_descr = trim($plugin_data['Description']);
									$product_descr_short = substr($product_descr, 0, 50) . '...';
									$product_descr_short .= ' #WordPress #plugin';

									$base_name_slug = basename(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);
									$base_name_slug = str_replace('.php', '', $base_name_slug);
									$product_page .= (strpos($product_page, '?') === false) ? '?' : '&';
									$product_page .= "utm_source=$base_name_slug&utm_medium=plugin-settings&utm_campaign=product";

									$product_page_tweet_link = $product_page;
									$product_page_tweet_link = str_replace('plugin-settings', 'tweet', $product_page_tweet_link);
									?>
                                    <!-- Twitter: code -->
                                    <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="https://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
                                    <!-- /Twitter: code -->

                                    <!-- Twitter: Orbisius_Follow:js -->
                                    <a href="https://twitter.com/orbisius" class="twitter-follow-button"
                                       data-align="right" data-show-count="false">Follow @orbisius</a>
                                    <!-- /Twitter: Orbisius_Follow:js -->

                                    &nbsp;

                                    <!-- Twitter: Tweet:js -->
                                    <a href="https://twitter.com/share" class="twitter-share-button"
                                       data-lang="en" data-text="Checkout <?php echo $product_name;?> #WordPress #plugin.<?php echo $product_descr_short; ?>"
                                       data-count="none" data-via="orbisius" data-related="orbisius"
                                       data-url="<?php echo $product_page_tweet_link;?>">Tweet</a>
                                    <!-- /Twitter: Tweet:js -->

                                    <br/>
                                    <span>
                                    <a href="<?php echo $product_page; ?>" target="_blank" title="[new window]">Product Page</a>
                                    |
                                    <a href="https://orbisius.com/forums/forum/community-support-forum/wordpress-plugins/<?php echo $base_name_slug;?>/?utm_source=<?php echo $base_name_slug;?>&utm_medium=plugin-settings&utm_campaign=product"
                                       target="_blank" title="[new window]">Support Forums</a>

                                        <!-- |
										<a href="https://docs.google.com/viewer?url=https%3A%2F%2Fdl.dropboxusercontent.com%2Fs%2Fwz83vm9841lz3o9%2FOrbisius_LikeGate_Documentation.pdf" target="_blank">Documentation</a>-->
                                </span>
                                </h3>
                            </div> <!-- .postbox -->
                            <!-- /Support options -->

                        </div> <!-- .meta-box-sortables -->

                    </div> <!-- #postbox-container-1 .postbox-container -->

                </div> <!-- #post-body .metabox-holder .columns-2 -->

                <br class="clear">
            </div> <!-- #poststuff -->

        </div> <!-- .wrap -->
		<?php
	}

	/**
	 * Sets the setting variables
	 */
	function registerSettings() { // whitelist options
		register_setting('orbisius_seo_editor_settings', 'orbisius_seo_editor_opts', [ $this, 'validateSettings' ]);
	}

	/**
	 * This is called by WP after the user hits the submit button.
	 * The variables are trimmed first and then passed to the who ever wantsto filter them.
	 * @param array the entered data from the settings page.
	 * @return array the modified input array
	 */
	function validateSettings($input) { // whitelist options
		$input = array_map('trim', $input);

		// let extensions do their thing
		$input_filtered = apply_filters('orbisius_seo_editor_ext_filter_settings', $input);

		// did the extension break stuff?
		$input = is_array($input_filtered) ? $input_filtered : $input;

		return $input;
	}

	/**
	 * Adds the action link to settings. That's from Plugins. It is a nice thing.
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	function addQuickSettingsLink($links, $file) {
		if ($file == plugin_basename(ORBISIUS_SEO_EDITOR_BASE_PLUGIN)) {
			$link = Orbisius_SEO_Editor_Util::getPageLink('support');
            $link_esc = esc_url($link);
			$settings_link = "<a href=\"{$link_esc}\" target='_blank'>Support</a>";
			array_unshift($links, $settings_link);

			$link = Orbisius_SEO_Editor_Util::getPageLink('settings');
			$link_esc = esc_url($link);
			$settings_link = "<a href=\"{$link_esc}\">Settings</a>";
			array_unshift($links, $settings_link);

			$link = Orbisius_SEO_Editor_Util::getPageLink('editor');
			$link_esc = esc_url($link);
			$action_link = "<a href=\"{$link_esc}\">SEO Editor</a>";
			array_unshift($links, $action_link);
		}

		return $links;
	}

    private $search_transient_key = 'orbisius_seo_editor_filters';

	/**
	 * @return array
	 */
	function getSearchFilter() {
		$req_obj = Orbisius_SEO_Editor_Request::getInstance();
		$transient_key = $this->search_transient_key;
		$query_filters = [];
        $could_be_empty_fields = [ 'search_kwd', ];
        $defaults = [
			'cat' => '',
			'tag' => '',
			'limit' => 25,
            'recent' => '',
			'post_type' => 'post',
			'src_field' => 'meta_title',
			'search_kwd' => '',
			'product_tag' => '',
			'post_status' => 'publish',
			'product_cat' => '',
			'src_seo_plugin' => '',
			'target_seo_plugin' => '',
		];

		$filter_options = $defaults;

        // Let's not check the transients if the request doesn't relate to my plugin
        if ($req_obj->isPluginRequest()) {
	        $cached_filter_params = get_transient( $transient_key );
	        $cached_filter_params = empty( $cached_filter_params ) ? [] : $cached_filter_params;

            if (!empty($cached_filter_params)) {
	            $filter_options = $cached_filter_params;
            }
        }

		$params = empty($_REQUEST['orbisius_seo_editor_search']) ? [] : (array) $_REQUEST['orbisius_seo_editor_search'];
		$params = Orbisius_SEO_Editor_String_Util::sanitize($params);

        // We'll loop through the defaults and if there's something in the request we'll set it.
        // the filter options could have been populated by the cached version earlier.
        foreach ($defaults as $key => $val) {
            if (!empty($params[$key])) {
	            $val = $params[$key];
            } elseif (!isset($params[$key]) // if search kwd
                    && $req_obj->isPost()
                    && in_array($key, $could_be_empty_fields)
            ) {
                $val = '';
            } else {
                continue; // otherwise it will override the value.
            }

            if (in_array($key, [ 'id', 'limit', ])) {
                $val = (int) $val;
            }

	        $filter_options[$key] = $val;
        }

		$filter_options['admin_cherry_picks_products'] = !empty($params['orb_woo_pc_i_want_to_select_products']);
		$filter_options['csv_export_prefill_empty_field'] = !empty($params['orbisius_seo_editor_csv_export_prefill_empty_field']);

		// thanks to https://wordpress.stackexchange.com/questions/198792/hide-old-attachments-from-wp-media-library
		if (!empty($filter_options['recent'])) {
			if ( $filter_options['recent'] == 'last_24' ) {
				$query_filters['date_query'] = array(
					array(
						'after'     => '24 hours ago',
						'inclusive' => true,
					),
				);
			}

			if ( $filter_options['recent'] == 'last_48' ) {
				$query_filters['date_query'] = array(
					array(
						'after'     => '48 hours ago',
						'inclusive' => true,
					),
				);
			}

			if ( $filter_options['recent'] == 'last_week' ) {
				$query_filters['date_query'] = array(
					array(
						'column'    => 'post_date',
						'after'     => '-7 days',
						'inclusive' => true,
					)
				);
			}

			if ( $filter_options['recent'] == 'last_month' ) {
				$query_filters['date_query'] = array(
					array(
						'column'    => 'post_date',
						'after'     => '-30 days',
						'inclusive' => true,
					),
				);
			}

			if ( $filter_options['recent'] == 'last_year' ) {
				$query_filters['date_query'] = array(
					array(
						'column'    => 'post_date',
						'after'     => '-365 days',
						'inclusive' => true,
					),
				);
			}
		}

		// Ok the user wants specific fields.
		if (!empty($filter_options['src_field'])) {
			$cols = (array) $filter_options['src_field'];

			if (empty($cols) || in_array('meta_title_and_description', $cols)) {
				$cols = array_merge($cols, [
					'meta_title',
					'meta_description',
				]);

				$idx = array_search('meta_title_and_description', $cols);

				if ($idx !== false) { // the field is not present in the mapping so rm it.
					unset($cols[$idx]);
				}
			}

			$cols_assoc = array_combine($cols, $cols);
			$cols_assoc = Orbisius_SEO_Editor_Util::toHumanReadable($cols_assoc);
			$query_filters['sel_cols'] = $cols_assoc;
		}

		$query_filters['limit'] = $filter_options['limit'];
		$query_filters['keyword'] = $filter_options['search_kwd'];
		$query_filters['skip_cache'] = 1;
		$query_filters['product_category'] = $filter_options['product_cat'];
		$query_filters['admin_cherry_picks_products'] = $filter_options['admin_cherry_picks_products'];
		$query_filters['csv_export_prefill_empty_field'] = $filter_options['csv_export_prefill_empty_field'];

		$filter_options['query_filters'] = $query_filters;

		// update cache only after a search and not on ajax (POST) calls
		if ($req_obj->isPost('search_submit') && $req_obj->isPluginRequest()) {
			$filter_options_cached = $filter_options;
			// if we skip empty values and that might be hard to
            // delete any search keywords because they will be saved in the db
            // and won't be passed in the request.
            //$filter_options_cached = array_filter($filter_options_cached); // Let's not cache empty vals
			set_transient($transient_key, $filter_options_cached, 3 * 24 * 3600);
		}

        return $filter_options;
	}

	/**
	 * Singleton pattern i.e. we have only one instance of this obj
	 * @staticvar static $instance
	 * @return static
	 */
	public static function getInstance() {
		static $instance = null;

		// This will make the calling class to be instantiated.
		// no need each sub class to define this method.
		if (is_null($instance)) {
			$instance = new self();
		}

		return $instance;
	}

	private function __construct() {}

	/**
	 * @param void
	 * @return string
	 */
	public function getCurrentTabId() : string {
		$req_obj = Orbisius_SEO_Editor_Request::getInstance();
		$tabs = $this->getTabs();
		$cur_tab = empty( $req_obj->tab_id ) || empty( $tabs[ $req_obj->tab_id ] ) ? 'seo_editor' : $req_obj->tab_id;
		return $cur_tab;
	}

	/**
	 * @return array
	 */
	public function getTabs(): array {
		return $this->tabs;
	}

	/**
	 * @param array $extra_info
	 * @return string
	 */
    public function generateCSVDownloadFileName($extra_info = []) {
        $file_prefix = basename(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);
        $file_prefix = str_replace('.php', '', $file_prefix);
        $file_prefix = sanitize_title( $file_prefix );
        $file_prefix = str_replace( '-', '_', $file_prefix );
        $file_prefix .= '_';

        if (!empty($extra_info['src_seo_plugin'])) {
	        $plugin_manager_obj = Orbisius_SEO_Editor_Plugin_Manager::getInstance();
	        $fmt_seo_plugin = $plugin_manager_obj->formatPluginSlug($extra_info['src_seo_plugin']);

            // we need to separate with dashes because we use them as separators later in js.
            // if this prefix changes then update the main.js too
	        $file_prefix .= 'seo_plugin-' . $fmt_seo_plugin . '-';
        }

	    $file_name = $file_prefix . date( 'Ymd' ) . '_' . date( 'His' ) . '.csv';

        return $file_name;
    }
}
