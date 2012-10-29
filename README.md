SmallTAL
========

A php template engine based on TAL, an attribute-based template engine.

There is at least two other php tal implementation (PHPTAL, for example), but
this implementation was written thinking about size and speed; tal templates
are converted into php code (without the inner data), allowing to reuse them
without the need of future conversion.
Each time a template is requested, if the template/metal file/smalltal source
was modified after the template compilation, the library recompile the template
into php code.
The template engine isn't an html parser/beautifier, so won't correct your
template.

It's licensed under a BSD-like license.


Using
-----
To use SmallTAL you need to do:

1) Create a temp directory, a cache directory and a template directory

2) Template directory should have this structure:
| Templates
+--  TemplateName.metal.html < macros for the template
+--| TemplateName
   +-- PageName.html

3) Use the library in your project, for example:

include( 'SmallTAL.php' );
$data_required_by_the_template = array
(
	'variabile' => 'a value',
	'arrayVariable' => array
	(
		'some data',
		'some other data'
	)
);
$smallTALpaths = array
(
	'temp' => '/path/to/temp/directory',
	'template' => '/path/to/the/template/directory',
	'cache' => '/path/where/cache/file/will/be/stored'
);

$returnValue = SmallTAL( "TemplateName", "PageName", $data_required_by_the_template, $smallTALpaths );

If you want to obtain the template in a variable, you should use ob_start()/ob_get_clean().

You can look the SmallTAL.Tests.php (it's a PHPUnit test case) file and/or the tests folder. 


Unsupported TAL/TALES features
------------------------------
This is a minimal TAL template engine, optional features isn't supported.
Also on-error isn't supported.


Usefull links
-------------
TAL specification: http://wiki.zope.org/ZPT/TALSpecification14
TALES specification: http://wiki.zope.org/ZPT/TALESSpecification13
METAL specification: http://wiki.zope.org/ZPT/METALSpecification10
Owlfish TAL, TALES and METAL reference: http://www.owlfish.com/software/simpleTAL/tal-guide.html
