<?php
/**
 * Capture ce que l'application rend, pour comparaison avant / apres migration.
 * Jetable : a supprimer avec le change sveltia-cms-emissions.
 *
 * S'execute DANS le conteneur, ou l'application tourne :
 *   docker exec -i ouiedire-srv php < bin/migration-capture.php
 *
 * Passe par le vrai getShow() et le vrai Twig : aucune reimplementation.
 * Les playlists sont rendues via playlist.html.twig, le meme partiel que les
 * pages publiques, donc la capture observe le meme point du code avant et apres.
 */
$debug = false;
require('/app/src/src/bootstrap.php');

$base = '/app/src/public/assets/emission';
$out  = '/app/.migration/avant';
if (!is_dir($out)) { mkdir($out, 0777, true); }

$slugs = array();
foreach (scandir($base) as $entry) {
    if ($entry[0] !== '.' && is_dir($base.'/'.$entry)) { $slugs[] = $entry; }
}
sort($slugs);

$handle = fopen($out.'/playlists.txt', 'w');
$ok = 0; $ko = array();
foreach ($slugs as $slug) {
    try {
        $show = getShow($slug);
    } catch (\Exception $e) {
        fwrite($handle, sprintf("# %s : ERREUR\n", $slug));
        $ko[] = $slug;
        continue;
    }
    $markup = $app['twig']->render('playlist.html.twig', array('show' => $show));
    fwrite($handle, sprintf("# %s\n%s\n", $slug, trim(preg_replace('/\s+/u', ' ', $markup))));
    $ok++;
}
fclose($handle);

// /artists?preview : tout le catalogue, publiques ou non.
$page = file_get_contents('http://127.0.0.1/artists?preview');
preg_match_all('/<li class="artist"[^>]*>(.*?)<\/li>/s', $page, $matches);
$artists = array();
foreach ($matches[1] as $raw) {
    $name = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES, 'UTF-8')));
    if ($name !== '') { $artists[$name] = true; }
}
$artists = array_keys($artists);
sort($artists);
file_put_contents($out.'/artists.txt', implode("\n", $artists)."\n");

echo sprintf("playlists : %d capturées, %d en erreur%s\n",
    $ok, count($ko), $ko ? ' ('.implode(', ', $ko).')' : '');
echo sprintf("artistes  : %d distincts\n", count($artists));
echo sprintf("-> %s\n", $out);
