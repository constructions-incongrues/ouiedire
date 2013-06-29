$(document).ready(function() {
  $('audio').mediaelementplayer(
    {
      // Enabled features
      features: ['playpause','progress','current','duration','tracks','volume','smartplaylist', 'googleanalytics'],

      // Smart playlists configuration
      smartplaylistLinkTitle: 'Écouter ce morceau',
      smartplaylistPositionQueryVar: 'position',

      // Google Analytics integration
      googleAnalyticsTitle: 'Ouïedire.net',
      googleAnalyticsCategory: 'Émissions'
      
      success: function(mediaElement) {
        // Autoplay
        if (window.play) {
          mediaElement.play();
        }

        // Autoplay previous mix
        mediaElement.addEventListener('ended', function() {
          if ($('a.previous').length) {
            window.location = $('a.previous').attr('href') + '?play';
          }
        });

        // Used when seeking asked and player has not started yet
        mediaElement.addEventListener('playing', function() {
          if (window.position) {
            mediaElement.setCurrentTime(window.position);
          }
        });
      }
    }
  );

});
