/**
 * RSS Display - 管理画面JS
 */
( function ( $ ) {
	'use strict';

	$( function () {

		// ---- メディアライブラリ ----

		var frame;
		var $idInput  = $( '#rss_d_default_image_id' );
		var $preview  = $( '.rss-d-image-preview' );
		var $urlInput = $( '#rss_d_default_image_url' );

		$( '#rss_d_select_image' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) { frame.open(); return; }

			frame = wp.media( {
				title: ( window.rssDAdmin && rssDAdmin.chooseTitle ) || 'Select image',
				button: { text: ( window.rssDAdmin && rssDAdmin.chooseButton ) || 'Use this image' },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$idInput.val( attachment.id );
				var url = ( attachment.sizes && attachment.sizes.medium ) ? attachment.sizes.medium.url : attachment.url;
				$preview.html( $( '<img>', { src: url, alt: '' } ) );
				$urlInput.val( '' );
			} );

			frame.open();
		} );

		$( '#rss_d_clear_image' ).on( 'click', function ( e ) {
			e.preventDefault();
			$idInput.val( '' );
			$preview.empty();
		} );

		// ---- タブ切替 ----

		var $tabs   = $( '.rss-d-tab' );
		var $panels = $( '.rss-d-tab-panel' );

		$tabs.on( 'click', function () {
			var target = $( this ).data( 'tab' );
			$tabs.removeClass( 'active' ).attr( 'aria-selected', 'false' );
			$panels.removeClass( 'active' );
			$( this ).addClass( 'active' ).attr( 'aria-selected', 'true' );
			$( '[data-panel="' + target + '"]' ).addClass( 'active' );
			history.replaceState( null, '', '#' + target );
		} );

		// URLハッシュでタブ復元。
		var hash = window.location.hash.replace( '#', '' );
		if ( hash ) {
			var $target = $( '[data-tab="' + hash + '"]' );
			if ( $target.length ) { $target.trigger( 'click' ); }
		}

		// ---- プレビュー ----

		var $frame        = $( '#rss-d-preview-frame' );
		var $previewBtn   = $( '#rss-d-preview-btn' );
		var $deviceBtns   = $( '.rss-d-device-btn' );
		var $frameWrap    = $( '.rss-d-preview-frame-wrap' );
		var rsDisplayLoaded = false;

		// デバイス幅切替。
		$deviceBtns.on( 'click', function () {
			$deviceBtns.removeClass( 'active' );
			$( this ).addClass( 'active' );
			$frameWrap.css( 'max-width', $( this ).data( 'width' ) );
		} );

		// プレビュー更新。
		$previewBtn.on( 'click', function () {
			if ( ! window.rssDAdmin ) { return; }

			var type    = $( '#rss-d-preview-type' ).val();
			var columns = $( '#rss-d-preview-columns' ).val();
			var count   = $( '#rss-d-preview-count' ).val();

			$frame.html( '<p style="padding:2em;color:#888;">読み込み中…</p>' );

			$.post(
				rssDAdmin.ajaxUrl,
				{
					action      : 'rss_d_preview',
					_ajax_nonce : rssDAdmin.nonce,
					type        : type,
					columns     : columns,
					count       : count
				},
				function ( res ) {
					if ( ! res.success ) {
						$frame.html( '<p style="padding:2em;color:#c00;">取得に失敗しました。</p>' );
						return;
					}
					$frame.html( res.data.html );

					// rss-display.js は初回のみ取得してキャッシュ、以降はDOMに対して再初期化。
					if ( res.data.js_url ) {
						if ( ! rsDisplayLoaded ) {
							rsDisplayLoaded = true;
							$.ajax( { url: res.data.js_url, dataType: 'script', cache: true } );
						} else if ( typeof window.rssDInitAll === 'function' ) {
							window.rssDInitAll();
						}
					}
				}
			).fail( function () {
				$frame.html( '<p style="padding:2em;color:#c00;">通信エラーが発生しました。</p>' );
			} );
		} );

		// ---- ショートコードジェネレーター ----

		function buildShortcode() {
			var parts      = [ 'rss_display' ];
			var feed       = $.trim( $( '#rss-d-gen-feed' ).val() );
			var type       = $( '#rss-d-gen-type' ).val();
			var columns    = $( '#rss-d-gen-columns' ).val();
			var count      = $( '#rss-d-gen-count' ).val();
			var orderby    = $( '#rss-d-gen-orderby' ).val();
			var target     = $( '#rss-d-gen-target' ).val();
			var defType    = ( window.rssDAdmin && rssDAdmin.defaultType )    || 'grid';
			var defColumns = ( window.rssDAdmin && rssDAdmin.defaultColumns ) || '3';
			var defCount   = ( window.rssDAdmin && rssDAdmin.defaultCount )   || '6';

			if ( feed )                    { parts.push( 'feed="' + feed + '"' ); }
			if ( type && type !== defType )       { parts.push( 'type="' + type + '"' ); }
			if ( columns && columns !== defColumns ) { parts.push( 'columns="' + columns + '"' ); }
			if ( count && count !== defCount )    { parts.push( 'count="' + count + '"' ); }
			if ( orderby )               { parts.push( 'orderby="' + orderby + '"' ); }
			if ( target )                { parts.push( 'target="' + target + '"' ); }

			if ( ! $( '#rss-d-gen-date' ).prop( 'checked' ) ) { parts.push( 'date="0"' ); }
			if ( $( '#rss-d-gen-site' ).prop( 'checked' ) )   { parts.push( 'site="1"' ); }
			if ( $( '#rss-d-gen-desc' ).prop( 'checked' ) )   { parts.push( 'desc="1"' ); }
			if ( $( '#rss-d-gen-bold' ).prop( 'checked' ) )   { parts.push( 'bold="1"' ); }

			$( '#rss-d-gen-result' ).val( '[' + parts.join( ' ' ) + ']' );
		}

		$( '#rss-d-gen-feed, #rss-d-gen-type, #rss-d-gen-columns, #rss-d-gen-count, #rss-d-gen-orderby, #rss-d-gen-target' )
			.on( 'input change', buildShortcode );
		$( '#rss-d-gen-date, #rss-d-gen-site, #rss-d-gen-desc, #rss-d-gen-bold' )
			.on( 'change', buildShortcode );

		buildShortcode();

		$( '#rss-d-gen-copy' ).on( 'click', function () {
			var val = $( '#rss-d-gen-result' ).val();
			navigator.clipboard.writeText( val );
			var $msg = $( '#rss-d-gen-copy-msg' );
			$msg.stop( true ).fadeIn( 100 ).delay( 2000 ).fadeOut( 400 );
		} );

	} );
} )( jQuery );
