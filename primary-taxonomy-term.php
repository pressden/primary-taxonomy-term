<?php
/*
Plugin Name: Primary Taxonomy Term
Plugin URI: https://github.com/pressden/primary-taxonomy-term
Description: Primary Taxonomy Term adds the ability to specify a primary term for any taxonomy.
Version: 0.3.0
Author: D.S. Webster
Author URI: http://pressden.com/
License: GPLv3
Text Domain: primary-taxonomy-term
*/

// enqueue the ptt admin scripts
add_action ( 'admin_enqueue_scripts', 'ptt_enqueue_scripts' );
function ptt_enqueue_scripts () {
	wp_enqueue_script( 'ptt-meta-box', plugins_url( 'js/meta-box.js', __FILE__ ), array( 'jquery' ), '0.2.0', true );
}

// add the ptt meta box
add_action ( 'add_meta_boxes', 'ptt_add_meta_boxes' );
function ptt_add_meta_boxes() {
	$screen = get_current_screen();

	// restrict PTT posts for MVP
	// @TODO: extend PTT functionality to all post types
	if ( 'post' === $screen->post_type && 'post' === $screen->id ) {
		add_meta_box ( 'ptt-primary-terms', 'Primary Terms', ptt_meta_box_callback, null, 'side', 'core' );
	}
}

// ptt meta box callback
function ptt_meta_box_callback ( $post ) {
	global $post;

	$taxonomies = get_object_taxonomies ( $post );

	foreach ( get_object_taxonomies ( $post ) as $tax_name ) {
		$taxonomy = get_taxonomy ( $tax_name );

		// restrict PTT categories for MVP
		// @TODO: extend PTT functionality to all taxonomies
		if ( 'category' === $taxonomy->name )
		{
			if ( ! $taxonomy->show_ui || false === $taxonomy->meta_box_cb )
				continue;

			$label = 'Primary ' . $taxonomy->labels->singular_name;
			$field = '_ptt-primary-' . $taxonomy->name;
			$terms = get_the_terms ( $post, $taxonomy->name );
			$primary = get_post_meta ( $post->ID, $field, true );
			?>

			<label for="<?php echo $field; ?>"><?php echo $label; ?></label>
			<select name="<?php echo $field; ?>" id="<?php echo $field; ?>">
				<option value="">Select a <?php echo strtolower ( $label ); ?></option>

				<?php
				// @TODO: give JS control of the options to reflect real-time updates

				foreach ( $terms as $term ) {
					$selected = ( (integer) $primary === $term->term_id ) ? 'selected' : '';
					?>

					<option value="<?php echo $term->term_id; ?>" <?php echo $selected; ?>><?php echo $term->name; ?></option>

					<?php
				}
				?>

			</select>

			<?php
		}
	}

	// generate a nonce for this meta box
	wp_nonce_field ( 'ptt_primary_terms', 'ptt_primary_terms_nonce' );
}

// save the values from the ptt meta box
add_action ( 'save_post', 'ptt_save_post' );
function ptt_save_post ( $post_id ) {
	// check the nonce
	if ( ! isset ( $_POST['ptt_primary_terms_nonce'] ) || ! wp_verify_nonce ( $_POST['ptt_primary_terms_nonce'], 'ptt_primary_terms' ) ) {
		return;
	}

	// check capabilities
	if ( ! current_user_can ( 'edit_post', $post_id ) ) {
		return;
	}

	// @TODO: this code should go into a loop that checks and updates all taxonomies
	// test with a hardcoded taxonomy for the MVP
	$field = '_ptt-primary-category';
	if ( array_key_exists ( $field, $_POST ) ) {
		// @TODO: update this check to work with all taxonomies
		$valid_ids = array_values ( $_POST['post_category'] );

		// validate the term id before saving it
		if ( in_array ( $_POST[$field], $valid_ids ) ) {
			update_post_meta ( $post_id, $field, $_POST[$field] );
		}
		// delete the existing value if it is no longer valid
		else if ( ! in_array ( get_post_meta ( $post_id, $valid_ids ) ) ) {
			delete_post_meta ( $post_id, $field );
		}
	}
}

// add a custom class to all primary content in archives
add_filter ( 'post_class', 'ptt_primary_content_class' );
function ptt_primary_content_class ( $classes ) {
	global $post;

	// restrict PTT categories for MVP
	// @TODO: extend PTT functionality to all taxonomies
	if( is_archive() && get_queried_object_id() === (integer) get_post_meta( $post->ID, '_ptt-primary-category', true ) ) {
		$classes[] = 'ptt-primary-content';
	}

	return $classes;
}

// move the primary term to the front of the list
// @TODO: extend PTT functionality to all taxonomies
add_filter ( 'the_category_list', 'ptt_sort_primary_term' );
function ptt_sort_primary_term ( $categories ) {
	global $post;

	$primary_term_id = (integer) get_post_meta ( $post->ID, '_ptt-primary-category', true );

	foreach ( $categories as $key => $category) {
		if ( $primary_term_id === $category->term_id ) {
			$matched_key = $key;
		}
	}

	$categories = array( $matched_key => $categories[$matched_key] ) + $categories;

	return $categories;
}

// add a custom class to the primary taxonomy term
// @TODO: extend PTT functionality to all taxonomies
add_filter ( 'the_category', 'ptt_primary_term_class' );
function ptt_primary_term_class ( $category_list ) {
	global $post;

	// find an opening in the first anchor tag to inject a class attribute
	// @TODO: This string manipulation method makes too many assumptions. Consider a more thorough method (perhaps parsing or regex)
	$position = strpos ( $category_list, ' ' );

	if ( $position !== false ) {
		$category_list = substr_replace ( $category_list, ' class="ptt_primary_term" ', $position, 1 );
	}

	return $category_list;
}
