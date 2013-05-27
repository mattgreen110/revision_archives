<?php

if (! defined('RA_VER'))
{
	define('RA_NAME', 'Revision Archives');
	define('RA_VERSION',  '1.0');
	define('RA_DESC', 'This extension documents all entry revisions by generating a PDF on entry additions/edits using PDF Crowd');
	define('RA_DOCS', 'https://github.com/mattgreen110/revision_archives');
	define('RA_SETTINGS', 'y');
}

$config['name']    = RA_NAME;
$config['version'] = RA_VERSION;
