<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use pocketmine\math\AxisAlignedBB;

/** One shape table shared by server collision and client collision permutations. */
final class StairShapes {
	public const SHAPES = ['none','outer_left','outer_right','inner_left','inner_right'];
	/** @return list<AxisAlignedBB> Block-local boxes, north-facing. */
	public static function boxes(string $shape, string $half = 'bottom', string $direction = 'north'): array {
		$top = $half === 'top';
		$boxes = [new AxisAlignedBB(0,$top ? 0.5 : 0,0,1,$top ? 1 : 0.5,1)];
		$quadrants = match($shape){
			'none' => [[0,0],[0.5,0]],
			'outer_left' => [[0,0]], 'outer_right' => [[0.5,0]],
			'inner_left' => [[0,0],[0.5,0],[0,0.5]],
			'inner_right' => [[0,0],[0.5,0],[0.5,0.5]],
			default => throw new \InvalidArgumentException('Unknown stair shape: '.$shape)
		};
		$turns = ['north'=>0,'west'=>1,'south'=>2,'east'=>3][$direction];
		foreach($quadrants as [$x,$z]){
			for($i=0;$i<$turns;++$i){ [$x,$z] = [$z,0.5-$x]; }
			$boxes[] = new AxisAlignedBB($x,$top ? 0 : 0.5,$z,$x+0.5,$top ? 0.5 : 1,$z+0.5);
		}
		return $boxes;
	}
}
