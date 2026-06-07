/**
 * Rssemble Cards for RSS Feeds - 管理画面JS
 */
( function ( $ ) {
	'use strict';

	$( function () {

		// ---- メディアライブラリ ----

		var frame;
		var $idInput  = $( '#rssecafo_default_image_id' );
		var $preview  = $( '.rss-d-image-preview' );
		var $urlInput = $( '#rssecafo_default_image_url' );

		$( '#rssecafo_select_image' ).on( 'click', function ( e ) {
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

		$( '#rssecafo_clear_image' ).on( 'click', function ( e ) {
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

		var $frame                 = $( '#rss-d-preview-frame' );
		var $previewShortcode      = $( '#rss-d-preview-shortcode' );
		var $previewShortcodeText  = $( '#rss-d-preview-shortcode-text' );
		var $previewShortcodeCopy  = $( '#rss-d-preview-shortcode-copy' );
		var $previewBtn            = $( '#rss-d-preview-btn' );
		var $deviceBtns       = $( '.rss-d-device-btn' );
		var $frameWrap        = $( '.rss-d-preview-frame-wrap' );
		var rsDisplayLoaded   = false;

		// デバイス幅切替。
		$deviceBtns.on( 'click', function () {
			$deviceBtns.removeClass( 'active' );
			$( this ).addClass( 'active' );
			$frameWrap.css( 'max-width', $( this ).data( 'width' ) );
		} );

		// プレビュー更新。
		$previewBtn.on( 'click', function () {
			if ( ! window.rssDAdmin ) { return; }

			var type        = $( '#rss-d-preview-type' ).val();
			var columns     = $( '#rss-d-preview-columns' ).val();
			var count       = $( '#rss-d-preview-count' ).val();
			var responsive  = $( '#rss-d-preview-responsive' ).is( ':checked' ) ? '1' : '0';
			var newTab      = $( '#rss-d-preview-new-tab' ).is( ':checked' ) ? '1' : '0';
			var showDesc    = $( '#rss-d-preview-show-desc' ).is( ':checked' ) ? '1' : '0';
			var showDate    = $( '#rss-d-preview-show-date' ).is( ':checked' ) ? '1' : '0';
			var showSite    = $( '#rss-d-preview-show-site' ).is( ':checked' ) ? '1' : '0';
			var boldTitle   = $( '#rss-d-preview-bold-title' ).is( ':checked' ) ? '1' : '0';
			var titleLines  = $( '#rss-d-preview-title-lines' ).val();

			$frame.html( '<p style="padding:2em;color:#888;">' + rssDAdmin.msgLoading + '</p>' );

			$.post(
				rssDAdmin.ajaxUrl,
				{
					action       : 'rssecafo_preview',
					_ajax_nonce  : rssDAdmin.nonce,
					type         : type,
					columns      : columns,
					count        : count,
					responsive   : responsive,
					new_tab      : newTab,
					show_desc    : showDesc,
					show_date    : showDate,
					show_site    : showSite,
					bold_title   : boldTitle,
					title_lines  : titleLines
				},
				function ( res ) {
					if ( ! res.success ) {
						$frame.html( '<p style="padding:2em;color:#c00;">' + rssDAdmin.msgError + '</p>' );
						$previewShortcode.hide();
						return;
					}
					$frame.html( res.data.html );

					// ショートコード表示（デフォルト値と同じ場合は省略）。
					var sc = '[rssecafo type="' + type + '" columns="' + columns + '" count="' + count + '"';
					if ( '0' === responsive )  { sc += ' responsive="0"'; }
					if ( '1' === newTab )      { sc += ' target="_blank"'; }
					if ( '1' === showDesc )    { sc += ' desc="1"'; }
					if ( '0' === showDate )    { sc += ' date="0"'; }
					if ( '1' === showSite )    { sc += ' site="1"'; }
					if ( '1' === boldTitle )   { sc += ' bold="1"'; }
					if ( '2' !== titleLines )  { sc += ' title_lines="' + titleLines + '"'; }
					sc += ']';
					$previewShortcodeText.text( sc );
					$previewShortcode.css( 'display', 'flex' );

					// rssemble-cards-for-rss-feeds.js は初回のみ取得してキャッシュ、以降はDOMに対して再初期化。
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
				$frame.html( '<p style="padding:2em;color:#c00;">' + rssDAdmin.msgNetError + '</p>' );
			} );
		} );

		$previewShortcodeCopy.on( 'click', function () {
			var text = $previewShortcodeText.text();
			if ( text ) {
				navigator.clipboard.writeText( text );
				var $btn = $( this );
				$btn.text( rssDAdmin.btnCopied );
				setTimeout( function () { $btn.text( rssDAdmin.btnCopy ); }, 2000 );
			}
		} );

	} );
} )( jQuery );
