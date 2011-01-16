<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');

/* Process a directory of DocBook-XML files for the web */

if(count($argv) < 2 || count($argv) > 3)
{
	echo "Usage: " . $argv[0] . " INPUT-DIR [OUTPUT-DIR]\n";
	exit(1);
}

require(dirname(__FILE__) . '/docbook.php');

$indir = realpath($argv[1]);
$outdir = realpath(isset($argv[2]) ? $argv[2] : '.');

exit(processdir($indir, $outdir));

function processdir($indir, $outdir)
{
	echo "Scanning $indir\n";
	$r = 0;
	$d = opendir($indir);
	$made = false;
	while(($de = readdir($d)))
	{
		if($de[0] == '.')
		{
			continue;
		}
		if(is_dir($indir . '/' . $de))
		{
			$r += processdir($indir . '/' . $de, $outdir . '/' . $de);
		}
		else if(substr($de, -4) == '.xml')
		{
			if(!$made)
			{
				if(!file_exists($outdir))
				{
					mkdir($outdir, 0777, true);
				}
				$made = true;
			}
			echo "Processing $de\n";
			$db = new DocBookParser($indir . '/' . $de, '/');
			if(!($db->output($outdir . '/' . $de)))
			{
				$r++;
			}
		}
	}
	closedir($d);
	return $r;
}



