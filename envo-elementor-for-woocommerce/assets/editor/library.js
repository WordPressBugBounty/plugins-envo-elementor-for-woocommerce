/**
 * Envo Templates in the Elementor editor.
 *
 * A button in the "add section" area opens a picker with every page of the library (home pages of the designs,
 * their subpages and single pages). "Insert" adds the page to the open document with Elementor's import command,
 * so it can be undone with Ctrl+Z.
 */
( function ( $ ) {
	'use strict';

	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var cfg = window.etwwEditorLibrary || {};
	var state = { data: null, loading: false, error: '', filter: 'all', access: 'all', search: '', at: undefined, addSection: null, busy: false };
	var modal = null;

	function esc( value ) {
		return String( null === value || undefined === value ? '' : value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function api( path ) {
		return fetch( cfg.rest + path, { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce, Accept: 'application/json' } } ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					var error = new Error( ( data && data.message ) || __( 'The library could not be loaded.', 'envo-elementor-for-woocommerce' ) );
					error.code = data && data.code;
					throw error;
				}
				return data;
			} );
		} );
	}

	function toast( message ) {
		if ( window.elementor && elementor.notifications && elementor.notifications.showToast ) {
			elementor.notifications.showToast( { message: message } );
		} else {
			window.alert( message ); // eslint-disable-line no-alert
		}
	}

	// Every page once: home pages of the designs first, then the other pages.
	function entries() {
		var list = [];
		var seen = {};
		var add = function ( page, extra ) {
			if ( seen[ page.id ] ) {
				return;
			}
			seen[ page.id ] = true;
			list.push( Object.assign( {}, page, extra ) );
		};
		state.data.kits.forEach( function ( kit ) {
			kit.pages.forEach( function ( page ) {
				if ( page.home ) {
					add( page, { pro: kit.pro, group: 'home', kit: kit.title } );
				}
			} );
		} );
		state.data.kits.forEach( function ( kit ) {
			kit.pages.forEach( function ( page ) {
				if ( ! page.home ) {
					add( page, { pro: kit.pro, group: 'other', kit: kit.title } );
				}
			} );
		} );
		state.data.pages.forEach( function ( page ) {
			add( page, { group: 'other', kit: page.category } );
		} );
		// New in the library: a new design marks all its pages, a new single page itself.
		var news = state.data.news || { kits: [], pages: [] };
		var fresh = {};
		news.pages.forEach( function ( id ) {
			fresh[ id ] = true;
		} );
		state.data.kits.forEach( function ( kit ) {
			if ( -1 !== news.kits.indexOf( kit.id ) ) {
				kit.pages.forEach( function ( page ) {
					fresh[ page.id ] = true;
				} );
			}
		} );
		list.forEach( function ( entry ) {
			entry.fresh = !! fresh[ entry.id ];
		} );
		return list;
	}

	function matches( entry ) {
		if ( 'all' !== state.filter && entry.group !== state.filter ) {
			return false;
		}
		if ( 'free' === state.access && entry.pro ) {
			return false;
		}
		if ( 'pro' === state.access && ! entry.pro ) {
			return false;
		}
		var search = state.search.trim().toLowerCase();
		return ! search || -1 !== ( entry.title + ' ' + ( entry.kit || '' ) ).toLowerCase().indexOf( search );
	}

	function locked( entry ) {
		return entry.pro && ! state.data.pro.active;
	}

	/* ---------- Button in the "add section" area ---------- */

	function addButtonToTemplate() {
		var template = document.getElementById( 'tmpl-elementor-add-section' );
		if ( ! template || -1 !== template.innerHTML.indexOf( 'etww-elib-button' ) ) {
			return;
		}
		var label = esc( __( 'Envo Templates', 'envo-elementor-for-woocommerce' ) );
		var button = '<button type="button" class="elementor-add-section-area-button etww-elib-button" data-tooltip="' + label + '" aria-label="' + label + '">' + cfg.logo + '</button>';
		template.innerHTML = template.innerHTML.replace( '<div class="elementor-add-section-drag-title"', button + '<div class="elementor-add-section-drag-title"' );
	}

	function listenInPreview() {
		elementor.$previewContents.off( 'click.etww' ).on( 'click.etww', '.etww-elib-button', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			var addSection = this.closest( '.elementor-add-section' );
			// An "add section" area between sections inserts at its place; the one at the end appends.
			state.at = addSection && 'elementor-add-new-section' !== addSection.id ? $( addSection ).prevAll( '.elementor-element' ).length : undefined;
			state.addSection = addSection;
			open();
		} );
	}

	/* ---------- Modal ---------- */

	function build() {
		modal = document.createElement( 'div' );
		modal.className = 'etww-elib';
		modal.hidden = true;
		modal.innerHTML =
			'<div class="etww-elib__dialog" role="dialog" aria-modal="true" aria-labelledby="etww-elib-title">' +
			'<header class="etww-elib__head"><span class="etww-elib__logo">' + cfg.logo + '</span>' +
			'<h2 id="etww-elib-title">' + esc( __( 'Envo Templates', 'envo-elementor-for-woocommerce' ) ) + '</h2>' +
			'<div class="etww-elib__filters">' +
			'<select class="etww-elib__filter" aria-label="' + esc( __( 'Pages', 'envo-elementor-for-woocommerce' ) ) + '">' +
			'<option value="all">' + esc( __( 'All pages', 'envo-elementor-for-woocommerce' ) ) + '</option>' +
			'<option value="home">' + esc( __( 'Home pages', 'envo-elementor-for-woocommerce' ) ) + '</option>' +
			'<option value="other">' + esc( __( 'Other pages', 'envo-elementor-for-woocommerce' ) ) + '</option></select>' +
			'<select class="etww-elib__access" aria-label="' + esc( __( 'Free or PRO', 'envo-elementor-for-woocommerce' ) ) + '">' +
			'<option value="all">' + esc( __( 'Free and PRO', 'envo-elementor-for-woocommerce' ) ) + '</option>' +
			'<option value="free">' + esc( __( 'Free', 'envo-elementor-for-woocommerce' ) ) + '</option>' +
			'<option value="pro">' + esc( __( 'PRO', 'envo-elementor-for-woocommerce' ) ) + '</option></select>' +
			'<input type="search" class="etww-elib__search" placeholder="' + esc( __( 'Search', 'envo-elementor-for-woocommerce' ) ) + '">' +
			'</div>' +
			'<a class="etww-elib__manage" href="' + esc( cfg.libraryUrl ) + '" target="_blank" rel="noopener">' + esc( __( 'Import whole designs', 'envo-elementor-for-woocommerce' ) ) + '</a>' +
			'<button type="button" class="etww-elib__close" aria-label="' + esc( __( 'Close', 'envo-elementor-for-woocommerce' ) ) + '"><i class="eicon-close" aria-hidden="true"></i></button>' +
			'</header>' +
			'<div class="etww-elib__body"></div>' +
			'<footer class="etww-elib__foot">' + esc( __( 'The images of the page are copied into your media library. Ctrl+Z takes the insert back.', 'envo-elementor-for-woocommerce' ) ) + '</footer>' +
			'</div>';
		document.body.appendChild( modal );

		modal.addEventListener( 'click', function ( event ) {
			if ( event.target === modal || event.target.closest( '.etww-elib__close' ) ) {
				close();
				return;
			}
			var insert = event.target.closest( '[data-insert]' );
			if ( insert ) {
				doInsert( insert );
			}
			if ( event.target.closest( '[data-retry]' ) ) {
				load();
			}
		} );
		modal.querySelector( '.etww-elib__search' ).addEventListener( 'input', function ( event ) {
			state.search = event.target.value;
			render();
		} );
		modal.querySelector( '.etww-elib__filter' ).addEventListener( 'change', function ( event ) {
			state.filter = event.target.value;
			render();
		} );
		modal.querySelector( '.etww-elib__access' ).addEventListener( 'change', function ( event ) {
			state.access = event.target.value;
			render();
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && modal && ! modal.hidden ) {
				event.stopPropagation();
				close();
			}
		}, true );
	}

	function open() {
		if ( ! modal ) {
			build();
		}
		modal.hidden = false;
		modal.querySelector( '.etww-elib__search' ).focus();
		if ( ! state.data && ! state.loading ) {
			load();
		}
		render();
	}

	function close() {
		if ( modal && ! state.busy ) {
			modal.hidden = true;
		}
	}

	function load() {
		state.loading = true;
		state.error = '';
		render();
		api( 'library' )
			.then( function ( data ) {
				state.data = data;
			} )
			.catch( function ( error ) {
				state.error = error.message;
			} )
			.then( function () {
				state.loading = false;
				render();
			} );
	}

	function body() {
		return modal.querySelector( '.etww-elib__body' );
	}

	function card( entry ) {
		var img = entry.image;
		var action = locked( entry )
			? '<a class="etww-elib__btn etww-elib__btn--pro" href="' + esc( state.data.pro.installed ? cfg.libraryUrl : state.data.pro.buy ) + '" target="_blank" rel="noopener">' + esc( state.data.pro.installed ? __( 'Needs an active PRO licence', 'envo-elementor-for-woocommerce' ) : __( 'Get PRO', 'envo-elementor-for-woocommerce' ) ) + '</a>'
			: '<button type="button" class="etww-elib__btn" data-insert="' + entry.id + '">' + esc( __( 'Insert', 'envo-elementor-for-woocommerce' ) ) + '</button>';
		return (
			'<div class="etww-elib__card">' +
			'<div class="etww-elib__shot">' +
			( img ? '<img src="' + esc( img.src ) + '" srcset="' + esc( img.srcset ) + '" sizes="240px" alt="" loading="lazy"' + ( img.lqip ? ' style="background-image:url(' + esc( img.lqip ) + ')"' : '' ) + '>' : '' ) +
			'<span class="etww-elib__badges">' +
			( entry.fresh ? '<span class="etww-elib__badge is-new">' + esc( __( 'New', 'envo-elementor-for-woocommerce' ) ) + '</span>' : '' ) +
			'<span class="etww-elib__badge' + ( entry.pro ? ' is-pro' : '' ) + '">' + esc( entry.pro ? __( 'PRO', 'envo-elementor-for-woocommerce' ) : __( 'Free', 'envo-elementor-for-woocommerce' ) ) + '</span>' +
			'</span>' +
			'</div>' +
			'<div class="etww-elib__info"><strong>' + esc( entry.title ) + '</strong>' + ( entry.kit && entry.kit !== entry.title ? '<span>' + esc( entry.kit ) + '</span>' : '' ) + '</div>' +
			'<div class="etww-elib__actions">' + action +
			( entry.preview ? '<a class="etww-elib__btn etww-elib__btn--ghost" href="' + esc( entry.preview ) + '" target="_blank" rel="noopener">' + esc( __( 'Preview', 'envo-elementor-for-woocommerce' ) ) + '</a>' : '' ) +
			'</div></div>'
		);
	}

	function render() {
		if ( ! modal ) {
			return;
		}
		if ( state.error && ! state.data ) {
			body().innerHTML = '<p class="etww-elib__message">' + esc( state.error ) + ' <button type="button" class="etww-elib__btn" data-retry="1">' + esc( __( 'Try again', 'envo-elementor-for-woocommerce' ) ) + '</button></p>';
			return;
		}
		if ( ! state.data ) {
			body().innerHTML = '<p class="etww-elib__message"><span class="etww-elib__spinner"></span>' + esc( __( 'Loading the library…', 'envo-elementor-for-woocommerce' ) ) + '</p>';
			return;
		}
		var list = entries().filter( matches );
		body().innerHTML = list.length ? '<div class="etww-elib__grid">' + list.map( card ).join( '' ) + '</div>' : '<p class="etww-elib__message">' + esc( __( 'Nothing found.', 'envo-elementor-for-woocommerce' ) ) + '</p>';
	}

	function doInsert( button ) {
		if ( state.busy ) {
			return;
		}
		state.busy = true;
		button.disabled = true;
		button.textContent = __( 'Inserting…', 'envo-elementor-for-woocommerce' );
		modal.classList.add( 'is-busy' );

		api( 'content?page=' + parseInt( button.dataset.insert, 10 ) )
			.then( function ( result ) {
				$e.run( 'document/elements/import', {
					model: new Backbone.Model( { title: result.title } ),
					data: { content: result.content },
					options: undefined !== state.at ? { at: state.at } : {},
				} );
				if ( state.addSection && 'elementor-add-new-section' !== state.addSection.id ) {
					var closeButton = state.addSection.querySelector( '.elementor-add-section-close' );
					if ( closeButton ) {
						closeButton.click();
					}
				}
				state.busy = false;
				close();
				toast( sprintf( __( '"%s" was inserted. Ctrl+Z takes it back.', 'envo-elementor-for-woocommerce' ), result.title ) );
			} )
			.catch( function ( error ) {
				toast( ( error && error.message ) || __( 'The page could not be inserted.', 'envo-elementor-for-woocommerce' ) );
			} )
			.then( function () {
				state.busy = false;
				button.disabled = false;
				button.textContent = __( 'Insert', 'envo-elementor-for-woocommerce' );
				modal.classList.remove( 'is-busy' );
			} );
	}

	$( window ).on( 'elementor:init', function () {
		addButtonToTemplate();
		elementor.on( 'preview:loaded', listenInPreview );
	} );
} )( jQuery );
