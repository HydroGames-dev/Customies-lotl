<?php
declare(strict_types=1);
if(Phar::isValidPharFilename(__FILE__)){ throw new RuntimeException('Run from the source directory'); }
$base = dirname(__DIR__);
$out = $argv[1] ?? $base.'/dist';
if(!is_dir($out) && !mkdir($out,0777,true)){ throw new RuntimeException('Cannot create output directory'); }
$out = realpath($out);
function buildPlugin(string $root, string $target): void {
	if(is_file($target)){ unlink($target); }
	$phar = new Phar($target);
	$phar->startBuffering();
	$phar->setStub("<?php __HALT_COMPILER();");
	$phar->addFile($root.'/plugin.yml','plugin.yml');
	if(is_file($root.'/LICENSE')){ $phar->addFile($root.'/LICENSE','LICENSE'); }
	foreach(['src','resources'] as $folder){
		if(!is_dir($root.'/'.$folder)){ continue; }
		foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS)) as $file){
			if($file->isFile()){
				$relative = str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
				$phar->addFile($file->getPathname(),$relative);
			}
		}
	}
	$phar->setSignatureAlgorithm(Phar::SHA256);
	$phar->stopBuffering();
	echo 'Built '.basename($target).PHP_EOL;
}
buildPlugin($base,$out.'/CustomiesShowcase.phar');
buildPlugin(dirname($base,2),$out.'/Customies-Axolotl.phar');
