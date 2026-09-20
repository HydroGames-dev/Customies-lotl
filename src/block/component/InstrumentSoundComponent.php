<?php

namespace customiesdevs\customies\block\component;

use customiesdevs\customies\item\properties\SoundEvent;

# TODO: Not sure of this
// This component is currently experimental
// This defines what sound will play based on above or below relative position to a note block.
// An instrument can be assigned to the "up" and "down" block faces.
// If either face is undefined, or the component is omitted,
// it will use its default value ("up" = "note.harp" and "down" = "note.none").
// While both faces do not need to be defined, at least one face needs to be defined for the component to be valid.
// "note.none" can be used to specify no sound for a face.
final class InstrumentSoundComponent implements BlockComponent {

	/**
	 * The instrument sound played when the note block is above this block.
	 *
	 * The block's down face is exposed to the note block.
	 */
	private ?string $upNote;

	/**
	 * The instrument sound played when the note block is below this block.
	 *
	 * The block's up face is exposed to the note block.
	 */
	private ?string $downNote;

	public function __construct(
		SoundEvent|string|null $upNote = "note.harp",
		SoundEvent|string|null $downNote = "note.none"
	) {
		$this->upNote = $upNote instanceof SoundEvent ? (string) $upNote->value : $upNote;
		$this->downNote = $downNote instanceof SoundEvent ? (string) $downNote->value : $downNote;
	}

	public function getName(): string {
		return 'minecraft:instrument_sound';
	}

	public function getValue(): array {
		return [
			"up" => $this->upNote,
			"down" => $this->downNote
		];
	}
}