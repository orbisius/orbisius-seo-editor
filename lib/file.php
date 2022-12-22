<?php

class Orbisius_SEO_Editor_File {
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

    private function __construct() {
    }

	/**
	 * @var array
	 */
    private $supported_ext = [
        'csv',
    	// images
//    	'jpg',
//    	'png',
//    	'tiff',
//    	'gif',
//    	'bmp',
//
//	    // docs
//    	'ppt',
//    	'pptx',
//    	'doc',
//    	'docx',
//    	'xls',
//    	'xlsx',
//    	'pdf',
//    	'txt',
//    	'pages',
//    	'rtf',
//
//	    // archives
//    	'zip',
//    	'tar',
//    	'gz',
//    	'tgz',
//    	'tar.gz',
//    	'rar',
//    	'7z',
    ];

	/**
	 * @param array $files
	 * @return bool
	 */
	function hasUpload( $files = [] ) {
		$files = empty($files) ? $_FILES : $files;
		$upload_rec = reset( $files ); // get the first elem (arr).
		return !empty( $upload_rec['tmp_name'] ) && is_uploaded_file($upload_rec['tmp_name']);
	}

	/**
	 * Generates the upload section and optionally adds a form (on demand)
	 * @param array $attribs
	 * @param string $content
	 */
	public function generateUploadSection( $attribs = [] ) {
		$ctx = [];
		$title = empty($attribs['title']) ? '' : $attribs['title'];
		$summary = empty($attribs['summary']) ? '' : $attribs['summary'];
		$render_form = !isset($attribs['render_form']) ? 0 : (int) $attribs['render_form'];
		ob_start();
		?>
		<div id="orbisius_seo_editor_file_upload_wrapper" class="orbisius_seo_editor_file_upload_wrapper orbisius_seo_editor_upload_form_wrapper">
            <?php if (!empty($title)) : ?>
			    <p><?php echo $title; ?></p>
            <?php endif; ?>

            <?php if (!empty($summary)) : ?>
			    <p><?php echo $summary; ?></p>
            <?php endif; ?>

			<?php do_action('orbisius_seo_editor_action_before_file_upload_form', $ctx); ?>

            <?php if ($render_form) : ?>
                <form id="orbisius_seo_editor_upload_form" class="orbisius_seo_editor_upload_form" action=""
                      method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'submit_content', 'my_nonce_field' ); ?>
                <?php endif; ?>

				<p>
                    <div class="custum_file_upload_btn_wrapper">
    <!--                    <span>Select file</sRpan>-->
                        <input type="file" id="orbisius_seo_editor_file" name="orbisius_seo_editor_file"
                               class="orbisius_seo_editor_file" accept=".csv"
                               value="" />

                        <button type='button' value='Upload' id="orbisius_seo_editor_upload_form_submit_btn"
                                name="orbisius_seo_editor_upload_form_submit_btn"
                                class="button orbisius_seo_editor_file_form_submit_btn
                                orbisius_seo_editor_upload_form_submit_btn">Select a file (CSV)</button>
                    </div>
				</p>
            <?php if ($render_form) : ?>
                </form>
            <?php endif; ?>

        </div> <!-- /orbisius_seo_editor_file_wrapper -->

		<!--        credit: https://codepen.io/adambene/pen/xRWrXN-->
		<style>
            .orbisius_seo_editor_hide {
                display: none;
            }
			.custum_file_upload_btn_wrapper {
				position: relative;
				overflow: hidden;
				display: inline-block;
			}

			.custum_file_upload_btn_wrapper input[type=file] {
				font-size: 100px;
				position: absolute;
				left: 0;
				top: 0;
				opacity: 0;
			}
		</style>

		<?php do_action('orbisius_seo_editor_action_after_file_upload_form', $ctx); ?>

		<?php
		$buff = ob_get_clean();
		return $buff;
	}

	/**
	 * @param string $dir_name
	 * @return string
	 */
	public function normalize( $dir_name ) {
		$dir_name = trim($dir_name);
		$dir_name = str_replace('\\', '/', $dir_name);
		$dir_name = preg_replace('#/+#', '/', $dir_name);
		return $dir_name;
	}

	/**
	 * @param string $file
	 * @return string
	 */
	public function sanitize( $file, $sep = '_' ) {
		$file = $this->normalize($file);
		$file = preg_replace('#[\s\:\"\'\?\|\<\>\*\x00]+#si', $sep, $file);
		$file = preg_replace('#-+#si', '-', $file);
		$file = preg_replace('#_+#si', $sep, $file);
		$file = trim($file, '-_' . $sep);
		$file = substr($file, 0, 250);
		return $file;
	}
}

