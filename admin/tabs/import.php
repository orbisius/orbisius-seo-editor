<?php
$msg = '';
$req_obj = Orbisius_SEO_Editor_Request::getInstance();
$admin_obj = Orbisius_SEO_Editor_Admin::getInstance();
$filter_options = $admin_obj->getSearchFilter();
$plugin_manager_obj = Orbisius_SEO_Editor_Plugin_Manager::getInstance();

try {
	$is_post = $req_obj->isPost();

    if ($is_post) {
	    if ( empty( $filter_options['target_seo_plugin'] ) ) {
		    throw new Exception( 'SEO plugin was not selected' );
	    }

	    if ( empty( $_FILES['orbisius_seo_editor_file']['tmp_name'] ) ) {
		    throw new Exception( 'Upload is missing' );
	    }
    }

	if (!empty( $_FILES['orbisius_seo_editor_file']['tmp_name'] )) {
        if ( ! isset( $_REQUEST['orbisius_seo_editor_nonce'] )
             || ! wp_verify_nonce( $_REQUEST['orbisius_seo_editor_nonce'], 'orbisius_seo_editor_import_form_action' )
        ) {
            throw new Exception('Sorry, cannot process. Please refresh the page and try again.');
        }

        if ( ! Orbisius_SEO_Editor_Util::hasAccess() ) {
            throw new Exception( "Cannot access the page" );
        }

        $upload_rec = $_FILES['orbisius_seo_editor_file'];

        if ( empty( $upload_rec['name'] ) ) {
            throw new Exception( "Invalid upload. Missing name" );
        }

        $file_name = $upload_rec['name'];
        $ext       = pathinfo( $file_name, PATHINFO_EXTENSION );
        $ext       = strtolower( $ext );

        if ( empty( $ext ) ) {
            throw new Exception( "Invalid upload: missing extension" );
        } elseif ( ! in_array( $ext, [ 'csv' ] ) ) {
            throw new Exception( "Invalid/unsupported file extension" );
        }

        if ( empty( $upload_rec['type'] ) ) {
            throw new Exception( "Invalid upload: wrong file type" );
        }

        if ( ! is_uploaded_file( $upload_rec['tmp_name'] ) ) {
            throw new Exception( "Invalid upload: missing file" );
        }

        if ( filesize( $upload_rec["tmp_name"] ) <= 0 ) {
            throw new Exception( "Invalid 0 file size?!?" );
        }

		Orbisius_SEO_Editor_Debug::time( __FILE__ );
		Orbisius_SEO_Editor_Util::extendRunningTime();

        // We'll process the tmp file and let php delete it after
        $file    = $upload_rec['tmp_name'];
        $csv_obj = new Orbisius_SEO_Editor_CSV();
        $data    = $csv_obj->read( $file );

        $errors    = 0;
        $skipped   = 0;
        $successes = 0;


		Orbisius_SEO_Editor_Util::extendRunningTime();
		$status_rec = Orbisius_SEO_Editor_Util::processRecords($data, $filter_options);

        if (empty($status_rec['status'])) {
	        throw new Exception( $status_rec['msg'] );
        }

        $exec_time = Orbisius_SEO_Editor_Debug::time( __FILE__ );
        $exec_time_esc = esc_html($exec_time);

		$buff = '';
		$buff .= Orbisius_SEO_Editor_Util::msg("Done (Exec time: {$exec_time_esc}s)", 1);
		$buff .= "<textarea class='widefat' rows='4'>" . join("\n", $status_rec['work_log']) . "</textarea>";
        $msg .= $buff;
    }
} catch (Exception $e) {
	$msg = Orbisius_SEO_Editor_Util::msg($e->getMessage());
}
?>

<div class="postbox">
	<div class="inside">
		<h3><span>Import</span></h3>

        <div><?php echo $msg;?></div>

        <div>
            You need to select the SEO plugin/theme whose fields you'd like to update.
        </div>

        <form id='orbisius_seo_editor_import_form' class='orbisius_seo_editor_import_form orbisius_seo_editor_form' method='post' enctype="multipart/form-data">
			<?php wp_nonce_field( 'orbisius_seo_editor_import_form_action', 'orbisius_seo_editor_nonce' ); ?>

            <div>
                <?php
                $file_obj = Orbisius_SEO_Editor_File::getInstance();
                echo $file_obj->generateUploadSection();
                ?>
		    </div>

	        <?php
	        $target_seo_plugins_filters = [ 'format' => Orbisius_SEO_Editor_Plugin_Manager::FORMAT_DROPDOWN, 'skip_unsupported' => 0, ];
	        $target_seo_plugins = $plugin_manager_obj->getSEOPlugins($target_seo_plugins_filters);
	        $target_seo_plugins = empty($target_seo_plugins) ? [] : $target_seo_plugins;
	        $target_seo_plugins = $target_seo_plugins;

	        echo " SEO Plugin/theme: ";
	        echo Orbisius_SEO_Editor_Util::htmlSelect(
		        'orbisius_seo_editor_search[target_seo_plugin]',
		        $filter_options['target_seo_plugin'],
		        $target_seo_plugins
	        );
	        ?>

            <hr/>
            <div>
                <input type="submit" value="Start Import" class="button-primary"
                       id="orbisius_seo_editor_import" name="orbisius_seo_editor_import" />
            </div>

            <p>
                <strong>Notes: </strong> <br/>
                1. If you want a row to be skipped and not updated set its ID column value to 0 <br/>
                2. Keep the value for 'hash' as is. It is used to determine if the row was modified so we can skip it. <br/>
                3. After the import you may see success: No. This could mean that the record wasn't updated or it didn't need a db update because it was already updated.
                For example if you upload the the same CSV file multiple times.
            </p>
        </form>

    </div>
</div>
