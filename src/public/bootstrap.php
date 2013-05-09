<?php
// Setup autoloading
require_once __DIR__.'/../../vendor/autoload.php';

// Uses
use Silex\Provider;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Zend\Feed\Writer\Feed;

function getShows() {
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

	return $shows;
}

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

// Debugging features
if (isset($debug) && $debug = true) {
	// Global debug flag
	$app['debug'] = true;

	// Web Profiler (@see https://github.com/sensiolabs/Silex-WebProfiler)
	$app->register(new Provider\WebProfilerServiceProvider(), array(
	    'profiler.cache_dir' => __DIR__.'/../cache/profiler',
	    'profiler.mount_prefix' => '/_profiler', // this is the default
	));
}

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
	// Render view
    return $app['twig']->render('emissions.twig.html', array('shows' => getShows()));
})
->bind('emissions');

// Shows RSS feed (@see http://framework.zend.com/manual/2.1/en/modules/zend.feed.writer.html)
$app->get('/feed', function(Silex\Application $app) {
	// Get all shows
	$shows = getShows();

	// Configure feed
	$feed = new Feed();
	$feed->setTitle("Ouïedire, j'en ai déjà entendu parler quelque part");
	$feed->setDescription("Ouïedire est une web-radio à but non lucratif née en 2005. Elle a pour but de diffuser des émissions de musique en tout genre.");
	$feed->setLink($app['url_generator']->generate('homepage', array(), UrlGenerator::ABSOLUTE_URL));
	$feed->setFeedLink($app['url_generator']->generate('feed', array(), UrlGenerator::ABSOLUTE_URL), 'rss');
	$feed->addAuthor(array('name' => 'Ouïedire', 'email' => 'contact@ouiedire.net', 'uri', 'http://www.ouiedire.net'));
	$feed->setDateModified(DateTime::createFromFormat('Y-m-d H:i:s', $shows[0]['releasedAt']));

	// TODO
	// $feed->setImage();

	// Add feed items
	$i = 0;
	$maxEntries = 25;
	foreach ($shows as $show) {
		// Limit number of feed entries
		if (++$i > $maxEntries) {
			break;
		}

		// Build entry using show data
		$entry = $feed->createEntry();
		$entry->setTitle(sprintf('Ouïedire #%s : %s par %s', $show['number'], $show['title'], $show['authors']));
		// TODO : getShows() import playlist and description
		$entry->setDescription('TODO');
		$entry->setContent('TODO'); // content = image + description + playlist + link to show
		$entry->addAuthor(array('name' => $show['authors']));
		$entry->setDateModified(DateTime::createFromFormat('Y-m-d H:i:s', $show['releasedAt']));
		$entry->setDateCreated(DateTime::createFromFormat('Y-m-d H:i:s', $show['releasedAt']));
		// TODO
		// $entry->setEnclosure(array('type' => 'audio/mpeg', 'uri' => '', 'length' => ''));

		// Add entry to feed
		$feed->addEntry($entry);
	}

	return new Response($feed->export('rss'), 200, array('content-type' => 'application/rss+xml'));
})
->bind('feed');

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
	$urlAssets = sprintf('%s/assets/emission/%d', $app['request']->getBasePath(), $id);

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