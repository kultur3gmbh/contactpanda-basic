<?php

defined( 'ABSPATH' ) || exit;

/** Public GitHub updater included only in the GitHub distribution package. */
final class ContactPanda_GitHub_Updater {
	private const API = 'https://api.github.com/repos/kultur3gmbh/contactpanda-basic';
	private const CACHE = 'contactpanda_github_release';
	private $plugin;
	private $version;
	private $install_release;

	public function __construct( $plugin, $version ) {
		$this->plugin  = $plugin;
		$this->version = $version;
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'updates' ) );
		add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'source' ), 10, 4 );
		add_action( 'admin_init', array( $this, 'maybe_clear_forced_check_cache' ), 1 );
		add_action( 'upgrader_process_complete', array( $this, 'clear' ), 10, 2 );
	}

	private function error( $message ) {
		return new WP_Error( 'contactpanda_github_update', $message );
	}

	private function asset_url( $id ) {
		return self::API . '/releases/assets/' . absint( $id );
	}

	private function request( $url, $binary = false, $filename = '' ) {
		if ( 0 !== strpos( $url, self::API . '/releases/' ) ) {
			return $this->error( __( 'The update source is invalid.', 'contactpanda' ) );
		}
		$headers = array(
			'Accept'               => $binary ? 'application/octet-stream' : 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		for ( $redirect = 0; $redirect < 4; $redirect++ ) {
			$args = array( 'timeout' => $filename ? 120 : 15, 'redirection' => 0, 'sslverify' => true, 'headers' => $headers );
			if ( $filename ) {
				$args['stream']   = true;
				$args['filename'] = $filename;
			}
			$response = wp_safe_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $this->error( __( 'The update service could not be reached.', 'contactpanda' ) );
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$next  = wp_remote_retrieve_header( $response, 'location' );
				$parts = is_string( $next ) ? wp_parse_url( $next ) : false;
				$hosts = array( 'release-assets.githubusercontent.com', 'objects.githubusercontent.com', 'github-releases.githubusercontent.com' );
				if ( ! $binary || ! $parts || 'https' !== ( $parts['scheme'] ?? '' ) || ! in_array( $parts['host'] ?? '', $hosts, true ) ) {
					return $this->error( __( 'The update download is invalid.', 'contactpanda' ) );
				}
				$url = $next;
				continue;
			}
			return 200 === $code ? $response : $this->error( __( 'No valid published update was found.', 'contactpanda' ) );
		}
		return $this->error( __( 'The update download could not be followed safely.', 'contactpanda' ) );
	}

	public function release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE );
			if ( is_array( $cached ) ) return $cached;
		}
		$response = $this->request( self::API . '/releases/latest' );
		if ( is_wp_error( $response ) ) return $response;
		$release = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = is_array( $release ) ? preg_replace( '/^v/', '', (string) ( $release['tag_name'] ?? '' ) ) : '';
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/D', $version ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
			return $this->error( __( 'The published update metadata is invalid.', 'contactpanda' ) );
		}
		$assets = array();
		foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
			if ( 'uploaded' === ( $asset['state'] ?? '' ) && ! empty( $asset['id'] ) && ! empty( $asset['name'] ) ) $assets[ $asset['name'] ] = $asset;
		}
		$zip      = $assets[ 'contactpanda-basic-' . $version . '.zip' ] ?? null;
		$manifest = $assets['contactpanda-update.json'] ?? null;
		if ( ! $zip || ! $manifest || empty( $zip['size'] ) || $zip['size'] > 100 * 1024 * 1024 ) {
			return $this->error( __( 'The update package is incomplete.', 'contactpanda' ) );
		}
		$response = $this->request( $this->asset_url( $manifest['id'] ), true );
		if ( is_wp_error( $response ) ) return $response;
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || 'contactpanda' !== ( $data['slug'] ?? '' ) || $version !== ( $data['version'] ?? '' ) || ! preg_match( '/^[a-f0-9]{64}$/D', (string) ( $data['sha256'] ?? '' ) ) ) {
			return $this->error( __( 'The update manifest is invalid.', 'contactpanda' ) );
		}
		$result = array(
			'version'      => $version,
			'requires'     => (string) ( $data['requires'] ?? '6.4' ),
			'requires_php' => (string) ( $data['requires_php'] ?? '7.4' ),
			'tested'       => (string) ( $data['tested'] ?? '' ),
			'sha256'      => $data['sha256'],
			'asset_id'    => absint( $zip['id'] ),
			'size'        => absint( $zip['size'] ),
		);
		if ( version_compare( $version, $this->version, '>' ) ) set_site_transient( self::CACHE, $result, 30 * MINUTE_IN_SECONDS );
		return $result;
	}

	private function icons() {
		$url = esc_url_raw( CONTACTPANDA_URL . 'assets/images/contactpanda-basic-dashboard-simple.png' );
		return array( '1x' => $url, '2x' => $url );
	}

	private function compatible( $release ) {
		global $wp_version;
		return is_array( $release ) && version_compare( $wp_version, $release['requires'], '>=' ) && version_compare( PHP_VERSION, $release['requires_php'], '>=' );
	}

	public function updates( $transient ) {
		if ( ! is_object( $transient ) ) return $transient;
		unset( $transient->response[ $this->plugin ], $transient->no_update[ $this->plugin ] );
		$release = $this->release();
		if ( is_wp_error( $release ) ) return $transient;
		$item = (object) array(
			'id' => 'https://github.com/kultur3gmbh/contactpanda-basic', 'slug' => 'contactpanda', 'plugin' => $this->plugin,
			'icons' => $this->icons(), 'new_version' => $release['version'], 'url' => 'https://github.com/kultur3gmbh/contactpanda-basic/releases',
			'package' => $this->compatible( $release ) ? $this->asset_url( $release['asset_id'] ) : '',
			'requires' => $release['requires'], 'requires_php' => $release['requires_php'], 'tested' => $release['tested'],
		);
		$key = version_compare( $release['version'], $transient->checked[ $this->plugin ] ?? $this->version, '>' ) ? 'response' : 'no_update';
		if ( ! isset( $transient->{$key} ) || ! is_array( $transient->{$key} ) ) $transient->{$key} = array();
		$transient->{$key}[ $this->plugin ] = $item;
		return $transient;
	}

	public function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'contactpanda' !== ( $args->slug ?? '' ) ) return $result;
		$release = $this->release();
		if ( is_wp_error( $release ) ) return $release;
		return (object) array( 'icons' => $this->icons(), 'name' => 'ContactPanda Basic', 'slug' => 'contactpanda', 'version' => $release['version'], 'author' => 'Kultur3 GmbH', 'requires' => $release['requires'], 'requires_php' => $release['requires_php'], 'tested' => $release['tested'], 'download_link' => $this->asset_url( $release['asset_id'] ) );
	}

	public function download( $reply, $package, $upgrader, $extra ) {
		if ( ! is_string( $package ) || 0 !== strpos( $package, self::API . '/releases/assets/' ) ) return $reply;
		if ( ! current_user_can( 'update_plugins' ) && ! wp_doing_cron() ) return $this->error( __( 'You are not allowed to install updates.', 'contactpanda' ) );
		$release = $this->release( true );
		if ( is_wp_error( $release ) || ! $this->compatible( $release ) || $package !== $this->asset_url( $release['asset_id'] ) ) return $this->error( __( 'The update package could not be verified.', 'contactpanda' ) );
		$file = wp_tempnam( 'contactpanda.zip' );
		if ( ! $file ) return $this->error( __( 'The update package could not be stored temporarily.', 'contactpanda' ) );
		$response = $this->request( $package, true, $file );
		if ( is_wp_error( $response ) || ! is_file( $file ) || filesize( $file ) !== $release['size'] || ! hash_equals( $release['sha256'], hash_file( 'sha256', $file ) ) ) {
			wp_delete_file( $file );
			return $this->error( __( 'The update package failed its integrity check.', 'contactpanda' ) );
		}
		$this->install_release = $release;
		return $file;
	}

	public function source( $source, $remote, $upgrader, $extra ) {
		$plugins = array_merge( (array) ( $extra['plugins'] ?? array() ), empty( $extra['plugin'] ) ? array() : array( $extra['plugin'] ) );
		if ( ! $this->install_release || is_wp_error( $source ) || ! in_array( $this->plugin, $plugins, true ) ) return $source;
		global $wp_filesystem;
		if ( 'contactpanda' !== basename( untrailingslashit( $source ) ) ) return $this->error( __( 'The update archive has an invalid folder.', 'contactpanda' ) );
		$entries = $wp_filesystem->dirlist( $remote );
		if ( ! is_array( $entries ) || array_keys( $entries ) !== array( 'contactpanda' ) ) return $this->error( __( 'The update archive has an invalid structure.', 'contactpanda' ) );
		$main = $wp_filesystem->get_contents( trailingslashit( $source ) . 'contactpanda.php' );
		if ( ! is_string( $main ) || ! preg_match( '/^[ \t\/*#@]*Version:\s*([^\r\n]*)/mi', substr( $main, 0, 8192 ), $match ) || trim( $match[1] ) !== $this->install_release['version'] ) return $this->error( __( 'The update archive version is invalid.', 'contactpanda' ) );
		return $source;
	}

	public function maybe_clear_forced_check_cache() {
		if ( current_user_can( 'update_plugins' ) && ! empty( $_GET['force-check'] ) ) $this->clear_update_cache(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only core refresh flag.
	}

	private function clear_update_cache() {
		delete_site_transient( self::CACHE );
		delete_site_transient( 'update_plugins' );
	}

	public function clear( $upgrader, $extra ) {
		if ( in_array( $this->plugin, (array) ( $extra['plugins'] ?? array( $extra['plugin'] ?? '' ) ), true ) ) $this->clear_update_cache();
	}
}
