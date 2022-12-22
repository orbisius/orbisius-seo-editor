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
Class Orbisius_SEO_Editor_CSV {
    protected $buff_size = 1024;
    protected $delimiter = ',';
	const APPEND = 2;
	const DO_EXIT = 4;
	const DONT_EXIT = 8;

    public function delimiter($delimiter = '') {
        if (!empty($delimiter)) {
            $this->delimiter = $delimiter;
        }

        return $this->delimiter;
    }

    const FORMAT_HEADER_COLS = 2;

    /**
    * Reads a CSV file and returns array of arrays.
    * The header rows are not returned. We can extract them by getting the keys of the first array element.
    * @link https://gist.github.com/385876
    * @return array / false on error
    */
   public function read($filename = '', $flags = 0) {
       if (!file_exists($filename) || !is_readable($filename)) {
           trigger_error(sprintf("File [%s] doesn't exist or not readable.", esc_attr($filename)), E_USER_WARNING);
           return false;
       }

       $data = array();
       $header = array();

       // In some cases (Windows/Linux) the line endings are not read correctly so we'll hint php to detect the line endings.
	   $old_auto_detect_line_endings_flag = ini_get("auto_detect_line_endings");
	   ini_set("auto_detect_line_endings", true);

       if (($handle = fopen($filename, 'rb')) !== false) {
           // we just need a read lock
           flock($handle, LOCK_SH);

           while (($row = fgetcsv($handle, $this->buff_size, $this->delimiter)) !== false) {
	           if (empty($row)) {
		           continue;
	           }

	           $row = array_map('trim', $row );

	           if (empty($row)) {
		           continue;
	           }

	           // Empty lines could produce empty columns
	           $row_alt_empty_check = array_filter($row);

	           if (empty($row_alt_empty_check)) {
		           continue;
	           }

               // No header row OR if the data contains header row somewhere instead of data
               if (empty($header) || count(array_diff($header, $row)) == 0) {
                   // Validate heading row and in case the user had decided to remove it for some reason
                   // the rows must be alphanumeric + underscore and may contain numbers
                   // correct cols must match be the same elements as the array i.e. all must be correct
                   $valid_cols = 0;
                   $correct_cols_regex = '#^\s*[a-z]+[\w\-]+\s*$#si';

                   foreach ( $row as $val ) {
                       if (!preg_match($correct_cols_regex, $val)) {
                           break; // do need to check others since at least one didn't validate.
                       }

                       $valid_cols++;
                   }

                   if ($valid_cols != count($row)) {
                       throw new Exception("The heading row is missing or invalid. It is necessary as we use it to map fields.");
                   }

                   if ($flags & self::FORMAT_HEADER_COLS) {
					   foreach ( $row as $idx => $val ) {
						   $val = strtolower( $val );
						   $val = preg_replace( '#[^\w]#si', '_', $val );
						   $val = preg_replace( '#\_+#si', '_', $val );
						   $val = trim( $val, ' _' );
                           $row[$idx] = $val;
					   }
				   }

                   $header = $row;
               } else {
                   $data[] = @array_combine($header, $row);
               }
           }

           flock($handle, LOCK_UN);
           fclose($handle);
       }

	   ini_set("auto_detect_line_endings", $old_auto_detect_line_endings_flag); // restore previous value

	   return $data;

       /*
        * Return value
        array (
        0 =>
        array (
          'product_title' => 'Bottle',
          'parent_product_id' => '0',
          'product_id' => '308',
          'old_regular_price' => '1.00',
          'new_regular_price' => '5',
          'old_sale_price' => '1.00',
          'new_sale_price' => '2.00',
          'currency' => 'GBP',
          'cur_user_id' => '20',
          'cur_user_email' => 'user@email.com',
          'cur_date' => '2014-06-09 10:59:37',
        ),
        1 =>
        array (
          'product_title' => 'Bottles (Case)',
          'parent_product_id' => '0',
          'product_id' => '309',
          'old_regular_price' => '100.00',
          'new_regular_price' => '50.00',
          'old_sale_price' => '10.00',
          'new_sale_price' => '10.00',
          'currency' => 'GBP',
          'cur_user_id' => '20',
          'cur_user_email' => 'user@email.com',
          'cur_date' => '2014-06-09 10:59:37',
        ),
      )
        */
   }

    /**
     * This outputs data in a file or in the browser (if file param is passed).
     * If the browser option is used it will output the data and will exit.
     *
     * @param array $data array of arrays
     * @param array $title_row (optional) title/header row of the CSV file
     * @param str $file target file to save the output to
     * @return bool true/false depending on success
     */
    public function write($data, $title_row = array(), $file = '', $flags = 0) {
    	static $browser_handle = null;
    	$headers_sent = 0;

        $local_file = !empty($file);
        $output_in_browser = empty($file);

        $append = $flags & self::APPEND;

        if ($output_in_browser) { // browser
        	if (is_null($browser_handle)) {
		        $this->sendDownloadHeadersForCSVDownload($file);
		        $headers_sent = 1;
		        $handle = fopen('php://output', 'wb');
		        $browser_handle = $handle;
	        } else {
		        $handle = $browser_handle;
	        }
        } else {
            $handle = fopen($file, $append ? 'ab' : 'wb');
        }

        if (empty($handle)) {
            return false;
        }

        // for some reason filesize would not update if the script accesses this file quickly
        // this resulted in header column being written multiple times.
        // that's why we clear the cache every time we write.
	    if ($local_file) {
		    clearstatcache();
		    flock( $handle, LOCK_EX );
	    }

	    $add_title_row = 0;

	    if (!empty($title_row)) {
	    	if ($output_in_browser) {
	    		if ($headers_sent) { // first time output is opened
				    $add_title_row = 1;
			    }
		    } elseif (!$append) {
			    $add_title_row = 1;
		    } elseif (!empty($file) && filesize($file) < 10) {
			    $add_title_row = 1;
		    }
	    }

        // add the title row only if we're not appending or if the file size is minimal (fewer than 10 bytes).
        // no need to add header rows all the time.
        if ($add_title_row) {
            fputcsv($handle, $title_row);
        }

        // In case the dev has passed just a row and not array of rows
        if (empty($data[0]) || !is_array($data[0])) {
	        $data = [ $data ];
        }

        foreach ($data as $csv_data) {
            fputcsv($handle, $csv_data);
        }

        if ($local_file) {
	        flock( $handle, LOCK_UN );
            fclose($handle);
        }

        if ($flags & self::DO_EXIT) {
            die;
        }
        
        return true;
    }

	/**
	 * Sends the headers
	 * @return void
	 */
	public function sendDownloadHeadersForCSVDownload($inp_filename = '') {
		if (headers_sent()) {
			return;
		}

		if (empty($inp_filename)) {
			$inp_filename = $this->getDownloadFileName();
		}

		if (empty($inp_filename)) {
			$file_prefix = basename(ORBISIUS_SEO_EDITOR_BASE_PLUGIN);
			$file_prefix = str_replace('.php', '', $file_prefix);
			$file_prefix = sanitize_title( $file_prefix );
			$file_prefix = str_replace( '-', '_', $file_prefix );
			$file_prefix .= '_';
			$filename    = $file_prefix . date( 'Ymd' ) . '_' . date( 'His' ) . '.csv';
		} else {
			$filename = basename($inp_filename);
		}

		// Output CSV-specific headers
		// https://stackoverflow.com/questions/393647/response-content-type-as-csv
		header("Pragma: public");
		header('X-Content-Type-Options: nosniff');
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		header("Content-Type: text/csv");
		header("Content-Disposition: attachment; filename=\"$filename\";" );
		header("Content-Transfer-Encoding: binary");
	}

	private $dl_file = '';

	/**
	 * @return string
	 */
	public function getDownloadFileName(): string {
		return $this->dl_file;
	}

	/**
	 * @param string $dl_file
	 */
	public function setDownloadFileName( string $dl_file ): void {
		$this->dl_file = $dl_file;
	}
}
