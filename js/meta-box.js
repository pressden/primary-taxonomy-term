jQuery( document ).ready( function($) {
	// restrict functionality to categories for MVP
	// @TODO: extend the JS to work for any taxonomy
	$( '#categorychecklist li' ).on( 'click', function() {
		var input = $( this ).find( 'input' );
		var inputChecked = input.prop( 'checked' );
		var inputValue = input.val();
		var inputText = $( this ).text();
		var primarySelect = $( '#_ptt-primary-category' );
		var primaryValue = primarySelect.val();

		// warn the user about removing the primary term
		if( primaryValue === inputValue ) {
			var confirmation = confirm( 'You are about to remove a primary taxonomy term for this post. Are you sure you want to do this?' );
		}

		if( false === confirmation ) {
			return false;
		}

		// @TODO: retain the 'selected' state of a primary term if it is readded
		if( true === inputChecked ) {
			primarySelect.append( $( '<option>', { value: inputValue, text: inputText } ) );
			// @TODO: sort the options alphabetically after appending a new option
		}
		else {
			$( '#_ptt-primary-category option[value="' + inputValue + '"]' ).remove();
		}
	} );
} );
