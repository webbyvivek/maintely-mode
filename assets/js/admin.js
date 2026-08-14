/**
 * Maintely Mode - Admin Settings Page Scripts
 *
 * Powers the media-uploader field used by the Logo control on the
 * General tab (via the generic initMediaField()), plus a few other
 * small settings-screen behaviors.
 */
( function ( $ ) {
	'use strict';

	/**
	 * Wire up a single media field container.
	 *
	 * @param {jQuery} $field The ".maintely-mode-media-field" wrapper.
	 */
	function initMediaField( $field ) {
		var $input     = $field.find( '.maintely-mode-media-value' );
		var $preview   = $field.find( '.maintely-mode-media-preview' );
		var $uploadBtn = $field.find( '.maintely-mode-media-upload' );
		var $removeBtn = $field.find( '.maintely-mode-media-remove' );
		var frame;

		if ( ! $uploadBtn.length || typeof wp === 'undefined' || ! wp.media ) {
			return;
		}

		$uploadBtn.on( 'click', function ( event ) {
			event.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: $uploadBtn.data( 'title' ) || '',
				button: { text: $uploadBtn.data( 'button-text' ) || '' },
				multiple: false,
				library: { type: 'image' }
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var imageUrl   = attachment.sizes && attachment.sizes.medium
					? attachment.sizes.medium.url
					: attachment.url;

				$input.val( attachment.id ).trigger( 'change' );
				$preview.attr( 'src', imageUrl ).addClass( 'is-visible' );
				$removeBtn.addClass( 'is-visible' );
				$field.addClass( 'has-image' );

				if ( $uploadBtn.data( 'replace-text' ) ) {
					$uploadBtn.text( $uploadBtn.data( 'replace-text' ) );
				}
			} );

			frame.open();
		} );

		$removeBtn.on( 'click', function ( event ) {
			event.preventDefault();
			$input.val( '' ).trigger( 'change' );
			$preview.attr( 'src', '' ).removeClass( 'is-visible' );
			$removeBtn.removeClass( 'is-visible' );
			$field.removeClass( 'has-image' );

			if ( $uploadBtn.data( 'upload-text' ) ) {
				$uploadBtn.text( $uploadBtn.data( 'upload-text' ) );
			}
		} );
	}

	/**
	 * Wire up a single repeater field container (used by the "Custom
	 * Social Links" field). Reads its own configuration from data-*
	 * attributes so this function stays generic and reusable for any
	 * future label+url repeater without further JS changes.
	 *
	 * @param {jQuery} $container The ".maintely-mode-repeater" wrapper.
	 */
	function initRepeaterField( $container ) {
		var $rows        = $container.find( '.maintely-mode-repeater-rows' );
		var $addBtn      = $container.find( '.maintely-mode-repeater-add' );
		var namePrefix   = $container.data( 'name-prefix' );
		var labelPh      = $container.data( 'label-placeholder' );
		var urlPh        = $container.data( 'url-placeholder' );
		var removeText   = $container.data( 'remove-text' );
		var nextIndex    = $rows.children( '.maintely-mode-repeater-row' ).length;

		function buildRow( index ) {
			var $row   = $( '<div class="maintely-mode-repeater-row"></div>' );
			var $label = $( '<input type="text" class="regular-text" />' )
				.attr( 'name', namePrefix + '[' + index + '][label]' )
				.attr( 'placeholder', labelPh );
			var $url   = $( '<input type="url" class="regular-text" />' )
				.attr( 'name', namePrefix + '[' + index + '][url]' )
				.attr( 'placeholder', urlPh );
			var $remove = $( '<button type="button" class="button maintely-mode-repeater-remove"></button>' )
				.text( removeText );

			$row.append( $label ).append( $url ).append( $remove );
			return $row;
		}

		$addBtn.on( 'click', function ( event ) {
			event.preventDefault();
			$rows.append( buildRow( nextIndex ) );
			nextIndex++;
			// Adding a row doesn't fire a native input/change event on its
			// own; trigger one so the unsaved-changes watcher notices it.
			$container.trigger( 'change' );
		} );

		// Delegated so it also catches rows added after page load.
		$rows.on( 'click', '.maintely-mode-repeater-remove', function ( event ) {
			event.preventDefault();
			$( this ).closest( '.maintely-mode-repeater-row' ).remove();
			$container.trigger( 'change' );
		} );
	}

	/**
	 * Copy a string to the clipboard, preferring the modern async
	 * Clipboard API and falling back to the older execCommand() for
	 * browsers/contexts where that API isn't available.
	 *
	 * @param {string} text Text to copy.
	 * @return {Promise} Resolves once the copy has completed.
	 */
	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			try {
				if ( document.execCommand( 'copy' ) ) {
					resolve();
				} else {
					reject();
				}
			} catch ( error ) {
				reject( error );
			}
		} );
	}

	/**
	 * Show a brief "Copied!" badge next to a field, reusing the same
	 * badge element on repeated clicks instead of stacking new ones.
	 *
	 * @param {jQuery} $field The field the badge should appear next to.
	 */
	function showCopiedBadge( $field ) {
		var settings = window.maintelyWpAdmin || {};
		var message  = settings.copiedText || 'Copied!';
		var $badge   = $field.next( '.maintely-mode-copied-badge' );

		if ( ! $badge.length ) {
			$badge = $( '<span class="maintely-mode-copied-badge"></span>' ).insertAfter( $field );
		}

		$badge.text( message ).addClass( 'is-visible' );

		window.clearTimeout( $badge.data( 'maintelyWpHideTimeout' ) );

		var hideTimeout = window.setTimeout( function () {
			$badge.removeClass( 'is-visible' );
		}, 1500 );

		$badge.data( 'maintelyWpHideTimeout', hideTimeout );
	}

	/**
	 * Move every WordPress admin notice this screen printed - the
	 * "Settings saved." notice from settings_errors(), the "secret
	 * access URL regenerated" notice from Maintely_Mode_Bypass, and any
	 * other notice a future addition might add - out of wherever
	 * WordPress/PHP happened to render it, into a single toast
	 * container appended directly to <body>.
	 *
	 * This is deliberately a DOM move, not a CSS reposition: no matter
	 * where in the page markup a notice originates, once it's a
	 * direct descendant of <body> it is structurally impossible for it
	 * to sit inside, push, or otherwise affect .maintely-mode-header or
	 * any other part of the page layout - it isn't a descendant of
	 * either any more. #maintely-mode-toast-container (admin.css) then
	 * displays it as a fixed top-right toast.
	 *
	 * Each moved notice:
	 *  - Gets a close ("x") button if it doesn't already have one.
	 *  - Auto-hides after 3 seconds.
	 *  - Is fully removed from the DOM (not just hidden) on both the
	 *    timeout and a manual close, so a later notice never has a
	 *    stale one left behind above/below it.
	 */
	function initNoticeToasts() {
		var containerId = 'maintely-mode-toast-container';
		var $container  = $( '#' + containerId );

		if ( ! $container.length ) {
			$container = $( '<div>', {
				id: containerId,
				'aria-live': 'polite'
			} ).appendTo( document.body );
		}

		// Only notices WordPress printed for THIS screen, scoped to
		// #wpbody-content so we never reach into the admin bar, the
		// admin menu, or (if somehow present) another screen's markup.
		var $notices = $( '#wpbody-content .notice' ).filter( function () {
			return ! $.contains( $container[ 0 ], this );
		} );

		$notices.each( function () {
			var $notice = $( this );

			if ( ! $notice.find( '.notice-dismiss' ).length ) {
				$notice
					.addClass( 'is-dismissible' )
					.append(
						$( '<button>', {
							type: 'button',
							'class': 'notice-dismiss'
						} ).append(
							$( '<span>', { 'class': 'screen-reader-text' } )
								.text( ( window.maintelyWpAdmin && window.maintelyWpAdmin.dismissText ) || 'Dismiss this notice.' )
						)
					);
			}

			$notice.appendTo( $container );

			// CSS-driven exit animation (see maintely-mode-toast-out in
			// admin.css) instead of a jQuery fade, so the dismiss
			// motion matches the toast's own entrance animation.
			function remove() {
				if ( $notice.hasClass( 'is-leaving' ) ) {
					return;
				}

				$notice.addClass( 'is-leaving' );
				window.setTimeout( function () {
					$notice.remove();
				}, 180 );
			}

			var timeout = window.setTimeout( remove, 3000 );

			$notice.on( 'click', '.notice-dismiss', function () {
				window.clearTimeout( timeout );
				remove();
			} );
		} );
	}

	/**
	 * Move the "Regenerate Secret URL" mini-form into the reserved slot
	 * inside the Secret Access URL card's heading row, so the action
	 * reads as living on the right side of that same box.
	 *
	 * The regenerate form has to be *printed* outside the Settings
	 * API's <form action="options.php"> (see
	 * Maintely_Mode_Bypass::maybe_render_regenerate_button() for why -
	 * nested <form> elements aren't valid HTML), which is why it
	 * doesn't already sit there in the server-rendered markup. This
	 * only repositions the existing node in the DOM on load; it
	 * doesn't change what it submits to or how.
	 */
	function initRegenerateFormPlacement() {
		var $slot = $( '.maintely-mode-secret-url-card-actions-slot' );
		var $form = $( '.maintely-mode-regenerate-form' );

		if ( $slot.length && $form.length ) {
			$form.appendTo( $slot );
		}
	}

	/**
	 * Wire up click-to-copy on the Secret Access URL field: clicking it
	 * selects the text and copies it to the clipboard in one action.
	 */
	function initSecretUrlCopy() {
		var $field = $( '.maintely-mode-secret-url' );

		if ( ! $field.length ) {
			return;
		}

		$field.on( 'click', function () {
			var $input = $( this );

			$input[ 0 ].select();

			copyText( $input.val() )
				.then( function () {
					showCopiedBadge( $input );
				} )
				.catch( function () {
					// Text is still selected for a manual copy even if this failed.
				} );
		} );
	}

	/**
	 * Wire up the Maintenance Mode toggle switch: purely cosmetic - it
	 * only updates the status card's active/disabled styling and label
	 * text as the admin flips the switch, ahead of saving. The
	 * underlying checkbox (name/value) is untouched, so the existing
	 * Settings API save logic keeps working exactly as before.
	 */
	function initMaintenanceToggle() {
		var $checkbox = $( '#maintely_mode_maintenance_enabled' );
		var $card      = $( '.maintely-mode-maintenance-card' );

		if ( ! $checkbox.length || ! $card.length ) {
			return;
		}

		var $statusText = $card.find( '.maintely-mode-maintenance-card-status-text' );
		var activeText  = ( window.maintelyWpAdmin && window.maintelyWpAdmin.activeText ) || 'Active';
		var disabledText = ( window.maintelyWpAdmin && window.maintelyWpAdmin.disabledText ) || 'Disabled';

		$checkbox.on( 'change', function () {
			var isChecked = $( this ).is( ':checked' );

			$card.toggleClass( 'is-active', isChecked ).toggleClass( 'is-disabled', ! isChecked );
			$statusText.text( isChecked ? activeText : disabledText );
		} );
	}

	/**
	 * Wire up the "Show website name" toggle switch: purely cosmetic -
	 * it only updates the On/Off status label as the admin flips the
	 * switch, ahead of saving. The underlying checkbox (name/value) is
	 * untouched, so the existing Settings API save logic keeps working
	 * exactly as before.
	 */
	function initSiteNameToggle() {
		var $checkbox = $( '#maintely_mode_show_site_name' );
		var $status   = $( '.maintely-mode-site-name-status' );

		if ( ! $checkbox.length || ! $status.length ) {
			return;
		}

		var onText  = ( window.maintelyWpAdmin && window.maintelyWpAdmin.onText ) || 'On';
		var offText = ( window.maintelyWpAdmin && window.maintelyWpAdmin.offText ) || 'Off';

		$checkbox.on( 'change', function () {
			var isChecked = $( this ).is( ':checked' );

			$status.toggleClass( 'is-on', isChecked ).toggleClass( 'is-off', ! isChecked );
			$status.text( isChecked ? onText : offText );
		} );
	}

	/**
	 * Wire up the Schedule tab's Automatic Scheduling toggle: purely
	 * cosmetic - it dims the Start/End date fields and takes them out
	 * of tab order while scheduling is off, and restores their normal
	 * active appearance when turned on. The checkbox's name/value and
	 * the date inputs' name/value/id are untouched, so the existing
	 * Settings API save logic and scheduling behaviour keep working
	 * exactly as before.
	 */
	function initScheduleToggle() {
		var $checkbox   = $( '#maintely_mode_schedule_enabled' );
		var $toggleCard = $( '.maintely-mode-schedule-toggle-card' );
		var $fieldsCard = $( '.maintely-mode-schedule-fields-card' );

		if ( ! $checkbox.length || ! $fieldsCard.length ) {
			return;
		}

		var $statusText = $toggleCard.find( '.maintely-mode-schedule-toggle-status-text' );
		var $inputs      = $fieldsCard.find( '.maintely-mode-schedule-input' );
		var activeText   = ( window.maintelyWpAdmin && window.maintelyWpAdmin.activeText ) || 'Active';
		var disabledText = ( window.maintelyWpAdmin && window.maintelyWpAdmin.disabledText ) || 'Disabled';

		function applyState( isChecked ) {
			$toggleCard.toggleClass( 'is-active', isChecked );
			$statusText.text( isChecked ? activeText : disabledText );

			$fieldsCard
				.toggleClass( 'is-disabled', ! isChecked )
				.attr( 'aria-disabled', isChecked ? 'false' : 'true' );

			$inputs.each( function () {
				var $input = $( this );

				if ( isChecked ) {
					$input.removeAttr( 'tabindex' ).removeAttr( 'aria-disabled' );
				} else {
					$input.attr( 'tabindex', '-1' ).attr( 'aria-disabled', 'true' );
				}
			} );
		}

		$checkbox.on( 'change', function () {
			applyState( $( this ).is( ':checked' ) );
		} );
	}

	/**
	 * Wire up the sticky Save Changes bar: unsaved-change detection,
	 * Discard, the post-save confirmation state, and the leave-page
	 * warning. Everything here is a UI layer on top of the existing
	 * form - field names/values, the form's action, and the submit
	 * mechanism are never touched, so the WordPress save flow keeps
	 * working exactly as before.
	 */
	function initSaveBar() {
		var $form = $( '.maintely-mode-tab-content > form' );

		if ( ! $form.length ) {
			return;
		}

		var settings    = window.maintelyWpAdmin || {};
		var unsavedText = settings.unsavedText || 'Unsaved changes';
		var savedText   = settings.savedText || 'Changes saved';

		// Snapshot of the form exactly as the server rendered it - used
		// both as the "no changes" baseline for comparison and as the
		// markup Discard restores (including the disabled Save button
		// and hidden Discard button, so nothing extra needs resetting).
		var initialFormHtml = $form.html();
		var initialState    = $form.serialize();
		var isSubmitting    = false;
		var savedMessageTimeout;

		var $bar, $button, $status, $discardButton;

		function captureRefs() {
			$bar           = $form.find( '.maintely-mode-save-bar' );
			$button        = $bar.find( '.button-primary' );
			$status        = $bar.find( '.maintely-mode-save-status' );
			$discardButton = $bar.find( '.maintely-mode-discard-button' );
		}

		captureRefs();

		if ( ! $bar.length || ! $button.length ) {
			return;
		}

		function refresh() {
			var isDirty = $form.serialize() !== initialState;

			window.clearTimeout( savedMessageTimeout );
			$bar.removeClass( 'is-saved' );
			$bar.toggleClass( 'has-changes', isDirty );
			$button.prop( 'disabled', ! isDirty );
			$status.text( isDirty ? unsavedText : '' );
		}

		// Re-run the other field controllers against the restored
		// markup after Discard, since replacing that markup discards
		// the event bindings that were on the old elements.
		function rebindDynamicFields() {
			$form.find( '.maintely-mode-media-field' ).each( function () {
				initMediaField( $( this ) );
			} );

			$form.find( '.maintely-mode-repeater' ).each( function () {
				initRepeaterField( $( this ) );
			} );

			initMaintenanceToggle();
			initSiteNameToggle();
			initScheduleToggle();
		}

		function bindDiscard() {
			$discardButton.on( 'click', function ( event ) {
				event.preventDefault();

				$form.html( initialFormHtml );
				captureRefs();
				rebindDynamicFields();
				bindDiscard();
				refresh();
			} );
		}

		bindDiscard();

		// Bound directly to the form element, so it keeps catching
		// bubbled input/change events from descendants even after
		// Discard replaces those descendants.
		$form.on( 'input change', refresh );

		$form.on( 'submit', function () {
			isSubmitting = true;
		} );

		$( window ).on( 'beforeunload', function ( event ) {
			if ( isSubmitting || $form.serialize() === initialState ) {
				return undefined;
			}

			event.preventDefault();
			event.returnValue = '';
			return '';
		} );

		refresh();

		if ( settings.justSaved ) {
			$bar.addClass( 'is-saved' );
			$status.text( savedText );

			savedMessageTimeout = window.setTimeout( function () {
				$bar.removeClass( 'is-saved' );
				$status.text( '' );
			}, 4000 );
		}
	}

	$( function () {
		$( '.maintely-mode-media-field' ).each( function () {
			initMediaField( $( this ) );
		} );

		$( '.maintely-mode-repeater' ).each( function () {
			initRepeaterField( $( this ) );
		} );

		initSecretUrlCopy();
		initRegenerateFormPlacement();
		initMaintenanceToggle();
		initSiteNameToggle();
		initScheduleToggle();
		initSaveBar();
		initNoticeToasts();
	} );
} )( jQuery );
