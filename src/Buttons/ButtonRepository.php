<?php
/**
 * Pay button storage.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Buttons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Buttons are a private post type: WP gives me slugs, trash and caching for free, and nothing about them is public.
 *
 * @phpstan-import-type ButtonConfig from Button
 */
class ButtonRepository {

	public const POST_TYPE = 'iftp_btn_button';
	public const META_KEY  = '_iftp_btn_config';

	/**
	 * Registers the post type. No UI or REST of its own: the Buttons screen is the only way in.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Pay buttons', 'ifthenpay-pay-button' ),
					'singular_name' => __( 'Pay button', 'ifthenpay-pay-button' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'can_export'          => true,
			)
		);
	}

	/**
	 * A button by ID, active or not, or null when there's no such button.
	 *
	 * @param int $id The post ID.
	 */
	public function find( int $id ): ?Button {
		$post = $id > 0 ? get_post( $id ) : null;

		return $post instanceof \WP_Post && self::POST_TYPE === $post->post_type && 'trash' !== $post->post_status ? self::from_post( $post ) : null;
	}

	/**
	 * An active button by its pay link slug.
	 *
	 * @param string $slug The slug.
	 */
	public function find_active_by_slug( string $slug ): ?Button {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'name'             => $slug,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'suppress_filters' => false,
			)
		);

		return isset( $posts[0] ) && $posts[0] instanceof \WP_Post ? self::from_post( $posts[0] ) : null;
	}

	/**
	 * Every button that isn't trashed, newest first.
	 *
	 * @return array<int, Button>
	 */
	public function all(): array {
		$posts = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		return array_values( array_map( array( self::class, 'from_post' ), array_filter( $posts, static fn( $post ): bool => $post instanceof \WP_Post ) ) );
	}

	/**
	 * Creates or updates a button.
	 *
	 * @param int                  $id     The post ID, or 0 for a new button.
	 * @param string               $title  The title.
	 * @param string               $slug   The wanted slug; WP makes it unique.
	 * @param bool                 $active Whether it takes payments.
	 * @param array<string, mixed> $config The config, sanitized again here.
	 * @return int The post ID, or 0 if WP refused it.
	 */
	public function save( int $id, string $title, string $slug, bool $active, array $config ): int {
		$config = Button::sanitize_config( $config );

		// A new button gets its outgoing webhook secret right away, so the editor can show it.
		if ( '' === $config['webhook_secret'] ) {
			$config['webhook_secret'] = wp_generate_password( 40, false, false );
		}

		$postarr = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $title,
			'post_name'   => sanitize_title( '' !== $slug ? $slug : $title ),
			'post_status' => $active ? 'publish' : 'draft',
		);

		if ( $id > 0 ) {
			$postarr['ID'] = $id;
		}

		$saved = wp_insert_post( wp_slash( $postarr ), true );

		if ( is_wp_error( $saved ) || 0 === $saved ) {
			return 0;
		}

		// A draft never gets a unique slug from WP, so I make it unique myself; the pay link must always point to one button.
		$wanted = sanitize_title( '' !== $slug ? $slug : $title );
		$unique = wp_unique_post_slug( '' !== $wanted ? $wanted : 'button-' . $saved, $saved, 'publish', self::POST_TYPE, 0 );

		if ( get_post_field( 'post_name', $saved ) !== $unique ) {
			wp_update_post(
				array(
					'ID'        => $saved,
					'post_name' => $unique,
				)
			);
		}

		update_post_meta( $saved, self::META_KEY, $config );

		return (int) $saved;
	}

	/**
	 * Copies a button as an inactive draft, with a fresh webhook secret.
	 *
	 * @param Button $button The button to copy.
	 * @return int The new post ID, or 0 on failure.
	 */
	public function duplicate( Button $button ): int {
		$config                   = $button->config;
		$config['webhook_secret'] = '';

		/* translators: %s: the copied button's title */
		return $this->save( 0, sprintf( __( '%s (copy)', 'ifthenpay-pay-button' ), $button->title ), $button->slug . '-copy', false, $config );
	}

	/**
	 * Turns a button on or off.
	 *
	 * @param Button $button The button.
	 * @param bool   $active Whether it takes payments.
	 */
	public function set_active( Button $button, bool $active ): bool {
		$result = wp_update_post(
			array(
				'ID'          => $button->id,
				'post_status' => $active ? 'publish' : 'draft',
			)
		);

		return is_int( $result ) && $result > 0;
	}

	/**
	 * Moves a button to the trash, where WP empties it after its usual trash days. Its payments stay, as history.
	 *
	 * @param Button $button The button.
	 */
	public function delete( Button $button ): bool {
		return false !== wp_trash_post( $button->id ) && 'trash' === get_post_status( $button->id );
	}

	/**
	 * Brings a trashed button back, on or off the way it was.
	 *
	 * @param int $id The post ID.
	 */
	public function restore( int $id ): bool {
		$post = $id > 0 ? get_post( $id ) : null;

		if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type || 'trash' !== $post->post_status ) {
			return false;
		}

		// WP brings a post back as a draft; I want it as it was, so the pay link keeps working.
		$was    = get_post_meta( $id, '_wp_trash_meta_status', true );
		$keep   = static fn(): string => 'publish' === $was ? 'publish' : 'draft';
		add_filter( 'wp_untrash_post_status', $keep );
		$result = wp_untrash_post( $id );
		remove_filter( 'wp_untrash_post_status', $keep );

		return $result instanceof \WP_Post && 'trash' !== get_post_status( $id );
	}

	/**
	 * Builds a button from its post.
	 *
	 * @param \WP_Post $post A post of POST_TYPE.
	 */
	public static function from_post( \WP_Post $post ): Button {
		$config = get_post_meta( $post->ID, self::META_KEY, true );

		return new Button( (int) $post->ID, (string) $post->post_title, (string) $post->post_name, 'publish' === $post->post_status, is_array( $config ) ? $config : array() );
	}
}
