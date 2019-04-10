<?php
/**
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
