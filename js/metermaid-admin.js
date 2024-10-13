/* jshint esversion: 6 */

let metermaid = {
	tabBlockers : {},

	registerTabBlocker : function ( id ) {
		this.tabBlockers[ id ] = true;
	},

	clearTabBlocker : function ( id ) {
		delete this.tabBlockers[ id ];

		if ( ! this.tabsAreBlocked() ) {
			metermaid.updateSelectedTab();
		}
	},

	tabsAreBlocked : function () {
		return Object.keys( this.tabBlockers ).length > 0;
	},

	updateSelectedTab : function () {
		( function ( $ ) {
			$( '.metermaid-tabbed-content-container' ).each( function () {
				let container = $( this );

				var selectedTabId = null;
				var selectedTab = null;

				// Select an active tab and show its content.
				let pageAnchor = window.location.hash;

				if ( ! pageAnchor && container.data( 'initialized' ) ) {
					return;
				}

				if ( pageAnchor ) {
					selectedTab = container.find( '.nav-tab[data-metermaid-tab="' + pageAnchor.replace( '#tab-', '' ) + '"]' );

					if ( ! selectedTab.length ) {
						selectedTab = null;
					}
				}

				if ( ! selectedTab ) {
					if ( container.data( 'initialized' ) ) {
						return;
					}

					selectedTab = container.find( '.nav-tab:first' );
				}

				selectedTabId = selectedTab.data( 'metermaid-tab' );

				// Hide the tabbed contents.
				let tabs = container.find( '.nav-tab' );

				tabs.each( function ( idx, el ) {
					let tab = $( el );

					tab.removeClass( 'nav-tab-active' );

					let tabId = $( el ).data( 'metermaid-tab' );
					let tabContent = container.find( '.metermaid-tabbed-content [data-metermaid-tab="' + tabId + '"]' );

					if ( tabId === selectedTabId ) {
						tab.addClass( 'nav-tab-active' );
						tabContent.show();
					} else {
						tabContent.hide();
					}
				} );

				container.data( 'initialized', 'true' );
			} );
		})( jQuery );
	}
};

jQuery( function ( $ ) {
	$( window ).on( 'hashchange', function( e ) {
		metermaid.updateSelectedTab( $ );
	} );

	$( 'input[name=metermaid_invite_access_level]' ).on( 'change', function () {
		var selectedValue = $( 'input[name=metermaid_invite_access_level]:checked' ).val();

		if ( selectedValue == 'meter' ) {
			$( '.metermaid_invite_manage_system' ).hide();
			$( '.metermaid_invite_manage_meter' ).show();
		} else if ( selectedValue == 'system' ) {
			$( '.metermaid_invite_manage_system' ).show();
			$( '.metermaid_invite_manage_meter' ).hide();
		}
	} );

	$( 'input[name=metermaid_invite_access_level]' ).change();

	if ( ! metermaid.tabsAreBlocked() ) {
		// Things like Google Charts get drawn at the wrong scale if they're hidden
		// when drawn, so don't hide the tab contents until all of the charts have
		// been drawn.
		metermaid.updateSelectedTab( $ );
	}
} );