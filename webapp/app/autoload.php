<?php
/**
 * Generated from 'autoload.php.in' on Tue Apr  9 12:41:46 CEST 2019.
 *
 * Autoload setup file for the Symfony application
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

use Doctrine\Common\Annotations\AnnotationRegistry;
use Composer\Autoload\ClassLoader;

/** @var ClassLoader $loader */
$loader = require LIBVENDORDIR.'/autoload.php';

AnnotationRegistry::registerLoader([$loader, 'loadClass']);

return $loader;
