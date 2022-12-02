<?php

class Orbisius_SEO_Editor_HTML {
    /**
     * Generates radio or checkboxes
     *
     * Orbisius_SEO_Editor_HTML();
     *
     * <?php
                                                $cur_val = empty($opts['mobile_move_sidebar']) ? '' : $opts['mobile_move_sidebar'];
                                                $move_options = array(
                                                    'none' => 'None',
                                                    'after' => 'After the Main Content',
                                                    'before' => 'Before the Main Content',
                                                );
                                                echo Orbisius_SEO_Editor_HTML::generateHtmlBoxes(
                                                        'orbisius_smart_sidebar_options[mobile_move_sidebar]',
                                                        $cur_val, $move_options, '', array('sep' => '&nbsp; | &nbsp;') );
                                                ?>
     *
     * @param string $name
     * @param string $sel
     * @param string $options
     * @param string $attr
     * @return string
     */
    public static function generateHtmlBoxes($name = '', $sel = null, $options = array(), $attr = '', $types = array()) {
        $esc_name = esc_attr($name);
        $html = "\n<div id='$esc_name' $attr>\n";

        $type = empty($types['type']) || $types['type'] == 'radio' ? 'radio' : 'checkbox';
        $sep = isset($types['sep']) ? $types['sep'] : "<br/>\n";

        $cnt = 0;

        foreach ($options as $key => $label) {
            $checked = $sel == $key ? ' checked="checked"' : '';
            $html .= "\t<label> <input type='$type' name='$esc_name' value='$key' $checked> $label</label>";

            if ($cnt < count($options) - 1) {
                $html .= $sep;
            }

            $cnt++;
        }

        $html .= '</div>';
        $html .= "\n";

        return $html;
    }

    /**
     * Orbisius_SEO_Editor_HTML::hidden('');
     * 
     * @param string $name
     * @param string $sel
     * @param string $attr
     * @return string
     */
    public static function hidden($name = '', $sel = null, $attr = '') {
    	$box = Orbisius_SEO_Editor_HTML::text($name, $sel, $attr);
	    $box = preg_replace('#(\s+type\s*=\s*[\'\"]*)text([\'\"]*)#si',  '${1}hidden${2}', $box);
    	return $box;
    }

    /**
     * Outputs a text box or a text area (depending of the attrib contains textarea keyword).
     * Orbisius_SEO_Editor_HTML::text('');
     *
     * @param string $name
     * @param string $sel
     * @param string $attr
     * @return string
     */
    public static function text($name = '', $sel = null, $attr = '') {
        $esc_id = esc_attr($name);
        $esc_name = esc_attr($name);

        $val = $sel;

        if (is_null($sel) && !empty($_REQUEST[$name])) {
            $val = $_REQUEST[$name];
            $val = wp_kses($val, array());
        }

        $val = trim($val);
        $esc_val = esc_attr($val);

		// allow these characters as they are inline attribs class='xyz'
	    // calling esc_attr(); puts more quotes
	    $attr_esc = preg_replace('#[^\s\w\-\=\'\"]+#si', '', $attr);

        if (stripos($attr, 'textarea') === false) {
            $html = "<input type='text' name='$esc_name' id='$esc_id' value='$esc_val' $attr_esc />\n";
        } else {
	        $attr_esc = str_replace('textarea', '', $attr);
            $html = "<textarea name='$esc_name' id='$esc_id' $attr_esc>$esc_val</textarea>\n";
        }

        return $html;
    }

    /**
     * Uses WP's wp_kses to clear the text
     */
    public static function strip_tags($buffer) {
        $buffer = wp_kses($buffer, array(
            'div' => array(),
            'p' => array(),
            'a' => array(
                'href' => array(),
                'title' => array(),
                'target' => array(),
            ),
            'ul' => array(
                'class' => array(),
            ),
            'ol' => array(
                'class' => array(),
            ),
            'li' => array(
                'class' => array(),
            ),
            'br' => array(),
            'hr' => array(),
            'strong' => array(),
                )
        );

        $buffer = trim($buffer);

        return $buffer;
    }

    /**
     * Gets the request url without the params
     * @param string $url
     * @param array $params
     * @return string
     */
    public static function getReqUri($full = 1, $keep_params = 0) {
	    $req_obj = Orbisius_SEO_Editor_Request::getInstance();
	    $req_uri = $req_obj->getServerEnv('REQUEST_URI');

        if (empty($keep_params)) {
            $req_uri = preg_replace('#\?.*#si', '', $req_uri);
            $req_uri = preg_replace('#\#.*#si', '', $req_uri);
        }

		if ($full) {
			$url = function_exists( 'site_url' ) ? site_url( $req_uri ) : 'https://' . $_SERVER['HTTP_HOST'] . $req_uri;
		}

        return $url;
    }

    // generates HTML select
    public static function htmlSelect($name = '', $sel = null, $options = array(), $attr = '') {
        $html = "\n" . '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" ' . $attr . '>' . "\n";

        foreach ($options as $key => $label) {
            $selected = $sel == $key ? ' selected="selected"' : '';
            $html .= "\t<option value='$key' $selected>$label</option>\n";
        }

        $html .= '</select>';
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
     *
     * Appends a parameter to an url; uses '?' or '&'
     * It's the reverse of parse_str().
     * WP has its own ?? add_arg???
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    public static function addUrlParams($url, $params = array()) {
        $str = $url;

        if (empty($params)) {
            return $str;
        }

        $params = (array) $params;

        $str .= (strpos($url, '?') === false) ? '?' : '&';
        $str .= http_build_query($params);

        return $str;
    }

    /**
     * Generates radio or checkboxes. If $sel is empty the first element from the $options array
     * orbisius_custom_phone_verificator_util::query_str_to_array('a=b');
     *
     * @param str $post_content
     * @return string
     */
    public static function query_str_to_array($post_content) {
        $check_fields_arr = array();

        // WP encodes the special chars so we need to decode them e.g. &amp; -> &
        $post_content = html_entity_decode($post_content);
        parse_str($post_content, $check_fields_arr);

        return $check_fields_arr;
    }

	/**
	 * Orbisius_SEO_Editor_HTML::get()
	 * Secure param retrieval.
	 * @param string $key
	 * @param mixed $default
	 * @param mixed $val
	 * @return mixed
	 */
	public static function get($key = '', $default = '', $val = '') {
		if (empty($key)) {
			$params = $_REQUEST;

			foreach ($params as $key => $val) {
				$params[$key] = self::get($key, $val);
			}

			return $params;
		} elseif (!empty($val)) {
			// ok use whatever was passes
		} elseif (!empty($_REQUEST[$key])) {
			$val = $_REQUEST[$key];
		} else {
			$val = $default;
		}

		if (is_scalar($val)) {
			if (function_exists('wp_kses')) {
				$val = wp_kses($val, array());
			}

			$val = strip_tags($val);
			$val = trim($val);
		} else {
			$val = array_map('strip_tags', $val);
			$val = array_map('trim', $val);
		}

		return $val;
	}

	/**
	 * Orbisius_SEO_Editor_HTML::sanitize()
	 * Secure param retrieval.
	 * @param string|aray $val
	 * @return mixed
	 */
	public static function sanitize($val) {
		if (is_null($val)) {
			$val = '';
		}

		if (is_scalar($val)) {
			if (function_exists('wp_kses')) {
				$val = wp_kses($val, array());
			}

			$val = strip_tags($val);
			$val = trim($val);
		} elseif (is_array($val)) {
			$val = array_map('Orbisius_SEO_Editor_HTML::sanitize', $val);
		} else {
			throw new Exception("Wrong data type passed to sanitize");
		}

		return $val;
	}

}

