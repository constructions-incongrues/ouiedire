$(document).ready(function() {
  // Titre des liens sur les timestamp des playlists
  $('a.a.mejs-smartplaylist-time').attr('title', 'Écouter ce morceau');

  // Configuration du player audio
  $('audio').mediaelementplayer(
    {
      // Enabled features
      features: ['playpause','progress','current','duration','tracks','volume','smartplaylist', 'googleanalytics', 'autochange'],

      // Smart playlists configuration
      smartplaylistLinkTitle: 'Écouter ce morceau',
      smartplaylistPositionQueryVar: 'position',
      smartplaylistPageTitleFormat:    '%timecode% - %title% | Ouïedire',
      smartplaylistPageTitleCallback:  function(currentTrack) {
        if (currentTrack.attr('title') == undefined) {
          var match = currentTrack.parent().text().match(/\d{2}:\d{2}:\d{2} (.*)/);
          if (match != null) {
            var trackTitle = match[1];
          }
        } else {
          var trackTitle = currentTrack.attr('title');
        }
        if (trackTitle == undefined) {
          return false;
        } else {
          return trackTitle;
        }
      },

      // Google Analytics integration
      googleAnalyticsTitle: 'Ouïedire.net',
      googleAnalyticsCategory: 'Émissions',
      
      // Autochange configuration
      autochangeSelectorNextLink: 'a.previous',
      autochangeQueryString: 'play',

      success: function(mediaElement) {
        // Autoplay
        if (window.MejsAutoplay) {
          mediaElement.play();
        }

        $(mediaElement).trigger('click');
      }
    }
  );

});
