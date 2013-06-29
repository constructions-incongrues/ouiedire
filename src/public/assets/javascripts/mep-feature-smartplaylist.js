/**
 * Smart playlists
 * 
 * Links a timestamped playlist to player states.
 */
(function($) {

  // Defaults
  $.extend(mejs.MepDefaults, {
    smartplaylistCurrentClass:       'mejs-smartplaylist-current',
    smartplaylistLinkTitle:          'Listen to this track',
    smartplaylistSelectorTimestamp:  'a.mejs-smartplaylist-time',
    smartplaylistSelectorPlaylist:   '.mejs-smartplaylist-playlist',
    smartplaylistTimestampRegex:     '(\\d{2}):(\\d{2}):(\\d{2})',
    smartplaylistPositionQueryVar:   'mejs-smartplaylist-position',
    smartplaylistTimeFactors: { 
      1: 60 * 60, // Hours
      2: 60,      // Minutes
      3: 1        // Seconds
    }
  });

  $.extend(MediaElementPlayer.prototype, {
    buildsmartplaylist: function(player, controls, layers, media) {
      var $this = $(this)[0];
      var $playlist = $($this.options.smartplaylistSelectorPlaylist);
      var factors = $this.options.smartplaylistTimeFactors;

      // Analyze playlist timestamps
      $playlist.find($this.options.smartplaylistSelectorTimestamp).each(function() {
        // Convert to seconds
        var seconds = 0;
        var parts = this.text.match(new RegExp($this.options.smartplaylistTimestampRegex));
        seconds += parseInt(parts[1]) * factors['1'];
        seconds += parseInt(parts[2]) * factors['2'];
        seconds += parseInt(parts[3]) * factors['3'];
        $(this).attr('href', '?' + $this.options.smartplaylistPositionQueryVar + '=' + seconds);
        $(this).data('mejs-smartplaylist-seconds', seconds);
        if ($(this).attr('title') == undefined) {
          $(this).attr('title', $this.options.smartplaylistLinkTitle);
        }
      });

      // Clicking on a timestamps seeks to the appropriate time in mix
      $playlist.find($this.options.smartplaylistSelectorTimestamp).on('click', function() {
        $playlist.find($this.options.smartplaylistSelectorTimestamp).removeClass($this.options.smartplaylistCurrentClass);
        $(this).addClass($this.options.smartplaylistCurrentClass);
        var player = mejs.players.mep_0;
        if (player.media.paused) {
          window.position = $(this).data('mejs-smartplaylist-seconds');
          mejs.players.mep_0.play();      
        } else {
          player.setCurrentTime($(this).data('mejs-smartplaylist-seconds'));
        }

        return false;
      });

      // Update current track
      media.addEventListener('timeupdate', function() {
        var times = $($this.options.smartplaylistSelectorTimestamp);
        times.each(function(i) {
          // Boundaries
          var timeMin = parseInt($(this).data('mejs-smartplaylist-seconds'));
          var timeMax = $(times[i + 1]).data('mejs-smartplaylist-seconds');
          if (timeMax == undefined) {
            timeMax = 666666666666666; // L'infini, en moins glorieux
          }
          var timeCurrent = media.currentTime;

          // Test if track is within boundaries
          if (timeCurrent > timeMin && timeCurrent < timeMax) {
            $($this.options.smartplaylistSelectorTimestamp).removeClass($this.options.smartplaylistCurrentClass);
            $(this).addClass($this.options.smartplaylistCurrentClass);
          }
        });
      }, false);
    }
  });

})(mejs.$);
