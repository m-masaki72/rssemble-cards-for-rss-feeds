/**
 * Rssemble Cards for RSS Feeds - フロントエンドJS（カルーセル・モーダル）
 */
( function () {
	'use strict';

	// ---- カルーセル ----

	function initCarousel( wrap ) {
		var track   = wrap.querySelector( '.rss-d-carousel-track' );
		var cards   = wrap.querySelectorAll( '.rss-d-carousel-card' );
		var btnPrev = wrap.querySelector( '.rss-d-carousel-prev' );
		var btnNext = wrap.querySelector( '.rss-d-carousel-next' );

		if ( ! track || cards.length === 0 ) {
			return;
		}

		var current = 0;

		function getVisible() {
			return parseInt( getComputedStyle( wrap ).getPropertyValue( '--rss-d-columns' ) || '3', 10 );
		}

		function goTo( index ) {
			var visible = getVisible();
			var max     = Math.max( 0, cards.length - visible );
			current     = Math.min( Math.max( 0, index ), max );
			track.style.transform  = 'translateX(-' + ( 100 / visible * current ) + '%)';
			btnPrev.disabled       = ( current === 0 );
			btnNext.disabled       = ( current >= max );
		}

		btnPrev.addEventListener( 'click', function () { goTo( current - 1 ); } );
		btnNext.addEventListener( 'click', function () { goTo( current + 1 ); } );

		// タッチスワイプ。
		var touchStartX = 0;
		wrap.addEventListener( 'touchstart', function ( e ) {
			touchStartX = e.touches[0].clientX;
		}, { passive: true } );
		wrap.addEventListener( 'touchend', function ( e ) {
			var diff = touchStartX - e.changedTouches[0].clientX;
			if ( Math.abs( diff ) > 40 ) {
				goTo( diff > 0 ? current + 1 : current - 1 );
			}
		}, { passive: true } );

		// リサイズ時に位置を補正（デバウンス150ms）。
		var resizeTimer;
		window.addEventListener( 'resize', function () {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( function () { goTo( current ); }, 150 );
		} );

		goTo( 0 );
	}

	// ---- ポップアップ（モーダル） ----

	function initPopupGrid( grid ) {
		var modal = document.getElementById( grid.id + '-modal' );
		if ( ! modal ) {
			return;
		}

		var modalImg   = modal.querySelector( '.rss-d-modal-img' );
		var modalTitle = modal.querySelector( '.rss-d-modal-title' );
		var modalDesc  = modal.querySelector( '.rss-d-modal-desc' );
		var modalDate  = modal.querySelector( '.rss-d-modal-date' );
		var modalSite  = modal.querySelector( '.rss-d-modal-site' );
		var modalLink  = modal.querySelector( '.rss-d-modal-link' );
		var btnClose   = modal.querySelector( '.rss-d-modal-close' );
		var lastFocus  = null;

		var FOCUSABLE = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

		function getFocusables() {
			return Array.prototype.slice.call( modal.querySelectorAll( FOCUSABLE ) ).filter( function ( el ) {
				return ! el.disabled && ! el.closest( '[hidden]' );
			} );
		}

		function openModal( trigger ) {
			lastFocus          = trigger;
			modalImg.src       = trigger.dataset.image || '';
			modalImg.alt       = trigger.dataset.title || '';
			modalTitle.textContent = trigger.dataset.title || '';
			modalDesc.textContent  = trigger.dataset.desc  || '';
			modalDate.textContent  = trigger.dataset.date  || '';
			modalSite.textContent  = trigger.dataset.site  || '';

			var link   = trigger.dataset.link   || '';
			var newTab = trigger.dataset.newtab  === '1';
			if ( link ) {
				modalLink.href   = link;
				modalLink.target = newTab ? '_blank' : '_self';
				modalLink.rel    = newTab ? 'noopener noreferrer' : '';
				modalLink.style.display = '';
			} else {
				modalLink.style.display = 'none';
			}

			modal.hidden = false;
			document.body.style.overflow = 'hidden';
			btnClose.focus();
		}

		function closeModal() {
			modal.hidden = true;
			document.body.style.overflow = '';
			if ( lastFocus ) { lastFocus.focus(); }
		}

		grid.querySelectorAll( '.rss-d-popup-trigger' ).forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function () { openModal( trigger ); } );
		} );

		btnClose.addEventListener( 'click', closeModal );

		modal.addEventListener( 'click', function ( e ) {
			if ( e.target === modal ) { closeModal(); }
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( modal.hidden ) { return; }

			if ( e.key === 'Escape' || e.key === 'Esc' ) {
				closeModal();
				return;
			}

			// フォーカストラップ: Tab キーをモーダル内に閉じ込める。
			if ( e.key === 'Tab' ) {
				var focusables = getFocusables();
				if ( focusables.length === 0 ) { e.preventDefault(); return; }
				var first = focusables[ 0 ];
				var last  = focusables[ focusables.length - 1 ];
				if ( e.shiftKey ) {
					if ( document.activeElement === first ) { e.preventDefault(); last.focus(); }
				} else {
					if ( document.activeElement === last )  { e.preventDefault(); first.focus(); }
				}
			}
		} );
	}

	// ---- 初期化 ----

	function initAll() {
		document.querySelectorAll( '.rss-d-carousel-wrap' ).forEach( initCarousel );
		document.querySelectorAll( '.rss-d-type-popup_grid' ).forEach( initPopupGrid );
	}

	initAll();

	// 管理画面プレビューの再初期化用にエクスポート。
	window.rssDInitAll = initAll;
} )();
