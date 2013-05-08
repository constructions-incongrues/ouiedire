<?php
// Setup autoloading
require_once __DIR__.'/../../vendor/autoload.php';

// Configurre application
$app = new Silex\Application();
$app['debug'] = true;

// Twig setup
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

// Homepage
$app->get('/', function(Silex\Application $app) {
    return $app['twig']->render('home.twig.html');
});

// About page
$app->get('/apropos', function(Silex\Application $app) {
    return $app['twig']->render('apropos.twig.html');
});

// Links
$app->get('/liens', function(Silex\Application $app) {
    return $app['twig']->render('liens.twig.html');
});

// Shows list
$app->get('/emissions', function(Silex\Application $app) {
    return $app['twig']->render('emissions.twig.html');
});

// Show page
$app->get('/emission/{id}', function(Silex\Application $app, $id) {
    return $app['twig']->render(sprintf('emission/%d.twig.html', $id));
});

// Run application
$app->run();