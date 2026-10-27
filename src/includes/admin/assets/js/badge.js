(function () {
	window.requestAnimationFrame = window.requestAnimationFrame || window.mozRequestAnimationFrame || window.webkitRequestAnimationFrame || window.msRequestAnimationFrame;

	var field      = document.getElementById( 'bbp-badge' ),
		bee        = document.getElementById( 'bbp-bee'   ),

		max_x      = field.clientWidth  - bee.offsetWidth,
		max_y      = field.clientHeight - bee.offsetHeight,
		duration   = 8,
		start      = null,
		last_angle = null;

	if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}

	function step( timestamp ) {
		var progress, phase, buzz, x, y, angle;

		if ( start === null ) {
			start = timestamp;
		}

		progress = ( timestamp - start ) / duration / 1000;
		phase    = progress * 2 * Math.PI;
		buzz     = progress * 2 * Math.PI;

		// Keep the larger loop gentle and the hover quick but subtle.
		x = 68 * Math.sin( phase ) + 9 * Math.sin( 3 * phase ) + 1.25 * Math.sin( 64 * buzz );
		y = 52 * Math.cos( phase ) + 8 * Math.sin( 2 * phase ) + 1.25 * Math.cos( 61 * buzz );
		// Point the bee along the loop rather than letting it fly sideways.
		angle = Math.atan2(
			52 * Math.sin( phase ) - 16 * Math.cos( 2 * phase ),
			68 * Math.cos( phase ) + 27 * Math.cos( 3 * phase )
		) * 180 / Math.PI + 90 + 1.5 * Math.sin( 64 * buzz );

		if ( last_angle !== null ) {
			while ( angle - last_angle > 180 ) {
				angle -= 360;
			}

			while ( angle - last_angle < -180 ) {
				angle += 360;
			}
		}

		last_angle = angle;

		bee.style.left            = max_x / 2 + x + 'px';
		bee.style.bottom          = max_y / 2 + y + 'px';
		bee.style.transform       = 'rotate(' + angle + 'deg)';
		bee.style.webkitTransform = 'rotate(' + angle + 'deg)';

		if ( progress >= 1 ) {
			start = null;
			last_angle = null;
		}

		requestAnimationFrame( step );
	}

	requestAnimationFrame( step );
})();
