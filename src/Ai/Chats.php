<?php
/**
 * The merchant's saved chats with the AI.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai;

use Ifthenpay\PayButton\Buttons\ButtonRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every admin has their own list of chats. A chat's thread is one option row, so it survives a cache flush, and a
 * small list in user meta says which chats there are, so the studio can show them without opening each one.
 * Chats older than the days set in Configuration › AI are dropped when the list is read.
 *
 * @phpstan-type Entry array{id: string, target: string, title: string, custom: bool, built: bool, updated: int}
 */
class Chats {

	// The most chats one admin keeps; the oldest go first.
	public const MAX = 40;

	public const TITLE = 60;

	private const META = 'iftp_btn_ai_chats';

	/**
	 * A fresh id for a chat.
	 */
	public static function make_id(): string {
		return strtolower( wp_generate_password( 10, false ) );
	}

	/**
	 * Whether a string can be a chat id.
	 *
	 * @param string $id The id.
	 */
	public static function valid( string $id ): bool {
		return 1 === preg_match( '/^[a-z0-9]{8,20}$/', $id );
	}

	/**
	 * Where a chat's thread is kept.
	 *
	 * @param string $id The chat's id.
	 */
	private static function option( string $id ): string {
		return 'iftp_btn_ai_chat_' . get_current_user_id() . '_' . $id;
	}

	/**
	 * The chats, newest first. Expired ones, and those of buttons that are gone, are cleared out on the way.
	 *
	 * @return array<int, Entry>
	 */
	public static function all(): array {
		$entries = get_user_meta( get_current_user_id(), self::META, true );
		$entries = is_array( $entries ) ? $entries : array();
		$limit   = time() - AiSettings::get()['keep_days'] * DAY_IN_SECONDS;
		$kept    = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['id'] ?? null ) || ! self::valid( $entry['id'] ) ) {
				continue;
			}

			$target = is_string( $entry['target'] ?? null ) ? $entry['target'] : Studio::NEW_TARGET;
			$gone   = Studio::NEW_TARGET !== $target && ButtonRepository::POST_TYPE !== get_post_type( (int) $target );

			if ( $gone || (int) ( $entry['updated'] ?? 0 ) < $limit ) {
				delete_option( self::option( $entry['id'] ) );

				continue;
			}

			$kept[] = array(
				'id'      => $entry['id'],
				'target'  => $target,
				'title'   => is_string( $entry['title'] ?? null ) ? $entry['title'] : '',
				'custom'  => ! empty( $entry['custom'] ),
				'built'   => ! empty( $entry['built'] ),
				'updated' => (int) ( $entry['updated'] ?? 0 ),
			);
		}

		usort(
			$kept,
			static fn( array $a, array $b ): int => $b['updated'] <=> $a['updated']
		);

		if ( count( $kept ) !== count( $entries ) ) {
			update_user_meta( get_current_user_id(), self::META, $kept );
		}

		return $kept;
	}

	/**
	 * The chat a button's studio opens on: its most recent one.
	 *
	 * @param string $target A button id, or Studio::NEW_TARGET.
	 */
	public static function latest_for( string $target ): string {
		foreach ( self::all() as $entry ) {
			if ( $entry['target'] === $target ) {
				return $entry['id'];
			}
		}

		return '';
	}

	/**
	 * A chat's thread, or null when there is none under that id.
	 *
	 * @param string $id The chat's id.
	 * @return array<string, mixed>|null
	 */
	public static function load( string $id ): ?array {
		$thread = self::valid( $id ) ? get_option( self::option( $id ), false ) : false;

		return is_array( $thread ) ? $thread : null;
	}

	/**
	 * Stores a thread and keeps the list in step: its title, whether it built anything, and when it last moved.
	 *
	 * @param string               $id     The chat's id.
	 * @param array<string, mixed> $thread The thread.
	 * @param string               $target A button id, or Studio::NEW_TARGET.
	 * @param string               $built  The title of the latest version, when the chat has built one.
	 */
	public static function save( string $id, array $thread, string $target, string $built ): void {
		if ( ! self::valid( $id ) ) {
			return;
		}

		update_option( self::option( $id ), $thread, false );

		$entries = self::all();
		$current = null;

		foreach ( $entries as $index => $entry ) {
			if ( $entry['id'] === $id ) {
				$current = $entry;
				unset( $entries[ $index ] );
			}
		}

		$first  = '';
		$custom = null !== $current && $current['custom'];

		foreach ( is_array( $thread['messages'] ?? null ) ? $thread['messages'] : array() as $message ) {
			if ( 'user' === ( $message['role'] ?? '' ) ) {
				$first = (string) $message['text'];
				break;
			}
		}

		if ( '' === $first && is_array( $thread['pending'] ?? null ) ) {
			$first = (string) $thread['pending']['text'];
		}

		$title = $custom ? $current['title'] : ( '' !== $built ? $built : self::shorten( $first ) );

		array_unshift(
			$entries,
			array(
				'id'      => $id,
				'target'  => $target,
				'title'   => $title,
				'custom'  => $custom,
				'built'   => '' !== $built,
				'updated' => time(),
			)
		);

		foreach ( array_slice( $entries, self::MAX ) as $dropped ) {
			delete_option( self::option( $dropped['id'] ) );
		}

		update_user_meta( get_current_user_id(), self::META, array_slice( array_values( $entries ), 0, self::MAX ) );
	}

	/**
	 * Gives a chat a name of the merchant's own; an empty one goes back to the automatic name.
	 *
	 * @param string $id    The chat's id.
	 * @param string $title The name.
	 */
	public static function rename( string $id, string $title ): bool {
		$title   = self::shorten( $title );
		$entries = self::all();
		$thread  = self::load( $id );

		foreach ( $entries as $index => $entry ) {
			if ( $entry['id'] !== $id || null === $thread ) {
				continue;
			}

			$entries[ $index ]['custom'] = '' !== $title;

			if ( '' !== $title ) {
				$entries[ $index ]['title'] = $title;
			} else {
				$entries[ $index ]['title'] = self::shorten( self::first_message( $thread ) );
			}

			update_user_meta( get_current_user_id(), self::META, $entries );

			return true;
		}

		return false;
	}

	/**
	 * Deletes a chat.
	 *
	 * @param string $id The chat's id.
	 */
	public static function delete( string $id ): void {
		if ( ! self::valid( $id ) ) {
			return;
		}

		delete_option( self::option( $id ) );

		update_user_meta(
			get_current_user_id(),
			self::META,
			array_values( array_filter( self::all(), static fn( array $entry ): bool => $entry['id'] !== $id ) )
		);
	}

	/**
	 * The first thing the merchant wrote in a thread.
	 *
	 * @param array<string, mixed> $thread The thread.
	 */
	private static function first_message( array $thread ): string {
		foreach ( is_array( $thread['messages'] ?? null ) ? $thread['messages'] : array() as $message ) {
			if ( 'user' === ( $message['role'] ?? '' ) ) {
				return (string) $message['text'];
			}
		}

		return '';
	}

	/**
	 * A name that fits one line of the list.
	 *
	 * @param string $text Whatever the name comes from.
	 */
	private static function shorten( string $text ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', sanitize_text_field( $text ) ) );

		return mb_strlen( $text ) > self::TITLE ? rtrim( mb_substr( $text, 0, self::TITLE - 1 ) ) . '…' : $text;
	}
}
