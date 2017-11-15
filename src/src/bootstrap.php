<?php
// Setup autoloading
require_once __DIR__.'/../../vendor/autoload.php';

// Uses
use Silex\Provider;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Zend\Feed\Writer\Feed;

// @see http://stackoverflow.com/questions/23105925/calling-iconv-via-php-produces-different-results-in-apache-and-command-line
setlocale(LC_CTYPE, "en_US.utf8");

function truncateText($text, $maxLength) {
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
    }

    return $text;
}

function slugify($text)
{
    // @see http://stackoverflow.com/questions/23105925/calling-iconv-via-php-produces-different-results-in-apache-and-command-line
    setlocale(LC_CTYPE, "en_US.utf8");

    // replace non letter or digits by -
    $text = preg_replace('#[^\\pL\d]+#u', '-', $text);

    // trim
    $text = trim($text, '-');

    // transliterate
    if (function_exists('iconv')) {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    }

    // lowercase
    $text = strtolower($text);

    // remove unwanted characters
    $text = preg_replace('#[^-\w]+#', '', $text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}

/**
 * Returns artists list for a show
 *
 * @param array $show Show as returned by getShow function
 *
 * @return array $artists Show artists
 */
function getArtists(array $show)
{
    $artists = array();

    // Parse show playlist
    $crawler = new Crawler();
    $crawler->addContent('<html><meta charset="utf-8" />'.$show['playlist']);
    $domArtists = $crawler->filter('.mejs-smartplaylist-time + span');
    foreach ($domArtists as $domArtist) {
        $artist = strtolower(trim($domArtist->textContent));
        if (!empty($artist)) {
            $artists[] = strtolower(trim($domArtist->textContent));
        }
    }

    $artists = array_unique($artists);
    sort($artists);

    return $artists;
}

function getDjs(array $shows)
{
    $djs = array();
    foreach ($shows as $show) {
        $djs[] = $show['authors'];
    }

    $djs = array_unique($djs);

    return $djs;
}

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
function getShow($id, Silex\Application $app = null, $config = array()) {
    // Defaults
    $config = array_merge(array('assets_version' => time(), 'cdn_url' => ''));

    // Path to data directories
    $id = explode('-', $id);
    $pathData = __DIR__.'/../data';
    $pathPublic = __DIR__.'/../public';
    $pathDataEmission = sprintf('%s/emission/%s-%s', $pathData, $id[0], $id[1]);
    $pathPublicEmission = sprintf('%s/assets/emission/%s-%s', $pathPublic, $id[0], $id[1]);

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
        'urlCoverHd'  => null,
        'isPublic'    => false
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
    $show['isPublic'] = $manifest->isPublic;

    // Pretty show type
    $show['typeSlug'] = $show['type'];
    if ($show['type'] == 'ailleurs') {
        $show['type'] = 'Ailleurs';
    } elseif ($show['type'] == 'bagage') {
        $show['type'] = 'Bagage';
    } else {
        $show['type'] = 'Ouïedire';
    }

  // Absolute URL to show assets
    if ($app && empty($config['cdn_url'])) {
        $urlAssets = sprintf(
            '%s://%s%s/assets/emission/%s-%s',
            $app['request']->getScheme(),
            $app['request']->getHttpHost(),
            $app['request']->getBasePath(),
            $show['typeSlug'],
            $show['number']
        );
    } else {
        $urlAssets = sprintf(
            '%s/assets/emission/%s-%s',
            $config['cdn_url'],
            $show['typeSlug'],
            $show['number']
        );
    }

    // Guess show MP3 properties
    $show['urlDownload'] = strtolower(sprintf('%s/ouiedire_%s-%s_%s_%s.mp3', $urlAssets, slugify($show['type']), $show['number'], slugify($show['authors']), $manifest->slug));
    try {
        $fileMp3 = new SplFileInfo(sprintf('%s/ouiedire_%s-%s_%s_%s.mp3', $pathPublicEmission, slugify($show['type']), $show['number'], slugify($show['authors']), $manifest->slug));
        $show['sizeDownload'] = $fileMp3->getSize();
    } catch (\RuntimeException $e) {
        $show['sizeDownload'] = 1;
    }

    // Guess covers URL
    $show['covers'] = array();
    $finder = new Finder();
    try {
        $covers = $finder
        ->files()
        ->name('*_cover-*.*')
        ->in($pathPublicEmission);
        foreach ($covers as $cover) {
            $show['covers'][] = sprintf('%s/%s', $urlAssets, basename($cover->getRealPath()));
        }
    } catch (\InvalidArgumentException $e) {
        // whatever
    }

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

    return $show;
}

/**
 * Returns all available shows.
 *
 * @param Silex\Application $app
 *
 * @return array Shows (as returned by getShow())
 */
function getShows(Silex\Application $app, $preview = false, $artist = null) {
    // Path to data directories
    $pathData = __DIR__.'/../data';

    // Search for shows manifests
    $finder = new Finder();
    $finder = $finder
        ->files()
        ->name('manifest.json')
        ->filter(function(\SplFileInfo $file) {
            return
                strpos(basename(dirname($file->getRealPath())), 'ailleurs') !== false
                || strpos(basename(dirname($file->getRealPath())), 'bagage') !== false
                || strpos(basename(dirname($file->getRealPath())), 'ouiedire') !== false;
        })
        ->sort(function(\SplFileInfo $a, \SplFileInfo $b) {
            $dateA = strtotime(json_decode($a->getContents(), true)['releasedAt']);
            $dateB = strtotime(json_decode($b->getContents(), true)['releasedAt']);

            return $dateA > $dateB;
        });

    if ($artist !== null) {
        $finder->filter(function(\SplFileInfo $file) use ($artist) {
            return json_decode($file->getContents(), true)['authors'] === $artist;
        });
    }

    $manifests = $finder->in(sprintf('%s/emission/', $pathData));

    // Parse manifests
    $shows = array();
    foreach ($manifests as $manifest) {
        try {
            // In not in preview mode, only return public shows
            $show = getShow(basename(dirname($manifest->getRealPath())), $app);
            if ($show['isPublic'] === true || $preview === true) {
                $shows[] = $show;
            }
        } catch (\RuntimeException $e) {
            // Skip faulty shows
            continue;
        }
    }

    // Show last show first
    $shows = array_reverse($shows);

    return $shows;
}

function getShowSiblings($showCurrent, Silex\Application $app)
{
    $shows = getShows($app);
    $showPrevious = null;
    $showNext = null;
    for ($i = 0; $i < count($shows); $i++) {
        $show = $shows[$i];
        if (sprintf('%s-%s', $show['typeSlug'], $show['id']) == sprintf('%s-%s', $showCurrent['typeSlug'], $showCurrent['id'])) {
            if (isset($shows[$i + 1])) {
                $showPrevious = $shows[$i + 1];
            }
            if (isset($shows[$i - 1])) {
                $showNext = $shows[$i - 1];
            }
            break;
        }
    }
    return array($showPrevious, $showNext);
}

function getRandomShow(Silex\Application $app)
{
    $shows = getShows($app);
    return $shows[array_rand($shows)];
}

// Load config
$config = json_decode(file_get_contents(__DIR__.'/../config.json'), true);
if (false === $config) {
    throw new \RuntimeException('Impossible de charger la configuration - pathConfig='.__DIR__.'/../config.json');
}

// Configure application
$app = new Silex\Application();

// Twig setup
$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__.'/../views'
]);

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

// Assets invalidator
if ($app['debug'] == true) {
    $app['assetsVersion'] = time();
} else {
    $app['assetsVersion'] = $config['assets_version'];
}

// Links
$app->get('/liens', function(Silex\Application $app) {
    return $app['twig']->render('liens.twig.html');
})
->bind('liens');

// Shows list
$app->get('/', function(Silex\Application $app, Request $request) use ($config) {
    $artists = array();
    $shows = getShows($app, array_key_exists('preview', $_GET), $request->query->get('artist'));
    foreach ($shows as $show) {
        $showArtists = getArtists($show);
        $artists = array_merge($artists, $showArtists);
        $artists = array_unique($artists);
        sort($artists);
    }

  // Render view
    return $app['twig']->render(
        'emissions.twig.html',
        array(
            'artists'    => $artists,
            'shows'      => $shows,
            'djs'        => getDjs($shows),
            'randomShow' => $shows[array_rand($shows)],
            'artist'     => $request->query->get('artist')
          )
    );
})
->bind('emissions');

// Jingle
$app->get('/jingle', function(Silex\Application $app) {
    // Render view
    return $app['twig']->render('jingle.twig.html', array('shows' => getShows($app, array_key_exists('preview', $_GET))));
})
->bind('jingle');

// Flyer
$app->get('/flyers', function(Silex\Application $app) {
    // Render view
    return $app['twig']->render('flyers.twig.html', array('shows' => getShows($app, array_key_exists('preview', $_GET))));
})
->bind('flyers');


// Shows RSS feed (@see http://framework.zend.com/manual/2.1/en/modules/zend.feed.writer.html)
$app->get('/feed', function(Silex\Application $app) {
    // Get all shows
    $shows = getShows($app);

    // Configure feed
    $feed = new Feed();
    $feed->setTitle("Ouïedire, j'en ai déjà entendu parler quelque part");
    $feed->setDescription("Ouïedire est une web-radio à but non lucratif née en 2005. Elle a pour but de diffuser des émissions de musique en tout genre.");
    $feed->setLink($app['url_generator']->generate('emissions', array(), UrlGenerator::ABSOLUTE_URL));
    $feed->setFeedLink($app['url_generator']->generate('feed', array(), UrlGenerator::ABSOLUTE_URL), 'rss');
    $feed->addAuthor(array('name' => 'Ouïedire', 'email' => 'contact@ouiedire.net', 'uri', 'http://www.ouiedire.net'));
    $feed->setDateModified(DateTime::createFromFormat('Y-m-d H:i:s', $shows[0]['releasedAt']));

    // TODO
    $feed->setImage(
        array(
            'uri'   => sprintf('%s://%s/%s/assets/img/logo_rss.png', $app['request']->getScheme(), $app['request']->getHttpHost(), $app['request']->getBasePath()),
            'title' => "Ouïedire, j'en ai déjà entendu parler quelque part.",
            'link'  => 'http://www.ouiedire.net'
        )
    );

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
        $htmlContent = sprintf($htmlContent, $show['covers'][0], $show['description'], $show['playlist'], $show['urlDownload']);
        // Build entry using show data
        $entry = $feed->createEntry();
        $entry->setTitle(sprintf('%s #%s : %s par %s', $show['type'], $show['number'], $show['title'], $show['authors']));
        $entry->setLink($app['url_generator']->generate('emission', array('id' => $show['id'], 'type' => $show['typeSlug']), UrlGenerator::ABSOLUTE_URL));
        if ($show['description']) {
          $entry->setDescription($show['description']);
        }
        $entry->setContent($htmlContent);
        $entry->addAuthor(array('name' => $show['authors']));
        $entry->setDateModified(DateTime::createFromFormat('Y-m-d H:i:s', $show['releasedAt']));
        $entry->setDateCreated(DateTime::createFromFormat('Y-m-d H:i:s', $show['releasedAt']));
        $entry->setEnclosure(array('type' => 'audio/mpeg', 'uri' => $show['urlDownload'], 'length' => (int)$show['sizeDownload']));

        // Add entry to feed
        $feed->addEntry($entry);
    }

    return new Response($feed->export('rss'), 200, array('content-type' => 'application/rss+xml; charset=utf8'));
})
->bind('feed');

// oEmbed
$app->get('/oembed', function(Silex\Application $app) {
    // Fetch show
    try {
        $url = $app['request']->get('url');
        $matches = array();
        preg_match('/^.*(ouiedire|ailleurs)-(\d+)$/', $url, $matches);
        $show = getShow("$matches[1]-$matches[2]", $app);
    } catch (\RuntimeException $e) {
        if ($app['debug']) {
            throw $e;
        } else {
            $app->abort(404, sprintf("L'émission #%d n'est pas disponible.", $id));
        }
    }

    // Website FQDN
    $urlRoot = $app['request']->getScheme() . '://' . $app['request']->getHost() . $app['request']->getBasePath();

    // HTML for embedding
    $html = <<<EOT
<iframe width="500" height="600" scrolling="no" frameborder="no" src="%s?embed">
</iframe>
EOT;
    $html = sprintf($html, $app['url_generator']->generate('emission', array('id' => $show['id'], 'type' => $show['typeSlug']), UrlGenerator::ABSOLUTE_URL));

    $data = array(
        'version'       => 1,
        'type'          => 'rich',
        'provider_name' => 'ouiedire',
        'provider_url'  => $urlRoot,
        'height'        => 600,
        'width'         => 250,
        'title'         => sprintf('%s #%s - %s, par %s', $show['type'], $show['number'], $show['title'], $show['authors']),
        'description'   => $show['description'],
        'html'          => $html
    );

    if ($app['request']->get('callback')) {
        $responseBody = sprintf('%s(%s);', $app['request']->get('callback'), json_encode($data));
    } else {
        $responseBody = json_encode($data);
    }

    // Prepare response
    return new Response($responseBody, 200, array('content-type' => 'application/json; charset=utf8'));
})
->bind('oembed');

// Show page
$app->get('/emission/{type}-{id}', function(Silex\Application $app, Request $request, $type, $id) {
    // Fetch show
    try {
        $show = getShow("$type-$id", $app);
        $siblings = getShowSiblings($show, $app);
        $latest = getShows($app);
        $latest = $latest[0];
    } catch (\RuntimeException $e) {
        if ($app['debug']) {
            throw $e;
        } else {
            $app->abort(404, sprintf("L'émission #%d n'est pas disponible.", $id));
        }
    }

    // Player state defaults
    $player = array('play' => false, 'position' => false);

    // Start playing ?
    if (array_key_exists('play', $_GET)) {
        $player['play'] = true;
    }

    // Seek to position
    if (filter_input(INPUT_GET, 'position', FILTER_VALIDATE_INT)) {
        $player['position'] = filter_input(INPUT_GET, 'position');
    }

    // Choose view
    if (array_key_exists('embed', $_GET)) {
        $view = 'embed.twig.html';
    } else {
        $view = 'emission.twig.html';
    }

    // Facebook player
    // NOTE : it's not possible to use MediaElement.js Flash player because it forbids passing file as a query string parameter
    $urlSwfPlayer = sprintf(
        '%s%s/vendor/mediaplayer-5.9/player.swf?autostart=true&file=%s&image=%s&width=450&height=450',
        $app['request']->getHost(),
        $app['request']->getBasePath(),
        urlencode($show['urlDownload']),
        urlencode($show['covers'][0])
    );

    // Other shows by the same DJ
    $otherShows = getShows($app, array_key_exists('preview', $_GET), $show['authors']);
    $otherShows = array_filter($otherShows, function ($otherShow) use ($show) {
        return $show['title'] != $otherShow['title'];
    });

    // Render view
    return $app['twig']->render(
        $view,
        array(
            'latest'        => $latest,
            'next'          => $siblings[1],
            'player'        => $player,
            'previous'      => $siblings[0],
            'show'          => $show,
            'urlSwfPlayer'  => $urlSwfPlayer,
            'otherShows'    => $otherShows
        )
    );
})
->bind('emission');

$app->get('/artists', function(Silex\Application $app, Request $request) use ($config) {
    $shows = getShows($app, array_key_exists('preview', $_GET), $request->query->get('artist'));
    $artists = array();
    $showsGroupedByArtist = array();
    foreach ($shows as $show) {
        $showArtists = getArtists($show);
        $artists = array_merge($artists, $showArtists);
        $artists = array_unique($artists);
        sort($artists);

        // Group by shows by artist
        foreach ($showArtists as $artist) {
            $showsGroupedByArtist[strtolower($artist)][] = $show;
        }
    }

    // Group alphabeticaly
    $artistsGroupedByAlpha = array();
    foreach ($artists as $artist) {
        $artistsGroupedByAlpha[strtolower(substr($artist, 0, 1))][] = $artist;
    }

    // Render view
    return $app['twig']->render(
        'artists.twig.html',
        array(
            'artists' => $artists,
            'artistsGroupedByAlpha' => $artistsGroupedByAlpha,
            'showsGroupedByArtist' => $showsGroupedByArtist,
            'shows' => $shows,
            'djs' => getDjs($shows)
        )
    );
})
->bind('artists');

// Dons
$app->get('/dons', function(Silex\Application $app) {
    // Render view
    return $app['twig']->render('dons.twig.html', array('shows' => getShows($app, array_key_exists('preview', $_GET))));
})
->bind('dons');

// Merci <3
$app->get('/merci', function(Silex\Application $app) {
    // Render view
    return $app['twig']->render('merci.twig.html', array('shows' => getShows($app, array_key_exists('preview', $_GET))));
})
->bind('merci');
