<?php

$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['DOCUMENT_ROOT'] = str_replace( 'deploy/tests', '', __DIR__ );
$_SERVER['SERVER_NAME'] = gethostname();
