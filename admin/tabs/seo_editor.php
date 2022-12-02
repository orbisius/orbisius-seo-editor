<?php
$msg = '';
$buff = '';
$error_found = false;
$disclaimer = "Disclaimer: THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.";
$msg = Orbisius_SEO_Editor_Util::msg("Before doing anything, it's always recommended to back up your site using a backup or migration plugin. We try to make our code rock solid and at the same time it's impossible to catch all the bugs, so we have this disclaimer."
                                     . "<br/>" . esc_html($disclaimer) . "<br/>", 0);
$req_obj = Orbisius_SEO_Editor_Request::getInstance();
$req_params = $req_obj->get();
$is_post = $req_obj->isPost();

$admin_obj = Orbisius_SEO_Editor_Admin::getInstance();
$filter_options = $admin_obj->getSearchFilter();

$plugin_manager_obj = Orbisius_SEO_Editor_Plugin_Manager::getInstance();

$exec_time = 0;
$proces_exec_time = 0;

$ctx = [
	'filter_options' => $filter_options,
];

$records = [];
$transient_key = 'orbisius_seo_editor_filters';

if ($is_post) {
	Orbisius_SEO_Editor_Debug::time( __FILE__ );

	$search_items_params = $filter_options;

	try {
		if (empty($search_items_params['src_seo_plugin'])) {
			throw new Exception( 'SEO Plugin was not selected');
		}

		$ctx['src_seo_plugin'] = $search_items_params['src_seo_plugin'];

		if (!empty($req_params['orbisius_seo_editor_data']) && !empty($req_params['save_changes'])) {
			if ( ! isset( $req_params['orbisius_seo_editor_nonce'] )
                 || ! wp_verify_nonce( $req_params['orbisius_seo_editor_nonce'], 'orbisius_seo_editor_form_action' )
            ) {
				throw new Exception('Sorry, cannot process. Please refresh the page and try again.');
			}

			Orbisius_SEO_Editor_Util::extendRunningTime();
			Orbisius_SEO_Editor_Debug::time( __FILE__ . '_process');
			$status_rec = Orbisius_SEO_Editor_Util::processRecords($req_params['orbisius_seo_editor_data'], $search_items_params);

			$proces_exec_time = Orbisius_SEO_Editor_Debug::time(  __FILE__ . '_process' );
		}

		$records = Orbisius_SEO_Editor_Util::getItems($search_items_params);
		$buff = Orbisius_SEO_Editor_Util::generateResultsTable($records, $search_items_params['query_filters']);
		set_transient($transient_key, $filter_options, 3 * 24 * 3600);
	} catch (Exception $e) {
		$msg = Orbisius_SEO_Editor_Util::msg($e->getMessage());
		$error_found = true;
	} finally {
		$exec_time = Orbisius_SEO_Editor_Debug::time( __FILE__ );
	}
}

?>

<form id='orbisius_seo_editor_form' class='orbisius_seo_editor_form' method='post' enctype="multipart/form-data">
	<?php wp_nonce_field( 'orbisius_seo_editor_form_action', 'orbisius_seo_editor_nonce' ); ?>

	<div class="postbox">
		<div class="inside">
            <h3><span>Step 1/2 | Search</span></h3>

            <div>
                Using this plugin you can easily edit the meta title and description of your posts, pages or WooCommerce products
                if the SEO plugin that you're currently running is supported.
                If you ever run into an error please contact <a href="https://orbisius.com/support" target="_blank">Orbisius Support</a>
            </div>

            <div id="seo_editor_search_filter_wrapper" class="seo_editor_search_filter_wrapper">
				<p class="search-box0">
					<?php
					$post_types = array(
						'' => '',
						'post' => 'Posts',
						'page' => 'Pages',
						'product' => 'Products',
					);

					$post_types = apply_filters('orbisius_seo_editor_filter_search_post_types', $post_types);
					echo "Post Type: ";
					echo Orbisius_SEO_Editor_Util::htmlSelect('orbisius_seo_editor_search[post_type]', $filter_options['post_type'], $post_types);

					// @todo ::dev:: get the status
					//$post_statuses = get_post_stati( array('show_in_admin_all_list' => true) );
					$post_statuses = array(
						'' => '',
						'publish' => 'Published',
						'draft' => 'Draft',
						//'private' => 'Private',
						'pending' => 'Pending Review',
					);

					echo " | Product Status: ";
					echo Orbisius_SEO_Editor_Util::htmlSelect('orbisius_seo_editor_search[post_status]', $filter_options['post_status'], $post_statuses);
					?>

					| Show:
					<?php
					// these keys should match data-price_type variable in each input text
					$limit_opts = array(
						-1 => 'No Limit (!)',
						1 => 1,
						5 => 5,
						10 => 10,
						15 => 15,
						25 => 25,
						50 => 50,
						100 => 100,
						150 => 150,
						200 => 200,
						250 => 250,
						500 => '500 (!)',
						1000 => '1,000 (!)',
						2500 => '2,500 (!)',
						5000 => '5,000 (!)',
						10000 => '10,000 (!)',
					);

					echo Orbisius_SEO_Editor_Util::htmlSelect('orbisius_seo_editor_search[limit]', $filter_options['limit'], $limit_opts);
					?>

                    <div class="app_search_filter">
                        Search text or IDs (comma separated): <span title="Put an id or comma separated">[?]</span>:
                        <input type="search" value="<?php echo esc_attr($filter_options['search_kwd']); ?>"
                               id="orbisius_seo_editor_search_kwd"
                               name="orbisius_seo_editor_search[search_kwd]"
                               class="orbisius_seo_editor_search_kwd"
                               placeholder="e.g. 1,2,3 or text" />
                    </div>
                    <br/>
                    <?php
                    $src_seo_plugins_filters = [ 'format' => Orbisius_SEO_Editor_Plugin_Manager::FORMAT_DROPDOWN, 'skip_unsupported' => 1, ];
                    $src_seo_plugins = $plugin_manager_obj->getSEOPlugins($src_seo_plugins_filters);
                    $src_seo_plugins = empty($src_seo_plugins) ? [] : $src_seo_plugins;
                    echo " Source SEO Plugin: ";
                    echo Orbisius_SEO_Editor_Util::htmlSelect('orbisius_seo_editor_search[src_seo_plugin]', $filter_options['src_seo_plugin'], $src_seo_plugins);

                    $src_fields = []; // empty. We'll load this with ajax
                    $supported_addon_meta_fields = empty( $src_fields ) ? [ '' => 'Nothing found', ] : [ '' => '== Select ==', 'meta_title_and_description' => 'Meta Title and Description' ] + $src_fields;

                    echo "<span id='orbisius_seo_editor_search_filter_supported_addon_fields_wrapper' class='app_hide0 orbisius_seo_editor_search_filter_supported_addon_fields_wrapper'>";
                    echo "| Field(s) to read/update: ";
                    echo "<span id='orbisius_seo_editor_search_filter_supported_addon_fields_select_wrapper' class='app_hide orbisius_seo_editor_search_filter_supported_addon_fields_select_wrapper'>";
                    echo Orbisius_SEO_Editor_Util::htmlSelect(
                        'orbisius_seo_editor_search[src_field]',
                        $filter_options['src_field'],
	                    $supported_addon_meta_fields
                    );
                    echo " (&larr; pick one field for better efficiency)";
                    echo "</span> <!-- /orbisius_seo_editor_search_filter_supported_addon_fields_select_wrapper -->";
                    echo "</span> <!-- /orbisius_seo_editor_search_filter_supported_addon_fields_wrapper -->";
                    ?>

	                <?php do_action('orbisius_seo_editor_action_editor_before_submit', $ctx); ?>

                    <div>
                        <input type="submit" value="Search" class="button-primary" id="search-submit" name="search_submit" />
                    </div>

	                <?php do_action('orbisius_seo_editor_action_editor_after_submit', $ctx); ?>
                </p>
			</div> <!-- .seo_editor_search_filter_wrapper -->
		</div> <!-- .inside -->
	</div> <!-- .postbox -->

	<div><?php echo $msg; ?></div>

	<?php if (!empty($status_rec['work_log'])) : ?>
        <div class="updated"><p>
            <h3>Process Status (Exec time: <?php echo $proces_exec_time;?>s)
                | <a href='<?php echo esc_url( Orbisius_SEO_Editor_Util::getPageLink('editor')); ?>'
                     class="button">Process New</a>

				<?php if (0) : ?>
                    | Download Work Log (both recommended):
                    <a href='<?php echo $status_rec['work_log_csv_file_url']; ?>' class="button000" target="_blank">Excel (CSV) </a>
                    | <a href='<?php echo $status_rec['work_log_txt_file_url']; ?>' class="button000" target="_blank">Text </a>
				<?php endif; ?>
            </h3>
            <textarea class="widefat" readonly="readonly" rows="4"><?php echo join("\n", $status_rec['work_log']); ?></textarea>
            </p></div>
	<?php endif; ?>

    <?php if ($is_post && empty($error_found)) : ?>
		<div class="postbox">
			<h3><span>Step 2/2 | Make Changes or Export & Save Changes <?php echo " | Found record(s) : " . count($records); ?>
                    | Exec time: <?php echo esc_html($exec_time); ?>s</span></h3>
			<div class="inside">
				<div id="app-partners-container">
					<?php
					$select_prods = empty($req_params['orb_woo_pc_i_want_to_select_products']) ? '' : $req_params['orb_woo_pc_i_want_to_select_products'];

					// these keys should match data-price_type variable in each input text
					$change_opts = array(
						'orb_woo_pc_i_want_to_select_products' => 'I want to select which products to have their price changed.',
						'_id' => 'orb_woo_pc_i_want_to_select_products_cloning',
						'_type' => 'checkbox',
					);

					echo Orbisius_SEO_Editor_Util::generateHtmlBoxes('orb_woo_pc_i_want_to_select_products',
						$select_prods, $change_opts, 'class="hide-if-js"');
					?>

					<?php echo $buff; ?>
				</div>
			</div> <!-- .inside -->
		</div> <!-- .postbox -->

		<?php echo esc_html($disclaimer); ?>
	<?php endif; ?>
</form>
