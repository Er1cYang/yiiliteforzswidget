<?php 
$dir = dirname(__FILE__);
$srcDir = $dir.'/../framework/';
$destDir = $dir.'/../framework/';
//$srcDir = 'd:/test/';
//$destDir = 'd:/test/';
readDirRecursive($srcDir);

/**
 * 递归目录文件
 */
function readDirRecursive($src) {
	global $srcDir;
	global $destDir;

	$folder=opendir($src);
	$newPath = $destDir.substr($src, strlen($srcDir));
	if(!file_exists($newPath)) {
		@mkdir($newPath, 0777, true);
	}

	while(($file=readdir($folder))!==false) {
		if($file==='.' || $file==='..')
			continue;
		$path=$src.'/'.$file;
		if(is_dir($path)) {
			readDirRecursive($path);
		} else {
			$pi = pathinfo($path);
			$newfile = $newPath.'/'.$pi['basename'];
			if(isset($pi['extension']) && in_array($pi['extension'], array('php'))) {
				compress($path, $newfile);
			} else {
				copy($path, $newfile);
			}
		}
	}
	closedir($folder);
}

/**
 * 压缩文件
 */
function compress($old, $new) {
    $content = file_get_contents($old);

	$spaces = array(
		// 文档注释
		'/\/\*.*?\*\//msi'=>'',
		// 整行注释
		'/^\s*\/\/.*?\n/smi' =>"\n",
		// 键值对
		'/\s*=>\s*/smi' => '=>',
		// 行前空白
		'/^[\s[^\n]]+(.*?)$/smi' => '\1',
		// 多余的回车
		'/\n+/smi' => "\n",
	);

	$content = preg_replace(
		array_keys($spaces),
		array_values($spaces),
		$content
	);

	file_put_contents($new, $content);
}






?>
