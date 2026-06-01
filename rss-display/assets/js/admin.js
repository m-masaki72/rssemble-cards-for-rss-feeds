/**
 * RSS Display - 管理画面JS（メディアライブラリ選択用）
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $idInput  = $( '#rss_d_default_image_id' );
		var $preview  = $( '.rss-d-image-preview' );
		var $urlInput = $( '#rss_d_default_image_url' );

		$( '#rss_d_select_image' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: ( window.rssDAdmin && rssDAdmin.chooseTitle ) || 'Select image',
				button: {
					text: ( window.rssDAdmin && rssDAdmin.chooseButton ) || 'Use this image'
				},
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$idInput.val( attachment.id );

				var url = attachment.url;
				if ( attachment.sizes && attachment.sizes.medium ) {
					url = attachment.sizes.medium.url;
				}

				$preview.html( $( '<img>', { src: url, alt: '' } ) );

				// メディア選択時はURL直接指定をクリアして ID を優先させる。
				$urlInput.val( '' );
			} );

			frame.open();
		} );

		$( '#rss_d_clear_image' ).on( 'click', function ( e ) {
			e.preventDefault();
			$idInput.val( '' );
			$preview.empty();
		} );
	} );
} )( jQuery );
