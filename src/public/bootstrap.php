<?php
// Setup autoloading
require_once __DIR__.'/../../vendor/autoload.php';

// Uses
use Silex\Provider;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Zend\Feed\Writer\Feed;

/**
 * Returns data about a show.
 *
 * @param integer $id Show ID
 * @param Silex\Application $app
 *
 * @return array The show
 *
 * @throws \RuntimeException When a show data file could not be loaded
 */
function getShow($id, Silex\Application $app) {
	// Path to data directories
	$id = explode('-', $id);
	$pathData = __DIR__.'/../data';
	$pathPublic = __DIR__.'/../public';
	$pathDataEmission = sprintf('%s/emission/%s-%d', $pathData, $id[0], $id[1]);
	$pathPublicEmission = sprintf('%s/assets/emission/%s-%d', $pathPublic, $id[0], $id[1]);

	// This variable describes the show will be passed to view
	$show = array(
		'authors'     => null,
		'description' => null, 
		'number'      => $id[1],
		'type'        => $id[0],
		'playlist'    => null,
		'releasedAt'  => null,
		'title'       => null,
		'urlDownload' => null,
		'urlCover'    => null,
		'urlCoverHd'  => null
	);

	// Absolute URL to show assets
	$urlAssets = sprintf(
		'%s://%s%s/assets/emission/%d', 
		$app['request']->getScheme(), 
		$app['request']->getHttpHost(), 
		$app['request']->getBasePath(), 
		$show['number']
	);

	// Load show data. 404 if some data file cannot be loaded.
	$fileManifest = new SplFileObject(sprintf('%s/manifest.json', $pathDataEmission));
	$filePlaylist = new SplFileObject(sprintf('%s/playlist.html', $pathDataEmission));
	$fileDescription = new SplFileObject(sprintf('%s/description.html', $pathDataEmission));

	// Parse manifest data and infer show attributes
	$manifest = json_decode(file_get_contents($fileManifest->getRealPath()));
	$show['authors'] = $manifest->authors;
	$show['releasedAt'] = $manifest->releasedAt;
	$show['title'] = $manifest->title;

	// Guess show MP3 properties
	try {
		$fileMp3 = new SplFileInfo(sprintf('%s/ouiedire_%s-%d_%s.mp3', $pathPublicEmission, $show['type'], $show['number'], $manifest->slug));
		$show['sizeDownload'] = $fileMp3->getSize();
		$show['urlDownload'] = sprintf('%s/ouiedire_%s-%d_%s.mp3', $urlAssets, $show['type'], $show['number'], $manifest->slug);
	} catch (\RuntimeException $e) {
		$show['urlDownload'] = null;
	}

	// Guess covers URL
	$show['urlCover'] = sprintf('%s/ouiedire_%s-%d_cover.png', $urlAssets, $show['type'], $show['number']);
	$show['urlCoverHd'] = sprintf('%s/ouiedire_%s-%d_cover_hd.png', $urlAssets, $show['type'], $show['number']);

	// Playlist
	$show['playlist'] = file_get_contents($filePlaylist->getRealPath());

	// Description
	$show['description'] = file_get_contents($fileDescription->getRealPath());

	// Pretty show number
	$show['id'] = $show['number'];
	if ($show['id'] < 10) {
		$show['number'] = '0'.$show['id'];
	} else {
		$show['number'] = $show['id'];
	}

	// Pretty show type
	$show['typeSlug'] = $show['type'];
	if ($show['type'] == 'ailleurs') {
		$show['type'] = 'Ailleurs';
	} else {
		$show['type'] = 'Ouïedire';
	}

	return $show;
}

/**
 * Returns all available shows.
 *
 * @param Silex\Application $app
 * 
 * @return array Shows (as returned by getShow())
 */
function getShows(Silex\Application $app) {
	// Path to data directories
	$pathData = __DIR__.'/../data';

	// Search for shows manifests
	$finder = new Finder();
	$manifests = $finder
		->files()
		->name('manifest.json')
		->sortByName()
		->filter(function(\SplFileInfo $file) {
			return 
				strpos(basename(dirname($file->getRealPath())), 'ailleurs') !== false
				|| strpos(basename(dirname($file->getRealPath())), 'ouiedire') !== false;
		})
		->in(sprintf('%s/emission/', $pathData));

	// Parse manifests
	$shows = array();
	foreach ($manifests as $manifest) {
		try {
			$shows[] = getShow(basename(dirname($manifest->getRealPath())), $app);
		} catch (\RuntimeException $e) {
			// Skip faulty shows
			continue;
		}
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
if (isset($debug) && $debug == true) {
	// Global debug flag
	$app['debug'] = true;

	// Web Profiler (@see https://github.com/sensiolabs/Silex-WebProfiler)
	$app->register(new Provider\WebProfilerServiceProvider(), array(
	    'profiler.cache_dir' => __DIR__.'/../cache/profiler',
	    'profiler.mount_prefix' => '/_profiler', // this is the default
	));
}

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
$app->get('/', function(Silex\Application $app) {
	// Render view
    return $app['twig']->render('emissions.twig.html', array('shows' => getShows($app)));
})
->bind('emissions');

// Shows RSS feed (@see http://framework.zend.com/manual/2.1/en/modules/zend.feed.writer.html)
$app->get('/feed', function(Silex\Application $app) {
	// Get all shows
	$shows = getShows($app);

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

		// Build item full HTML content
		$htmlContent = <<<EOT
<img src="%s" />
%s
%s
<a href="%s">Télécharger l'émission</a>
EOT;
		$htmlContent = sprintf($htmlContent, $show['urlCover'], $show['description'], $show['playlist'], $show['urlDownload']);

		// Build entry using show data
		$entry = $feed->createEntry();
		$entry->setTitle(sprintf('Ouïedire #%s : %s par %s', $show['number'], $show['title'], $show['authors']));
		$entry->setLink($app['url_generator']->generate('emission', array('id' => $show['id'], 'type' => $show['typeSlug']), UrlGenerator::ABSOLUTE_URL));
		$entry->setDescription($show['description']);
		$entry->setContent($htmlContent);
		$entry->addAuthor(array('name' => $show['authors']));
		$entry->setDateModified(DateTime::createFromFormat('Y-m-d H:i:s', $show['releasedAt']));
		$entry->setDateCreated(DateTime::createFromFormat('Y-m-d H:i:s', $show['releasedAt']));
		if ($show['urlDownload']) {
			$entry->setEnclosure(array('type' => 'audio/mpeg', 'uri' => $show['urlDownload'], 'length' => $show['sizeDownload']));
		}

		// Add entry to feed
		$feed->addEntry($entry);
	}

	return new Response($feed->export('rss'), 200, array('content-type' => 'application/rss+xml; charset=utf8'));
})
->bind('feed');

// Show page
$app->get('/emission/{type}-{id}', function(Silex\Application $app, $type, $id) {
	// Fetch show
	try {
		$show = getShow("$type-$id", $app);
	} catch (\RuntimeException $e) {
		if ($app['debug']) {
			throw $e;
		} else {
			$app->abort(404, sprintf("L'émission #%d n'est pas disponible.", $id));
		}
	}

	// Render view
    return $app['twig']->render('emission.twig.html', array('show' => $show));
})
->bind('emission');

// Run application
$app->run();