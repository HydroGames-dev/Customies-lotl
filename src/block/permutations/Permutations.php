<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\permutations;

use customiesdevs\customies\block\states\BlockState;
use Exception;
use function array_map;
use function array_product;
use function count;
use function current;
use function next;
use function reset;

class Permutations {

	/**
	 * Attempts to return an array of block properties from the provided meta value based on the possible permutations
	 * of the block. An exception is thrown if the meta value does not match any combinations of all the block
	 * properties.
	 */
	public static function fromMeta(BlockPermutations $block, int $meta): array {
		if($meta < 0){ throw new Exception("Negative block meta"); }
		$states = array_values($block->getStates());
		$result = [];
		for($i = count($states) - 1; $i >= 0; --$i){
			$values = $states[$i]->getValues();
			$result[$i] = $values[$meta % count($values)];
			$meta = intdiv($meta, count($values));
		}
		if($meta !== 0){ throw new Exception("Block meta exceeds state range"); }
		ksort($result);
		return array_values($result);
	}

	/**
	 * Attempts to convert the block in to a meta value based on the possible permutations of the block. An exception is
	 * thrown if the state of the block is not a possible combination of all the block properties.
	 */
	public static function toMeta(BlockPermutations $block): int {
		$meta = 0;
		$current = array_values($block->getCurrentStates());
		foreach(array_values($block->getStates()) as $i => $state){
			$index = array_search($current[$i] ?? null, $state->getValues(), true);
			if($index === false){ throw new Exception("Invalid value for state " . $state->getName()); }
			$meta = $meta * count($state->getValues()) + $index;
		}
		return $meta;
	}

	/**
	 * Returns the number of bits required to represent all the possible permutations of the block.
	 */
	public static function getStateBitmask(BlockPermutations $block): int {
		$possibleValues = array_map(static fn(BlockState $blockProperty) => $blockProperty->getValues(), $block->getStates());
		return (int) array_product(array_map("count", $possibleValues)) - 1;
	}

	/**
	 * Returns an 2-dimensional array containing all possible combinations of the provided arrays using the cartesian
	 * product (https://en.wikipedia.org/wiki/Cartesian_product).
	 */
	public static function getCartesianProduct(array $arrays): array {
		if($arrays === []){
			return [[]];
		}
		$result = [[]];
		foreach($arrays as $values){
			$next = [];
			foreach($result as $row){
				foreach($values as $value){ $next[] = [...$row, $value]; }
			}
			$result = $next;
		}
		return $result;
	}
}