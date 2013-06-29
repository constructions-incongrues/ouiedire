$(document).ready(function() {
  $('a.a.mejs-smartplaylist-time').attr('title', 'Écouter ce morceau');
  $('audio').mediaelementplayer(
    {
      // Enabled features
      features: ['playpause','progress','current','duration','tracks','volume','smartplaylist', 'googleanalytics', 'autochange'],

      // Smart playlists configuration
      smartplaylistLinkTitle: 'Écouter ce morceau',
      smartplaylistPositionQueryVar: 'position',

      // Google Analytics integration
      googleAnalyticsTitle: 'Ouïedire.net',
      googleAnalyticsCategory: 'Émissions',
      
      // Autochange configuration
      autochangeSelectorNextLink: 'a.previous',
      autochangeQueryString: 'play',

      success: function(mediaElement) {
        // Autoplay
        if (window.play) {
          mediaElement.play();
        }
      }
    }
  );

});
