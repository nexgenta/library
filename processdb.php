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
				echo $db->rootNode . "\n";
				if($db->rootNode == 'http://www.w3.org/1999/02/22-rdf-syntax-ns#RDF')
				{
					copy($indir . '/' . $de, $outdir . '/' . $de);
					continue;
				}
				$r++;
				continue;
			}
			$f = fopen($outdir . '/' . substr($de, 0, -4) . '.html', 'w');
			fwrite($f, '<!DOCTYPE html>' . "\n");
			fwrite($f, '<html>' . "\n");
			fwrite($f, "\t" . '<head>' . "\n");
			fwrite($f, "\t\t" . '<title>' . $db->title . '</title>' . "\n");
			fwrite($f, "\t\t" . '<link rel="stylesheet" type="text/css" href="/doc.css">' . "\n");
			fwrite($f, "\t" . '</head>' . "\n");
			fwrite($f, "\t" . '<body>' . "\n");
			fwrite($f, "\t\t" . '<div id="header">' . "\n");
			fwrite($f, "\t\t\t" . '<h1>' . $db->title . '</h1>' . "\n");
			fwrite($f, "\t\t" . '</div>' . "\n");
			fwrite($f, "\t\t" . '<div id="container">' . "\n");
			fwrite($f, "\t\t\t" . '<div id="toc">' . "\n");
			fwrite($f, "\t\t\t\t" . '<iframe id="toc-frame" src="toc.xml"></iframe>' . "\n"); 
			fwrite($f, "\t\t\t" . '</div>' . "\n");
			fwrite($f, "\t\t\t" . '<div id="content">' . "\n");
			fwrite($f, "\t\t\t\t" . '<iframe id="content-frame" src="' . $de . '"></iframe>' . "\n");
			fwrite($f, "\t\t\t" . '</div>' . "\n");
			fwrite($f, "\t\t" . '</div>' . "\n");
			fwrite($f, "\t" . '</body>' . "\n");
			fwrite($f, '</html>' . "\n");
			fclose($f);
		}
	}
	closedir($d);
	return $r;
}



