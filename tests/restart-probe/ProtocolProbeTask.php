<?php
declare(strict_types=1);
namespace customiesdevs\restarttest;

use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\scheduler\AsyncTask;

/** Translates a custom block state with a client protocol's palette inside an async worker (chunk encoding path). */
final class ProtocolProbeTask extends AsyncTask {
	public function __construct(private int $protocol, private int $stateId, \Closure $onResult){
		$this->storeLocal('onResult', $onResult);
	}

	public function onRun(): void {
		$translator = TypeConverter::getInstance($this->protocol)->getBlockTranslator();
		$this->setResult($translator->getBlockStateDictionary()->generateDataFromStateId($translator->internalIdToNetworkId($this->stateId))?->getName());
	}

	public function onCompletion(): void {
		($this->fetchLocal('onResult'))($this->getResult());
	}
}
