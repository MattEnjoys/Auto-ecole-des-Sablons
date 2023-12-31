<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Hustle_Campaignmonitor_API class
 *
 * @package Hustle
 */

/**
 * Class Hustle_Campaignmonitor
 */
class Hustle_Campaignmonitor_API {

	/**
	 * Campaignmonitor api key
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * CampaignMonitor API VERSION
	 *
	 * @since 4.0.2
	 */
	const HUSTLE_CAMPAIGNMONITOR_API_VERSION = '1.0';

	/**
	 * Base API Endpoint
	 *
	 * @var string
	 */
	private $endpoint = 'https://api.createsend.com/api/v3.2/';

	/**
	 * Activecampaign Provider Instance
	 *
	 * @since 3.0.5
	 *
	 * @var self|null
	 */
	protected static $instances;

	/**
	 * Hustle_Campaignmonitor constructor.
	 *
	 * @since 4.0.2
	 *
	 * @param string $api_key Api key.
	 *
	 * @throws Exception Missing required API Key.
	 */
	private function __construct( $api_key ) {
		// prerequisites.
		if ( ! $api_key ) {
			throw new Exception( __( 'Missing required API Key', 'hustle' ) );
		}

		$this->api_key = $api_key;
	}


	/**
	 * Get singleton
	 *
	 * @since 4.0.2
	 *
	 * @param string $api_key Api key.
	 *
	 * @return Hustle_Campaignmonitor|null
	 */
	public static function boot( $api_key ) {
		if ( ! isset( self::$instances[ md5( $api_key ) ] ) ) {
			self::$instances[ md5( $api_key ) ] = new static( $api_key );
		}

		return self::$instances[ md5( $api_key ) ];
	}

	/**
	 * Add custom user agent on request
	 *
	 * @since 4.0.2
	 *
	 * @param string $user_agent User agent.
	 *
	 * @return string
	 */
	public function filter_user_agent( $user_agent ) {
		$user_agent .= ' HustleCampaignmonitor/' . self::HUSTLE_CAMPAIGNMONITOR_API_VERSION;

		/**
		 * Filter user agent to be used by campaignmonitor api
		 *
		 * @since 4.0.2
		 *
		 * @param string $user_agent current user agent
		 */
		$user_agent = apply_filters( 'hustle_campaignmonitor_api_user_agent', $user_agent );

		return $user_agent;
	}

	/**
	 * HTTP Request
	 *
	 * @since 4.0.2
	 *
	 * @param string $path Path.
	 * @param string $verb Verb.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 * @throws Exception Failed to process request.
	 */
	private function request( $path, $verb = 'GET', $args = array() ) {
		// Adding extra user agent for wp remote request.
		add_filter( 'http_headers_useragent', array( $this, 'filter_user_agent' ) );

		$url = trailingslashit( $this->endpoint ) . $path;

		/**
		 * Filter campaignmonitor url to be used on sending api request
		 *
		 * @since 4.0.2
		 *
		 * @param string $url  full url with scheme
		 * @param string $verb `GET` `POST` `PUT` `DELETE` `PATCH`
		 * @param string $path requested path resource
		 * @param array  $args argument sent to this function
		 */
		$url = apply_filters( 'hustle_campaignmonitor_api_url', $url, $verb, $path, $args );

		$encoded_auth = base64_encode( $this->api_key . ':hustle-no_pass' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$headers      = array(
			'Authorization' => 'Basic ' . $encoded_auth,
		);

		/**
		 * Filter campaignmonitor headers to sent on api request
		 *
		 * @since 4.0.2
		 *
		 * @param array  $headers
		 * @param string $verb `GET` `POST` `PUT` `DELETE` `PATCH`
		 * @param string $path requested path resource
		 * @param array  $args argument sent to this function
		 */
		$headers = apply_filters( 'hustle_campaignmonitor_api_request_headers', $headers, $verb, $path, $args );

		$_args = array(
			'method'  => $verb,
			'headers' => $headers,
		);

		$request_data = $args;
		/**
		 * Filter campaignmonitor request data to be used on sending api request
		 *
		 * @since 4.0.2
		 *
		 * @param array  $request_data it will be `http_build_query`-ed when `GET` or `wp_json_encode`-ed otherwise
		 * @param string $verb         `GET` `POST` `PUT` `DELETE` `PATCH`
		 * @param string $path         requested path resource
		 */
		$args = apply_filters( 'hustle_campaignmonitor_api_request_data', $request_data, $verb, $path );

		if ( 'GET' === $verb || 'DELETE' === $verb ) {
			$url .= ( '?' . http_build_query( $args ) );
		} else {
			$_args['body'] = wp_json_encode( $args );
		}

		$res = wp_remote_request( $url, $_args );

		// logging data.
		$utils                     = Hustle_Provider_Utils::get_instance();
		$utils->last_url_request   = $url;
		$utils->last_data_sent     = $_args;
		$utils->last_data_received = $res;

		$wp_response = $res;
		remove_filter( 'http_headers_useragent', array( $this, 'filter_user_agent' ) );

		if ( is_wp_error( $res ) || ! $res ) {
			throw new Exception(
				__( 'Failed to process request, make sure your Webhook URL is correct and your server has internet connection.', 'hustle' )
			);
		}

		if ( isset( $res['response']['code'] ) ) {
			$status_code = $res['response']['code'];
			$msg         = '';
			if ( $status_code >= 400 ) {
				if ( isset( $res['response']['message'] ) ) {
					$msg = $res['response']['message'];
				}

				$body_json = wp_remote_retrieve_body( $res );
				$res_json  = json_decode( $body_json );

				if ( ! is_null( $res_json ) && is_object( $res_json ) && isset( $res_json->Message ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$msg = $res_json->Message;// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				}

				/* translators: error message */
				throw new Exception( sprintf( __( 'Failed to processing request : %s', 'hustle' ), $msg ) );
			}
		}

		$body = wp_remote_retrieve_body( $res );

		// probably silent mode.
		if ( ! empty( $body ) ) {
			$res = json_decode( $body );
		}

		$response = $res;

		/**
		 * Filter campaignmonitor api response returned to addon
		 *
		 * @since 4.0.2
		 *
		 * @param mixed          $response    original wp remote request response or decoded body if available
		 * @param string         $body        original content of http response's body
		 * @param array|WP_Error $wp_response original wp remote request response
		 */
		$res = apply_filters( 'hustle_campaignmonitor_api_response', $response, $body, $wp_response );

		return $res;
	}

	/**
	 * Send data to static webhook campaignmonitor URL
	 *
	 * @since 4.0.2
	 * @param array $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function post_( $args ) {

		return $this->request(
			'',
			'POST',
			$args
		);
	}

	/**
	 * Get Primary Contact
	 *
	 * @since 4.0.2
	 * @param array $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function get_primary_contact( $args = array() ) {
		$default_args = array();

		$args = array_merge( $default_args, $args );

		return $this->request(
			'primarycontact.json',
			'GET',
			$args
		);
	}

	/**
	 * Get Current Data on Campaign Monitor
	 *
	 * @since 4.0.2
	 * @param array $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function get_system_date( $args = array() ) {
		$default_args = array();

		$args = array_merge( $default_args, $args );

		return $this->request(
			'systemdate.json',
			'GET',
			$args
		);
	}

	/**
	 * Get List Detail
	 *
	 * @since 4.0.2
	 *
	 * @param string $list_id List ID.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function get_list( $list_id, $args = array() ) {
		$default_args = array();

		$args = array_merge( $default_args, $args );

		return $this->request(
			'lists/' . rawurlencode( trim( $list_id ) ) . '.json',
			'GET',
			$args
		);
	}

	/**
	 * Get Lists on a Client
	 *
	 * @since 4.0.2
	 *
	 * @param string $client_id Client ID.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function get_client_lists( $client_id, $args = array() ) {
		$default_args = array();

		$args = array_merge( $default_args, $args );

		return $this->request(
			'clients/' . rawurlencode( trim( $client_id ) ) . '/lists.json',
			'GET',
			$args
		);
	}

	/**
	 * Get Clients
	 *
	 * @since 4.0.2
	 * @param array $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function get_clients( $args = array() ) {
		$default_args = array();

		$args = array_merge( $default_args, $args );

		return $this->request(
			'clients.json',
			'GET',
			$args
		);
	}

	/**
	 * Get Client Details
	 *
	 * @since 4.0.2
	 * @param string $client_id Client ID.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function get_client( $client_id, $args = array() ) {
		$default_args = array();

		$args = array_merge( $default_args, $args );

		return $this->request(
			'clients/' . rawurlencode( trim( $client_id ) ) . '.json',
			'GET',
			$args
		);
	}

	/**
	 * Get Custom Fields on Lists
	 *
	 * @since 4.0.2
	 * @param string $list_id List ID.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function get_list_custom_field( $list_id, $args = array() ) {
		$default_args = array();

		$args = array_merge( $default_args, $args );

		return $this->request(
			'lists/' . rawurlencode( trim( $list_id ) ) . '/customfields.json',
			'GET',
			$args
		);
	}

	/**
	 * Add Custom Fields on Lists
	 *
	 * @since 4.0.2
	 * @param string $list_id List ID.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function add_list_custom_field( $list_id, $args = array() ) {
		$default_args = array(
			'VisibleInPreferenceCenter' => true,
		);

		$args = array_merge( $default_args, $args );

		return $this->request(
			'lists/' . rawurlencode( trim( $list_id ) ) . '/customfields.json',
			'POST',
			$args
		);
	}

	/**
	 * Add Subscriber to the list
	 *
	 * @since 4.0.2
	 * @param string $list_id List ID.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function add_subscriber( $list_id, $args = array() ) {
		return $this->request(
			'subscribers/' . rawurlencode( trim( $list_id ) ) . '.json',
			'POST',
			$args
		);
	}

	/**
	 * Update subscriber
	 *
	 * @since 4.0.2
	 * @param string $list_id List ID.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function update_subscriber( $list_id, $args = array() ) {
		return $this->request(
			'subscribers/' . rawurlencode( trim( $list_id ) ) . '.json?email=' . $args['EmailAddress'],
			'PUT',
			$args
		);
	}

	/**
	 * Check if Subscriber exists
	 *
	 * @since 4.0.2
	 * @param string $list_id List ID.
	 * @param string $email_address Email.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function get_subscriber( $list_id, $email_address, $args = array() ) {
		$default_args = array(
			'email' => $email_address,
		);

		$args = array_merge( $default_args, $args );

		return $this->request(
			'subscribers/' . rawurlencode( trim( $list_id ) ) . '.json',
			'GET',
			$args
		);
	}

	/**
	 * Delete subscriber from the list
	 *
	 * @param string $list_id List ID.
	 * @param string $email Email.
	 *
	 * @return bool
	 */
	public function delete_email( $list_id, $email ) {
		$res = $this->request(
			'subscribers/' . rawurlencode( trim( $list_id ) ) . '/unsubscribe.json',
			'POST',
			array(
				'EmailAddress' => $email,
			)
		);

		return ! is_wp_error( $res ) && 'OK' === wp_remote_retrieve_response_message( $res );
	}

	/**
	 * Delete Subscriber from the list
	 *
	 * @since 4.0.2
	 * @param string $list_id List ID.
	 * @param string $email_address Email.
	 * @param array  $args Args.
	 *
	 * @return array|mixed|object
	 */
	public function delete_subscriber( $list_id, $email_address, $args = array() ) {
		$default_args = array(
			'email' => $email_address,
		);

		$args = array_merge( $default_args, $args );

		return $this->request(
			'subscribers/' . rawurlencode( trim( $list_id ) ) . '.json',
			'DELETE',
			$args
		);
	}
}
