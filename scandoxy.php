<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');

require(dirname(__FILE__) . '/docbook.php');

$classes = $interfaces = $functions = $types = $constants = $namespaces = array();

if(count($argv) < 2 && count($argv) > 3)
{
	echo "Usage: scandoxy XMLDIR [OUTDIR]\n";
	die(1);
}

if(false === ($d = opendir($argv[1])))
{
	die(1);
}

$base = realpath($argv[1]) . '/';
if(isset($argv[2]))
{
	$outbase = realpath($argv[2]);
}
else
{
	$outbase = realpath('.');
}
while(($de = readdir($d)))
{
	if($de[0] == '.')
	{
		continue;
	}
	if(substr($de, -4) == '.xml')
	{
		parse($base . $de);
	}
}
closedir($d);

foreach($namespaces as $k => $ns)
{
	if(!strlen($k)) continue;
	echo "Writing package " . $ns['name'] . "\n";
	$index = fopen($outbase . '/' . $ns['name'] . '/toc.xml', 'w');
	fwrite($index, '<?xml version="1.0" encoding="UTF-8" ?>' . "\n" .
		   '<?xml-stylesheet type="text/css" href="/toc.css" ?>' . "\n" .
		   '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:html="http://www.w3.org/1999/xhtml">' . "\n");
	fwrite($index, "\t" . '<html:base target="content" />' . "\n");
	fwrite($index, "\t" . '<rdf:Description>' . "\n");
	fwrite($index, "\t\t" . '<dc:title><html:a href="index.xml">Introduction</html:a></dc:title>' . "\n");
	fwrite($index, "\t" . '</rdf:Description>' . "\n");
	
	if(!file_exists($outbase . '/' . $ns['name']))
	{
		mkdir($outbase . '/' . $ns['name']);
	}
	if(isset($ns['functions']))
	{
		fwrite($index, "\t" . '<rdf:Description rdf:about="#functions">' . "\n");
		fwrite($index, "\t\t" . '<dc:title>Function Reference</dc:title>' . "\n");
		fwrite($index, "\t" . '</rdf:Description>' . "\n");

		fwrite($index, "\t" . '<rdf:Seq xml:id="functions">' . "\n");
		foreach($ns['functions'] as $id)
		{
			if(isset($functions[$id]))
			{
				fwrite($index, "\t\t" . '<rdf:li><rdf:Description><dc:title><html:a href="' . $functions[$id]['name'] . '.xml' . '">' . $functions[$id]['name'] . '</html:a></dc:title></rdf:Description></rdf:li>' . "\n");
				write_function($ns, $functions[$id], $outbase);
			}
		}
		fwrite($index, "\t" . '</rdf:Seq>' . "\n");
	}
	fwrite($index, '</rdf:RDF>' . "\n");
	fclose($index);
}

exit(0);

function innerXML($node)
{
	$x = array();
	foreach($node->children() as $child)
	{
		$x[] = $child->asXML();
	}
	return trim(implode('', $x));
}

function attrs_array($node, $extra = null)
{
	$list = array();
	$a = $node->attributes();
	foreach($a as $k => $v)
	{
		$list[$k] = strval($v);
	}
	if(is_array($extra))
	{
		foreach($extra as $k => $v)
		{
			$list[$k] = $v;
		}
	}
	return $list;
}

function parse($path)
{
	$fn = basename($path);
	$root = simplexml_load_file($path);
	if(strcmp($root->getName(), 'doxygen'))
	{
		echo "$fn: root element is not 'doxygen'; skipping.\n";
		return;
	}
	echo $fn . ":\n";
	foreach($root->children() as $child)
	{
		if(strcmp($child->getName(), 'compounddef'))
		{
			echo "$fn: skipping unknown second-level node " . $child->getName() . "\n";
			continue;
		}
		$a = $child->attributes();
		$id = strval($a->id);
		$kind = strval($a->kind);
		$name = strval($child->compoundname);
		switch($kind)
		{
		case 'namespace':			
			parse_namespace($child, $fn, $id, $kind, $name);
			break;
		case 'file':
			parse_file($child, $fn, $id, $kind, $name);
			break;
		default:
			echo "$fn: skipping unknown compounddef type $kind\n";
		}
	}	
}

function parse_namespace($node, $fn, $id, $kind, $name)
{
	global $namespaces;

	$namespaces[$id]['name'] = $name;
}

function parse_file($node, $fn, $id, $kind, $name)
{
	$ns = null;
	foreach($node->children() as $child)
	{
		$attrs = attrs_array($child, array('namespace' => $ns));
		$cname = $child->getName();
		switch($cname)
		{
		case 'innernamespace':
			$ns = $attrs['refid'];
			break;
		case 'compoundname':
		case 'includes':
		case 'includedby':
		case 'incdepgraph':
		case 'briefdescription':
		case 'detaileddescription':
		case 'location':
			break;
		case 'innerclass':
			parse_innerclass($child, $fn, $attrs);
			break;
		case 'sectiondef':
			switch($attrs['kind'])
			{
			case 'func':
				parse_funcsec($child, $fn, $attrs);
				break;
			default:
				echo "$fn: skipping unhandled section definition " . $attrs['kind'] . "\n";
			}
			break;
		default:
			echo "$fn: skipping unhandled child type $cname\n";
		}
	}
}

function parse_innerclass($child, $fn, $attrs)
{
	global $classes, $namespaces;
	
	$classes[$attrs['refid']]['name'] = strval($child);
	$namespaces[$attrs['namespace']]['classes'][] = $attrs['refid'];
}

function parse_funcsec($node, $fn, $attrs)
{
	foreach($node->children() as $child)
	{
		$a = attrs_array($child, array('namespace' => $attrs['namespace']));
		$name = strval($child->getName());
		switch($name)
		{
		case 'memberdef':
			switch($a['kind'])
			{
			case 'function':
				parse_function($child, $fn, $a);
				break;
			default:
				echo "$fn: skipping unhandled memberdef " . $a['kind'] . "\n";
			}
			break;
		default:
			echo "$fn: skipping unhandled section member $name\n";
		}
	}
}

function parse_function($child, $fn, $attrs)
{
	global $functions, $namespaces;
  
	$f = strval($child->location->attributes()->file);
	if(is_header($f))
	{
		return;
	}
	if($child->detaileddescription->internal)
	{
		return;
	}
	$namespaces[$attrs['namespace']]['functions'][] = $attrs['id'];
	$func = $attrs;
	$func['name'] = strval($child->name);
	$func['brief'] = trim(strval($child->briefdescription->para));
	$func['type'] = strval($child->type);
	$func['argstring'] = strval($child->argstring);
	$func['definition'] = strval($child->definition);
	$func['params'] = array();
	$func['sections'] = array();
	foreach($child->param as $param)
	{
		$p = array(
			'type' => strval($param->type),
			'name' => strval($param->declname),
			);
		$func['params'][] = $p;
	}
	foreach($child->detaileddescription->children() as $dc)
	{
		if($dc->getName() == 'internal')
		{
			continue;
		}
		if($dc->getName() == 'para')
		{
			foreach($dc->children() as $pc)
			{
				if($pc->getName() == 'parameterlist' &&
				   $pc->attributes()->kind == 'param')
				{
					$func['sections']['Parameters'][] = $pc->asXML();
				}
				else if($pc->getName() == 'simplesect' &&
						$pc->attributes()->kind == 'return')
				{
					$func['sections']['Return value'] = innerXML($pc);
				}
				else if($pc->getName() == 'simplesect' &&
						$pc->attributes()->kind == 'see')
				{
					$func['sections']['See also'] = innerXML($pc);
				}
				else
				{
					$func['sections']['Description'][] = $dc->asXML();
					break;
				}
			}
		}
		else
		{
			$func['sections']['Description'][] = $pc->asXML();
		}		
	}
	if(!isset($func['sections']['Description'])) return;
	$functions[$attrs['id']] = $func;
}

function is_header($f)
{
	$i = pathinfo($f);
	switch(@strtolower($i['extension']))
	{
	case 'h':
	case 'hh':
	case 'hpp':
	case 'hxx':
		return true;
	}
}

function write_function($ns, $func, $outbase)
{
	$f = fopen($outbase . '/' . $ns['name'] . '/' . $func['name'] . '.xml', 'w');	
	fwrite($f, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
	fwrite($f, '<refentry id="' . $func['id'] . '" xmlns="' . DocBookParser::NS_DOCBOOK . '">' . "\n");
	fwrite($f, "\t" . '<refmeta>' . "\n");
	fwrite($f, "\t\t" . '<refentrytitle>' . $func['name'] . '</refentrytitle>' . "\n");
	fwrite($f, "\t\t" . '<manvolnum>3</manvolnum>' . "\n");
	fwrite($f, "\t" . '</refmeta>' . "\n");

	fwrite($f, "\t" . '<refnamediv>' . "\n");
	fwrite($f, "\t\t" . '<refname>' . $func['name'] . '</refname>' . "\n");
	fwrite($f, "\t\t" . '<refpurpose>' . $func['brief'] . '</refpurpose>' . "\n");	
	fwrite($f, "\t" . '</refnamediv>' . "\n");

	fwrite($f, "\t" . '<refsynopsisdiv>' . "\n");
	fwrite($f, "\t\t" . '<funcsynopsis>' . "\n");
	fwrite($f, "\t\t\t" . '<funcprototype>' . "\n");
	fwrite($f, "\t\t\t\t" . '<funcdef>' . 
		   ($func['static'] == 'yes' ? 'static ' : '') . 
		   ($func['const'] == 'yes' ? 'const ' : '') . 
		   $func['type'] . (substr($func['type'], -1) != '*' ? ' ' : '') .
		   '<function>' . $func['name'] . '</function>' .
		   '</funcdef>' . "\n");
	foreach($func['params'] as $param)
	{
		fwrite($f, "\t\t\t\t" . '<paramdef>' .
			   $param['type'] . (substr($param['type'], -1) != '*' ? ' ' : '') .
			   '<parameter>' . $param['name'] . '</parameter>' .
			   '</paramdef>' . "\n");
	}
	fwrite($f, "\t\t\t" . '</funcprototype>' . "\n");
	fwrite($f, "\t\t" . '</funcsynopsis>' . "\n");

	fwrite($f, "\t" . '</refsynopsisdiv>' . "\n");

	foreach($func['sections'] as $title => $body)
	{
		fwrite($f, "\t" . '<refsection>' . "\n");
		fwrite($f, "\t\t" . '<title>' . strtoupper($title) . '</title>' . "\n");
		if(is_array($body))
		{
			fwrite($f, implode('', $body) . "\n");
		}
		else
		{
			fwrite($f, $body . "\n");
		}
		fwrite($f, "\t" . '</refsection>' . "\n");
	}
	fwrite($f, '</refentry>' . "\n");
	fclose($f);
}
