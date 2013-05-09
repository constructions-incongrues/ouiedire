<?php
// Setup autoloading
require_once __DIR__.'/../../vendor/autoload.php';

// Uses
use Silex\Provider;
use Symfony\Component\Finder\Finder;

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
	// Path to data directories
	$pathData = __DIR__.'/../data';

	// Search for shows manifests
	$finder = new Finder();
	$manifests = $finder
		->files()
		->name('manifest.json')
		->sortByName()
		->filter(function(\SplFileInfo $file) {
			return is_numeric(basename(dirname($file->getRealPath())));
		})
		->in(sprintf('%s/emission/', $pathData));

	// Parse manifests
	$shows = array();
	foreach ($manifests as $manifest) {
		$show = json_decode($manifest->getContents(), true);
		$show['id'] = basename(dirname($manifest->getRealPath()));
		if ($show['id'] < 10) {
			$show['number'] = '0'.$show['id'];
		} else {
			$show['number'] = $show['id'];
		}
		$shows[] = $show;
	}

	// Show last show first
	$shows = array_reverse($shows);

	// Render view
    return $app['twig']->render('emissions.twig.html', array('shows' => $shows));
})
->bind('emissions');

// Show page
$app->get('/emission/{id}', function(Silex\Application $app, $id) {
	// Path to data directories
	$pathData = __DIR__.'/../data';
	$pathDataEmission = sprintf('%s/emission/%d', $pathData, $id);

	// This variable describes the show will be passed to view
	$show = array(
		'authors' => null,
		'description' => null, 
		'number' => $id,
		'playlist' => null,
		'releasedAt' => null,
		'title' => null,
		'urlDownload' => null,
		'urlCover' => null,
		'urlCoverHd' => null
	);

	// Absolute URL to show assets
	$urlAssets = sprintf('%s/assets/emission/%d', $app['request']->getBaseUrl(), $id);

	// Load show data. 404 if some data file cannot be loaded.
	try {
		$fileManifest = new SplFileObject(sprintf('%s/manifest.json', $pathDataEmission, $id));
		$filePlaylist = new SplFileObject(sprintf('%s/playlist.html', $pathDataEmission));
		$fileDescription = new SplFileObject(sprintf('%s/description.html', $pathDataEmission));
	} catch (\Exception $e) {
		$app->abort(404, sprintf("L'émission #%d n'est pas disponible.", $id));
	}

	// Parse manifest data and infer show attributes
	$manifest = json_decode(file_get_contents($fileManifest->getRealPath()));
	$show['authors'] = $manifest->authors;
	$show['releasedAt'] = $manifest->releasedAt;
	$show['title'] = $manifest->title;

	// Guess show MP3 download URL
	$show['urlDownload'] = sprintf('%s/ouiedire_%d_%s.mp3', $urlAssets, $id, $manifest->slug);

	// Guess covers URL
	$show['urlCover'] = sprintf('%s/ouiedire_%d_cover.png', $urlAssets, $id);
	$show['urlCoverHd'] = sprintf('%s/ouiedire_%d_cover_hd.png', $urlAssets, $id);

	// Playlist
	$show['playlist'] = file_get_contents($filePlaylist->getRealPath());

	// Description
	$show['description'] = file_get_contents($fileDescription->getRealPath());

	// Render view
    return $app['twig']->render('emission.twig.html', array('show' => $show));
})
->bind('emission');

// Run application
$app->run();