#!/usr/bin/php
<?php
/**
 * Created by PhpStorm.
 * User: Michał Kowalik <maf.michal@gmail.com>
 * Date: 16.08.16 21:59
 */

require_once 'vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new \org\majkel\utility_scripts\Git\AddRefToCommitMessageCommand());
$application->run();
