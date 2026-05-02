/**
 * Light Custom Code — Admin Scripts
 *
 * Handles: CodeMirror editor initialisation, editor theme switcher (dark/light),
 * CSS tab switching, unsaved-changes warning, and delete confirmation.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

/* global lccData, wp */

( function ( $ ) {
	'use strict';

	// -----------------------------------------------------------------------
	// Constants
	// -----------------------------------------------------------------------

	var THEME_KEY     = 'lcc_editor_theme'; // localStorage key
	var THEME_DARK    = 'dark';
	var THEME_LIGHT   = 'light';
	var CLASS_LIGHT   = 'lcc-editor-block--light';

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Parse a settings object that may have come in as a JSON string.
	 *
	 * @param  {string|Object} raw
	 * @return {Object}
	 */
	function parseSettings( raw ) {
		if ( typeof raw === 'object' && raw !== null ) {
			return raw;
		}
		try {
			return JSON.parse( raw );
		} catch ( e ) {
			return {};
		}
	}

	/**
	 * Read the saved theme from localStorage (defaults to dark).
	 *
	 * @return {string} 'dark' or 'light'
	 */
	function getSavedTheme() {
		try {
			var saved = localStorage.getItem( THEME_KEY );
			return saved === THEME_LIGHT ? THEME_LIGHT : THEME_DARK;
		} catch ( e ) {
			return THEME_DARK;
		}
	}

	/**
	 * Persist the theme choice to localStorage.
	 *
	 * @param {string} theme
	 */
	function saveTheme( theme ) {
		try {
			localStorage.setItem( THEME_KEY, theme );
		} catch ( e ) {
			// localStorage unavailable — no-op.
		}
	}

	// -----------------------------------------------------------------------
	// CodeMirror initialisation
	// -----------------------------------------------------------------------

	/** Map of textarea id → CodeMirror instance */
	var editors = {};
	var isDirty = false;

	/**
	 * Initialise a CodeMirror instance on a textarea element.
	 *
	 * @param  {HTMLTextAreaElement} textarea
	 * @param  {Object}             settings  wp.codeEditor settings object.
	 * @return {Object|null}
	 */
	function initEditor( textarea, settings ) {
		if ( ! textarea || ! settings || ! window.wp || ! wp.codeEditor ) {
			return null;
		}
		var editor = wp.codeEditor.initialize( textarea, settings );
		return editor && editor.codemirror ? editor.codemirror : null;
	}

	function initAllEditors() {
		if ( ! lccData ) {
			return;
		}

		var phpSettings  = parseSettings( lccData.phpSettings );
		var cssSettings  = parseSettings( lccData.cssSettings );
		var htmlSettings = parseSettings( lccData.htmlSettings );

		$( '.lcc-code-editor--php' ).each( function () {
			var cm = initEditor( this, phpSettings );
			if ( cm ) {
				editors[ this.id ] = cm;
				cm.on( 'change', markDirty );
			}
		} );

		$( '.lcc-code-editor--css' ).each( function () {
			var cm = initEditor( this, cssSettings );
			if ( cm ) {
				editors[ this.id ] = cm;
				cm.on( 'change', markDirty );
				$( this ).data( 'cm', cm );
			}
		} );

		$( '.lcc-code-editor--html' ).each( function () {
			var cm = initEditor( this, htmlSettings );
			if ( cm ) {
				editors[ this.id ] = cm;
				cm.on( 'change', markDirty );
			}
		} );
	}

	// -----------------------------------------------------------------------
	// Editor theme switcher
	// -----------------------------------------------------------------------

	/**
	 * Apply a theme to all .lcc-editor-block elements on the page and
	 * refresh all CodeMirror instances so they repaint at the correct size.
	 *
	 * @param {string}  theme    'dark' or 'light'
	 * @param {boolean} animate  Whether to allow CSS transitions (true after init).
	 */
	function applyTheme( theme ) {
		var isDark = theme === THEME_DARK;

		$( '.lcc-editor-block' ).each( function () {
			$( this ).toggleClass( CLASS_LIGHT, ! isDark );
		} );

		// Update toggle button labels.
		$( '.lcc-theme-toggle' ).each( function () {
			var $btn = $( this );
			if ( isDark ) {
				$btn.attr( 'title', 'Switch to light theme' );
				$btn.find( '.lcc-theme-toggle__icon' ).text( '☀' );
				$btn.find( '.lcc-theme-toggle__text' ).text( 'Light' );
			} else {
				$btn.attr( 'title', 'Switch to dark theme' );
				$btn.find( '.lcc-theme-toggle__icon' ).text( '☾' );
				$btn.find( '.lcc-theme-toggle__text' ).text( 'Dark' );
			}
		} );

		// Refresh CodeMirror instances so they repaint correctly.
		$.each( editors, function ( id, cm ) {
			if ( cm && cm.refresh ) {
				setTimeout( function () {
					cm.refresh();
				}, 20 );
			}
		} );
	}

	/**
	 * Build and inject a theme toggle button into every editor bar.
	 */
	function injectThemeToggles() {
		var currentTheme = getSavedTheme();
		var isDark       = currentTheme === THEME_DARK;

		$( '.lcc-editor-block__bar' ).each( function () {
			// Don't inject twice.
			if ( $( this ).find( '.lcc-theme-toggle' ).length ) {
				return;
			}

			var $btn = $(
				'<button type="button" class="lcc-theme-toggle">' +
					'<span class="lcc-theme-toggle__icon">' + ( isDark ? '☀' : '☾' ) + '</span>' +
					'<span class="lcc-theme-toggle__text">' + ( isDark ? 'Light' : 'Dark' ) + '</span>' +
				'</button>'
			);

			$btn.attr( 'title', isDark ? 'Switch to light theme' : 'Switch to dark theme' );
			$( this ).append( $btn );
		} );
	}

	function initThemeSwitcher() {
		// Inject toggle buttons into all editor bars.
		injectThemeToggles();

		// Apply the saved theme immediately (no transition on init).
		applyTheme( getSavedTheme() );

		// Toggle on click — delegate so dynamically added bars also work.
		$( document ).on( 'click', '.lcc-theme-toggle', function () {
			var current  = getSavedTheme();
			var newTheme = current === THEME_DARK ? THEME_LIGHT : THEME_DARK;
			saveTheme( newTheme );
			applyTheme( newTheme );
		} );
	}

	// -----------------------------------------------------------------------
	// CSS Tabs
	// -----------------------------------------------------------------------

	function initCssTabs() {
		var $nav     = $( '.lcc-css-tabs__nav' );
		var $buttons = $nav.find( '.lcc-css-tabs__btn' );

		if ( ! $buttons.length ) {
			return;
		}

		$buttons.on( 'click', function () {
			var $btn     = $( this );
			var targetId = $btn.attr( 'aria-controls' );

			$buttons.removeClass( 'lcc-css-tabs__btn--active' ).attr( 'aria-selected', 'false' );
			$btn.addClass( 'lcc-css-tabs__btn--active' ).attr( 'aria-selected', 'true' );

			$( '.lcc-css-tabs__panel' ).addClass( 'lcc-css-tabs__panel--hidden' );
			$( '#' + targetId ).removeClass( 'lcc-css-tabs__panel--hidden' );

			// Refresh CodeMirror editors in the now-visible panel.
			$( '#' + targetId ).find( '.lcc-code-editor--css' ).each( function () {
				var cm = $( this ).data( 'cm' );
				if ( cm ) {
					cm.refresh();
				}
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// Unsaved changes warning
	// -----------------------------------------------------------------------

	function markDirty() {
		isDirty = true;
	}

	function initUnsavedWarning() {
		$( '#lcc-snippet-form, #lcc-hf-form, #lcc-css-form' ).on(
			'change input',
			'input, textarea, select',
			markDirty
		);

		$( '#lcc-snippet-form, #lcc-hf-form, #lcc-css-form' ).on( 'submit', function () {
			isDirty = false;
		} );

		$( '.lcc-cancel-btn' ).on( 'click', function () {
			isDirty = false;
		} );

		$( window ).on( 'beforeunload', function () {
			if ( isDirty ) {
				return lccData.i18n.unsavedChanges;
			}
		} );
	}

	// -----------------------------------------------------------------------
	// Delete confirmation
	// -----------------------------------------------------------------------

	function initDeleteConfirmation() {
		$( document ).on( 'click', '.lcc-delete-snippet', function ( e ) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( lccData.i18n.confirmDelete ) ) {
				e.preventDefault();
			}
		} );
	}

	// -----------------------------------------------------------------------
	// Admin notices: auto-dismiss successes after 5 s
	// -----------------------------------------------------------------------

	function initNoticeDismiss() {
		setTimeout( function () {
			$( '.notice-success.is-dismissible' ).fadeOut( 400, function () {
				$( this ).remove();
			} );
		}, 5000 );
	}

	// -----------------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------------

	$( function () {
		initAllEditors();
		initThemeSwitcher();
		initCssTabs();
		initUnsavedWarning();
		initDeleteConfirmation();
		initNoticeDismiss();
		initCopyButtons();
	} );

}( jQuery ) );

	// -----------------------------------------------------------------------
	// Copy-to-clipboard (recovery URL)
	// -----------------------------------------------------------------------

	function initCopyButtons() {
		$( document ).on( 'click', '.lcc-copy-btn', function () {
			var $btn  = $( this );
			var text  = $btn.data( 'copy' );
			var $label = $btn.find( '.lcc-copy-btn__label' );

			if ( ! text ) {
				return;
			}

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then( function () {
					showCopied( $btn, $label );
				} );
			} else {
				// Fallback for non-HTTPS or older browsers.
				var $temp = $( '<textarea>' )
					.val( text )
					.css( { position: 'fixed', opacity: 0 } )
					.appendTo( 'body' );
				$temp[0].select();
				document.execCommand( 'copy' ); // eslint-disable-line no-deprecated
				$temp.remove();
				showCopied( $btn, $label );
			}
		} );
	}

	function showCopied( $btn, $label ) {
		var original = $label.text();
		$btn.addClass( 'lcc-copy-btn--copied' );
		$label.text( 'Copied!' );
		setTimeout( function () {
			$btn.removeClass( 'lcc-copy-btn--copied' );
			$label.text( original );
		}, 2000 );
	}
