/**
 * Autochange
 * 
 * Changes URL at the end of a media.
 */
(function($) {

  // Defaults
  $.extend(mejs.MepDefaults, {
    autochangeSelectorNextLink: 'a.mejs-autochange-next',
    autochangeQueryString: 'mejs-autochange-play'
  });

  $.extend(MediaElementPlayer.prototype, {
    buildautochange: function(player, controls, layers, media) {
      media.addEventListener('ended', function() {
        console.log($(player.options.autochangeSelectorNextLink));
        if ($(player.options.autochangeSelectorNextLink).length) {
          window.location = $(player.options.autochangeSelectorNextLink).attr('href') + '?' + player.options.autochangeQueryString;
        }
      });
    }
  });

})(mejs.$);
