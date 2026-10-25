<?php
declare(strict_types=1);

// Reuses the real host API and regression-world fixture; never opens a live server.
$benchSetupStart = hrtime(true);
ob_start();
try{ require dirname(__DIR__).'/tests/verify.php'; }
finally{ ob_end_clean(); }
$benchSetupMs = (hrtime(true)-$benchSetupStart)/1e6;
$benchBaseline = memory_get_usage(true);
$benchResults = [];
foreach(['runic_arch','astral_beacon','prism_cottage','market_hall','grand_gateway'] as $benchName){
	$benchPhases = ['place_apply'=>[], 'toggle'=>[], 'break'=>[]];
	for($benchIteration=0;$benchIteration<30;++$benchIteration){
		$benchWorld = new ExpansionWorld();
		$benchAt = new \pocketmine\math\Vector3(60,70,60);
		$benchBlock = \customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->get('showcase:'.$benchName);
		$benchReplace = $benchWorld->getBlock($benchAt);
		$benchTx = new \pocketmine\world\BlockTransaction($benchWorld);
		$benchStart = hrtime(true);
		check($benchBlock->place($benchTx,$benchBlock->asItem(),$benchReplace,$benchReplace,1,\pocketmine\math\Vector3::zero()) && $benchTx->apply(),'Benchmark placement');
		$benchPhases['place_apply'][] = (hrtime(true)-$benchStart)/1e6;
		$benchPlaced = $benchWorld->getBlock($benchAt);
		$benchStart = hrtime(true);
		$benchPlaced->onInteract(\pocketmine\item\VanillaItems::AIR(),1,\pocketmine\math\Vector3::zero());
		$benchPhases['toggle'][] = (hrtime(true)-$benchStart)/1e6;
		$benchTool = \pocketmine\item\VanillaItems::AIR();
		$benchStart = hrtime(true);
		check($benchWorld->useBreakOn($benchAt,$benchTool),'Benchmark break');
		$benchPhases['break'][] = (hrtime(true)-$benchStart)/1e6;
	}
	foreach($benchPhases as &$benchSamples){
		sort($benchSamples);
		$benchSamples = ['median_ms'=>round(($benchSamples[14]+$benchSamples[15])/2,4),'p95_ms'=>round($benchSamples[28],4)];
	}
	unset($benchSamples);
	$benchResults[$benchName] = ['occupied_cells'=>\customiesdevs\showcase\ExpansionBlocks::definitions()[$benchName]['parts'],'samples'=>30,'phases'=>$benchPhases];
}
echo json_encode([
	'generated_at'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Calcutta')))->format(DATE_ATOM),
	'php'=>PHP_VERSION,'os'=>PHP_OS_FAMILY,
	'scope'=>'Synthetic in-memory test world on an unpinned development host; not a production capacity test',
	'excludes'=>['real chunk loading/saving','lighting propagation','client rendering','network traffic/compression','protection plugins','concurrent players'],
	'setup_validation_ms'=>round($benchSetupMs,2),
	'php_allocated_after_validation_mib'=>round($benchBaseline/1048576,2),
	'php_peak_allocated_mib'=>round(memory_get_peak_usage(true)/1048576,2),
	'worker_and_os_memory_included'=>false,
	'structures'=>$benchResults
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
