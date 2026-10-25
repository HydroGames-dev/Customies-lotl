<?php
declare(strict_types=1);
// Export the checked-in wrapper API, not a guessed version of the Bedrock schema.
$axolotl = $argv[1] ?? '';
$output = $argv[2] ?? __DIR__.'/api-reference.json';
if(!is_file($axolotl.'/vendor/autoload.php')){ throw new RuntimeException('Pass an Axolotl source checkout with dependencies'); }
require $axolotl.'/vendor/autoload.php';
$repository = dirname(__DIR__);
spl_autoload_register(static function(string $class) use($repository): void {
	foreach(['customiesdevs\\customies\\'=>$repository.'/src/','customiesdevs\\showcase\\'=>$repository.'/examples/CustomiesShowcase/src/'] as $prefix=>$base){
		if(str_starts_with($class,$prefix)){ require $base.str_replace('\\','/',substr($class,strlen($prefix))).'.php'; return; }
	}
});
function normal(mixed $v): mixed {
	if($v instanceof \pocketmine\nbt\tag\CompoundTag){ return (object)array_map('normal',$v->getValue()); }
	if($v instanceof \pocketmine\nbt\tag\Tag){ return normal($v->getValue()); }
	if($v instanceof BackedEnum){ return $v->value; }
	if(is_object($v)){ return array_map('normal',get_object_vars($v)); }
	if(is_array($v)){ return array_map('normal',$v); }
	return $v;
}
$result = [];
foreach(['block'=>'blocks','item'=>'items'] as $kind=>$method){
	foreach(\customiesdevs\showcase\ComponentCatalog::$method() as $key=>$component){
		$class = new ReflectionClass($component);$ctor = $class->getConstructor();
		$comment = $ctor?->getDocComment() ?: '';
		$parameters = [];
		foreach($ctor?->getParameters() ?? [] as $parameter){
			preg_match('/@param\s+\S+\s+\$'.preg_quote($parameter->getName(),'/').'\s*([^\r\n]*)/',$comment,$description);
			$parameters[] = ['name'=>$parameter->getName(),'type'=>(string)$parameter->getType(),'required'=>!$parameter->isOptional(),'default'=>$parameter->isDefaultValueAvailable() ? normal($parameter->getDefaultValue()) : null,'default_constant'=>$parameter->isDefaultValueAvailable() && $parameter->isDefaultValueConstant() ? $parameter->getDefaultValueConstantName() : null,'description'=>trim($description[1] ?? '')];
		}
		$methods=[];
		foreach($class->getMethods(ReflectionMethod::IS_PUBLIC) as $methodInfo){
			if($methodInfo->isConstructor() || in_array($methodInfo->getName(),['getName','getValue','getPropertyMapping'],true)){ continue; }
			$methodParameters=[];
			foreach($methodInfo->getParameters() as $parameter){
				$methodParameters[]=['name'=>$parameter->getName(),'type'=>(string)$parameter->getType(),'required'=>!$parameter->isOptional(),'default'=>$parameter->isDefaultValueAvailable() ? normal($parameter->getDefaultValue()) : null];
			}
			$methods[]=['name'=>$methodInfo->getName(),'parameters'=>$methodParameters,'return_type'=>(string)$methodInfo->getReturnType(),'documentation'=>$methodInfo->getDocComment() ?: ''];
		}
		$lines=file($class->getFileName());
		$result[$kind][$key]=['class'=>$class->getName(),'name'=>$component->getName(),'parameters'=>$parameters,'methods'=>$methods,'constants'=>normal($class->getConstants()),'serialized_example'=>normal($component->getValue()),'source'=>'src/'.$kind.'/component/'.$class->getShortName().'.php','source_sha256'=>hash('sha256',str_replace("\r\n","\n",file_get_contents($class->getFileName()))),'constructor'=>$ctor ? str_replace("\r\n","\n",implode('',array_slice($lines,$ctor->getStartLine()-1,$ctor->getEndLine()-$ctor->getStartLine()+1))) : ''];
	}
}
file_put_contents($output,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
echo 'Exported '.count($result['block']).' block and '.count($result['item'])." item wrappers.\n";
