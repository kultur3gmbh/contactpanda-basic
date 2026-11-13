<?php

defined( 'ABSPATH' ) || exit;

/**
 * Provider-neutral WordPress-user to ContactPanda-contact resolver.
 *
 * An explicit user link always wins. An exact normalized email address may be
 * used only when the contact is not linked to a different user. This service
 * never merges contacts and never changes consent or suppression state.
 */
final class ContactPanda_Identity {
	public static function resolve_user( $user_id, $create = false ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user ) {
			return new WP_Error( 'contactpanda_portal_user_missing', __( 'The WordPress account could not be found.', 'contactpanda' ) );
		}

		$filtered = apply_filters( 'contactpanda_resolve_user_contact', null, $user, (bool) $create );
		if ( null !== $filtered ) {
			return $filtered;
		}

		return ContactPanda_Store::standalone()
			? self::resolve_standalone( $user, $create )
			: self::resolve_groundhogg( $user, $create );
	}

	public static function update_profile( $identity, $data ) {
		if ( ! is_array( $identity ) || empty( $identity['id'] ) ) {
			return new WP_Error( 'contactpanda_portal_contact_missing', __( 'The ContactPanda person could not be resolved.', 'contactpanda' ) );
		}
		$data = self::sanitize_profile( $data );
		if ( 'standalone' === ( $identity['source'] ?? '' ) ) {
			return ContactPanda_Store::update_contact(
				$identity['id'],
				array(
					'first_name'   => $data['first_name'],
					'last_name'    => $data['last_name'],
					'anrede'       => $data['salutation'],
					'titel_vorangestellt' => $data['title_prefix'],
					'titel_nachgestellt'  => $data['title_suffix'],
					'primary_phone'=> $data['phone'],
					'meta'         => array(
						'company'   => $data['company'],
						'address_1' => $data['address_1'],
						'address_2' => $data['address_2'],
						'postcode'  => $data['postcode'],
						'city'      => $data['city'],
						'state'     => $data['state'],
						'country'   => $data['country'],
					),
				)
			);
		}

		if ( empty( $identity['object'] ) || ! is_object( $identity['object'] ) ) {
			return new WP_Error( 'contactpanda_portal_source_unavailable', __( 'The selected contact data source is not available.', 'contactpanda' ) );
		}
		try {
			$contact = $identity['object'];
			if ( method_exists( $contact, 'update' ) ) {
				$contact->update( array( 'first_name' => $data['first_name'], 'last_name' => $data['last_name'] ) );
			}
			if ( method_exists( $contact, 'update_meta' ) ) {
				foreach ( array( 'salutation', 'title_prefix', 'title_suffix', 'phone', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $key ) {
					$meta_key = array( 'salutation' => 'anrede', 'title_prefix' => 'titel_vorangestellt', 'title_suffix' => 'titel_nachgestellt', 'phone' => 'primary_phone' )[ $key ] ?? $key;
					$contact->update_meta( $meta_key, $data[ $key ] );
				}
			}
			return true;
		} catch ( Throwable $error ) {
			return new WP_Error( 'contactpanda_portal_source_update', $error->getMessage() );
		}
	}

	public static function update_email( $identity, $email ) {
		$email = sanitize_email( $email );
		if ( ! $email || empty( $identity['id'] ) ) {
			return false;
		}
		if ( 'standalone' === ( $identity['source'] ?? '' ) ) {
			return ContactPanda_Store::update_contact( $identity['id'], array( 'email' => $email ) );
		}
		try {
			return ! empty( $identity['object'] ) && method_exists( $identity['object'], 'update' )
				? false !== $identity['object']->update( array( 'email' => $email ) )
				: false;
		} catch ( Throwable $error ) {
			return false;
		}
	}

	private static function resolve_standalone( $user, $create ) {
		$contact = ContactPanda_Store::contact_by_user_id( $user->ID );
		if ( $contact ) {
			return self::normalize( $contact, 'standalone' );
		}
		$contact = ContactPanda_Store::contact_by_email( $user->user_email );
		if ( $contact ) {
			if ( ! empty( $contact['user_id'] ) && absint( $contact['user_id'] ) !== absint( $user->ID ) ) {
				return new WP_Error( 'contactpanda_portal_contact_linked', __( 'This email address is already linked to another WordPress account.', 'contactpanda' ) );
			}
			if ( empty( $contact['user_id'] ) && ! ContactPanda_Store::link_user( $contact['id'], $user->ID ) ) {
				return new WP_Error( 'contactpanda_portal_link_failed', __( 'The existing ContactPanda person could not be linked safely.', 'contactpanda' ) );
			}
			return self::normalize( ContactPanda_Store::contact( $contact['id'] ), 'standalone' );
		}
		if ( ! $create ) {
			return self::empty_identity( $user, 'standalone' );
		}
		$id = ContactPanda_Store::create_contact(
			array(
				'user_id'      => $user->ID,
				'email'        => $user->user_email,
				'first_name'   => get_user_meta( $user->ID, 'first_name', true ),
				'last_name'    => get_user_meta( $user->ID, 'last_name', true ),
				'source'       => 'wordpress_account',
				'optin_status' => 'unconfirmed',
			)
		);
		return is_wp_error( $id ) ? $id : self::normalize( ContactPanda_Store::contact( $id ), 'standalone' );
	}

	private static function resolve_groundhogg( $user, $create ) {
		if ( ! class_exists( '\\Groundhogg\\Contact' ) ) {
			return self::empty_identity( $user, 'groundhogg' );
		}
		$contact = false;
		try {
			$by_user = new \Groundhogg\Contact( $user->ID, 'user_id' );
			if ( ! method_exists( $by_user, 'exists' ) || $by_user->exists() ) {
				$contact = $by_user;
			}
		} catch ( Throwable $error ) {
			$contact = false;
		}
		if ( ! $contact && method_exists( 'ContactPanda_Plugin', 'extension_contact_by_email' ) ) {
			$contact = ContactPanda_Plugin::extension_contact_by_email( $user->user_email );
		}
		if ( $contact ) {
			$linked = absint( ContactPanda_Plugin::extension_value( $contact, array( 'user_id', 'get_user_id' ) ) );
			if ( $linked && $linked !== absint( $user->ID ) ) {
				return new WP_Error( 'contactpanda_portal_contact_linked', __( 'This email address is already linked to another WordPress account.', 'contactpanda' ) );
			}

			return self::normalize( $contact, 'groundhogg' );
		}
		// Preview mode never creates or links source-plugin accounts.
		return self::empty_identity( $user, 'groundhogg' );
	}

	private static function normalize( $contact, $source ) {
		$value = static function ( $keys, $default = '' ) use ( $contact ) {
			return ContactPanda_Plugin::extension_value( $contact, (array) $keys, $default );
		};
		$meta = is_array( $contact ) && isset( $contact['meta'] ) ? (array) $contact['meta'] : array();
		if ( is_object( $contact ) && method_exists( $contact, 'get_meta' ) ) {
			foreach ( array( 'anrede', 'primary_phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $key ) {
				try { $meta[ $key ] = $contact->get_meta( $key ); } catch ( Throwable $error ) { $meta[ $key ] = ''; }
			}
		}
		return array(
			'id'           => absint( $value( array( 'id', 'ID', 'get_id' ) ) ),
			'user_id'      => absint( $value( array( 'user_id', 'get_user_id' ) ) ),
			'email'        => sanitize_email( $value( array( 'email', 'get_email' ) ) ),
			'first_name'   => sanitize_text_field( $value( array( 'first_name', 'get_first_name' ) ) ),
			'last_name'    => sanitize_text_field( $value( array( 'last_name', 'get_last_name' ) ) ),
			'optin_status' => $value( array( 'optin_status', 'get_optin_status' ), 'unconfirmed' ),
			'meta'         => $meta,
			'source'       => $source,
			'object'       => is_object( $contact ) ? $contact : null,
		);
	}

	private static function empty_identity( $user, $source ) {
		return array( 'id' => 0, 'user_id' => $user->ID, 'email' => $user->user_email, 'first_name' => get_user_meta( $user->ID, 'first_name', true ), 'last_name' => get_user_meta( $user->ID, 'last_name', true ), 'optin_status' => 'unconfirmed', 'meta' => array(), 'source' => $source, 'object' => null );
	}

	private static function sanitize_profile( $data ) {
		$result = array();
		foreach ( array( 'first_name', 'last_name', 'salutation', 'title_prefix', 'title_suffix', 'phone', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state' ) as $key ) {
			$result[ $key ] = sanitize_text_field( $data[ $key ] ?? '' );
		}
		$result['country'] = strtoupper( sanitize_key( $data['country'] ?? '' ) );
		return $result;
	}
}
