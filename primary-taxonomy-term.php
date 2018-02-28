<?php
/*
Plugin Name: Primary Taxonomy Term
Plugin URI: https://github.com/pressden/primary-taxonomy-term
Description: Primary Taxonomy Term adds the ability to specify a primary term for any taxonomy.
Version: 0.4.0
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

	foreach ( $categories as $key => $category ) {
		if ( $primary_term_id === $category->term_id ) {
			$matched_key = $key;
		}
	}

	$categories = array ( $matched_key => $categories[$matched_key] ) + $categories;

	return $categories;
}

// add a custom class to the primary taxonomy term
// @TODO: extend PTT functionality to all taxonomies
add_filter ( 'the_category', 'ptt_primary_term_class' );
function ptt_primary_term_class ( $category_list ) {
	// ensure this filter is only run on single posts
	if ( ! is_single() ) {
		return $category_list;
	}
	
	global $post;

	$term_id = (integer) get_post_meta ( $post->ID, '_ptt-primary-category', true );
	$term = get_term ( $term_id, 'category' );

	/******************************************
	 *
	 * @NOTE: DOM Manipulation seems like overkill for such a small feature.
	 * The previous solution relied on basic string manipulation and made too
	 * many assumptions. More research may reveal a more direct solution for
	 * manipulating the category list. If not a future version of WP almost
	 * certainly will. See inline comments below to explain each step.
	 *
	 ******************************************/

	$list = new DOMDocument();
	$list->loadHTML ( $category_list );

	// segment the DOM by anchors
	$links = $list->getElementsByTagName ( 'a' );

	// loop through each anchor
	foreach ( $links as $link ) {
		// locate the primary term anchor
		if ( strtolower ( $term->name ) === strtolower ( $link->nodeValue ) ) {
			$attributes = $link->attributes;

			// loop through the existing attributes
			foreach ( $attributes as $attribute ) {
				$has_class = false;

				// if a class attribute exists, append our class
				if ( 'class' === $attribute->name ) {
					$has_class = true;
					$attribute->value .= ' ptt-primary-term';
				}
			}

			// otherwise create class attribute from scratch
			if ( false === $has_class ) {
				$link->setAttribute ( 'class', 'ptt-primary-term' );
			}
		}
	}

	// return the updated category list
	return $list->saveHTML();
}

/******************************************
 *
 * @NOTE: The 'ptt_archive_query' hook is currently disabled and included
 * for demonstration purposes only. This function manipulates the archive
 * query to return ONLY posts that match a primary category check.
 *
 * Reducing an archive to only display primary content is a restrictive
 * use case. This hook is only meant to demonstrate an understanding of
 * the WP_Query object.
 *
 ******************************************/
//add_action ( 'pre_get_posts', 'ptt_archive_query' );
function ptt_archive_query ( $query ) {
	// ensure this filter is only run on archives
	if ( ! is_archive() ) {
		return $query;
	}

	// modify the main query
  if ( $query->is_main_query() ) {
		// get the queried object id
		$term_id = get_queried_object_id();

		// define the meta query
		$meta_query = array (
			'primary-term-clause' => array (
				'key' => '_ptt-primary-category',
				'value' => $term_id,
			),
		);

		$query->set ( 'meta_query', $meta_query );
	}

	return $query;
}

/******************************************
 *
 * @NOTE: The 'ptt_archive_results' hook is currently disabled and included
 * for demonstration purposes only. This function sorts the posts (after
 * the query) so that primary content bubbles up to the top of each page.
 *
 * This hook is only meant to demonstrate an understanding of the WP_Query
 * object. This approach has limitied application. For example, when dealing
 * with a paginated archive, the primary content will be sprinkled across
 * multiple pages vs. being moved to the first page(s) of the archive.
 *
 * A better approach would be to modify the behavior of archive pages in
 * the theme to treat primary content more like "sticky posts" so they
 * all most to the front of the archive.
 *
 ******************************************/
//add_action ( 'loop_start', 'ptt_archive_results' );
function ptt_archive_results ( $query ) {
	// ensure this filter is only run on archives
	if ( ! is_archive() ) {
		return $query;
	}

	// sort the results (posts) of the main query
	if ( $query->is_main_query() ) {
		usort ( $query->posts, function ( $post_a, $post_b ) {
			$term_id = get_queried_object_id();
			$a = get_post_meta ( $post_a->ID, '_ptt-primary-category', true );
			$b = get_post_meta ( $post_b->ID, '_ptt-primary-category', true );

			if ( $term_id == $a && $term_id != $b ) {
				return -1;
			}
			else if ( $term_id == $b && $term_id != $a ) {
				return 1;
			}
			else {
				return 0;
			}
		} );
	}

	return $query;
}
