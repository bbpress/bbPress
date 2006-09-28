<?php
require_once('admin.php');
bb_get_admin_header();

// BB_User_Search class
// by Mark Jaquith

class BB_User_Search {
	var $results;
	var $search_term;
	var $page;
	var $raw_page;
	var $users_per_page = 50;
	var $first_user;
	var $last_user;
	var $query_limit;
	var $query_from_where;
	var $total_users_for_query = 0;
	var $too_many_total_users = false;
	var $search_errors;

	function BB_User_Search ($search_term = '', $page = '') { // constructor
		$this->search_term = $search_term;
		$this->raw_page = ( '' == $page ) ? false : (int) $page;
		$this->page = (int) ( '' == $page ) ? 1 : $page;

		$this->prepare_query();
		$this->query();
		$this->prepare_vars_for_template_usage();
		$this->do_paging();
	}

	function prepare_query() {
		global $bbdb;
		$this->first_user = ($this->page - 1) * $this->users_per_page;
		$this->query_limit = 'LIMIT ' . $this->first_user . ',' . $this->users_per_page;
		if ( $this->search_term ) {
			$searches = array();
			$search_sql = 'AND (';
			foreach ( array('user_login', 'user_nicename', 'user_email', 'user_url', 'display_name') as $col )
				$searches[] = $col . " LIKE '%$this->search_term%'";
			$search_sql .= implode(' OR ', $searches);
			$search_sql .= ')';
		}
		$this->query_from_where = "FROM $bbdb->users WHERE 1=1 $search_sql";

		if ( !$_GET['update'] && !$this->search_term && !$this->raw_page && $bbdb->get_var("SELECT COUNT(ID) FROM $bbdb->users") > $this->users_per_page )
			$this->too_many_total_users = sprintf(__('Because this forum has more than %s users, they cannot all be shown on one page.  Use the paging or search functionality in order to find the user you want to edit.'), $this->users_per_page);
	}

	function query() {
		global $bbdb;
		$this->results = $bbdb->get_col('SELECT ID ' . $this->query_from_where . $this->query_limit);

		if ( $this->results )
			$this->total_users_for_query = $bbdb->get_var('SELECT COUNT(ID) ' . $this->query_from_where); // no limit
		else
			$this->search_errors = new WP_Error('no_matching_users_found', __('No matching users were found!'));
	}

	function prepare_vars_for_template_usage() {
		$this->search_term = stripslashes($this->search_term); // done with DB, from now on we want slashes gone
	}

	function do_paging() {
		if ( $this->total_users_for_query > $this->users_per_page ) { // have to page the results
			$this->paging_text = paginate_links( array(
				'total' => ceil($this->total_users_for_query / $this->users_per_page),
				'current' => $this->page,
				'prev_text' => '&laquo; Previous Page',
				'next_text' => 'Next Page &raquo;',
				'base' => 'users.php?%_%',
				'format' => 'userspage=%#%',
				'add_args' => array( 'usersearch' => urlencode($this->search_term) )
			) );
		}
	}

	function get_results() {
		return (array) $this->results;
	}

	function page_links() {
		echo $this->paging_text;
	}

	function results_are_paged() {
		if ( $this->paging_text )
			return true;
		return false;
	}

	function is_search() {
		if ( $this->search_term )
			return true;
		return false;
	}
}

// Query the users
$bb_user_search = new BB_User_Search($_GET['usersearch'], $_GET['userspage']);

// Make the user objects
foreach ( $bb_user_search->get_results() as $user_id ) {
	$tmp_user = new BB_User($user_id);
	$roles = $tmp_user->roles;
	$role = array_shift($roles);
	$roleclasses[$role][$tmp_user->data->user_login] = $tmp_user;
}

if ( $bb_user_search->too_many_total_users ) : ?>
	<div id="message" class="updated">
		<p><?php echo $bb_user_search->too_many_total_users; ?></p>
	</div>
<?php endif; ?>

<div class="wrap">

<?php if ( $bb_user_search->is_search() ) : ?>
	<h2><?php printf(__('Users Matching "%s" by Role'), wp_specialchars($bb_user_search->search_term)); ?></h2>
<?php else : ?>
	<h2><?php _e('User List by Role'); ?></h2>
<?php endif; ?>

	<form action="" method="get" name="search" id="search">
		<p><input type="text" name="usersearch" id="usersearch" value="<?php echo wp_specialchars($bb_user_search->search_term, 1); ?>" /> <input type="submit" value="<?php _e('Search for users &raquo;'); ?>" /></p>
	</form>

<?php if ( is_wp_error( $bb_user_search->search_errors ) ) : ?>
	<div class="error">
		<ul>
		<?php
			foreach ( $bb_user_search->search_errors->get_error_messages() as $message )
				echo "<li>$message</li>";
		?>
		</ul>
	</div>
<?php endif; ?>


<?php if ( $bb_user_search->get_results() ) : ?>

<?php 	if ( $bb_user_search->is_search() ) : ?>
	<p><a href="users.php"><?php _e('&laquo; Back to All Users'); ?></a></p>
<?php 	endif; ?>

	<h3><?php printf(__('%1$s &#8211; %2$s of %3$s shown below'), $bb_user_search->first_user + 1, min($bb_user_search->first_user + $bb_user_search->users_per_page, $bb_user_search->total_users_for_query), $bb_user_search->total_users_for_query); ?></h3>

<?php 	if ( $bb_user_search->results_are_paged() ) : ?>
	<div class="user-paging-text"><?php $bb_user_search->page_links(); ?></p></div>
<?php 	endif; ?>

<table class="widefat">
<?php
foreach($roleclasses as $role => $roleclass) {
	ksort($roleclass);
?>

<tr>
<?php if ( !empty($role) ) : ?>
	<th colspan="7"><h3><?php echo $bb_roles->role_names[$role]; ?></h3></th>
<?php else : ?>
	<th colspan="7"><h3><em><?php _e('No role for this blog'); ?></h3></th>
<?php endif; ?>
</tr>
<tr class="thead">
	<th><?php _e('ID') ?></th>
	<th><?php _e('Username') ?></th>
	<th><?php _e('Registered Since') ?></th>
	<th style="text-align: center"><?php _e('Actions') ?></th>
</tr>
</thead>
<tbody id="role-<?php echo $role; ?>"><?php
$style = '';
	foreach ( (array) $roleclass as $user_object )
		echo "\n\t" . bb_user_row($user_object->ID, $role);
?>

</tbody>
<?php } ?>
</table>

<?php 	if ( $bb_user_search->results_are_paged() ) : ?>
	<div class="user-paging-text"><?php $bb_user_search->page_links(); ?></div>
<?php 	endif; ?>

<?php endif; ?>
</div>

<?php bb_get_admin_footer(); ?>
