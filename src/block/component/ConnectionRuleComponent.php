<?php

namespace customiesdevs\customies\block\component;

final class ConnectionRuleComponent implements BlockComponent {

	/**
	 * Allows any block (fences, walls, panes, etc.) to connect.
	 */
	public const RULE_ACCEPTS_ALL = "all";
	/**
	 * Allows only fences to connect.
	 * Prevents walls and glass panes from connecting.
	 */
	public const RULE_ACCEPTS_ONLY_FENCES = "only_fences";
	/**
	 * Prevents connections from all supported blocks.
	 */
	public const RULE_ACCEPTS_NONE = "none";
	/** @var string[] */
	private const VALID_DIRECTIONS = [
		"north",
		"south",
		"east",
		"west"
	];
	/** @var string[]*/
	private array $enabledDirections;
	private string $acceptsConnectionsFrom;

	/**
	 * Determines whether other blocks (such as fences, walls, or glass panes)
	 * can visually and physically connect to this block.
	 * 
	 * @param string $acceptsConnectionsFrom
	 * @param string[] $enabledDirections
	 */
	public function __construct(
		string $acceptsConnectionsFrom = self::RULE_ACCEPTS_ALL,
		array $enabledDirections = self::VALID_DIRECTIONS
	) {
		$this->setAcceptsConnectionsFrom($acceptsConnectionsFrom);
		$this->setEnabledDirections($enabledDirections);
	}

	public function getName(): string {
		return 'minecraft:connection_rule';
	}

	public function getValue(): array {
		if($this->acceptsConnectionsFrom === self::RULE_ACCEPTS_NONE){
			return [
				"accepts_connections_from" => self::RULE_ACCEPTS_NONE
			];
		}
		return [
			"accepts_connections_from" => $this->acceptsConnectionsFrom,
			"enabled_directions" => $this->enabledDirections
		];
	}

	public function setAcceptsConnectionsFrom(string $rule): self {
		if(!in_array($rule, [
			self::RULE_ACCEPTS_ALL,
			self::RULE_ACCEPTS_ONLY_FENCES,
			self::RULE_ACCEPTS_NONE
		], true)){
			throw new \InvalidArgumentException("Invalid connection acceptance rule: {$rule}");
		}
		$this->acceptsConnectionsFrom = $rule;
		return $this;
	}

	/**
	 * @param string[] $directions
	 */
	public function setEnabledDirections(array $directions): self {
		$directions = array_values(array_unique($directions));
		foreach($directions as $direction){
			if(!in_array($direction, self::VALID_DIRECTIONS, true)){
				throw new \InvalidArgumentException("Invalid connection direction: {$direction}");
			}
		}
		$this->enabledDirections = $directions;
		return $this;
	}

	public function addDirection(string $direction): self {
		if(!in_array($direction, self::VALID_DIRECTIONS, true)){
			throw new \InvalidArgumentException(
				"Invalid connection direction: {$direction}"
			);
		}
		if(!in_array($direction, $this->enabledDirections, true)){
			$this->enabledDirections[] = $direction;
		}
		return $this;
	}

	public function removeDirection(string $direction): self {
		$this->enabledDirections = array_values(
			array_diff($this->enabledDirections, [$direction])
		);
		return $this;
	}

	public function clearDirections(): self {
		$this->enabledDirections = [];
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getEnabledDirections(): array {
		return $this->enabledDirections;
	}

	public function getAcceptsConnectionsFrom(): string {
		return $this->acceptsConnectionsFrom;
	}
}