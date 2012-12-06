<?php 
$dir = dirname(__FILE__);
$srcDir = $dir.'/../framework/';
$destDir = $dir.'/../framework/';

readDirRecursive($srcDir);

/**
 * �ݹ�Ŀ¼�ļ�
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
			if(isset($pi['extension']) && in_array($pi['extension'], array('php', 'js', 'css'))) {
				compress($path, $newfile);
			} else {
				copy($path, $newfile);
			}
		}
	}
	closedir($folder);
}

/**
 * ѹ���ļ�
 */
function compress($old, $new) {
    $content = file_get_contents($old);

	$spaces = array(
		// �ĵ�ע��
		'/\/\*.*?\*\//msi'=>'',
		'/^\s+\/\/.*?\n/smi'	=>"\n",
		// ����ע��
		'/\n{2,}/smi' => "\n",
		// ���пհ�
		'/\n^\s+$/smi' => '',
		// ��ֵ��
		'/\s*=>\s*/smi' => '=>',
		// ��ǰ�հ�
		'/^\s+(.*?)$/smi' => '\1',
	);

	$content = preg_replace(
		array_keys($spaces),
		array_values($spaces),
		$content
	);

	file_put_contents($new, $content);
}






?>
