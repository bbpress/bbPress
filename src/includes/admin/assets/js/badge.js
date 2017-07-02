(function () {
	window.requestAnimationFrame = window.requestAnimationFrame || window.mozRequestAnimationFrame || window.webkitRequestAnimationFrame || window.msRequestAnimationFrame;

	var field    = document.getElementById( 'bbp-badge' ),
		bee      = document.getElementById( 'bbp-bee'   ),

		max_x    = field.clientWidth  - bee.offsetWidth,
		max_y    = field.clientHeight - bee.offsetHeight,
		angle    = 95,
		offset   = 95,

		duration = 4,
		canvas   = 50,
		start    = null,
		variance = 1;

	function step( timestamp ) {
		var progress, x, y;

		if ( start === null ) {
			start    = timestamp;
			variance = 1;
			angle    = 95;
		}

		progress = ( timestamp - start ) / duration / 1000;
		angle    = ( 360 * progress ) + offset;

		x = variance * Math.sin( progress * 2 * Math.PI );
		y = Math.cos( progress * 2 * Math.PI );

		bee.style.left            = max_x / 2 + ( canvas * x ) + 'px';
		bee.style.bottom          = max_y / 2 + ( canvas * y ) + 'px';
		bee.style.transform       = 'rotate(' + angle + 'deg)';
		bee.style.webkitTransform = 'rotate(' + angle + 'deg)';

		// Reset
		if ( progress >= 1 ) {
			start = null;
		}

		requestAnimationFrame( step );
	}

	requestAnimationFrame( step );
})();
