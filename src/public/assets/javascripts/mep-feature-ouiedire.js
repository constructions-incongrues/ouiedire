/**
 * Ouiedire : Ajout des liens de téléchargement et de partage au player
 */
(function($) {

  // Defaults
  $.extend(mejs.MepDefaults, {
    ouiedireDownloadUrl: '#',
  });

  $.extend(MediaElementPlayer.prototype, {
    buildouiedire: function(player, controls, layers, media) {
      var html = 
        '<p class="download">' +
        '<a href="%downloadUrl%" title="Télécharger cette émission au format MP3">Télécharger</a>' +
        '<a href="#" title="Partager cette émission sur Facebook" onclick=" window.open(\'https://www.facebook.com/sharer/sharer.php?u=\'+encodeURIComponent(location.href), \'facebook-share-dialog\', \'width=626,height=436\'); return false;">Partager</a>' + 
        '</p>';
      html = html.replace(/%downloadUrl%/, player.options.ouiedireDownloadUrl);
      $(html).appendTo(controls);
    }
  });
  
})(mejs.$);