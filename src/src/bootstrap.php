<?php
// Setup autoloading
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/audio.php';

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

// dump an array
function dump($array){
    echo '<pre>';print_r($array);echo '</pre>';
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

    if (is_array($show['playlist'])) {
        // Playlist structuree : l'artiste est porte par l'entree de type track.
        foreach ($show['playlist'] as $entry) {
            if (!isset($entry->kind) || $entry->kind !== 'track' || !isset($entry->artist)) {
                continue;
            }
            $artist = strtolower(trim($entry->artist));
            if (!empty($artist) && $artist!='intro' && $artist!='outro') {
                $artists[] = $artist;
            }
        }
    }
    if (isset($show['playlist'][0]->kind) && $show['playlist'][0]->kind === 'html') {
        // Playlist conservee verbatim : on parse le balisage, comme avant.
        $crawler = new Crawler();
        $crawler->addContent('<html><meta charset="utf-8" />'.$show['playlist'][0]->html);
        $domArtists = $crawler->filter('.mejs-smartplaylist-time + span');
        foreach ($domArtists as $domArtist) {
            $artist = strtolower(trim($domArtist->textContent));
            if (!empty($artist) && $artist!='intro' && $artist!='outro') {
                $artists[] = $artist;
            }
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
        $djs[] = trim($show['authors']);
    }
    $djs = array_unique($djs);
    return $djs;
}

function getDjsWithShows(array $shows)
{
    $djs = array();
    foreach ($shows as $show) {
        $djs[trim($show['authors'])][] = $show;
    }
    return $djs;
}

function getYears(array $shows)
{
    $years = array();
    foreach ($shows as $show) {
        $releasedAt = new DateTime($show['releasedAt']);
        $year = $releasedAt->format('Y');
        $years[$year] = $year;
    }
    $years = array_unique($years);
    $yearMin = min($years);
    $yearMax = max($years);
    return $yearMax-$yearMin;
}

function getYear($show)
{
    $releasedAt = new DateTime($show['releasedAt']);
    $year = $releasedAt->format('Y');
    return $year;
}

function getDuration()
{
    $seconds = file_get_contents('../public/duration');
    return round(intval($seconds) / 60 / 60);
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
function getShow($id, Silex\Application $app = null) {
    // Path to data directories
    $id = explode('-', $id);
    $pathPublic = __DIR__.'/../public';
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

    // Load show data. 404 if the data file cannot be loaded.
    $fileIndex = new SplFileObject(sprintf('%s/index.json', $pathPublicEmission));

    // Parse show data and infer show attributes
    $manifest = json_decode(file_get_contents($fileIndex->getRealPath()));
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
    } elseif ($show['type'] == 'bureau') {
        $show['type'] = 'Bureau';
    } else {
        $show['type'] = 'Ouïedire';
    }

  // Absolute URL to show assets
  if (isset($app['request'])) {
        $urlAssets = sprintf(
            '%s://%s%s/assets/emission/%s-%s',
            $app['request']->getScheme(),
            $app['request']->getHttpHost(),
            $app['request']->getBasePath(),
            $show['typeSlug'],
            $show['number']
        );
        $urlAssetsRoot = sprintf(
            '%s://%s%s/assets',
            $app['request']->getScheme(),
            $app['request']->getHttpHost(),
            $app['request']->getBasePath()
        );
    } else {
        $urlAssets = sprintf(
            'https://www.ouiedire.net/assets/emission/%s-%s',
            $show['typeSlug'],
            $show['number']
        );
        $urlAssetsRoot = 'https://www.ouiedire.net/assets';
    }

    // Guess show audio properties (MP3 and FLAC).
    // Le nom du fichier n'est pas une donnee : on balaye le dossier, la
    // convention historique d'abord. Voir le change audio-sans-convention.
    // Tout le calcul vit dans audio.php, la fusion et la regle de publication
    // comprises : ici, la suite n'entre pas et la jauge ne mesure rien. Les
    // slugs partent sous des cles NOMMEES — PHP 7.4 n'a pas d'arguments nommes,
    // et trois chaines de meme type a la file se permutent sans que rien ne le
    // voie. Le numero se lit dans $show, non slugifie.
    // Voir AudioDownloadsTest et ApplyAudioDownloadsTest.
    $show = applyAudioDownloads($show, $pathPublicEmission, $urlAssets, array(
        'type' => slugify($show['type']),
        'authors' => slugify($show['authors']),
        'title' => slugify($show['title']),
    ));

    $show['slugDownload'] = strtolower(sprintf('%s/ouiedire_%s-%s_%s_%s', $urlAssets, slugify($show['type']), $show['number'], slugify($show['authors']), slugify($show['title'])));

    // Guess covers URL. Toute image du dossier compte, pour qu'une couverture
    // deposee depuis l'outil d'edition soit vue quel que soit son nom. L'ordre
    // est deterministe et independant du systeme de fichiers : les fichiers
    // suivant la convention historique d'abord, alphabetiques dans chaque groupe.
    $show['covers'] = array();
    $finder = new Finder();
    try {
        $covers = $finder
            ->files()
            ->name('/\.(png|jpe?g|gif|webp)$/i')
            ->in($pathPublicEmission);
        // Trois groupes : la couverture au nom de cette emission, puis les
        // autres suivant la convention, puis le reste. Sans le premier groupe,
        // un fichier mal numerote gagnerait au tri (ailleurs-186 contient une
        // couverture nommee ailleurs-182).
        $own = sprintf('%s-%s_cover-', slugify($show['type']), $show['number']);
        $mine = array();
        $conventional = array();
        $others = array();
        foreach ($covers as $cover) {
            $name = basename($cover->getRealPath());
            if (strpos($name, $own) !== false) {
                $mine[] = $name;
            } elseif (strpos($name, '_cover-') !== false) {
                $conventional[] = $name;
            } else {
                $others[] = $name;
            }
        }
        sort($mine);
        sort($conventional);
        sort($others);
        foreach (array_merge($mine, $conventional, $others) as $name) {
            $show['covers'][] = sprintf('%s/%s', $urlAssets, $name);
        }
    } catch (\InvalidArgumentException $e) {
        // whatever
    }

    // og:image et le flux RSS accedent a covers[0] sans garde : il doit exister.
    if (empty($show['covers'])) {
        $show['covers'][] = sprintf('%s/img/cover-defaut.png', $urlAssetsRoot);
    }

    // Playlist : liste d'entrees structurees, ou fragment HTML conserve verbatim
    $show['playlist'] = $manifest->playlist;

    // Description
    $show['description'] = $manifest->description;

    // Pretty show number
    $show['id'] = $show['number'];
    if ($show['id'] < 10) {
        $show['number'] = '00'.$show['id'];
    } elseif ($show['id'] < 100) {
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
    $pathPublic = __DIR__.'/../public/assets';

    // Search for shows manifests
    $finder = new Finder();
    $finder = $finder
    ->files()
    ->name('index.json')
    ->filter(function(\SplFileInfo $file) {
        return
        strpos(basename(dirname($file->getRealPath())), 'ailleurs') !== false
        || strpos(basename(dirname($file->getRealPath())), 'bagage') !== false
        || strpos(basename(dirname($file->getRealPath())), 'bureau') !== false
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

    $manifests = $finder->in(sprintf('%s/emission/', $pathPublic));

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

// Configure application
$app = new Silex\Application();

// Twig setup
$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__.'/../views'
]);

// Named routes (@see http://silex.sensiolabs.org/doc/providers/url_generator.html)
$app->register(new Provider\UrlGeneratorServiceProvider());

$app['cache.max_age'] = 3600 * 24 * 90;
$app['cache.expires'] = 3600 * 24 * 90;
$app['cache.dir'] = __DIR__ . '/../cache';

use Silex\Provider\HttpCacheServiceProvider;

// Registers Symfony Cache component extension
$app->register(new HttpCacheServiceProvider(), array(
    'http_cache.cache_dir'  => $app['cache.dir'],
    'http_cache.options'    => array(
        'allow_reload'      => true,
        'allow_revalidate'  => true
)));

// Default cache values
$app['cache.defaults'] = array(
    'Cache-Control'     => sprintf('public, max-age=%d, s-maxage=%d, must-revalidate, proxy-revalidate', $app['cache.max_age'], $app['cache.max_age']),
    'Expires'           => date('r', time() + $app['cache.expires'])
);

// Debugging features
if (isset($debug) && $debug == true) {
    // Global debug flag
    $app['debug'] = true;
}

// Assets invalidator
$app['assetsVersion'] = time();

// Links
$app->get('/liens', function(Silex\Application $app) {
    $body = $app['twig']->render('liens.html.twig');

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('liens');

// Shows list
$app->get('/', function(Silex\Application $app, Request $request) {
    $artists = array();
    $shows = getShows($app, array_key_exists('preview', $_GET), $request->query->get('artist'));

    $showsGroupedByYear = array();
    foreach ($shows as $show) {
        $year = getYear($show);
        $showsGroupedByYear[$year][] = $show;
        $showArtists = getArtists($show);
        $artists = array_merge($artists, $showArtists);
        $artists = array_unique($artists);
        sort($artists);
    }

    // Render view
    $template_name = 'emissions.html.twig';

    $body = $app['twig']->render(
        $template_name,
        array(
            'artist'     => $request->query->get('artist'),
            'artists'    => $artists,
            'djs'        => getDjs($shows),
            'duration'   => getDuration(),
            'shows'      => $shows,
            'showsGroupedByYear' => $showsGroupedByYear,
            'years' => getYears($shows)
          )
    );

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('emissions');

// Jingle
$app->get('/jingle', function(Silex\Application $app) {
    // Render view
    $body = $app['twig']->render('jingle.html.twig', array('shows' => getShows($app, array_key_exists('preview', $_GET))));

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('jingle');

// Flyer
$app->get('/flyers', function(Silex\Application $app) {
    // Render view
    $body = $app['twig']->render('flyers.html.twig', array('shows' => getShows($app, array_key_exists('preview', $_GET))));

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('flyers');

// Random
$app->get('/random', function(Silex\Application $app) {
    // Render view
    $randomShow = getRandomShow($app);
    return $app->redirect($app['url_generator']->generate('emission', array('id' => $randomShow['id'], 'type' => $randomShow['typeSlug']), UrlGenerator::ABSOLUTE_URL));

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('random');

$app->get('/feed', function(Silex\Application $app) {
    // Get all shows
    $shows = getShows($app);

    // Configure feed
    $feed = new Feed();
    $feed->setTitle("Ouïedire, j'en ai déjà entendu parler quelque part");
    $feed->setDescription("Ouïedire est une web-radio à but non lucratif née en 2005. Elle a pour but de diffuser des émissions de musique en tout genre.");
    $feed->setLink($app['url_generator']->generate('emissions', array(), UrlGenerator::ABSOLUTE_URL));
    $feed->setFeedLink($app['url_generator']->generate('feed', array(), UrlGenerator::ABSOLUTE_URL), 'rss');
    $feed->addAuthor(array('name' => 'Ouïedire', 'email' => 'contact@ouiedire.net', 'uri', 'https://www.ouiedire.net'));
    try {
        $feed->setDateModified(DateTime::createFromFormat('Y-m-d H:i:s', $shows[0]['releasedAt']));
    } catch (InvalidArgumentException $e) {
        $feed->setDateModified(DateTime::createFromFormat('Y-m-d', $shows[0]['releasedAt']));
    }

    $feed->setImage(
        array(
            'uri'   => sprintf('%s://%s/%s/assets/img/logo_rss.png', $app['request']->getScheme(), $app['request']->getHttpHost(), $app['request']->getBasePath()),
            'title' => "Ouïedire, j'en ai déjà entendu parler quelque part.",
            'link'  => 'https://www.ouiedire.net'
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

        // Determine the preferred download link and type
        $enclosureUrl = $show['urlDownloadFlac'] ?: $show['urlDownloadMp3'];
        $enclosureType = $show['urlDownloadFlac'] ? 'audio/flac' : 'audio/mpeg';
        $enclosureLength = $show['urlDownloadFlac'] ? (int)$show['sizeDownloadFlac'] : (int)$show['sizeDownloadMp3'];

        // Build item full HTML content
        $htmlContent = <<<EOT
<img src="%s" />
%s
%s
<a href="%s">Télécharger l'émission</a>
EOT;
        $playlistHtml = $app['twig']->render('playlist.html.twig', array('show' => $show));
        $htmlContent = sprintf($htmlContent, $show['covers'][0], $show['description'], $playlistHtml, $enclosureUrl);

        // Build entry using show data
        $entry = $feed->createEntry();
        $entry->setTitle(sprintf('%s #%s : %s par %s', $show['type'], $show['number'], $show['title'], $show['authors']));
        $entry->setLink($app['url_generator']->generate('emission', array('id' => $show['id'], 'type' => $show['typeSlug']), UrlGenerator::ABSOLUTE_URL));
        if ($show['description']) {
            $entry->setDescription($show['description']);
        }
        $entry->setContent($htmlContent);
        $entry->addAuthor(array('name' => $show['authors']));
        try {
            $entry->setDateModified(DateTime::createFromFormat('Y-m-d H:i:s', $show['releasedAt']));
        } catch (InvalidArgumentException $e) {
            $entry->setDateModified(DateTime::createFromFormat('Y-m-d', $show['releasedAt']));
        }
        try {
            $entry->setDateCreated(DateTime::createFromFormat('Y-m-d H:i:s', $show['releasedAt']));
        } catch (InvalidArgumentException $e) {
            $entry->setDateCreated(DateTime::createFromFormat('Y-m-d', $show['releasedAt']));
        }
        $entry->setEnclosure(array('type' => $enclosureType, 'uri' => $enclosureUrl, 'length' => $enclosureLength));

        // Add entry to feed
        $feed->addEntry($entry);
    }

    return new Response($feed->export('rss'), 200, array('content-type' => 'application/rss+xml; charset=utf8'));
})->bind('feed');

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

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
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
        $view = 'embed.html.twig';
    } else {
        $view = 'emission.html.twig';
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
    $body = $app['twig']->render(
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

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('emission');


$app->get('/artists', function(Silex\Application $app, Request $request) {
    $shows = getShows($app, array_key_exists('preview', $_GET), $request->query->get('artist'));
    $artists = array();
    $showsGroupedByArtist = array();
    $showsGroupedByYear = array();
    foreach ($shows as $show) {        
        $year = getYear($show);
        $showsGroupedByYear[$year][] = $show;
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
    $body = $app['twig']->render(
        'artists.html.twig',
        array(
            'artists' => $artists,
            'artistsGroupedByAlpha' => $artistsGroupedByAlpha,
            'showsGroupedByArtist' => $showsGroupedByArtist,
            'showsGroupedByYear' => $showsGroupedByYear,
            'shows' => $shows,
            'djs' => getDjs($shows),
            'years' => getYears($shows),
            'duration' => getDuration()
        )
    );

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('artists');

// curator list
$app->get('/djs', function(Silex\Application $app, Request $request) {
    $shows = getShows($app, array_key_exists('preview', $_GET), $request->query->get('artist'));
    $artists = array();        
    $djs = array();
    $showsGroupedByYear = array();
    foreach ($shows as $show) {
        $year = getYear($show);
        $showsGroupedByYear[$year][] = $show;
        $showDjs = getDjs($shows);
        $djs = array_merge($djs, $showDjs);
        $djs = array_unique($djs);
        asort($djs);  
        $showArtists = getArtists($show);
        $artists = array_merge($artists, $showArtists);
        $artists = array_unique($artists);
        sort($artists);
    }
    // Group alphabeticaly
    $djsGroupedByAlpha = array();
    foreach ($djs as $k => $dj) {
        $djsGroupedByAlpha[strtolower(substr($dj, 0, 1))][] = $dj;        
    }
    //dump($showsGroupedByDj);
    // Render view
    $body = $app['twig']->render(
        'djs.html.twig',
        array(
            'artists' => $artists,
            'djsGroupedByAlpha' => $djsGroupedByAlpha,
            'duration' => getDuration(),
            'showsGroupedByDj' => getDjsWithShows($shows),
            'showsGroupedByYear' => $showsGroupedByYear,
            'shows' => $shows,
            'years' => getYears($shows),
        )
    );

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('djs');

// years
$app->get('/years', function(Silex\Application $app, Request $request) {
    $shows = getShows($app, array_key_exists('preview', $_GET), $request->query->get('artist'));
    $artists = array();
    $showsGroupedByYear = array();
    foreach ($shows as $show) {
        $year = getYear($show);
        $showsGroupedByYear[$year][] = $show;
        $showArtists = getArtists($show);
        $artists = array_merge($artists, $showArtists);
        $artists = array_unique($artists);
        sort($artists);
    }
    //dump($showsGroupedByYear);
    // Render view
    $body = $app['twig']->render(
        'years.html.twig',
        array(
            'artists' => $artists,
            'djs' => getDjs($shows),
            'duration' => getDuration(),
            'shows' => $shows,
            'showsGroupedByDj' => getDjsWithShows($shows),
            'showsGroupedByYear' => $showsGroupedByYear,
            'years' => getYears($shows)
        )
    );

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('years');

// Night mode
$app->get('/night', function(Silex\Application $app, Request $request) {
    $refererUrl = $_SERVER['HTTP_REFERER'];
    if(!isset($_COOKIE['night'])) setcookie("night", 1, time()+(10*365*24*60*60));
    else setcookie ("night", "", time()-3600);
    header('Location: '.$refererUrl);
    exit;

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('night');

// Dons
$app->get('/dons', function(Silex\Application $app) {
    // Render view
    $body = $app['twig']->render('dons.html.twig', array('shows' => getShows($app, array_key_exists('preview', $_GET))));

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('dons');

// Merci <3
$app->get('/merci', function(Silex\Application $app) {
    // Render view
    $body = $app['twig']->render('merci.html.twig', array('shows' => getShows($app, array_key_exists('preview', $_GET))));

    return new Response($body, 200, $app['debug'] ? array() : $app['cache.defaults']);
})
->bind('merci');
