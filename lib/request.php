<?php

class Orbisius_SEO_Editor_Request {
	const INT = 2;
	const FLOAT = 4;
	const ESC_ATTR = 8;
	const JS_ESC_ATTR = 16;
	const EMPTY_STR = 32; // when int/float numbers are 0 make it an empty str
	const STRIP_SOME_TAGS = 64;
	const STRIP_ALL_TAGS = 128;
	const SKIP_STRIP_ALL_TAGS = 256;
	const REDIRECT_EXTERNAL_SITE = 2;

	/**
	 * @var array
	 * @see https://codex.wordpress.org/Function_Reference/wp_kses
	 */
	private $allowed_permissive_html_tag_attribs = [
		'id' => [],
		'title' => [],
		'style' => [],
		'class' => [],
	];

	/**
	 * @var array
	 * @see https://codex.wordpress.org/Function_Reference/wp_kses
	 */
	private $allowed_permissive_html_tags = array(
		'a' => array(
			'href' => [],
			'target' => [],
		),
		'br' => [],
		'em' => [],
		'i' => [],
		'b' => [],
		'p' => [],
		'img' => [
			'src' => [],
			'data-src' => [],
			'data-srcset' => [],
			'border' => [],
		],
		'code' => [],
		'blockquote' => [],
		'ins' => [],
		'del' => [],
		'div' => [],
		'pre' => [],
		'span' => [],
		'link' => [ 'rel' => [], 'type' => [], 'media' => [], 'href' => [], ],
		'style' => [],
		'strong' => [],
		'hr' => [],
		'ul' => [],
		'ol' => [],
		'li' => [],
		'h1' => [],
		'h2' => [],
		'h3' => [],
		'h4' => [],
		'h5' => [],
		'h6' => [],
	);

	protected $data = null;

	protected $raw_data = [];

	/**
	 * Some sections of the code may pass state so it can be pulled from another spot.
	 * @var array
	 */
	private $state = [];

	public function __construct() {
		$this->init();
	}

	/**
	 * Singleton pattern i.e. we have only one instance of this obj
	 *
	 * @staticvar static $instance
	 * @return static
	 */
	public static function getInstance() {
		static $instance = null;

		// This will make the calling class to be instantiated.
		// no need each sub class to define this method.
		if (is_null($instance)) {
			$instance = new static();
		}

		return $instance;
	}

	/**
	 * if a key exists in the request
	 * @param $key
	 * @return bool
	 */
	public function has( $key) {
		return isset($this->data[$key]);
	}

	/**
	 * if a key exists in the request
	 * @param $key
	 * @return bool
	 */
	public function hasPostKey($key) {
		return isset($_POST[$key]);
	}

	public function getRaw( $key = '', $default = '', $force_type = 1 ) {
		$val = !empty($this->raw_data[$key]) ? $this->raw_data[$key] : $default;
		return $val;
	}

	/**
	 * @param string $key
	 * @param mixed $val
	 * @return mixed
	 */
	public function set(string $key, $val) {
		if (is_null($val)) {
			unset($this->data[$key]);
		} else {
			$this->data[$key] = $val;
		}

		return $val;
	}

	/**
	 * $req_obj->getSeparators();
	 * @param string $key
	 * @return mixed
	 */
	public function getSeparators() {
		return [ ',', ';', '|', ];
	}

	/**
	 * $req_obj->getSep();
	 * @param string $key
	 * @return mixed
	 */
	public function getSep($checked_val = '') {
		foreach ($this->getSeparators() as $loop_sep) {
			if (strpos($checked_val, $loop_sep) !== false) {
				return $loop_sep;
			}
		}

		return '';
	}

	/**
	 * $req_obj = Orbisius_SEO_Editor_Request::getInstance();
	 * $req_obj->get();
	 * check for multiple fields and return when once matches.
	 * ->get('contact_form_msg|msg|message')
	 * @param string $key
	 * @return mixed
	 */
	public function get( $key = '', $default = '', $force_type = 1 ) {
		if (empty($key)) {
			return $this->data;
		}

		$key = trim( $key );
		$sep = $this->getSep($key);

		// ->get('contact_form_msg|msg|message')
		if (!empty($sep)) { // checking for multiple keys
			$separtors = $this->getSeparators();
			$separtors = array_map('trim', $separtors);
			$separtors = array_unique($separtors);
			$separtors_esc = array_map('preg_quote', $separtors);
			$separtors_esc_str = join('', $separtors_esc); // we'll put them in a regex group [;\|]

			// We split on all of them because there could be multiple separators.
			// the getSep returned the first one that matched.
			$multiple_keys = preg_split("/[$separtors_esc_str]+/si", $key);
			$multiple_keys = array_map('trim', $multiple_keys);
			$multiple_keys = array_unique($multiple_keys);

			foreach ($multiple_keys as $loop_key) {
				$loop_val = $this->get($loop_key); // recursion!

				if (!empty($loop_val)) {
					return $loop_val;
				}
			}

			// nothing found for the multiple keys so don't check below.
			return $default;
		}

		$val = !empty($this->data[$key]) ? $this->data[$key] : $default;

		if ( $force_type & self::INT ) {
			$val = intval($val);

			if ( $val == 0 && $force_type & self::EMPTY_STR ) {
				$val = "";
			}
		}

		if ( $force_type & self::FLOAT ) {
			$val = floatval($val);

			if ( $val == 0 && $force_type & self::EMPTY_STR ) {
				$val = "";
			}
		}

		if ( $force_type & self::ESC_ATTR ) {
			$val = esc_attr($val);
		}

		if ( $force_type & self::JS_ESC_ATTR ) {
			$val = esc_js($val);
		}

		if ( $force_type & self::STRIP_SOME_TAGS ) {
			$allowed_tags = [];

			// Let's merge the tags with the default allowed attribs
			foreach ($this->allowed_permissive_html_tags as $tag => $allowed_attribs) {
				$allowed_attribs = array_replace_recursive($allowed_attribs, $this->allowed_permissive_html_tag_attribs);
				$allowed_tags[$tag] = $allowed_attribs;
			}

			$val = wp_kses($val, $allowed_tags);
		}

		// Sanitizing a var
		if ( $force_type & self::STRIP_ALL_TAGS ) {
			$val = wp_kses($val, []);
		}

		$val = is_scalar($val) ? trim($val) : $val;

		// passing email via request param converts + to spaces. Sometimes I am too busy to encode the +
		if (strpos($key, 'email') !== false) {
			$val = str_replace( ' ', '+', $val );
		}

		return $val;
	}

	/**
	 * get and escape
	 * @param string $key
	 * @param int $force_type
	 * @return string
	 */
	public function gete( $key, $force_type = 1 ) {
		$v = $this->get( $key, '', $force_type );
		$v = esc_attr( $v );
		return $v;
	}

	private $server_env = [];

	/**
	 * WP puts slashes in the values so we need to remove them.
	 * @param array $data
	 */
	public function init( $data = null ) {
		// see https://codex.wordpress.org/Function_Reference/stripslashes_deep
		if ( is_null( $this->data ) ) {
			$data = empty( $data ) ? $_REQUEST : $data;
			$this->raw_data = $data;
			$data = stripslashes_deep( $data );
			$data = $this->sanitizeData( $data );
			$this->data = $data;

			if (!empty($_SERVER)) {
				$server_env = $_SERVER;
				$server_env = stripslashes_deep( $server_env );
				$server_env = $this->sanitizeData( $server_env );
				$server_env = Orbisius_SEO_Editor_String_Util::trim($server_env);
				$this->server_env = $server_env;
			}
		}
	}

	/**
	 *
	 * @param string|array $data
	 * @return string|array
	 * @throws Exception
	 */
	public function sanitizeData( $data = null ) {
		if ( is_scalar( $data ) ) {
			//$data = wp_strip_all_tags( $data ); // this really cleans stuff
			//$data = sanitize_text_field( $data ); // <- this breaks urls passed as url params such as next_link
			//$data = wp_kses_data( $data ); // <- this encodes & -> &amp; and breaks the links with params.
			$allowed_tags = [];

			// Let's merge the tags with the default allowed attribs
			foreach ($this->allowed_permissive_html_tags as $tag => $allowed_attribs) {
				$allowed_attribs = array_replace_recursive($allowed_attribs, $this->allowed_permissive_html_tag_attribs);
				$allowed_tags[$tag] = $allowed_attribs;
			}

			$data = wp_kses($data, $allowed_tags);
			$data = trim( $data );
		} elseif ( is_array( $data ) ) {
			$data = array_map( array( $this, 'sanitizeData' ), $data );
		} elseif (is_null($data)) { // maybe it's run from the command line
			$data = '';
		} else {
			throw new Exception( "Invalid data type passed for sanitization" );
		}

		return $data;
	}

	/**
	 *
	 * @param array/void $params
	 * @return bool
	 */
	public function validate($params = []) {
		return !empty($_POST);
	}

	/**
	 *
	 * @param void
	 * @return bool
	 */
	public function isHead() {
		return (!empty($this->server_env['REQUEST_METHOD']) && strcasecmp($this->server_env['REQUEST_METHOD'], 'head') == 0);
	}

	/**
	 *
	 * @param void
	 * @return bool
	 */
	public function isGet() {
		return (!empty($this->server_env['REQUEST_METHOD']) && strcasecmp($this->server_env['REQUEST_METHOD'], 'get') == 0);
	}

	/**
	 * If post field is passed we want request to be POST and that field to have been passed.
	 * @param string $post_field
	 * @return bool
	 */
	public function isPost($post_field = '') {
		if (!empty($post_field)) {
			if (!$this->hasPostKey($post_field)) {
				return false;
			}
		}

		return !empty($_POST) || (!empty($this->server_env['REQUEST_METHOD']) && strcasecmp($this->server_env['REQUEST_METHOD'], 'post') == 0);
	}

	const REQUEST_METHOD_GET = 'GET';
	const REQUEST_METHOD_POST = 'POST';
	const REQUEST_METHOD_HEAD = 'HEAD';

	const REQUEST_FAILED = 2;

	/**
	 * @param string $url
	 * @param array $params
	 * @param array $extra
	 * @return Orbisius_SEO_Editor_Result
	 */
	public function call($url, array $params = [], array $extra = []) {
		if (empty($url)) {
			throw new Exception("URL is empty");
		}

		$dev_env = defined('QS_SITE_DEV_ENV') && QS_SITE_DEV_ENV;
		$timeout = $dev_env ? 90 : 30;

		if ( ! empty( $extra['timeout'] ) ) {
			$timeout = $extra['timeout'];
		} elseif ( ! empty( $req_params['__timeout'] ) ) {
			$timeout = $req_params['__timeout'];
			unset( $req_params['__timeout'] );
		}

		$verify_ssl = $dev_env ? false : true;

		if (isset($extra['verify_ssl'])) {
			$verify_ssl = !empty($extra['verify_ssl']);
		}

		$res_obj = new Orbisius_SEO_Editor_Result();
		$wp_remote_post_params = array(
			'method' => empty($params) ? self::REQUEST_METHOD_GET : self::REQUEST_METHOD_POST,
			'timeout' => $timeout,
			'redirection' => 3,
			//'httpversion' => '1.0',
			'sslverify' => $verify_ssl,
			'blocking' => true,
			'headers' => [],
			'cookies' => [],
		);

		$req_method = Orbisius_SEO_Editor_Util::getField('req_method|request_method|method', $extra);

		if (!empty($req_method)) {
			$wp_remote_post_params['method'] = $req_method;
		}

		if (!empty($extra['cookies'])) {
			$wp_cookies = [];

			foreach ($extra['cookies'] as $cookie_rec) {
				$cookie_params = array(
					'name'  => $cookie_rec['name'],
					'value' => $cookie_rec['value'],
					'path' => empty($cookie_rec['path']) ? '/' : $cookie_rec['path'],
				);

				if (!empty($cookie_rec['domain'])) {
					$cookie_params['domain'] = $cookie_rec['domain'];
				}

				$wp_cookies[] = new WP_Http_Cookie($cookie_params);
			}

			$wp_remote_post_params['cookies'] = $wp_cookies;
		}

		// Can be used by hash auth.
		if (!empty($extra['user_agent'])) {
			$wp_remote_post_params['user-agent'] = $extra['user_agent'];
		} elseif (!empty($this->server_env['HTTP_USER_AGENT'])) {
			$wp_remote_post_params['user-agent'] = $this->server_env['HTTP_USER_AGENT'];
		}

		$wp_remote_post_params['headers'] = empty('headers') ? [] : $wp_remote_post_params['headers'];

		if (!empty($extra['headers'])) {
			$wp_remote_post_params['headers'] = array_merge($extra['headers'], $wp_remote_post_params['headers']);
		}

		if (!empty($extra['method'])) {
			$wp_remote_post_params['method'] = $extra['method'];
		}

		$req_params = $params;
		$req_params = apply_filters('qs_site_app_filter_request_params', $req_params);

		$basic_auth_user = '';
		$basic_auth_pass = '';

		// check the basic auth params. We'll remove them from the API request so we don't confuse the API
		// as the API may assume it's the OS user that we're passing
		if (!empty($extra['_user']) && !empty($extra['_pass'])) {
			$basic_auth_user = $extra['_user'];
			$basic_auth_pass = $extra['_pass'];
		} elseif (!empty($req_params['_user']) && !empty($req_params['_pass'])) {
			$basic_auth_user = $req_params['_user'];
			$basic_auth_pass = $req_params['_pass'];
			unset($req_params['_user']);
			unset($req_params['_pass']);
		} elseif (!empty($extra['user']) && !empty($extra['pass'])) { // backcompat
			$basic_auth_user = $extra['user'];
			$basic_auth_pass = $extra['pass'];
		}

		// Now we send the user/pass if passed.
		if (!empty($basic_auth_user) && !empty($basic_auth_pass)) {
			$username = $basic_auth_user;
			$password = $basic_auth_pass;

			$wp_remote_post_params['headers'] = array_merge( array(
				'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
			), $wp_remote_post_params['headers'] );
		}

		$wp_remote_post_params['body'] = $req_params;

		$response = '';
		$response_code = 200;

		// Let's try several times if necessary
		for ( $i = 1; $i <= 3; $i++ ) {
			$response      = wp_remote_post( $url, $wp_remote_post_params );
			$response_code = wp_remote_retrieve_response_code( $response );

			if ( ! empty( $response ) && $response_code == 200 && ! is_wp_error( $response ) ) {
				break;
			}

			// Serious backend error?
			if ( $response_code == 401 || $response_code == 403 || $response_code == 500 || $response_code == 503 ) {
				break;
			}

			// if lower timeout we don't care about the status as it maybe intentionally using low timeout
			// so we don't slow down the UI
			if ($timeout < 5) {
				break;
			}

			// If it's the dev site don't do multiple attempts because on dev and with debugging enabled
			// it can get confusing really quick when multiple requests come in.
			/*if ($dev_env) {
				break;
			}*/

			usleep( 5000 * 1000 ); // take a short break
		}

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$res_obj->msg("Error: $error_message" );

			// This also captures when connection times out.
			// Setting up a site will naturally take about 20-30 seconds
			// so this is not an error per say.
			if ( isset( $error_message['http_request_failed'] ) || preg_match( '#-1 bytes?#si', $response->get_error_message() ) ) {
				$res_obj->msg( __( 'Connection to the server has failed.', 'qs_site_app' ) );
				$res_obj->code( self::REQUEST_FAILED );
			} else {
				$res_obj->msg( $response->get_error_message() );
			}
		} else {
			$buff = wp_remote_retrieve_body( $response );

			$parse_obj = QS_Site_App_String_Util::removeLeadingNoticesFromOutput($buff);
			$buff = $parse_obj->output;

			if (QS_Site_App_String_Util::isJSON($buff)) {
				$res_obj = new Orbisius_SEO_Editor_Result($buff);
			} else {
				$res_obj = new Orbisius_SEO_Editor_Result(1);
			}

			$res_obj->data('raw_data', $buff);

			if ($dev_env) {
				$res_obj->data( 'stderr', $parse_obj->stderr );
			}

			$res_obj->data('url', $url);
			$res_obj->data('output', $buff);
			$res_obj->data('request_params', $wp_remote_post_params);
		}

		if ($res_obj->isError() && empty($res_obj->msg)) {
			if ($response_code == 404) {
				$res_obj->msg = 'Backend returned: Resource Not Found.';
			} elseif ($response_code == 500) {
				$res_obj->msg = 'Backend returned: Internal Server Error';
			} else {
				$res_obj->msg = 'Backend returned: ' . $response_code;
			}
		}

		$res_obj->data('raw_response', $response);
		$res_obj->data('response_code', $response_code);

		return $res_obj;
	}

	const REDIRECT_FORCE = 1;
	const REDIRECT_DEFAULT = 0;

	private $local_ips = [ '::1', '127.0.0.1', '10.0.0', '192.168.0.', '192.168.1.', '192.168.2.', ];

	/**
	 * @param string $ip
	 * @return bool
	 */
	public function isLocalIP($ip = '') {
		$ip = empty($ip) ? QS_Site_App_User::getUserIP() : $ip;
		return QS_Site_App_User::isLocalIP($ip);
	}

	/**
	 * Smart redirect method. Sends header redirect or HTTP meta redirect.
	 * @param string $url
	 */
	public function redirect($url = '', $force = self::REDIRECT_DEFAULT) {
		if (defined('WP_CLI') || empty($url)) {
			// don't do anything if WP-CLI is running.
			return;
		}

		if (defined('DOING_CRON') && DOING_CRON) { // wp cron
			return;
		}

		// Don't do anything for ajax requests
		if (defined('DOING_AJAX') && DOING_AJAX) {
			return;
		}

		if (isset($_REQUEST['wc-api']) || isset($_REQUEST['mailpoet_router'])) {
			return;
		}

		// Don't do anything if qs_site_app_cmd is passed (future)
		if (!empty($_REQUEST['qs_site_app_cmd'])) {
			return;
		}

		$local_ips = [ '::1', '127.0.0.1' ];

		if ($force == self::REDIRECT_DEFAULT
		    && (!empty($this->server_env['REMOTE_ADDR'])
		        && !in_array($this->server_env['REMOTE_ADDR'], $local_ips)
		        && $this->server_env['REMOTE_ADDR'] == $this->server_env['SERVER_ADDR'])
		) { // internal req or dev machine
			return;
		}

		$url = apply_filters('qs_site_app_filter_redirect_url', $url);

		$req_url = $this->getRequestUrl();
		$future_redirect_url = $url;
		$future_redirect_url_web_path = parse_url($future_redirect_url, PHP_URL_PATH);

		// On that page already. This happens when we redirect but the browser keeps the POST data with 307 redir.
		if ($req_url == $future_redirect_url_web_path) {
			return;
		}

		$pg = QS_Site_App_Page::getInstance();

		if ($pg->hasPage($url)) { // is this a short page
			$url = $pg->getPage( $url );
		} else {
			$url = $pg->makeAbsUrl($url);
		}

		if (!is_numeric($force) && !is_bool($force) && is_string($force) && $force != $url) {
			$url = add_query_arg('redirect_to', $force, $url);
		}

		$start_url = substr($url, 0, 20);

		// if the main url is encoded then decode it.
		if ( (stripos($start_url, urlencode('http://')) !== false)
		     || (stripos($start_url, urlencode('https://')) !== false)
		     || (stripos($url, '&amp;') !== false) // still stuff to decode? &
		     || (stripos($url, '#038;') !== false) // still stuff to decode? &
		) {
			$url = urldecode($url);
			$url = wp_specialchars_decode($url);
		}

		if ( headers_sent() ) { // if we encode it twice data won't be transferred.
			$url = wp_sanitize_redirect($url); // the wp_safe redir does this
			echo sprintf('<meta http-equiv="refresh" content="0;URL=\'%s\'" />', $url); // jic
			echo sprintf('<script>window.parent.location="%s";</script>', $url);
		} elseif ($force & self::REDIRECT_EXTERNAL_SITE) {
			wp_redirect( $url, 302 );
		} else {
			wp_safe_redirect($url, 302);
		}

		exit;
	}

	/**
	 * @return string
	 */
	public function getRequestUrl() {
		$req_url = empty($this->server_env['REQUEST_URI']) ? '' : $this->server_env['REQUEST_URI'];
		return $req_url;
	}

	/**
	 * returns URL without any params
	 * @return string
	 */
	public function getCleanRequestUrl() {
		$clean_url = $this->getRequestUrl();

		if (strpos($clean_url, '?') !== false) {
			$clean_url = preg_replace( '#\?.*#si', '', $clean_url );
		}

		return $clean_url;
	}

	/**
	 * Quick method for checking if we're on a given page.
	 * Supports regex pipe to check multiple pages.
	 * @return string
	 */
	public function requestUrlMatches($page) {
		$req_url = $this->getRequestUrl();

		if (stripos($req_url, $page) !== false) {
			return true;
		}

		$page = str_replace('|', '__PIPE_ESC__', $page);
		$regex = '#' . preg_quote($page, '#') . '#si';
		$regex = str_replace('__PIPE_ESC__', '|', $regex);
		$match = preg_match($regex, $req_url);
		return $match;
	}

	/**
	 * checks if the current page is our /login or wp login page.
	 * @return bool
	 */
	public function isLoginPage($flags = 0) {
		$is_on_login_page = $this->requestUrlMatches('/login') || $this->requestUrlMatches('/wp-login.php');
		return $is_on_login_page;
	}

	/**
	 * Quick method for checking if we're on a the home page. is_home() or is_front_page() may not always work
	 * @return bool
	 */
	public function isHomePage($flags = 0) {
		$req_script = empty($this->server_env['PHP_SELF']) ? '/' : $this->server_env['PHP_SELF'];
		$site_web_path = dirname($req_script);
		$req_url = $this->getRequestUrl();
		$clean_url = $req_url;
		$clean_url = preg_replace('#^' . preg_quote($site_web_path, '#') . '#si', '', $clean_url);

		if (strpos($clean_url, '?') !== false) {
			$clean_url = preg_replace( '#\?.*#si', '', $clean_url );
		}

		if (stripos($clean_url, '.php') !== false) {
			$clean_url = preg_replace( '#\.php#si', '', $clean_url );
		}

		$clean_url = str_replace('//', '/', $clean_url);
		$clean_url = trim($clean_url, '/');

		if (empty($clean_url)) {
			return true;
		}

		if ($flags) { // let's do this check only when necessary.
			if ( is_front_page() || is_home() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Quick method for checking if we're on a given page.
	 * @return string
	 */
	public function getHost() {
		$host = '';

		if (!empty($this->server_env['SERVER_NAME'])) {
			$host = $this->server_env['SERVER_NAME'];
		} elseif (!empty($this->server_env['HTTP_HOST'])) {
			$host = $this->server_env['HTTP_HOST'];
			$host = strip_tags($host);
			$host = trim($host);
		}

		return $host;
	}

	/**
	 * Get user agent.
	 * @return string
	 */
	public function getUserAgent() {
		$user_agent = '';

		if (!empty($this->server_env['HTTP_USER_AGENT'])) {
			$user_agent = $this->server_env['HTTP_USER_AGENT'];
			$user_agent = strip_tags($user_agent);
			$user_agent = trim($user_agent);
		}

		return $user_agent;
	}

	/**
	 * Quick method for checking if the host matches something.
	 * @param string $page
	 * @return bool
	 */
	public function hostMatches($page) {
		$host = $this->getHost();

		if (empty($host)) {
			return false;
		}

		if (stripos($host, $page) !== false) {
			return true;
		}

		$regex = '#' . preg_quote($page, '#') . '#si';
		$match = preg_match($regex, $host);

		return $match;
	}

	/**
	 * @return bool
	 */
	public function is_public_side() {
		$req_url = $this->getRequestUrl();

		// consider all ajax public side.
		if (defined('DOING_AJAX') && DOING_AJAX) {
			return 1;
		}

		if ( ! preg_match( '#/wp-admin/#si', $req_url ) ) {
			return 1;
		}

		return 0;
	}

	/**
	 * @return bool
	 */
	public function is_admin_side() {
		return !$this->is_public_side();
	}

	/**
	 * @return bool
	 */
	public function isAjax() {
		if (function_exists('wp_doing_ajax')) {
			return wp_doing_ajax();
		}

		$yes = defined( 'DOING_AJAX' ) && DOING_AJAX;

		if ($yes) {
			return true;
		}

		$yes = ! empty( $this->server_env['HTTP_X_REQUESTED_WITH'] ) && strcasecmp( $this->server_env['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0;

		return $yes;
	}

	/**
	 * @return string
	 */
	public function getState($key = 'default') {
		return isset($this->state[$key]) ? $this->state[$key] : '';
	}

	/**
	 * @param string $key
	 * @param array $state
	 */
	public function setState( $key, $val ) {
		$this->state[$key] = $val;
	}


	public function __set($name, $value) {
		if (property_exists($this, $name)) {
			$this->$name = $value;
		} else {
			$this->data[$name] = $value;
		}
	}

	/**
	 * Returns member data or a key from data. It's easier e.g. $data_res->output
	 * @param string $name
	 * @return mixed|null
	 */
	public function __get($name) {
		if (property_exists($this, $name) && isset($this->$name)) {
			return $this->$name;
		}

		if (isset($this->data[$name])) {
			return $this->data[$name];
		}

		return null;
	}

	/**
	 * Checks if a data property exists
	 * @param string $key Property key
	 */
	public function __isset($key) {
		return !is_null($this->__get($key));
	}

	/**
	 * @param array $struct
	 */
	public function json($struct = []) {
		$default_struct = [
			'status' => false,
			'msg' => '',
			'data' => [],
		];

		$struct = array_replace_recursive($default_struct, (array) $struct);
		$struct['status'] = (bool) $struct['status'];

		nocache_headers();

		// Different header is required for ajax and jsonp
		// see https://gist.github.com/cowboy/1200708
		$callback = !empty($_REQUEST['callback'])
			? preg_replace('/[^\w\$]/si', '', $this->sanitizeData($_REQUEST['callback']))
			: false;

		if (!headers_sent()) {
			header('Access-Control-Allow-Origin: *'); // safe? smart? to allow access from anywhere?
			header('Access-Control-Allow-Methods: GET, POST');
			header("Access-Control-Allow-Headers: X-Requested-With");

			if (defined('QS_SITE_LIVE_ENV') && QS_SITE_LIVE_ENV) { // debugger doesn't start when it's app/js content type
				header( 'Content-Type: ' . ( $callback ? 'application/javascript' : 'application/json' ) . ';charset=UTF-8' );
			}
		}

		// For some odd reason converting result obj to an array leaves the expected system keys in the array ?!?
		foreach ($struct as $key => $val) {
			if (stripos($key, 'app_result') !== false) {
				unset($struct[$key]);
			}
		}

		$struct['status'] = !empty($struct['status']); // force bool

		// We need to convert all the fields to string so we can parse them correctly in a go cli app that
		// has a more string format of the values.
		$struct = QS_Site_App_String_Util::toStringVal($struct);

		// Let's convert the status to bool
		$struct['status'] = !empty($struct['status']);

		if (empty($struct['code'])) {
			unset($struct['code']);
		}

		echo ($callback ? $callback . '(' : '') . json_encode($struct, JSON_PRETTY_PRINT) . ($callback ? ')' : '');
		exit;
	}

	public static $curl_options = array(
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9) Gecko/2008052906 Firefox/99.0',
		CURLOPT_AUTOREFERER => true,
		CURLOPT_COOKIEFILE => '',
		CURLOPT_FORBID_REUSE => true,
		CURLOPT_FRESH_CONNECT => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 3,


//        CURLOPT_SSL_VERIFYPEER => false,
//        CURLOPT_SSL_VERIFYHOST => false,

		// https://stackoverflow.com/questions/34243096/php-curl-ssl-cipher-suite-order
		//CURLOPT_SSL_CIPHER_LIST => 'ECDHE-RSA-AES128-GCM-SHA256,ECDHE-ECDSA-AES128-SHA,TLSv1',

		CURLOPT_TIMEOUT => 20,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_FAILONERROR => true,
	);

	/**
	 * Check if the url is OK ... checking for 200 status
	 * @see http://stackoverflow.com/questions/6136022/script-to-get-the-http-status-code-of-a-list-of-urls
	 *
	 * usage: Orbisius_SEO_Editor_Request::checkUrl();
	 * @param string $url
	 * @return Orbisius_SEO_Editor_Result
	 */
	public function checkUrl($url, $params = []) {
		$res_obj = new Orbisius_SEO_Editor_Result();
		$success_http_codes = array(200, 301);

		// this is the linux version
		// curl -o /dev/null --silent --head --write-out '%{http_code}\n'
		$curl_opts = self::$curl_options;

		$curl_opts[CURLOPT_TIMEOUT] = 30;
		$curl_opts[CURLOPT_CONNECTTIMEOUT] = 10;

		$ch = curl_init();
		curl_setopt_array($ch, $curl_opts);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_REFERER, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'OrbisiusSiteChecker');
		//curl_setopt($ch, CURLOPT_NOBODY, true);

		$data = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // we only care about that status code here

		$res_obj->msg = curl_error($ch);
		$res_obj->status(!empty($data) && in_array($code, $success_http_codes));
		$res_obj->data('status_code', $code);
		$res_obj->data('status_error_code', curl_errno($ch));
		$res_obj->data('buffer', $data);

		curl_close($ch);

		return $res_obj;
	}

	/**
	 *  An example CORS-compliant method.  It will allow any GET, POST, or OPTIONS requests from any
	 *  origin.
	 *
	 *  In a production environment, you probably want to be more restrictive, but this gives you
	 *  the general idea of what is involved.  For the nitty-gritty low-down, read:
	 *
	 *  - https://developer.mozilla.org/en/HTTP_access_control
	 *  - https://fetch.spec.whatwg.org/#http-cors-protocol
	 * - https://stackoverflow.com/questions/8719276/cross-origin-request-headerscors-with-php-headers
	 *
	 */
	public function sendCORS() {
		if ( headers_sent() ) {
			return;
		}

		// Allow from any origin
		if ( isset( $this->server_env['HTTP_ORIGIN'] ) ) {
			// Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
			// you want to allow, and if so:
			header( "Access-Control-Allow-Origin: {$this->server_env['HTTP_ORIGIN']}" );
			header( 'Access-Control-Allow-Credentials: true' );
			header( "Access-Control-Allow-Headers: X-Requested-With, token, Content-Type" );
			header( 'Access-Control-Max-Age: 86400' );    // cache for 1 day
		}

		// Access-Control headers are received during OPTIONS requests
		if ( $this->server_env['REQUEST_METHOD'] == 'OPTIONS' ) {
			// may also be using PUT, PATCH, HEAD etc
			if ( isset( $this->server_env['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ) ) {
				header( "Access-Control-Allow-Methods: GET, POST, OPTIONS" );
			}

			if ( isset( $this->server_env['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ) ) {
				header( "Access-Control-Allow-Headers: {$this->server_env['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}" );
			}

			exit(0);
		}
	}

	/**
	 * This is a flash message. It can be set once and pulled once.
	 * It's useful when a page sets a message or obj and then redirects
	 * to another page which can show the message.
	 * @param string $msg
	 */
	public function message($res_obj = null) {
		$res = new Orbisius_SEO_Editor_Result();

		if (is_null($res_obj)) { // getting the message
			unset($_SESSION['qs_site_app_flash_message']);
			return  $res;
		}

		$_SESSION['qs_site_app_flash_message'] = $res_obj;
		return $res_obj;
	}

	/**
	 * Orbisius_SEO_Editor_Request::addQueryParam();
	 * @param string|array $key
	 * @param string $val
	 * @param string $url
	 * @return string
	 */
	public static function addQueryParam($key, $val, $url = '') {
		$keys = [];

		if (is_array($key)) {
			if (empty($url) && !empty($val)) {
				$url = $val;
			}
			$keys = $key;
		} elseif (is_scalar($key)) {
			$keys = [ $key => $val ];
		} else {
			return '';
		}

		$req_obj = self::getInstance();
		$server_env = $req_obj->getServerEnv();

		if (empty($url) && !empty($server_env['REQUEST_URI'])) {
			$url = $server_env['REQUEST_URI'];
		}

		$url = strip_tags( $url );
		$url = trim( $url );
		$url = urldecode( $url );

		// Remove any existing keys from the url
		foreach ($keys as $query_key => $query_val) {
			if (strpos($url, $query_key) === false) {
				continue;
			}

			$url = preg_replace('#([?&])' . preg_quote($query_key, '#') . '=[^&]*#si', '${1}', $url);
			$url = trim($url, '?&');
		}

		if (strpos($url, '?') === false) {
			$sep = '?';
		} else {
			$sep = '&';
		}

		$url .= $sep . http_build_query( $keys );

		// if there are fields with empty values e.g. ?debug we'll remove the = sign
		$url = str_replace('=&', '&', $url);
		$url = trim($url, '=');

		return $url;
	}

	/**
	 * Orbisius_SEO_Editor_Request::removeQueryParam();
	 * @param string|array $key
	 * @param string $url
	 * @return string
	 */
	public static function removeQueryParam($key, $url = '') {
		$req_obj = self::getInstance();
		$server_env = $req_obj->getServerEnv();

		$keys = (array) $key;

		if (empty($url) && !empty($server_env['REQUEST_URI'])) {
			$url = $server_env['REQUEST_URI'];
		}

		$url = strip_tags( $url );
		$url = trim( $url );
		$url = urldecode( $url );

		// Remove any existing keys from the url
		foreach ($keys as $query_key) {
			if (strpos($url, $query_key) === false) {
				continue;
			}

			$url = preg_replace('#([?&])' . preg_quote($query_key, '#') . '(=[^&]*|[^&]*)#si', '${1}', $url);
			$url = trim($url, '?&');
		}

		return $url;
	}

	/**
	 * @return bool
	 */
	public function isPluginRequest() : bool {
		if ($this->has('orbisius_seo_editor_search')) {
			return true;
		}

		$page = $this->page;
		$action = $this->action; // ajax

		if (empty($page) && empty($action)) {
			return false;
		}

		$searched_text = ORBISIUS_SEO_EDITOR_BASE_PLUGIN;
		$searched_text = basename($searched_text);
		$searched_text = str_replace( [ ' ', "\t", '-', '.php', ], '_', $searched_text );
		$searched_text = strtolower($searched_text);
		$searched_text = trim($searched_text, '_');

		if (empty($page)) {
			if (!empty($_REQUEST['orbisius_seo_editor_csv_export'])) {
				$page = 'orbisius_seo_editor_fake_for_export_import';
			} else {
				$page = $action;
			}
		}

		if (empty($page)) { // still empty?
			return false;
		}

		if (!empty($page)) {
			$page_fmt = $page;
			$page_fmt = basename( $page_fmt ); // could be plugin-dir/plugin-name.php

			// When we combine these variables we'll just need to do one search
			if ( ! empty( $action ) != $page_fmt ) {
				$page_fmt .= $action;
			}

			$page_fmt = str_replace( '-', '_', $page_fmt );
		}

		return (strpos($page_fmt, $searched_text) !== false);
	}

	/**
	 * @return array
	 */
	public function getServerEnv(): array {
		return $this->server_env;
	}
}
