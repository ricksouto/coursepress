<?php
/**
 * Class CoursePress_Extension
 *
 * @since 3.0
 * @package CoursePress
 */
class CoursePress_Extension {

	/**
	 * CoursePress_Extension constructor.
	 */
	public function __construct() {
		// Set extensions.
		add_filter( 'coursepress_extensions', array( $this, 'set_extensions' ) );
		// Check for active extensions.
	    $this->active_extensions();
		add_filter( 'coursepress_admin_localize_array', array( $this, 'change_localize_array' ) );
	}

	/**
	 * Add CoursePress Extensions JS strings.
	 *
	 * @since 3.0
	 */
	public function change_localize_array( $localize_array ) {
		$localize_array['text']['extensions'] = array(
			'not_installed' => __( 'This extension is not installed.', 'cp' ),
			'buttons' => array(
				'activate' => __( 'Activate', 'cp' ),
				'deactivate' => __( 'Dectivate', 'cp' ),
			),
			'activating_plugin' => __( 'Try to active plugin... Please wait.', 'cp' ),
			'deactivating_plugin' => __( 'Try to deactive plugin... Please wait.', 'cp' ),
		);
		return $localize_array;
	}

	/**
	 * Load active extension class.
	 *
	 * @global $CoursePress
	 */
	function active_extensions() {
		global $CoursePress;
		$extensions = coursepress_get_setting( 'extensions' );
		$active = array(
			'commerce' => false,
		);
		if ( ! empty( $extensions ) ) {
			foreach ( $extensions as $extension ) {
				switch ( $extension ) {
					case 'marketpress':
						$CoursePress->get_class( 'CoursePress_Extension_MarketPress' );
					break;
					case 'woocommerce':
						$CoursePress->get_class( 'CoursePress_Extension_WooCommerce' );
					break;
				}
				// Set extension type to active
				if ( isset( $extension['type'] ) ) {
					$active[ $extension['type'] ] = true;
				}
			}
		}
		// Load some code for missing extensions - like fix missing
		// shortcodes for commerce.
		if ( false === $active['commerce'] ) {
			$CoursePress->get_class( 'CoursePress_Extension_Commerce' );
		}
	}

	private function get_extensions( $extensions = array() ) {
		global $CoursePress;
		/**
		 * sanitize
		 */
		if ( ! is_array( $extensions ) ) {
			$extensions = array();
		}
		/**
		 * Try to get date from installed plugins.
		 */
		$plugins = get_plugins();
		foreach ( $plugins as $id => $plugin ) {
			switch ( $plugin['Name'] ) {
				case 'MarketPress':
					$extensions['marketpress'] = array(
					'name' => $plugin['Name'],
					'source_info' => sprintf( __( 'From %s', 'cp' ), $plugin['Author'] ),
					'soruce_link' => $plugin['AuthorURI'],
					'file' => $id,
					'link' => $plugin['PluginURI'],
					'description' => $plugin['Description'],
					'version' => $plugin['Version'],
					'type' => 'commerce',
					'is_installed' => true,
					);
				break;
				case 'WooCommerce':
					$extensions['woocommerce'] = array(
					'name' => $plugin['Name'],
					'source_info' => sprintf( __( 'From %s', 'cp' ), $plugin['Author'] ),
					'soruce_link' => $plugin['AuthorURI'],
					'file' => $id,
					'link' => $plugin['PluginURI'],
					'description' => $plugin['Description'],
					'version' => $plugin['Version'],
					'type' => 'commerce',
					'is_installed' => true,
					);
				break;
			}
		}
		/**
		 * Add default data if plugins are not installed.
		 * Plugin: MarketPress
		 */
		if ( ! isset( $extensions['marketpress'] ) ) {
			$extensions['marketpress'] = array(
				'name' => 'MarketPress',
				'source_info' => sprintf( __( 'From %s', 'cp' ), 'WPMU DEV' ),
				'soruce_link' => 'http://premium.wpmudev.org',
				'file' => 'marketpress/marketpress.php',
				'link' => 'https://premium.wpmudev.org/project/e-commerce/',
				'description' => '',
				'version' => '',
				'type' => 'commerce',
				'is_installed' => false,
			);
		}
		/**
		 * Add default data if plugins are not installed.
		 * Plugin: WooCommerce
		 */
		if ( ! isset( $extensions['woocommerce'] ) ) {
			$extensions['woocommerce'] = array(
				'name' => 'WooCommerce',
				'source_info' => sprintf( __( 'Requires %s plugin.', 'cp' ), 'WooCommerce' ),
				'soruce_link' => 'https://woocommerce.com',
				'file' => 'woocommerce/woocommerce.php',
				'link' => 'https://woocommerce.com/',
				'description' => '',
				'version' => '',
				'type' => 'commerce',
				'is_installed' => false,
			);
		}
		/**
		 * Add nonces.
		 */
		foreach ( $extensions as $id => $data ) {
			$nonce_name = $this->get_nonce_name( $id );
			$extensions[ $id ]['nonce'] = wp_create_nonce( $nonce_name );
		}
		/**
		 * get status data
		 */
		foreach ( $extensions as $key => $data ) {
			// Set default values.
			$class_name = $CoursePress->get_class( 'CoursePress_Extension_'.$data['name'] );
			$extensions[ $key ]['class_name'] = $class_name;
			// Check if extension is installed.
			if ( method_exists( $class_name, 'installed' ) ) {
				$extensions[ $key ]['is_installed'] = $class_name->installed();
			}
			// Check if extension is active.
			if ( method_exists( $class_name, 'activated' ) ) {
				$extensions[ $key ]['is_active'] = $class_name->activated();
			}
		}
		return $extensions;
	}

	/**
	 * Set active extensions data details.
	 *
	 * @param array $extensions Extensions data.
	 *
	 * @global $CoursePress
	 *
	 * @return mixed
	 */
	public function set_extensions( $extensions ) {
		$extensions = $this->get_extensions( $extensions );
		// Check status.
		foreach ( $extensions as $key => $data ) {
			// Initialize the extension class, if active.
			if ( $extensions[ $key ]['is_active'] && method_exists( $data['class_name'], 'init' ) ) {
				$data['class_name']->init();
			}
		}
		return $extensions;
	}

	private function get_nonce_name( $name ) {
		return 'coursepress-extension-'.$name;
	}

	public function activate( $extension, $nonce ) {
		$extensions = $this->get_extensions();
		$nonce_name = $this->get_nonce_name( $extension );
		if ( isset( $extensions[ $extension ] ) && wp_verify_nonce( $nonce, $nonce_name ) ) {
			$plugin = $extensions[ $extension ];
			if ( isset( $plugin['is_active'] ) && $plugin['is_active'] ) {
				$error = new WP_Error( 'broke', __( 'Plugin is already active.', 'cp' ) );
				return $error;
			}
			$result = activate_plugin( $plugin['file'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			} else {
				$args = array(
					'message' => __( 'Plugin was successfully activated!', 'cp' ),
					'plugin' => $plugin,
					'extension' => $extension,
				);
				return $args;
			}
		}
		$error = new WP_Error( 'broke', __( 'Plugin can not be activated.', 'cp' ) );
		return $error;
	}

	public function deactivate( $extension, $nonce ) {
		$extensions = $this->get_extensions();
		$nonce_name = $this->get_nonce_name( $extension );
		if ( isset( $extensions[ $extension ] ) && wp_verify_nonce( $nonce, $nonce_name ) ) {
			$plugin = $extensions[ $extension ];
			if ( isset( $plugin['is_active'] ) && $plugin['is_active'] ) {
				$result = deactivate_plugins( array( $plugin['file'] ) );
				if ( is_wp_error( $result ) ) {
					return $result;
				} else {
					$args = array(
						'message' => __( 'Plugin was successfully deactivated!', 'cp' ),
						'plugin' => $plugin,
						'extension' => $extension,
					);
					return $args;
				}
			} else {
				$error = new WP_Error( 'broke', __( 'Plugin is not active.', 'cp' ) );
				return $error;
			}
		}
		$error = new WP_Error( 'broke', __( 'Plugin can not be deactivated.', 'cp' ) );
		return $error;
	}
}
