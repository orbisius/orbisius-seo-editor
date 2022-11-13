<?php

class Orbisius_SEO_Editor_String_Util {
	/**
	 * Sanitizes al the data
	 * Orbisius_SEO_Editor_String_Util::sanitize()
	 * @param string|array $data
	 * @return mixed
	 */
	static public function sanitize($data) {
		if ( is_array( $data ) ) {
			$params = $data;

			foreach ( $params as $key => $val) {
				$params[$key] = self::stripSomeTags( $val, self::STRIP_ALL_TAGS );
			}

			$data = $params;
		} else {
			$data = self::stripSomeTags($data, self::STRIP_ALL_TAGS);
			$data = self::trim($data);
		}

		return $data;
	}

	/**
	 * Sanitizes some
	 * Orbisius_SEO_Editor_String_Util::sanitizeSome()
	 * @param string|array $data
	 * @return mixed
	 */
	static public function sanitizeSome($data) {
		if ( is_array( $data ) ) {
			$params = $data;

			foreach ( $params as $key => $val) {
				$params[$key] = self::stripSomeTags( $val );
			}

			$data = $params;
		} else {
			$data = self::stripSomeTags($data);
			$data = self::trim($data);
		}

		return $data;
	}

	const STRIP_SOME_TAGS = 2;
	const STRIP_ALL_TAGS = 4;

	/**
	 * Uses WP's wp_kses to clear some of the html tags but allow some attribs
	 * usage: Orbisius_SEO_Editor_String_Util::stripSomeTags($buffer);
	 * uses WordPress' wp_kses()
	 * @param string $buffer string buffer
	 * @return string cleaned up text
	 */
	public static function stripSomeTags($buffer, $flags = self::STRIP_SOME_TAGS ) {
		if (is_scalar($buffer)) {
			// ok
		} elseif (is_array($buffer)) {
			return array_map( 'Orbisius_SEO_Editor_String_Util::stripSomeTags', $buffer );
		} else {
			return $buffer;
		}

		// these work only in WP ctx
		static $default_attribs = array(
			'id' => array(),
			'rel' => array(),
			'class' => array(),
			'title' => array(),
			'style' => array(),
			'data' => array(),
			'target' => array(),
			'data-mce-id' => array(),
			'data-mce-style' => array(),
			'data-mce-bogus' => array(),
		);

		$allowed_tags = array(
			'div'           => $default_attribs,
			'span'          => $default_attribs,
			'p'             => $default_attribs,
			'a'             => array_merge( $default_attribs, array(
				'href' => array(),
				'target' => array('_blank', '_top', '_self'),
			) ),
			'u'             => $default_attribs,
			'i'             => $default_attribs,
			'q'             => $default_attribs,
			'b'             => $default_attribs,
			'ul'            => $default_attribs,
			'ol'            => $default_attribs,
			'li'            => $default_attribs,
			'br'            => $default_attribs,
			'hr'            => $default_attribs,
			'strong'        => $default_attribs,
			'strike'        => $default_attribs,
			'blockquote'    => $default_attribs,
			'del'           => $default_attribs,
			'em'            => $default_attribs,
			'pre'           => $default_attribs,
			'code'          => $default_attribs,
			'style'         => $default_attribs,
		);

		if (function_exists('wp_kses')) { // WP is here
			$buffer = wp_kses($buffer, $allowed_tags);
		} elseif ( $flags & self::STRIP_ALL_TAGS ) {
			$buffer = strip_tags($buffer);
		} else {
			$tags = array();

			foreach (array_keys($allowed_tags) as $tag) {
				$tags[] = "<$tag>";
			}

			$buffer = strip_tags($buffer, join('', $tags));
		}

		$buffer = self::trim($buffer);

		return $buffer;
	}

	/**
	 * Orbisius_SEO_Editor_String_Util::trim();
	 * @param string $str
	 * @return string|array
	 * Ideas gotten from: http://www.jonasjohn.de/snippets/php/trim-array.htm
	 */
	public static function trim($data) {
		if ( is_scalar( $data ) ) {
			return trim( $data );
		}

		return array_map( 'Orbisius_SEO_Editor_String_Util::trim', $data );
	}
}