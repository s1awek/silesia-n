<?php

/**
 * Manages email provider class instances.
 */
class ET_Core_API_Email_Providers {

	private static $_instance;

	protected static $_fields;
	protected static $_metadata;
	protected static $_names;
	protected static $_names_by_slug;
	protected static $_name_field_only = array();
	protected static $_slugs;

	public static $providers = array();

	public function __construct() {
		if ( null === self::$_metadata ) {
			$this->_initialize();
		}
	}

	protected function _initialize() {
		self::$_metadata       = et_core_get_components_metadata();
		$third_party_providers = et_core_get_third_party_components( 'api/email' );

		$load_fields = is_admin() || et_core_is_fb_enabled() || isset( $_GET['et_fb'] );
		$all_names   = array(
			'official'    => self::$_metadata['groups']['api/email']['members'],
			'third-party' => array_keys( $third_party_providers ),
		);

		$_names_by_slug = array();

		foreach ( $all_names as $provider_type => $provider_names ) {
			$_names_by_slug[ $provider_type ] = array();

			foreach ( $provider_names as $provider_name ) {
				if ( false !== strpos( $provider_name, 'Provider' ) ) {
					continue;
				}

				if ( 'official' === $provider_type ) {
					$class_name    = self::$_metadata[ $provider_name ];
					$provider_slug = self::$_metadata[ $class_name ]['slug'];
					$provider      = $load_fields ? new $class_name( 'ET_Core', '' ) : null;
				} else {
					$provider      = $third_party_providers[ $provider_name ];
					$provider_slug = is_object( $provider ) ? $provider->slug : '';
				}

				if ( ! $provider_slug ) {
					continue;
				}

				$_names_by_slug[ $provider_type ][ $provider_slug ] = $provider_name;

				if ( $load_fields && is_object( $provider ) ) {
					self::$_fields[ $provider_slug ] = $provider->get_account_fields();

					if ( $provider->name_field_only ) {
						self::$_name_field_only[ $provider_slug ] = $provider_name;
					}
				}
			}
		}

		/**
		 * Filters the enabled email providers.
		 *
		 * @param array[] {
		 *
		 *     @type string[] $provider_type {
		 *
		 *         @type string $slug Provider name
		 *     }
		 * }
		 */
		self::$_names_by_slug = apply_filters( 'et_core_api_email_enabled_providers', $_names_by_slug );

		foreach ( array_keys( $all_names ) as $provider_type ) {
			self::$_names[ $provider_type ] = array_values( self::$_names_by_slug[ $provider_type ] );
			self::$_slugs[ $provider_type ] = array_keys( self::$_names_by_slug[ $provider_type ] );
		}

	}

	/**
	 * Returns the email provider accounts array from core.
	 *
	 * @return array|mixed
	 */
	public function accounts() {
		return ET_Core_API_Email_Provider::get_accounts();
	}

	/**
	 * @see {@link \ET_Core_API_Email_Provider::account_exists()}
	 */
	public function account_exists( $provider, $account_name ) {
		return ET_Core_API_Email_Provider::account_exists( $provider, $account_name );
	}

	public function account_fields( $provider = 'all' ) {
		if ( 'all' !== $provider ) {
			return isset( self::$_fields[ $provider ] ) ? self::$_fields[ $provider ] : array();
		}

		return self::$_fields;
	}

	/**
	 * Get class instance for a provider. Instance will be created if necessary.
	 *
	 * @param string $name_or_slug The provider's name or slug.
	 * @param string $account_name The identifier for the desired account with the provider.
	 * @param string $owner        The owner for the instance.
	 *
	 * @return bool|ET_Core_API_Email_Provider The provider instance or `false` if not found.
	 */
	public function get( $name_or_slug, $account_name, $owner = 'ET_Core' ) {
		$name_or_slug = str_replace( ' ', '', $name_or_slug );
		$is_official  = isset( self::$_metadata[ $name_or_slug ] );

		if ( ! $is_official && ! $this->is_third_party( $name_or_slug ) ) {
			return false;
		}

		// Make sure we have the component name
		if ( $is_official ) {
			$class_name = self::$_metadata[ $name_or_slug ];
			$name       = self::$_metadata[ $class_name ]['name'];
		} else {
			$components = et_core_get_third_party_components( 'api/email' );

			if ( ! $name = array_search( $name_or_slug, self::$_names_by_slug['third-party'] ) ) {
				$name = $name_or_slug;
			}
		}

		if ( ! isset( self::$providers[ $name ][ $owner ] ) ) {
			self::$providers[ $name ][ $owner ] = $is_official
				? new $class_name( $owner, $account_name )
				: $components[ $name ];
		}

		return self::$providers[ $name ][ $owner ];
	}

	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	public function is_third_party( $name_or_slug ) {
		$is_third_party = in_array( $name_or_slug, self::$_names['third-party'] );

		return $is_third_party ? $is_third_party : in_array( $name_or_slug, self::$_slugs['third-party'] );
	}

	/**
	 * Returns the names of available providers. List can optionally be filtered.
	 *
	 * @param string $type The component type to include ('official'|'third-party'|'all'). Default is 'all'.
	 *
	 * @return array
	 */
	public function names( $type = 'all' ) {
		if ( 'all' === $type ) {
			$names = array_merge( self::$_names['third-party'], self::$_names['official'] );
		} else {
			$names = self::$_names[ $type ];
		}

		return $names;
	}

	/**
	 * Returns an array mapping the slugs of available providers to their names. List can optionally be filtered.
	 *
	 * @param string $type   The component type to include ('official'|'third-party'|'all'). Default is 'all'.
	 * @param string $filter Optionally filter the list by a condition. Accepts 'name_field_only'.
	 *
	 * @return array
	 */
	public function names_by_slug( $type = 'all', $filter = '' ) {
		if ( 'all' === $type ) {
			$names_by_slug = array_merge( self::$_names_by_slug['third-party'], self::$_names_by_slug['official'] );
		} else {
			$names_by_slug = self::$_names_by_slug[ $type ];
		}

		if ( 'name_field_only' === $filter ) {
			$names_by_slug = self::$_name_field_only;
		}

		return $names_by_slug;
	}

	/**
	 * @see {@link \ET_Core_API_Email_Provider::remove_account()}
	 */
	public function remove_account( $provider, $account_name ) {
		ET_Core_API_Email_Provider::remove_account( $provider, $account_name );
	}

	/**
	 * @see {@link \ET_Core_API_Email_Provider::update_account()}
	 */
	public function update_account( $provider, $account, $data ) {
		ET_Core_API_Email_Provider::update_account( $provider, $account, $data );
	}
}
