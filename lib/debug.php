<?php

class Orbisius_SEO_Editor_Debug {
	/**
	 * Returns the start time when an operation takes place so you can later do a delta.
	 *
	 * If it's called twice with the same parameter.
	 * The second time it will return the time delta.
	 * Smart, eh?
	 *
	 * Usage 1:
	 * $time_ms = Orbisius_SEO_Editor_Debug::time();
	 * sprintf( "%.02f", abs( $time_ms - Orbisius_SEO_Editor_Debug::time() ) );
	 *
	 * Usage 2:
	 * Orbisius_SEO_Editor_Debug::time( 'setup_vhost' );
	 * ......
	 * $time_delta = Orbisius_SEO_Editor_Debug::time( 'setup_vhost' );
	 *
	 * if you don't want the time formatted (to 2 decimals) pass 0 as 2nd param.
	 * $time_delta = Orbisius_SEO_Editor_Debug::time( 'setup_vhost', 0 );
	 * sprintf( "%.02f", $time_delta );
	 *
	 * @param string $marker optional
	 * @return float
	 */
	public static function time( $marker = 'default', $fmt = 1, $precision = 4 ) {
		static $times = array();

		list ($usec, $sec) = explode(" ", microtime());
		$time_ms = ((float) $usec + (float) $sec);

		if ( ! empty( $marker ) ) {
			$marker = is_scalar( $marker ) ? $marker : sha1( serialize( $marker ) );

			if ( empty( $times[ $marker ] ) ) {
				$times[ $marker ] = $time_ms;
			} else {
				$time_ms = abs( $time_ms - $times[ $marker ] ); // jic
				//unset( $times[ $marker ] );
			}
		}

		if ( $fmt ) {
			$time_ms = sprintf( "%.0{$precision}f", $time_ms );
		}

		return $time_ms;
	}

    /**
     * Dumps data
     * Orbisius_SEO_Editor_Debug::dump
     *
     * @param array $attr
     * @return string
     */
    public static function dump($var = array(), $label = '', $ret = 0) {
        $buff = '';
        $buff .= "<pre>\n";

        if (!empty($label)) {
            $label = esc_attr($label);
            $buff .= "<h3>$label</h3>";
        }

		ob_start();
		var_dump($var); // var_export() could fail... circular refs (objs?)
		$data_dump_str = ob_get_clean();
		// the data may contain HTML/js and stuff so it has to be presentable in the browser.
	    // if it has tags they won't show up visually because the browser will interpret them.
		$buff .= function_exists('esc_html') ? esc_html($data_dump_str) : htmlentities($data_dump_str);
        $buff .= "</pre>\n";

        if ($ret) {
            return $buff;
        } else {
            echo $buff;
        }
    }
}