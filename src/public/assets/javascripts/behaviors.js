$(document).ready(function() {
  $('audio').mediaelementplayer(
    {
      features: ['playpause','progress','current','duration','tracks','volume','smartplaylist'],
      smartplaylistLinkTitle: 'Écouter ce morceau',
      smartplaylistPositionQueryVar: 'position',
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
