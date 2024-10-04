/* jshint esversion: 6 */

jQuery( function ( $ ) {
	function updateSelectedTab() {
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
	}

	$( window ).on( 'hashchange', function( e ) {
		updateSelectedTab();
	} );

	updateSelectedTab();

	/*

				<div class="metermaid-tabbed-content-container">
					<nav class="nav-tab-wrapper">
						<a href="#" class="nav-tab nav-tab-active" data-metermaid-tab="reading">Add Reading</a>
						<a href="#" class="nav-tab" data-metermaid-tab="supplement">Add Supplement</a>
						<a href="#" class="nav-tab" data-metermaid-tab="edit">Edit Meter</a>
					</nav>
					<div class="metermaid-tabbed-content">
						<div data-metermaid-tab="reading">
							<?php self::add_reading_form( $meter->id ); ?>
						</div>
						<div data-metermaid-tab="supplement">
							<?php self::add_supplement_form( $meter->id ); ?>
						</div>
						<div data-metermaid-tab="edit">
							<?php self::edit_meter_form( $meter->id ); ?>
						</div>
					</div>
				</div>
*/
} );