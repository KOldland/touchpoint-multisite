<?php

namespace KHM\Services;

/**
 * MembershipNoteService
 *
 * Stores simple admin notes against individual membership records.
 */
class MembershipNoteService {

	private const OPTION_PREFIX = 'khm_membership_notes_';

	/**
	 * Retrieve all notes for a membership.
	 *
	 * @param int $membershipId Membership record identifier.
	 * @return array<int,array<string,mixed>>
	 */
	public function getNotes( int $membershipId ): array {
		$stored = get_option( $this->optionName( $membershipId ), [] );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		usort(
			$stored,
			static function ( array $a, array $b ): int {
				return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
			}
		);

		return $stored;
	}

	/**
	 * Persist a new note.
	 *
	 * @param int    $membershipId Membership record identifier.
	 * @param int    $authorId     Author user ID.
	 * @param string $content      Note content.
	 * @return array<string,mixed>|null Note record or null when skipped.
	 */
	public function addNote( int $membershipId, int $authorId, string $content ): ?array {
		$content = trim( $content );
		if ( '' === $content ) {
			return null;
		}

		$notes = $this->getNotes( $membershipId );
		$note  = [
			'id'         => wp_generate_password( 12, false ),
			'author_id'  => $authorId,
			'content'    => $content,
			'created_at' => current_time( 'mysql', true ),
		];

		$notes[] = $note;

		update_option( $this->optionName( $membershipId ), $notes, false );

		do_action( 'khm_membership_note_added', $membershipId, $note );

		return $note;
	}

	/**
	 * Remove a note by identifier.
	 *
	 * @param int    $membershipId Membership identifier.
	 * @param string $noteId       The note identifier.
	 * @return bool True when a note was removed.
	 */
	public function deleteNote( int $membershipId, string $noteId ): bool {
		$notes = $this->getNotes( $membershipId );

		$filtered = array_filter(
			$notes,
			static function ( array $note ) use ( $noteId ): bool {
				return ( $note['id'] ?? '' ) !== $noteId;
			}
		);

		if ( count( $filtered ) === count( $notes ) ) {
			return false;
		}

		update_option( $this->optionName( $membershipId ), array_values( $filtered ), false );

		do_action( 'khm_membership_note_deleted', $membershipId, $noteId );

		return true;
	}

	private function optionName( int $membershipId ): string {
		return self::OPTION_PREFIX . $membershipId;
	}
}
