<?php

/* Read DocBook-XML and augment it so that it’s suitable for presentation
 * within a web browser.
 */

class DocBookParser
{
	const NS_DOCBOOK = 'http://docbook.org/ns/docbook';
	const NS_XHTML = 'http://www.w3.org/1999/xhtml';
	
	protected $filename;
	protected $depth;
	protected $relPath;
	protected $css;
	protected $roots;
							 
	public function __construct($filename, $depth = '', $relPath = '')
	{
		$this->filename = $filename;
		$this->depth = $depth;
		$this->relPath = $relPath;
		$this->css = array('dbook.css');
		$this->roots = array('article', 'refentry');
	}
	
	public function output($file)
	{
		if(!($root = simplexml_load_file($this->filename)))
		{
			return false;
		}
		if(!($dom = dom_import_simplexml($root)))
		{
			return false;
		}
		if(!($out = fopen($file . '.tmp', 'w')))
		{
			return false;
		}
		if($this->processXML($root, $out))
		{
			fclose($out);
			rename($file . '.tmp', $file);
			return true;
		}
		fclose($out);
		unlink($file . '.tmp');
		return false;
	}
	
	protected function processXML(&$root, &$out)
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->loadXML($root->asXML());
		if($doc->documentElement->namespaceURI != self::NS_DOCBOOK)
		{
			echo "Error: Root node is not in the " . self::NS_DOCBOOK . " namespace\n";
			echo "       Found: " . $doc->documentElement->namespaceURI . "\n";
			return false;
		}
		if(!in_array($doc->documentElement->localName, $this->roots))
		{
			echo "Unsupported root node " . $doc->documentElement->localName . " (expected one of " . implode(', ', $this->roots) . ")\n";
			return false;
		}
		$leader = array();
		$leader[] = '<?xml version="1.0" encoding="UTF-8" ?>';
		foreach($this->css as $stylesheet)
		{
			$leader[] = '<?xml-stylesheet type="text/css" href="' . htmlspecialchars($this->depth . $stylesheet, ENT_QUOTES, 'UTF-8') . '" ?>';
		}
		$leader[] = '<!DOCTYPE article PUBLIC "-//OASIS//DTD DocBook V5.0//EN" "http://www.oasis-open.org/docbook/xml/5.0/docbook.dtd">';
		fwrite($out, implode("\n", $leader) . "\n");
		$title = null;
		switch($root->getName())
		{
			case 'article':
				if($root->articleinfo && $root->articleinfo->title)
				{
					$title = trim(strval($root->articleinfo->title));
				}
				break;
			case 'refentry':
				if($root->refnamediv && $root->refnamediv->refname)
				{
					$title = trim(strval($root->refnamediv->refname));
				}
				break;
		}
		if($title !== null)
		{
			$refChild = $doc->documentElement->firstChild;
			/* Add some whitespace to ensure output is pleasantly-readable */
			$doc->documentElement->insertBefore($doc->createTextNode("\n\t"), $refChild);
			$head = $doc->createElementNS(self::NS_XHTML, 'head');
			$head->appendChild($doc->createTextNode("\n\t\t"));
			$head->appendChild($doc->createElementNS(self::NS_XHTML, 'title', $title));
			$head->appendChild($doc->createTextNode("\n\t"));
			$doc->documentElement->insertBefore($head, $refChild);
		}
		fwrite($out, $doc->saveXML($doc->documentElement));
		return true;
	}
}
