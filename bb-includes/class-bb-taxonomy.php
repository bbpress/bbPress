<?php

// TODO: cache
class BB_Taxonomy extends WP_Taxonomy {
	//
	// Term API
	//

	/**
	 * get_objects_in_term() - Return object_ids of valid taxonomy and term
	 *
	 * The strings of $taxonomies must exist before this function will continue. On failure of finding
	 * a valid taxonomy, it will return an WP_Error class, kind of like Exceptions in PHP 5, except you
	 * can't catch them. Even so, you can still test for the WP_Error class and get the error message.
	 *
	 * The $terms aren't checked the same as $taxonomies, but still need to exist for $object_ids to
	 * be returned.
	 *
	 * It is possible to change the order that object_ids is returned by either using PHP sort family
	 * functions or using the database by using $args with either ASC or DESC array. The value should
	 * be in the key named 'order'.
	 *
	 * @package WordPress
	 * @subpackage Taxonomy
	 * @since 2.3
	 *
	 * @uses wp_parse_args() Creates an array from string $args.
	 *
	 * @param string|array $terms String of term or array of string values of terms that will be used
	 * @param string|array $taxonomies String of taxonomy name or Array of string values of taxonomy names
	 * @param array|string $args Change the order of the object_ids, either ASC or DESC
	 * @return WP_Error|array If the taxonomy does not exist, then WP_Error will be returned. On success
	 *	the array can be empty meaning that there are no $object_ids found or it will return the $object_ids found.
	 */
	function get_objects_in_term( $terms, $taxonomies, $args = array() ) {
		if ( !is_array($terms) )
			$terms = array($terms);

		if ( !is_array($taxonomies) )
			$taxonomies = array($taxonomies);

		foreach ( $taxonomies as $taxonomy ) {
			if ( !$this->is_taxonomy($taxonomy) )
				return new WP_Error('invalid_taxonomy', __('Invalid Taxonomy'));
		}

		$defaults = array('order' => 'ASC', 'user_id' => 0);
		$args = wp_parse_args( $args, $defaults );
		extract($args, EXTR_SKIP);

		$user_id = (int) $user_id;
		$user_sql = '';
		if ( $user_id )
			$user_sql = " AND tr.user_id = $user_id";

		$order = ( 'desc' == strtolower($order) ) ? 'DESC' : 'ASC';

		$terms = array_map('intval', $terms);

		$taxonomies = "'" . implode("', '", $taxonomies) . "'";
		$terms = "'" . implode("', '", $terms) . "'";

		$object_ids = $this->db->get_col("SELECT tr.object_id FROM {$this->db->term_relationships} AS tr INNER JOIN {$this->db->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy IN ($taxonomies) AND tt.term_id IN ($terms)$user_sql ORDER BY tr.object_id $order");

		if ( ! $object_ids )
			return array();

		return $object_ids;
	}

	/**
	 * delete_object_term_relationships() - Will unlink the term from the taxonomy
	 *
	 * Will remove the term's relationship to the taxonomy, not the term or taxonomy itself.
	 * The term and taxonomy will still exist. Will require the term's object ID to perform
	 * the operation.
	 *
	 * @package WordPress
	 * @subpackage Taxonomy
	 * @since 2.3
	 *
	 * @param int $object_id The term Object Id that refers to the term
	 * @param string|array $taxonomy List of Taxonomy Names or single Taxonomy name.
	 */
	function delete_object_term_relationships( $object_id, $taxonomies, $user_id = 0 ) {
		$object_id = (int) $object_id;
		$user_id = (int) $user_id;

		if ( !is_array($taxonomies) )
			$taxonomies = array($taxonomies);

		$user_sql = '';
		if ( $user_id )
			$user_sql = " AND user_id = $user_id";

		foreach ( $taxonomies as $taxonomy ) {
			$terms = $this->get_object_terms($object_id, $taxonomy, 'fields=tt_ids');
			$in_terms = "'" . implode("', '", $terms) . "'";
			$this->db->query("DELETE FROM {$this->db->term_relationships} WHERE object_id = '$object_id'$user_sql AND term_taxonomy_id IN ($in_terms)");
			$this->update_term_count($terms, $taxonomy);
		}
	}

	/**
	 * get_object_terms() - Retrieves the terms associated with the given object(s), in the supplied taxonomies.
	 *
	 * The following information has to do the $args parameter and for what can be contained in the string
	 * or array of that parameter, if it exists.
	 *
	 * The first argument is called, 'orderby' and has the default value of 'name'. The other value that is
	 * supported is 'count'.
	 *
	 * The second argument is called, 'order' and has the default value of 'ASC'. The only other value that
	 * will be acceptable is 'DESC'.
	 *
	 * The final argument supported is called, 'fields' and has the default value of 'all'. There are
	 * multiple other options that can be used instead. Supported values are as follows: 'all', 'ids',
	 * 'names', and finally 'all_with_object_id'.
	 *
	 * The fields argument also decides what will be returned. If 'all' or 'all_with_object_id' is choosen or
	 * the default kept intact, then all matching terms objects will be returned. If either 'ids' or 'names'
	 * is used, then an array of all matching term ids or term names will be returned respectively.
	 *
	 * @package WordPress
	 * @subpackage Taxonomy
	 * @since 2.3
	 *
	 * @param int|array $object_id The id of the object(s) to retrieve.
	 * @param string|array $taxonomies The taxonomies to retrieve terms from.
	 * @param array|string $args Change what is returned
	 * @return array|WP_Error The requested term data or empty array if no terms found. WP_Error if $taxonomy does not exist.
	 */
	function get_object_terms($object_ids, $taxonomies, $args = array()) {
		if ( !is_array($taxonomies) )
			$taxonomies = array($taxonomies);

		foreach ( $taxonomies as $taxonomy ) {
			if ( !$this->is_taxonomy($taxonomy) )
				return new WP_Error('invalid_taxonomy', __('Invalid Taxonomy'));
		}

		if ( !is_array($object_ids) )
			$object_ids = array($object_ids);
		$object_ids = array_map('intval', $object_ids);

		$defaults = array('orderby' => 'name', 'order' => 'ASC', 'fields' => 'all', 'user_id' => 0);
		$args = wp_parse_args( $args, $defaults );
		extract($args, EXTR_SKIP);

		$user_id = (int) $user_id;
		$user_sql = '';
		if ( $user_id )
			$user_sql = " AND tr.user_id = $user_id";

		if ( 'count' == $orderby )
			$orderby = 'tt.count';
		else if ( 'name' == $orderby )
			$orderby = 't.name';

		$taxonomies = "'" . implode("', '", $taxonomies) . "'";
		$object_ids = implode(', ', $object_ids);

		if ( 'all' == $fields )
			$select_this = 't.*, tt.*';
		else if ( 'ids' == $fields )
			$select_this = 't.term_id';
		else if ( 'names' == $fields ) 
			$select_this = 't.name';
		else if ( 'all_with_object_id' == $fields )
			$select_this = 't.*, tt.*, tr.object_id';

		$query = "SELECT $select_this FROM {$this->db->terms} AS t INNER JOIN {$this->db->term_taxonomy} AS tt ON tt.term_id = t.term_id INNER JOIN {$this->db->term_relationships} AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy IN ($taxonomies) AND tr.object_id IN ($object_ids)$usr_sql ORDER BY $orderby $order";

		if ( 'all' == $fields || 'all_with_object_id' == $fields ) {
			$terms = $this->db->get_results($query);
			$this->update_term_cache($terms);
		} else if ( 'ids' == $fields || 'names' == $fields ) {
			$terms = $this->db->get_col($query);
		} else if ( 'tt_ids' == $fields ) {
			$terms = $this->db->get_col("SELECT tr.term_taxonomy_id FROM {$this->db->term_relationships} AS tr INNER JOIN {$this->db->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id IN ($object_ids) AND tt.taxonomy IN ($taxonomies) ORDER BY tr.term_taxonomy_id $order");
		}

		if ( ! $terms )
			return array();

		return $terms;
	}

	/**
	 * set_object_terms() - Create Term and Taxonomy Relationships
	 * 
	 * Relates an object (post, link etc) to a term and taxonomy type. Creates the term and taxonomy
	 * relationship if it doesn't already exist. Creates a term if it doesn't exist (using the slug).
	 *
	 * A relationship means that the term is grouped in or belongs to the taxonomy. A term has no
	 * meaning until it is given context by defining which taxonomy it exists under.
	 *
	 * @package WordPress
	 * @subpackage Taxonomy
	 * @since 2.3
	 *
	 * @param int $object_id The object to relate to.
	 * @param array|int|string $term The slug or id of the term.
	 * @param array|string $taxonomy The context in which to relate the term to the object.
	 * @param bool $append If false will delete difference of terms.
	 * @return array|WP_Error Affected Term IDs
	 */
	function set_object_terms($object_id, $terms, $taxonomy, $user_id, $append = false) {
		$object_id = (int) $object_id;
		if ( !$user_id = (int) $user_id )
			return new WP_Error( 'invalid_user_id', __('Invalid User ID') );

		if ( !$this->is_taxonomy($taxonomy) )
			return new WP_Error('invalid_taxonomy', __('Invalid Taxonomy'));

		if ( !is_array($terms) )
			$terms = array($terms);

		if ( ! $append )
			$old_terms = $this->get_object_terms($object_id, $taxonomy, 'fields=tt_ids');

		$tt_ids = array();
		$term_ids = array();

		foreach ($terms as $term) {
			if ( !strlen(trim($term)) )
				continue;
			
			if ( !$id = $this->is_term($term, $taxonomy) )
				$id = $this->insert_term($term, $taxonomy);
			if ( is_wp_error($id) )
				return $id;
			$term_ids[] = $id['term_id'];
			$id = $id['term_taxonomy_id'];
			$tt_ids[] = $id;

			if ( $this->db->get_var( $this->db->prepare( "SELECT term_taxonomy_id FROM {$this->db->term_relationships} WHERE object_id = %d AND term_taxonomy_id = %d", $object_id, $id ) ) )
				continue;
			$this->db->insert( $this->db->term_relationships, array( 'object_id' => $object_id, 'term_taxonomy_id' => $id, 'user_id' => $user_id ) );
		}

		$this->update_term_count($tt_ids, $taxonomy);

		if ( ! $append ) {
			$delete_terms = array_diff($old_terms, $tt_ids);
			if ( $delete_terms ) {
				$in_delete_terms = "'" . implode("', '", $delete_terms) . "'";
				$this->db->query("DELETE FROM {$this->db->term_relationships} WHERE object_id = '$object_id' AND term_taxonomy_id IN ($in_delete_terms)");
				$this->update_term_count($delete_terms, $taxonomy);
			}
		}

		return $tt_ids;
	}

}

?>
