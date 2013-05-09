<?php
// Setup autoloading
require_once __DIR__.'/../../vendor/autoload.php';

// Uses
use Silex\Provider;

// Configure application
$app = new Silex\Application();

// Twig setup
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

// Named routes (@see http://silex.sensiolabs.org/doc/providers/url_generator.html)
$app->register(new Provider\UrlGeneratorServiceProvider());

// Controller classes (required by Web Profiler - @see http://silex.sensiolabs.org/doc/providers/service_controller.html)
$app->register(new Provider\ServiceControllerServiceProvider());

// Debugging features. TODO : make does switchable

// Global debug flag
$app['debug'] = true;

// Web Profiler (@see https://github.com/sensiolabs/Silex-WebProfiler)
$app->register(new Provider\WebProfilerServiceProvider(), array(
    'profiler.cache_dir' => __DIR__.'/../cache/profiler',
    'profiler.mount_prefix' => '/_profiler', // this is the default
));

// Homepage
$app->get('/', function(Silex\Application $app) {
    return $app['twig']->render('home.twig.html');
})
->bind('homepage');

// About page
$app->get('/apropos', function(Silex\Application $app) {
    return $app['twig']->render('apropos.twig.html');
})
->bind('apropos');

// Links
$app->get('/liens', function(Silex\Application $app) {
    return $app['twig']->render('liens.twig.html');
})
->bind('liens');

// Shows list
$app->get('/emissions', function(Silex\Application $app) {
    return $app['twig']->render('emissions.twig.html');
})
->bind('emissions');

// Show page
$app->get('/emission/{id}', function(Silex\Application $app, $id) {
	// This variable describes the show will be passed to view
	$show = array(
		'authors' => null,
		'description' => null, 
		'images' => array('hd' => array(), 'sd' => array()),
		'number' => $id,
		'playlist' => null,
		'releasedAt' => null,
		'title' => null,
	);

    return $app['twig']->render('emission.twig.html', array('show' => $show));
})
->bind('emission');

// Run application
$app->run();