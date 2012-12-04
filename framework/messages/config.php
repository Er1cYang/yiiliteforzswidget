<?php
return array(
'sourcePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
'messagePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'messages',
'languages'=>array('zh_cn'),
'fileTypes'=>array('php'),
'overwrite'=>true,
'exclude'=>array(
'.svn',
'.gitignore',
'yiilite.php',
'yiit.php',
'/i18n/data',
'/messages',
'/vendors',
'/web/js',
),
);
