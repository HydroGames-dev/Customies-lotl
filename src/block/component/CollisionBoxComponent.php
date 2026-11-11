<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

use customiesdevs\customies\block\properties\Box;
use customiesdevs\customies\network\BedrockVersion;
use pocketmine\nbt\tag\ListTag;

final class CollisionBoxComponent implements BlockComponent {
	/** Limit for Bedrock 26.60 hosts. Use getMaxBoxes() for the limit of the running host. */
	public const MAX_BOXES = BedrockVersion::COLLISION_BOX_LIMIT;

	private bool $enabled;
	/** @var Box[] */
	private array $boxes = [];

	/**
	 * Defines the area of the block that collides with entities.
	 * @param bool $enabled If collision should be enabled
	 */
	public function __construct(bool $enabled = true) {
		$this->enabled = $enabled;
	}

	public function getName(): string {
		return 'minecraft:collision_box';
	}

	public function getValue(): array {
		$boxes = [];
		foreach($this->enabled ? $this->boxes : [] as $box){
			$boxes[] = $box->toNbtArray();
		}
		// if no boxes are defined we add a default full block box
		if($this->enabled && $boxes === []){
			$boxes[] = Box::defaultBox()->toNbtArray();
		}
		return [
			"boxes" => $boxes === [] ? new ListTag() : $boxes,
			"enabled" => $this->enabled
		];
	}

	/**
	 * Adds a single collision box.
	 * @param Box $box
	 * The collision box to add.
	 */
	public function addBox(Box $box): self {
		if(count($this->boxes) >= self::getMaxBoxes()){ throw self::tooManyBoxes(); }
		$this->boxes[] = $box;
		return $this;
	}

	/**
	 * Adds multiple collision boxes.
	 * @param Box[] $boxes
	 * An array of collision boxes to add.
	 * @throws \InvalidArgumentException If any element in the array is not an instance of Box.
	 */
	public function addBoxes(array $boxes): self {
		if(count($this->boxes) + count($boxes) > self::getMaxBoxes()){ throw self::tooManyBoxes(); }
		foreach($boxes as $box){
			if(!$box instanceof Box){
				throw new \InvalidArgumentException("All boxes must be instances of " . Box::class);
			}
		}
		array_push($this->boxes, ...array_values($boxes));
		return $this;
	}

	/**
	 * Maximum boxes the running host's clients accept: 16 on Bedrock 26.60, 32 from 1.26.70.
	 * The limit follows the host version, so content written for 26.60 keeps loading after an update.
	 */
	public static function getMaxBoxes(): int {
		return BedrockVersion::getCollisionBoxLimit();
	}

	private static function tooManyBoxes(): \InvalidArgumentException {
		return new \InvalidArgumentException('At most ' . self::getMaxBoxes() . ' collision boxes are allowed on Bedrock ' . BedrockVersion::host());
	}
}
