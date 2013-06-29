
$(document).ready(function() {
  $('audio').mediaelementplayer(
    {
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

        // Update current track
        mediaElement.addEventListener('timeupdate', function() {
          $('a.time').each(function(i) {
            var times = $('a.time');
            var timeMin = parseInt($(this).data('seconds'));
            var timeMax = $(times[i + 1]).data('seconds');
            if (timeMax == undefined) {
              timeMax = 666666666666666; // L'infini, en moins glorieux
            }
            var timeCurrent = mediaElement.currentTime;
            if (timeCurrent > timeMin && timeCurrent < timeMax) {
              $('a.time').removeClass('current');
              $(this).addClass('current');
            }
          });
        });
      }
    }
  );

  // Analyze playlist timestamps
  $('a.time').each(function() {
    // Convert to seconds
    var $this = $(this);
    var seconds = 0;
    var parts = this.text.match(/(\d{2}):(\d{2}):(\d{2})/);
    seconds += parseInt(parts[1]) * 60 * 60;
    seconds += parseInt(parts[2]) * 60;
    seconds += parseInt(parts[3]);
    $this.attr('href', '?position=' + seconds);
    $this.data('seconds', seconds);
    if ($this.attr('title') == undefined) {
      $this.attr('title', 'Écouter ce morceau')
    }
  });

  // Clicking on a timestamps seeks to the appropriate time in mix
  $('a.time').on('click', function() {
    $('a.time').removeClass('current');
    $(this).addClass('current');
    var player = mejs.players.mep_0;
    if (player.media.paused) {
      window.position = $(this).data('seconds');
      mejs.players.mep_0.play();      
    } else {
      player.setCurrentTime($(this).data('seconds'));
    }

    return false;
  });
});
