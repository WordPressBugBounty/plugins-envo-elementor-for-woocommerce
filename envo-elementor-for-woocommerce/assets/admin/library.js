/**
 * Envo Templates – the library page (Envo Templates in the admin menu).
 *
 * Designs (a home page with its subpages) and single pages from envothemes.com. A design is imported page by page
 * (one request per page), then the "finish" request links the pages to each other and optionally sets the front
 * page and creates a menu.
 */
( function () {
	'use strict';

	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;
	var cfg = window.etwwLibrary || {};
	var root = document.getElementById( 'etww-library' );

	var state = {
		data: null,
		error: '',
		tab: 'kits',
		access: 'all',
		category: '',
		search: '',
		busy: false,
		dialog: null,
	};

	function esc( value ) {
		return String( null === value || undefined === value ? '' : value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function api( path, options ) {
		return wp.apiFetch( Object.assign( { path: '/etww/v1/' + path }, options || {} ) );
	}

	var ICONS = {
		external: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M11 3h6v6h-2V6.4l-6.3 6.3-1.4-1.4L13.6 5H11V3ZM4 5h5v2H5v8h8v-4h2v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>',
		lock: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2a4 4 0 0 1 4 4v2h1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1h1V6a4 4 0 0 1 4-4Zm0 2a2 2 0 0 0-2 2v2h4V6a2 2 0 0 0-2-2Z"/></svg>',
		refresh: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 3a7 7 0 0 1 6.3 4H14v2h5V4h-2v1.7A9 9 0 0 0 1 10h2a7 7 0 0 1 7-7Zm7 7a7 7 0 0 1-13.3 3H6v-2H1v5h2v-1.7A9 9 0 0 0 19 10h-2Z"/></svg>',
		close: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5.4 4 4.6 4.6L14.6 4 16 5.4 11.4 10l4.6 4.6-1.4 1.4-4.6-4.6L5.4 16 4 14.6 8.6 10 4 5.4 5.4 4Z"/></svg>',
		check: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="m8 13.2-3.6-3.6L3 11l5 5 9-9-1.4-1.4L8 13.2Z"/></svg>',
		error: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm-1 4v5h2V6H9Zm0 6v2h2v-2H9Z"/></svg>',
		search: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M8.5 3a5.5 5.5 0 0 1 4.4 8.8l4.1 4.1-1.4 1.4-4.1-4.1A5.5 5.5 0 1 1 8.5 3Zm0 2a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/></svg>',
	};

	/* ---------- Data ---------- */

	function load( refresh ) {
		state.error = '';
		if ( refresh ) {
			state.busy = true;
			render();
		}
		return api( 'library' + ( refresh ? '?refresh=1' : '' ) )
			.then( function ( data ) {
				state.data = data;
				keepPreviews();
				setTimeout( markSeen );
			} )
			.catch( function ( error ) {
				state.error = ( error && error.message ) || __( 'The library could not be loaded.', 'envo-elementor-for-woocommerce' );
			} )
			.then( function () {
				state.busy = false;
				render();
				openFromHash();
			} );
	}

	// "#kit:12" or "#page:34" (links in the notice about new templates) opens that item.
	function openFromHash() {
		var match = /^#(kit|page):(\d+)$/.exec( window.location.hash );
		if ( ! match || ! state.data || state.dialog ) {
			return;
		}
		history.replaceState( null, '', window.location.pathname + window.location.search );
		state.tab = 'kit' === match[ 1 ] ? 'kits' : 'pages';
		render();
		openDialog( match[ 1 ], parseInt( match[ 2 ], 10 ) );
	}

	function isNew( kind, id ) {
		var news = ( state.data && state.data.news ) || {};
		return -1 !== ( news[ 'kit' === kind ? 'kits' : 'pages' ] || [] ).indexOf( id );
	}

	// Opening the library marks the new items seen: the "New" badges stay for this visit, the count in the menu
	// and the notice go away.
	function markSeen() {
		var news = state.data.news || {};
		var keys = ( news.kits || [] ).map( function ( id ) {
			return 'kit:' + id;
		} ).concat( ( news.pages || [] ).map( function ( id ) {
			return 'page:' + id;
		} ) );
		if ( ! keys.length ) {
			return;
		}
		api( 'seen', { method: 'POST', data: { keys: keys } } )
			.then( function () {
				var count = document.querySelector( '#toplevel_page_etww-library .update-plugins' );
				if ( count ) {
					count.remove();
				}
			} )
			.catch( function () {} );
	}

	// Images that are not stored on this site yet: ask the server to keep a copy (in the background).
	function keepPreviews() {
		var keys = [];
		var add = function ( image ) {
			if ( image && image.remote && -1 === keys.indexOf( image.key ) ) {
				keys.push( image.key );
			}
		};
		state.data.kits.forEach( function ( kit ) {
			add( kit.image );
			kit.pages.forEach( function ( page ) {
				add( page.image );
			} );
		} );
		state.data.pages.forEach( function ( page ) {
			add( page.image );
		} );
		var next = function () {
			if ( ! keys.length ) {
				return;
			}
			api( 'previews', { method: 'POST', data: { keys: keys.splice( 0, 30 ) } } )
				.then( function ( result ) {
					if ( result && result.kept && result.kept.length ) {
						next();
					}
				} )
				.catch( function () {} );
		};
		next();
	}

	function plugin( slug ) {
		var found = null;
		( state.data.plugins || [] ).forEach( function ( item ) {
			if ( item.slug === slug ) {
				found = item;
			}
		} );
		return found || { slug: slug, name: slug, state: 'missing' };
	}

	function items() {
		var kind = 'kits' === state.tab ? 'kit' : 'page';
		var list = 'kits' === state.tab ? state.data.kits : state.data.pages;
		var search = state.search.trim().toLowerCase();
		// New items first, otherwise the order of the library.
		list = list.filter( function ( item ) {
			return isNew( kind, item.id );
		} ).concat( list.filter( function ( item ) {
			return ! isNew( kind, item.id );
		} ) );
		return list.filter( function ( item ) {
			if ( 'free' === state.access && item.pro ) {
				return false;
			}
			if ( 'pro' === state.access && ! item.pro ) {
				return false;
			}
			if ( state.category && item.category !== state.category ) {
				return false;
			}
			if ( search ) {
				var text = [ item.title, item.category, item.description ].concat( item.tags || [] ).join( ' ' ).toLowerCase();
				return -1 !== text.indexOf( search );
			}
			return true;
		} );
	}

	function categories() {
		var list = [];
		( 'kits' === state.tab ? state.data.kits : state.data.pages ).forEach( function ( item ) {
			if ( item.category && -1 === list.indexOf( item.category ) ) {
				list.push( item.category );
			}
		} );
		return list.sort();
	}

	function locked( item ) {
		return item.pro && ! state.data.pro.active;
	}

	/* ---------- Page ---------- */

	function image( img, alt, sizes ) {
		if ( ! img ) {
			return '<span class="etww-lib__noimg">' + cfg.logo + '</span>';
		}
		return (
			'<img src="' + esc( img.src ) + '" srcset="' + esc( img.srcset ) + '" sizes="' + esc( sizes ) + '" alt="' + esc( alt ) + '" loading="lazy" decoding="async"' +
			( img.lqip ? ' style="background-image:url(' + esc( img.lqip ) + ')"' : '' ) +
			'>'
		);
	}

	function header() {
		var pro = state.data && state.data.pro;
		var badge = '';
		if ( pro && pro.active ) {
			badge = '<span class="etww-lib__pro-state is-active">' + esc( __( 'PRO active', 'envo-elementor-for-woocommerce' ) ) + '</span>';
		} else if ( pro ) {
			badge = '<a class="etww-btn etww-btn--pro" href="' + esc( pro.buy ) + '" target="_blank" rel="noopener">' + esc( __( 'Get PRO', 'envo-elementor-for-woocommerce' ) ) + '</a>';
		}
		return (
			'<header class="etww-lib__head">' +
			'<span class="etww-lib__logo">' + cfg.logo + '</span>' +
			'<div class="etww-lib__title"><h2>' + esc( __( 'Envo Templates', 'envo-elementor-for-woocommerce' ) ) + '</h2>' +
			'<p>' + esc( __( 'Ready-made Elementor designs for your shop – a home page with its subpages, or a single page.', 'envo-elementor-for-woocommerce' ) ) + '</p></div>' +
			'<div class="etww-lib__head-actions">' + badge +
			'<button type="button" class="etww-btn etww-btn--ghost" data-action="refresh"' + ( state.busy ? ' disabled' : '' ) + '>' + ICONS.refresh + '<span>' + esc( __( 'Refresh', 'envo-elementor-for-woocommerce' ) ) + '</span></button>' +
			'</div></header>'
		);
	}

	function toolbar() {
		var d = state.data;
		var tab = function ( key, label, count ) {
			return '<button type="button" role="tab" aria-selected="' + ( state.tab === key ) + '" data-tab="' + key + '">' + esc( label ) + ' <span class="etww-lib__count">' + count + '</span></button>';
		};
		var access = function ( key, label ) {
			return '<button type="button" aria-pressed="' + ( state.access === key ) + '" data-access="' + key + '">' + esc( label ) + '</button>';
		};
		var cats = categories();
		return (
			'<div class="etww-lib__toolbar">' +
			'<div class="etww-lib__tabs" role="tablist">' + tab( 'kits', __( 'Designs', 'envo-elementor-for-woocommerce' ), d.kits.length ) + tab( 'pages', __( 'Single pages', 'envo-elementor-for-woocommerce' ), d.pages.length ) + '</div>' +
			'<div class="etww-lib__filters">' +
			'<div class="etww-lib__seg" role="group" aria-label="' + esc( __( 'Free or PRO', 'envo-elementor-for-woocommerce' ) ) + '">' + access( 'all', __( 'All', 'envo-elementor-for-woocommerce' ) ) + access( 'free', __( 'Free', 'envo-elementor-for-woocommerce' ) ) + access( 'pro', __( 'PRO', 'envo-elementor-for-woocommerce' ) ) + '</div>' +
			( cats.length > 1
				? '<select data-filter="category" aria-label="' + esc( __( 'Category', 'envo-elementor-for-woocommerce' ) ) + '"><option value="">' + esc( __( 'All categories', 'envo-elementor-for-woocommerce' ) ) + '</option>' +
				  cats.map( function ( cat ) {
						return '<option' + ( cat === state.category ? ' selected' : '' ) + '>' + esc( cat ) + '</option>';
				  } ).join( '' ) + '</select>'
				: '' ) +
			'<label class="etww-lib__search">' + ICONS.search + '<input type="search" data-filter="search" value="' + esc( state.search ) + '" placeholder="' + esc( __( 'Search', 'envo-elementor-for-woocommerce' ) ) + '"></label>' +
			'</div></div>'
		);
	}

	// Small previews of the subpages of a design, under its screenshot.
	function subpages( kit ) {
		var pages = kit.pages.filter( function ( page ) {
			return ! page.home;
		} );
		if ( ! pages.length ) {
			return '';
		}
		var max = 4;
		var more = pages.length > max ? pages.length - max + 1 : 0;
		var shown = more ? pages.slice( 0, max - 1 ) : pages;
		return (
			'<div class="etww-card__pages">' +
			shown.map( function ( page ) {
				return (
					'<button type="button" class="etww-card__page" data-open="kit:' + kit.id + '" title="' + esc( page.title ) + '" aria-label="' + esc( sprintf( __( '%1$s: %2$s', 'envo-elementor-for-woocommerce' ), kit.title, page.title ) ) + '">' +
					image( page.image, '', '80px' ) +
					'<span>' + esc( page.title ) + '</span></button>'
				);
			} ).join( '' ) +
			( more ? '<button type="button" class="etww-card__page etww-card__page--more" data-open="kit:' + kit.id + '">+' + more + '</button>' : '' ) +
			'</div>'
		);
	}

	function card( item ) {
		var kind = 'kits' === state.tab ? 'kit' : 'page';
		var meta = [];
		if ( 'kit' === kind ) {
			meta.push( sprintf( _n( '%d page', '%d pages', item.pages.length, 'envo-elementor-for-woocommerce' ), item.pages.length ) );
		}
		if ( item.category ) {
			meta.push( item.category );
		}
		return (
			'<article class="etww-card' + ( locked( item ) ? ' is-locked' : '' ) + '">' +
			'<button type="button" class="etww-card__shot" data-open="' + kind + ':' + item.id + '" aria-label="' + esc( item.title ) + '">' +
			image( item.image, '', '(max-width: 782px) 100vw, 320px' ) +
			'<span class="etww-card__badges">' +
			( isNew( kind, item.id ) ? '<span class="etww-badge etww-badge--new">' + esc( __( 'New', 'envo-elementor-for-woocommerce' ) ) + '</span>' : '' ) +
			'<span class="etww-badge ' + ( item.pro ? 'etww-badge--pro' : 'etww-badge--free' ) + '">' + ( item.pro ? ( locked( item ) ? ICONS.lock : '' ) + esc( __( 'PRO', 'envo-elementor-for-woocommerce' ) ) : esc( __( 'Free', 'envo-elementor-for-woocommerce' ) ) ) + '</span>' +
			'</span>' +
			'</button>' +
			( 'kit' === kind ? subpages( item ) : '' ) +
			'<div class="etww-card__body"><h3>' + esc( item.title ) + '</h3><p>' + esc( meta.join( ' · ' ) ) + '</p></div>' +
			'<div class="etww-card__actions">' +
			'<button type="button" class="etww-btn etww-btn--primary" data-open="' + kind + ':' + item.id + '">' + esc( locked( item ) ? __( 'Details', 'envo-elementor-for-woocommerce' ) : __( 'Import', 'envo-elementor-for-woocommerce' ) ) + '</button>' +
			( item.preview ? '<a class="etww-btn etww-btn--ghost" href="' + esc( item.preview ) + '" target="_blank" rel="noopener">' + esc( __( 'Preview', 'envo-elementor-for-woocommerce' ) ) + ICONS.external + '</a>' : '' ) +
			'</div></article>'
		);
	}

	function render() {
		var focus = document.activeElement && document.activeElement.dataset ? document.activeElement.dataset.filter : '';
		var html = header();
		if ( state.error && ! state.data ) {
			html += '<div class="etww-lib__message is-error"><p>' + esc( state.error ) + '</p><button type="button" class="etww-btn etww-btn--primary" data-action="retry">' + esc( __( 'Try again', 'envo-elementor-for-woocommerce' ) ) + '</button></div>';
		} else if ( ! state.data ) {
			html += '<div class="etww-lib__message"><span class="etww-spinner"></span>' + esc( __( 'Loading the library…', 'envo-elementor-for-woocommerce' ) ) + '</div>';
		} else {
			if ( state.error ) {
				html += '<div class="notice notice-warning inline"><p>' + esc( state.error ) + '</p></div>';
			}
			if ( state.data.pro.outdated ) {
				html += '<div class="notice notice-warning inline"><p>' + esc( __( 'Update the PRO plugin to version 2.0 to unlock the PRO designs.', 'envo-elementor-for-woocommerce' ) ) + '</p></div>';
			}
			html += toolbar();
			var list = items();
			html += list.length
				? '<div class="etww-lib__grid">' + list.map( card ).join( '' ) + '</div>'
				: '<div class="etww-lib__message">' + esc( __( 'Nothing found.', 'envo-elementor-for-woocommerce' ) ) + '</div>';
			html +=
				'<p class="etww-lib__foot">' +
				esc( __( 'The designs are loaded from envothemes.com. When you import one, its images are copied into your media library.', 'envo-elementor-for-woocommerce' ) ) +
				'</p>';
		}
		root.innerHTML = html;
		if ( focus ) {
			var field = root.querySelector( '[data-filter="' + focus + '"]' );
			if ( field ) {
				field.focus();
				if ( 'search' === focus ) {
					field.setSelectionRange( field.value.length, field.value.length );
				}
			}
		}
	}

	/* ---------- Dialog ---------- */

	function find( kind, id ) {
		var list = 'kit' === kind ? state.data.kits : state.data.pages;
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ].id === id ) {
				return list[ i ];
			}
		}
		return null;
	}

	function openDialog( kind, id ) {
		var item = find( kind, id );
		if ( ! item ) {
			return;
		}
		var el = document.createElement( 'div' );
		el.className = 'etww-dialog';
		el.innerHTML = '<div class="etww-dialog__box" role="dialog" aria-modal="true" aria-labelledby="etww-dialog-title" tabindex="-1"></div>';
		document.body.appendChild( el );
		document.body.classList.add( 'etww-dialog-open' );
		state.dialog = {
			el: el,
			kind: kind,
			item: item,
			selected: 'kit' === kind ? item.pages.map( function ( page ) { return page.id; } ) : [ item.id ],
			to: targets()[ 0 ] || '',
			publish: false,
			front: false,
			menu: false,
			menuName: item.title,
			running: false,
			rows: {},
			done: null,
			error: '',
			returnFocus: document.activeElement,
		};
		renderDialog();
		el.querySelector( '.etww-dialog__box' ).focus();
	}

	// Where the user may import: a page, a post or a template in the Elementor library ("My Templates").
	function targets() {
		var can = state.data.can;
		var list = [];
		if ( can.pages ) {
			list.push( 'page' );
		}
		if ( can.posts ) {
			list.push( 'post' );
		}
		if ( can.library ) {
			list.push( 'library' );
		}
		return list;
	}

	function targetBox() {
		var d = state.dialog;
		var labels = {
			page: __( 'Page', 'envo-elementor-for-woocommerce' ),
			post: __( 'Post', 'envo-elementor-for-woocommerce' ),
			library: __( 'Template (My Templates)', 'envo-elementor-for-woocommerce' ),
		};
		var canPublish = 'page' === d.to ? state.data.can.publish : 'post' === d.to ? state.data.can.publishPosts : false;
		return (
			'<fieldset class="etww-options etww-target"' + ( d.running ? ' disabled' : '' ) + '><legend>' + esc( __( 'Import as', 'envo-elementor-for-woocommerce' ) ) + '</legend>' +
			'<div class="etww-target__choices">' +
			targets().map( function ( key ) {
				return '<label class="etww-target__choice' + ( d.to === key ? ' is-active' : '' ) + '"><input type="radio" name="etww-target" data-target="' + key + '"' + ( d.to === key ? ' checked' : '' ) + '> ' + esc( labels[ key ] ) + '</label>';
			} ).join( '' ) +
			'</div>' +
			( canPublish ? '<label><input type="checkbox" data-option="publish"' + ( d.publish ? ' checked' : '' ) + '> ' + esc( __( 'Publish (otherwise saved as a draft)', 'envo-elementor-for-woocommerce' ) ) + '</label>' : '' ) +
			'</fieldset>'
		);
	}

	function closeDialog() {
		var dialog = state.dialog;
		if ( ! dialog || dialog.running ) {
			return;
		}
		dialog.el.remove();
		document.body.classList.remove( 'etww-dialog-open' );
		state.dialog = null;
		if ( dialog.returnFocus && dialog.returnFocus.focus ) {
			dialog.returnFocus.focus();
		}
	}

	function neededPlugins() {
		var dialog = state.dialog;
		var pages = 'kit' === dialog.kind ? dialog.item.pages : [ dialog.item ];
		var slugs = [];
		pages.forEach( function ( page ) {
			if ( -1 !== dialog.selected.indexOf( page.id ) ) {
				page.plugins.forEach( function ( slug ) {
					if ( -1 === slugs.indexOf( slug ) ) {
						slugs.push( slug );
					}
				} );
			}
		} );
		return slugs.map( plugin );
	}

	function pluginsBox() {
		var list = neededPlugins();
		if ( ! list.length ) {
			return '';
		}
		var can = state.data.can;
		return (
			'<div class="etww-plugins"><h4>' + esc( __( 'Required plugins', 'envo-elementor-for-woocommerce' ) ) + '</h4><ul>' +
			list.map( function ( p ) {
				var action = '';
				if ( 'active' === p.state ) {
					action = '<span class="etww-plugins__ok">' + ICONS.check + esc( __( 'Active', 'envo-elementor-for-woocommerce' ) ) + '</span>';
				} else if ( p.busy ) {
					action = '<span class="etww-plugins__busy"><span class="etww-spinner"></span>' + esc( 'missing' === p.state ? __( 'Installing…', 'envo-elementor-for-woocommerce' ) : __( 'Activating…', 'envo-elementor-for-woocommerce' ) ) + '</span>';
				} else if ( ( 'missing' === p.state && can.install ) || ( 'installed' === p.state && can.activate ) ) {
					action = '<button type="button" class="etww-btn etww-btn--small" data-plugin="' + esc( p.slug ) + '">' + esc( 'missing' === p.state ? __( 'Install and activate', 'envo-elementor-for-woocommerce' ) : __( 'Activate', 'envo-elementor-for-woocommerce' ) ) + '</button>';
				} else {
					action = '<span class="etww-plugins__no">' + esc( __( 'Ask an administrator to install it', 'envo-elementor-for-woocommerce' ) ) + '</span>';
				}
				return '<li><strong>' + esc( p.name ) + '</strong>' + action + ( p.error ? '<em class="etww-plugins__error">' + esc( p.error ) + '</em>' : '' ) + '</li>';
			} ).join( '' ) +
			'</ul></div>'
		);
	}

	function pluginsReady() {
		return neededPlugins().every( function ( p ) {
			return 'active' === p.state;
		} );
	}

	function lockedBox() {
		var pro = state.data.pro;
		var text = pro.outdated
			? __( 'This design is part of PRO. Update the PRO plugin to version 2.0 to import it.', 'envo-elementor-for-woocommerce' )
			: pro.installed
			? __( 'This design is part of PRO. Activate your PRO licence in the PRO plugin to import it.', 'envo-elementor-for-woocommerce' )
			: __( 'This design is part of PRO. Get the PRO plugin to import it and all other PRO designs.', 'envo-elementor-for-woocommerce' );
		return (
			'<div class="etww-locked">' + ICONS.lock + '<p>' + esc( text ) + '</p>' +
			( pro.installed ? '' : '<a class="etww-btn etww-btn--pro" href="' + esc( pro.buy ) + '" target="_blank" rel="noopener">' + esc( __( 'Get PRO', 'envo-elementor-for-woocommerce' ) ) + '</a>' ) +
			'</div>'
		);
	}

	function rowState( id ) {
		var row = state.dialog.rows[ id ];
		if ( ! row ) {
			return '';
		}
		if ( 'running' === row.status ) {
			return '<span class="etww-row-state"><span class="etww-spinner"></span>' + esc( __( 'Importing…', 'envo-elementor-for-woocommerce' ) ) + '</span>';
		}
		if ( 'done' === row.status ) {
			return (
				'<span class="etww-row-state is-done">' + ICONS.check +
				'<a href="' + esc( row.result.edit_url ) + '" target="_blank" rel="noopener">' + esc( __( 'Edit', 'envo-elementor-for-woocommerce' ) ) + '</a>' +
				( row.result.url ? '<a href="' + esc( row.result.url ) + '" target="_blank" rel="noopener">' + esc( __( 'View', 'envo-elementor-for-woocommerce' ) ) + '</a>' : '' ) +
				'</span>'
			);
		}
		if ( 'error' === row.status ) {
			return '<span class="etww-row-state is-error">' + ICONS.error + esc( row.message ) + '</span>';
		}
		return '<span class="etww-row-state">' + esc( __( 'Waiting', 'envo-elementor-for-woocommerce' ) ) + '</span>';
	}

	/**
	 * Body of a kit dialog: main (right: pages, plugins, result, the import button) and side (left, under the preview:
	 * the import settings), so everything is visible at once.
	 */
	function kitBody() {
		var d = state.dialog;
		var kit = d.item;
		var can = state.data.can;
		var started = d.running || d.done;
		var side = '';
		var html =
			'<ul class="etww-pages">' +
			kit.pages.map( function ( page ) {
				var checked = -1 !== d.selected.indexOf( page.id );
				return (
					'<li class="etww-pages__item' + ( checked ? ' is-checked' : '' ) + '">' +
					'<label><input type="checkbox" data-page="' + page.id + '"' + ( checked ? ' checked' : '' ) + ( started || locked( kit ) ? ' disabled' : '' ) + '>' +
					'<span class="etww-pages__thumb">' + image( page.image, '', '96px' ) + '</span>' +
					'<span class="etww-pages__name">' + esc( page.title ) + ( page.home ? ' <span class="etww-tag">' + esc( __( 'Home', 'envo-elementor-for-woocommerce' ) ) + '</span>' : '' ) + '</span></label>' +
					( started ? rowState( page.id ) : page.preview ? '<a class="etww-pages__preview" href="' + esc( page.preview ) + '" target="_blank" rel="noopener" aria-label="' + esc( sprintf( __( 'Preview %s', 'envo-elementor-for-woocommerce' ), page.title ) ) + '">' + ICONS.external + '</a>' : '' ) +
					'</li>'
				);
			} ).join( '' ) +
			'</ul>';

		if ( locked( kit ) ) {
			return { main: html + lockedBox(), side: '' };
		}
		if ( ! targets().length ) {
			return { main: html + '<p class="etww-note">' + esc( __( 'You are not allowed to add pages, posts or templates.', 'envo-elementor-for-woocommerce' ) ) + '</p>', side: '' };
		}
		if ( d.done ) {
			return { main: html + doneBox(), side: '' };
		}
		var hasHome = kit.pages.some( function ( page ) {
			return page.home && -1 !== d.selected.indexOf( page.id );
		} );
		html += pluginsBox();
		side += targetBox();
		var front = can.front && hasHome && 'page' === d.to;
		var menu = can.menu && 'library' !== d.to;
		if ( front || menu ) {
			side +=
				'<fieldset class="etww-options"' + ( d.running ? ' disabled' : '' ) + '><legend>' + esc( __( 'Options', 'envo-elementor-for-woocommerce' ) ) + '</legend>' +
				( front ? '<label><input type="checkbox" data-option="front"' + ( d.front ? ' checked' : '' ) + '> ' + esc( __( 'Use the home page as the front page of the site', 'envo-elementor-for-woocommerce' ) ) + '</label>' : '' ) +
				( menu
					? '<label><input type="checkbox" data-option="menu"' + ( d.menu ? ' checked' : '' ) + '> ' + esc( __( 'Create a menu with the imported pages', 'envo-elementor-for-woocommerce' ) ) + '</label>' +
					  ( d.menu ? '<label class="etww-options__name">' + esc( __( 'Menu name', 'envo-elementor-for-woocommerce' ) ) + ' <input type="text" data-option="menuName" value="' + esc( d.menuName ) + '"></label>' : '' )
					: '' ) +
				'</fieldset>';
		}
		if ( d.error ) {
			html += '<p class="etww-error">' + esc( d.error ) + '</p>';
		}
		var count = d.selected.length;
		html +=
			'<div class="etww-dialog__actions">' +
			'<button type="button" class="etww-btn etww-btn--primary etww-btn--large" data-action="import-kit"' + ( ! count || d.running || ! pluginsReady() ? ' disabled' : '' ) + '>' +
			esc( d.running ? __( 'Importing…', 'envo-elementor-for-woocommerce' ) : sprintf( _n( 'Import %d page', 'Import %d pages', count, 'envo-elementor-for-woocommerce' ), count ) ) +
			'</button>' +
			( ! pluginsReady() ? '<span class="etww-note">' + esc( __( 'Activate the required plugins first.', 'envo-elementor-for-woocommerce' ) ) + '</span>' : '' ) +
			'</div>';
		return { main: html, side: side };
	}

	function doneBox() {
		var d = state.dialog;
		var done = d.done;
		var count = Object.keys( d.rows ).filter( function ( id ) {
			return 'done' === d.rows[ id ].status;
		} ).length;
		var links = [];
		if ( done.front ) {
			links.push( '<a class="etww-btn etww-btn--primary" href="' + esc( done.home ) + '" target="_blank" rel="noopener">' + esc( __( 'View the site', 'envo-elementor-for-woocommerce' ) ) + '</a>' );
		}
		if ( done.menu_url ) {
			links.push( '<a class="etww-btn etww-btn--ghost" href="' + esc( done.menu_url ) + '">' + esc( __( 'Edit the menu', 'envo-elementor-for-woocommerce' ) ) + '</a>' );
		}
		if ( 'library' === d.to ) {
			links.push( '<a class="etww-btn etww-btn--ghost" href="' + esc( cfg.elementor ) + '">' + esc( __( 'My Templates', 'envo-elementor-for-woocommerce' ) ) + '</a>' );
		} else {
			links.push( '<a class="etww-btn etww-btn--ghost" href="' + esc( 'post' === d.to ? cfg.posts : cfg.pages ) + '">' + esc( 'post' === d.to ? __( 'All posts', 'envo-elementor-for-woocommerce' ) : __( 'All pages', 'envo-elementor-for-woocommerce' ) ) + '</a>' );
		}
		var text = {
			page: _n( '%d page was imported.', '%d pages were imported.', count, 'envo-elementor-for-woocommerce' ),
			post: _n( '%d post was imported.', '%d posts were imported.', count, 'envo-elementor-for-woocommerce' ),
			library: _n( '%d template was added to My Templates.', '%d templates were added to My Templates.', count, 'envo-elementor-for-woocommerce' ),
		}[ d.to ];
		return (
			'<div class="etww-done">' + ICONS.check +
			'<div><p><strong>' + esc( sprintf( text, count ) ) + '</strong></p>' +
			( done.menu ? '<p>' + esc( __( 'The menu was created. Assign it to a location of your theme in Appearance › Menus.', 'envo-elementor-for-woocommerce' ) ) + '</p>' : '' ) +
			( done.error ? '<p class="etww-error">' + esc( done.error ) + '</p>' : '' ) +
			'<div class="etww-done__links">' + links.join( '' ) + '</div></div></div>'
		);
	}

	function pageBody() {
		var d = state.dialog;
		var page = d.item;
		var can = state.data.can;
		if ( locked( page ) ) {
			return { main: lockedBox(), side: '' };
		}
		var html = pluginsBox();
		if ( d.result ) {
			var message = {
				page: __( 'The page was imported.', 'envo-elementor-for-woocommerce' ),
				post: __( 'The post was imported.', 'envo-elementor-for-woocommerce' ),
				library: __( 'The page was added to your Elementor templates.', 'envo-elementor-for-woocommerce' ),
			}[ d.result.to ];
			html +=
				'<div class="etww-done">' + ICONS.check + '<div><p><strong>' +
				esc( message ) +
				'</strong></p><div class="etww-done__links">' +
				'<a class="etww-btn etww-btn--primary" href="' + esc( d.result.edit_url ) + '" target="_blank" rel="noopener">' + esc( __( 'Edit with Elementor', 'envo-elementor-for-woocommerce' ) ) + '</a>' +
				( 'library' === d.result.to ? '<a class="etww-btn etww-btn--ghost" href="' + esc( cfg.elementor ) + '">' + esc( __( 'My Templates', 'envo-elementor-for-woocommerce' ) ) + '</a>' : '<a class="etww-btn etww-btn--ghost" href="' + esc( d.result.url ) + '" target="_blank" rel="noopener">' + esc( __( 'Preview', 'envo-elementor-for-woocommerce' ) ) + '</a>' ) +
				'</div></div></div>';
			return { main: html, side: '' };
		}
		if ( d.error ) {
			html += '<p class="etww-error">' + esc( d.error ) + '</p>';
		}
		if ( ! targets().length ) {
			return { main: html + '<p class="etww-note">' + esc( __( 'You are not allowed to add pages, posts or templates.', 'envo-elementor-for-woocommerce' ) ) + '</p>', side: '' };
		}
		var side = targetBox();
		html += '<p class="etww-note">' + esc( __( 'Tip: in the Elementor editor you can also insert this page into the page you are editing with the Envo Templates button.', 'envo-elementor-for-woocommerce' ) ) + '</p>';
		var disabled = d.running || ! pluginsReady() ? ' disabled' : '';
		html += '<div class="etww-dialog__actions">';
		html += '<button type="button" class="etww-btn etww-btn--primary etww-btn--large" data-action="import-page"' + disabled + '>' + esc( __( 'Import', 'envo-elementor-for-woocommerce' ) ) + '</button>';
		if ( d.running ) {
			html += '<span class="etww-note"><span class="etww-spinner"></span>' + esc( __( 'Importing…', 'envo-elementor-for-woocommerce' ) ) + '</span>';
		}
		html += '</div>';
		return { main: html, side: side };
	}

	function renderDialog() {
		var d = state.dialog;
		if ( ! d ) {
			return;
		}
		var item = d.item;
		var box = d.el.querySelector( '.etww-dialog__box' );
		var top = box.scrollTop;
		var body = 'kit' === d.kind ? kitBody() : pageBody();
		box.innerHTML =
			'<button type="button" class="etww-dialog__close" data-action="close" aria-label="' + esc( __( 'Close', 'envo-elementor-for-woocommerce' ) ) + '"' + ( d.running ? ' disabled' : '' ) + '>' + ICONS.close + '</button>' +
			'<div class="etww-dialog__side">' +
			'<div class="etww-dialog__shot">' + image( item.image, '', '(max-width: 782px) 100vw, 420px' ) + '</div>' +
			body.side +
			'</div>' +
			'<div class="etww-dialog__main">' +
			'<p class="etww-dialog__kicker">' +
			'<span class="etww-badge ' + ( item.pro ? 'etww-badge--pro' : 'etww-badge--free' ) + '">' + esc( item.pro ? __( 'PRO', 'envo-elementor-for-woocommerce' ) : __( 'Free', 'envo-elementor-for-woocommerce' ) ) + '</span>' +
			esc( 'kit' === d.kind ? __( 'Design', 'envo-elementor-for-woocommerce' ) : __( 'Single page', 'envo-elementor-for-woocommerce' ) ) + ( item.category ? ' · ' + esc( item.category ) : '' ) + '</p>' +
			'<h2 id="etww-dialog-title">' + esc( item.title ) + '</h2>' +
			( item.description ? '<p class="etww-dialog__desc">' + esc( item.description ) + '</p>' : '' ) +
			( item.preview ? '<p><a href="' + esc( item.preview ) + '" target="_blank" rel="noopener" class="etww-link">' + esc( __( 'Live preview', 'envo-elementor-for-woocommerce' ) ) + ICONS.external + '</a></p>' : '' ) +
			body.main +
			'</div>';
		box.scrollTop = top;
	}

	/* ---------- Actions ---------- */

	function installPlugin( slug ) {
		var p = plugin( slug );
		p.busy = true;
		p.error = '';
		renderDialog();
		api( 'plugin', { method: 'POST', data: { slug: slug } } )
			.then( function ( result ) {
				p.state = result.state;
			} )
			.catch( function ( error ) {
				p.error = ( error && error.message ) || __( 'The plugin could not be installed.', 'envo-elementor-for-woocommerce' );
			} )
			.then( function () {
				p.busy = false;
				renderDialog();
			} );
	}

	function errorMessage( error, fallback ) {
		if ( error && error.data && error.data.plugins ) {
			error.data.plugins.forEach( function ( item ) {
				var p = plugin( item.slug );
				p.state = item.state;
			} );
		}
		return ( error && error.message ) || fallback;
	}

	function importKit() {
		var d = state.dialog;
		var pages = d.item.pages.filter( function ( page ) {
			return -1 !== d.selected.indexOf( page.id );
		} );
		// The home page first: the others link to it.
		pages.sort( function ( a, b ) {
			return ( b.home ? 1 : 0 ) - ( a.home ? 1 : 0 );
		} );
		d.running = true;
		d.error = '';
		d.rows = {};
		pages.forEach( function ( page ) {
			d.rows[ page.id ] = { status: 'waiting' };
		} );
		renderDialog();

		var results = [];
		var stop = false;
		var chain = Promise.resolve();
		pages.forEach( function ( page ) {
			chain = chain.then( function () {
				if ( stop ) {
					d.rows[ page.id ] = { status: 'error', message: __( 'Skipped', 'envo-elementor-for-woocommerce' ) };
					return;
				}
				d.rows[ page.id ] = { status: 'running' };
				renderDialog();
				return api( 'import', { method: 'POST', data: { page: page.id, to: d.to, status: d.publish ? 'publish' : 'draft' } } )
					.then( function ( result ) {
						d.rows[ page.id ] = { status: 'done', result: result };
						results.push( { id: result.id, demo_url: result.demo_url, home: result.home } );
					} )
					.catch( function ( error ) {
						d.rows[ page.id ] = { status: 'error', message: errorMessage( error, __( 'The page could not be imported.', 'envo-elementor-for-woocommerce' ) ) };
						// Without the licence or a plugin no other page would work either.
						if ( error && ( 'etww_pro_locked' === error.code || 'etww_library_license' === error.code || 'etww_needs_plugins' === error.code ) ) {
							stop = true;
						}
					} )
					.then( renderDialog );
			} );
		} );
		chain
			.then( function () {
				if ( ! results.length ) {
					return { error: __( 'No page was imported.', 'envo-elementor-for-woocommerce' ) };
				}
				// Templates are not linked to each other and get no menu.
				if ( 'library' === d.to ) {
					return {};
				}
				return api( 'finish', { method: 'POST', data: { pages: results, front: d.front, menu: d.menu, menu_name: d.menuName } } ).catch( function ( error ) {
					return { error: errorMessage( error, __( 'The pages were imported, but the links between them could not be updated.', 'envo-elementor-for-woocommerce' ) ) };
				} );
			} )
			.then( function ( done ) {
				d.running = false;
				d.done = done;
				renderDialog();
			} );
	}

	function importPage() {
		var d = state.dialog;
		var to = d.to;
		d.running = true;
		d.error = '';
		renderDialog();
		api( 'import', { method: 'POST', data: { page: d.item.id, to: to, status: d.publish ? 'publish' : 'draft' } } )
			.then( function ( result ) {
				result.to = to;
				d.result = result;
			} )
			.catch( function ( error ) {
				d.error = errorMessage( error, __( 'The page could not be imported.', 'envo-elementor-for-woocommerce' ) );
			} )
			.then( function () {
				d.running = false;
				renderDialog();
			} );
	}

	/* ---------- Events ---------- */

	root.addEventListener( 'click', function ( event ) {
		var target = event.target.closest( 'button, a' );
		if ( ! target || ! root.contains( target ) ) {
			return;
		}
		if ( target.dataset.tab ) {
			state.tab = target.dataset.tab;
			state.category = '';
			render();
		} else if ( target.dataset.access ) {
			state.access = target.dataset.access;
			render();
		} else if ( target.dataset.open ) {
			var parts = target.dataset.open.split( ':' );
			openDialog( parts[ 0 ], parseInt( parts[ 1 ], 10 ) );
		} else if ( 'refresh' === target.dataset.action ) {
			load( true );
		} else if ( 'retry' === target.dataset.action ) {
			state.data = null;
			render();
			load( false );
		}
	} );

	root.addEventListener( 'input', function ( event ) {
		if ( 'search' === event.target.dataset.filter ) {
			state.search = event.target.value;
			render();
		}
	} );

	root.addEventListener( 'change', function ( event ) {
		if ( 'category' === event.target.dataset.filter ) {
			state.category = event.target.value;
			render();
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var d = state.dialog;
		if ( ! d || ! d.el.contains( event.target ) ) {
			return;
		}
		if ( event.target === d.el ) {
			closeDialog();
			return;
		}
		var target = event.target.closest( 'button' );
		if ( ! target ) {
			return;
		}
		if ( 'close' === target.dataset.action ) {
			closeDialog();
		} else if ( target.dataset.plugin ) {
			installPlugin( target.dataset.plugin );
		} else if ( 'import-kit' === target.dataset.action ) {
			importKit();
		} else if ( 'import-page' === target.dataset.action ) {
			importPage();
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		var d = state.dialog;
		if ( ! d || ! d.el.contains( event.target ) ) {
			return;
		}
		var input = event.target;
		if ( input.dataset.page ) {
			var id = parseInt( input.dataset.page, 10 );
			d.selected = input.checked
				? d.selected.concat( [ id ] )
				: d.selected.filter( function ( item ) {
						return item !== id;
				  } );
			renderDialog();
		} else if ( input.dataset.target ) {
			d.to = input.dataset.target;
			renderDialog();
		} else if ( input.dataset.option && 'checkbox' === input.type ) {
			d[ input.dataset.option ] = input.checked;
			renderDialog();
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		var d = state.dialog;
		if ( d && 'menuName' === event.target.dataset.option ) {
			d.menuName = event.target.value;
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && state.dialog ) {
			closeDialog();
		}
	} );

	render();
	load( false );
} )();
