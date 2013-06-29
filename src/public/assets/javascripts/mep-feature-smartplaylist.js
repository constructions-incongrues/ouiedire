(function($) {

  // Defaults
  $.extend(mejs.MepDefaults, {
    smartplaylistCurrentClass:   'me-smartplaylist-current',
    smartplaylistLinkTitle:      'Listen to this track',
    smartplaylistSelector:       '.me-smartplaylist-playlist',
    smartplaylistTimestampRegex: '(\\d{2}):(\\d{2}):(\\d{2})',
    smartplaylistPositionQueryVar: 'me-smartplaylist-position',
    smartplaylistTimeFactors: { 
      1: 60 * 60, // Hours
      2: 60,      // Minutes
      3: 1        // Seconds
    }
  });

  $.extend(MediaElementPlayer.prototype, {
    buildsmartplaylist: function(player, controls, layers, media) {
      var $this = $(this)[0];
      var $playlist = $($this.options.smartplaylistSelector);
      var factors = $this.options.smartplaylistTimeFactors;

      // Analyze playlist timestamps
      $playlist.find('a.time').each(function() {
        // Convert to seconds
        var seconds = 0;
        var parts = this.text.match(new RegExp($this.options.smartplaylistTimestampRegex));
        seconds += parseInt(parts[1]) * factors['1'];
        seconds += parseInt(parts[2]) * factors['2'];
        seconds += parseInt(parts[3]) * factors['3'];
        $(this).attr('href', '?' + $this.options.smartplaylistPositionQueryVar + '=' + seconds);
        $(this).data('seconds', seconds);
        if ($(this).attr('title') == undefined) {
          $(this).attr('title', $this.options.smartplaylistLinkTitle);
        }
      });

      // Clicking on a timestamps seeks to the appropriate time in mix
      $playlist.find('a.time').on('click', function() {
        $playlist.find('a.time').removeClass($this.options.smartplaylistCurrentClass);
        $(this).addClass($this.options.smartplaylistCurrentClass);
        var player = mejs.players.mep_0;
        if (player.media.paused) {
          window.position = $(this).data('seconds');
          mejs.players.mep_0.play();      
        } else {
          player.setCurrentTime($(this).data('seconds'));
        }

        return false;
      });

      // Update current track
      media.addEventListener('timeupdate', function() {
        var times = $('a.time');
        times.each(function(i) {
          // Boundaries
          var timeMin = parseInt($(this).data('seconds'));
          var timeMax = $(times[i + 1]).data('seconds');
          if (timeMax == undefined) {
            timeMax = 666666666666666; // L'infini, en moins glorieux
          }
          var timeCurrent = media.currentTime;

          // Test if track is within boundaries
          if (timeCurrent > timeMin && timeCurrent < timeMax) {
            $('a.time').removeClass($this.options.smartplaylistCurrentClass);
            $(this).addClass($this.options.smartplaylistCurrentClass);
          }
        });
      }, false);
    }
  });

})(mejs.$);
