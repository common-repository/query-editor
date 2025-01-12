<?php

/*
Plugin Name: Query Editor
Description: Adds some simple options on the reading settings page to customise the main query. You can change the default post type(s), exclude terms from any taxonomy and change the ordering.
Version: 0.4
Author: Robert O'Rourke
Author URI: http://sanchothefat.com
License: GPLv2 or later
*/

// plugin helper
require_once( 'inc/icit-plugin.php' );
icit_register_plugin( 'query_editor', __FILE__, array( 'parent_slug' => 'tools.php' ) );

if ( ! defined( 'QUERY_EDITOR_BASE' ) )
	define( 'QUERY_EDITOR_BASE', dirname( __FILE__ ) );
if ( ! defined( 'QUERY_EDITOR_URL' ) )
	define( 'QUERY_EDITOR_URL', plugins_url( '', __FILE__ ) );

// initialise
add_action( 'plugins_loaded', array( 'query_editor', 'instance' ) );

class query_editor {

	public $section = __CLASS__;
	public $default_queries = array();
	public $orderby_tax = false;
	public $orderby_tax_order = 'ASC';

	/**
	 * Reusable object instance.
	 *
	 * @type object
	 */
	protected static $instance = null;


	/**
	 * Creates a new instance. Called on 'after_setup_theme'.
	 * May be used to access class methods from outside.
	 *
	 * @see    __construct()
	 * @return void
	 */
	public static function instance() {
		null === self :: $instance AND self :: $instance = new self;
		return self :: $instance;
	}


	function __construct() {

		// interface
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// functionality
		add_action( 'init', array( $this, 'init' ) );

		// set default
		$this->default_queries = array(
			array(
				'title' => 'Query %s',
				'use_condition' => 0,
				'ruleset' => array(
					array(
						'operator' => 'is',
						'condition' => 'is_home',
						'is_post_type_archive' => '',
						'is_tax' => '',
						'is_author' => '',
						'is_search' => '',
						'request_key' => '',
						'request_val' => ''
					)
				),
				'match_all' => 0,
				'post_types' => array(),
				'exclude_terms' => array(),
				'orderby' => 'date',
				'order' => 'desc',
				'orderby_tax' => '',
				'orderby_tax_order' => 'asc',
				'offset' => 0,
				'nopaging' => 0,
				'per_page' => '',
				'meta_key' => '',
				'meta_compare' => '=',
				'meta_value' => '',
				'meta_type' => 'CHAR',
				'ignore_stickies' => 0
			)
		);

	}

	function admin_init() {
		global $pagenow;

		// query modifier settings section
		add_settings_section( $this->section, '', array( $this, $this->section ), $this->section );
		register_setting( $this->section, 'qe_queries', array( $this, 'save' ) );

		$plugin = icit_plugins::instance();
		if ( $plugin->is_plugin_page() ) {
			wp_enqueue_style( 'query-editor', QUERY_EDITOR_URL . '/css/admin.css' );
			wp_enqueue_script( 'query-editor', QUERY_EDITOR_URL . '/js/admin.js', array( 'jquery' ) );
		}

	}


	function init() {

		// filter posts just before any regular filters are run
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ), apply_filters( 'query_editor_pre_get_posts_priority', 9 ), 1 );

		// post archive link for built ins
		add_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link' ), 10, 2 );

	}

	function get_field_name( $name, $index = false ) {
		return "qe_{$name}" . ( is_int( $index ) ? "[$index]" : '' );
	}

	function get_field_id( $name, $index = false ) {
		return "qe-{$name}" . ( is_int( $index ) ? "-$index" : '' );
	}

	// main section explanation
	function query_editor() {

		$default_queries = $this->default_queries;

		$queries = get_option( 'qe_queries', $default_queries );

		$screen = get_current_screen();

		foreach( $queries as $i => $query ) {
			add_meta_box(
				"query-editor-$i",
				sprintf( __( isset( $query[ 'title' ] ) ? wptexturize( stripcslashes( $query[ 'title' ] ) ) : 'Query %s' ), ($i+1) ),
				array( $this, 'meta_box' ),
				$screen->id,
				'normal',
				'default',
				array( 'i' => $i, 'query' => $query )
			);
		}

		?>
		<input type="hidden" name="qe_queries" value="" />
		<p class="intro">To find out more about how modifying the query works and for some examples of order by statements see the <a href="http://codex.wordpress.org/Class_Reference/WP_Query">WP_Query</a> codex page.</p>
		<div class="queries">
			<?php do_meta_boxes( $screen->id, 'normal', $screen ); ?>
			<div><input class="button" type="submit" name="addquery" value="<?php _e( '+ Add query modifier' ); ?>" /></div>
		</div>
		<?php
	}

	function meta_box( $screen, $callback ) {
		extract( $callback[ 'args' ], EXTR_SKIP );
		?>
					<div class="query-title">
						<label for="qe-title-<?php echo $i; ?>"><strong><?php _e( 'Query title:' ); ?></strong></label>
						<input id="qe-title-<?php echo $i; ?>" type="text" class="regular-text" size="50" name="qe_title[<?php echo $i; ?>]" value="<?php esc_attr_e( isset( $query[ 'title' ] ) ? stripcslashes( $query[ 'title' ] ) : 'Query %s' ); ?>" />
					</div>
					<div class="query-toolbar">
						<input class="button button-primary" type="submit" value="<?php _e( 'Save' ); ?>" />
						<?php if ( $i > 0 ) { ?>
						<input class="button" type="submit" name="deletequery[<?php echo $i; ?>]" value="<?php _e( 'Delete query' ); ?>" />
						<?php } ?>
					</div>
					<div class="condition-wrap">
						<h4>Apply these rules
							<label><input type="radio" name="qe_use_condition[<?php echo $i; ?>]" value="0" <?php checked( 0, $query[ 'use_condition' ] ); ?> /> <span>to the main query</span></label> <span class="hide-if-js">or</span>
							<label><input type="radio" name="qe_use_condition[<?php echo $i; ?>]" value="1" <?php checked( 1, $query[ 'use_condition' ] ); ?> /> <span>when a condition is met</span></label>
						</h4>
						<div class="conditions<?php if ( ! $query[ 'use_condition' ] ) echo ' hide-if-js'; ?>">
							<p>If current location...</p>
							<ul class="ruleset">
							<?php foreach( $query[ 'ruleset' ] as $j => $rule ) { ?>
								<li class="rule">
									<select class="operator" name="qe_operator<?php echo "[$i][$j]"; ?>">
										<option <?php selected( 'is', $rule['operator'] ); ?> value="is">is</option>
										<option <?php selected( 'not', $rule['operator'] ); ?> value="not">is not</option>
									</select>
									<select class="condition" name="qe_condition<?php echo "[$i][$j]"; ?>">
										<option <?php selected( 'is_home', $rule['condition'] ); ?> value="is_home">home / blog page</option>
										<option <?php selected( 'is_archive', $rule['condition'] ); ?> value="is_archive">an archive</option>
										<option <?php selected( 'is_post_type_archive', $rule['condition'] ); ?> value="is_post_type_archive">a post type archive</option>
										<option <?php selected( 'is_tax', $rule['condition'] ); ?> value="is_tax">a taxonomy archive</option>
										<option <?php selected( 'is_date', $rule['condition'] ); ?> value="is_date">a date archive</option>
										<option <?php selected( 'is_author', $rule['condition'] ); ?> value="is_author">an author archive</option>
										<option <?php selected( 'is_search', $rule['condition'] ); ?> value="is_search">search results</option>
										<option <?php selected( 'is_feed', $rule['condition'] ); ?> value="is_feed">a feed</option>
										<option <?php selected( 'get', $rule['condition'] ); ?> value="get">using GET parameter</option>
										<option <?php selected( 'post', $rule['condition'] ); ?> value="post">using POST parameter</option>
										<option <?php selected( 'request', $rule['condition'] ); ?> value="request">using REQUEST parameter</option>
									</select>

									<!-- choose post type(s) -->
									<select class="is_post_type_archive<?php if ( $rule[ 'condition' ] != 'is_post_type_archive' ) echo ' hide-if-js'; ?>" name="qe_is_post_type_archive<?php echo "[$i][$j]"; ?>">
										<option value=""><?php _e( 'Any' ); ?></option>
									<?php foreach( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) { ?>
										<option <?php selected( $post_type->name, $rule[ 'is_post_type_archive' ] ); ?> value="<?php echo $post_type->name; ?>"><?php _e( $post_type->label ); ?></option>
									<?php } ?>
									</select>

									<!-- choose taxonomies [choose term(s)] -->
									<select class="is_tax<?php if ( $rule[ 'condition' ] != 'is_tax' ) echo ' hide-if-js'; ?>" name="qe_is_tax<?php echo "[$i][$j]"; ?>">
										<option value=""><?php _e( 'Any' ); ?></option>
									<?php foreach( get_taxonomies( array(), 'objects' ) as $taxonomy ) { ?>
										<option <?php selected( $taxonomy->name, $rule[ 'is_tax' ] ); ?> value="<?php echo $taxonomy->name; ?>"><?php _e( $taxonomy->label ); ?></option>
									<?php } ?>
									</select>

									<!-- choose author(s) -->
									<select class="is_author<?php if ( $rule[ 'condition' ] != 'is_author' ) echo ' hide-if-js'; ?>" name="qe_is_author<?php echo "[$i][$j]"; ?>">
										<option value=""><?php _e( 'Any' ); ?></option>
									<?php foreach( get_users() as $user ) { ?>
										<option <?php selected( $user->ID, $rule[ 'is_author' ] ); ?> value="<?php echo $user->ID; ?>"><?php _e( $user->display_name ); ?></option>
									<?php } ?>
									</select>

									<!-- choose search term(s) -->
									<span class="is_search<?php if ( $rule[ 'condition' ] != 'is_search' ) echo ' hide-if-js'; ?>">
										<label>for term <input type="text" name="qe_is_search<?php echo "[$i][$j]"; ?>" value="<?php esc_attr_e( $rule[ 'is_search' ] ); ?>" /> <small>(optional)</small></label>
									</span>

									<!-- choose feed type(s)? -->

									<!-- choose GET/POST/REQUEST key/val -->
									<span class="get post request<?php if ( ! in_array( $rule[ 'condition' ], array( 'get', 'post', 'request' ) ) ) echo ' hide-if-js'; ?>">
										<label>with key <input type="text" name="qe_request_key<?php echo "[$i][$j]"; ?>" value="<?php esc_attr_e( $rule[ 'request_key' ] ); ?>" /></label>
										<label>and value of <input type="text" name="qe_request_val<?php echo "[$i][$j]"; ?>" value="<?php esc_attr_e( $rule[ 'request_val' ] ); ?>" /> <small>(optional)</small></label>
									</span>

									<?php if ( $j > 0 ) { ?>
									<input class="button remove" type="submit" name="deleterule<?php echo "[$i][$j]"; ?>" value="<?php _e( 'Delete' ); ?>" />
									<?php } ?>
								</li>
							<?php } ?>
								<li><input class="button" type="submit" name="newrule[<?php echo $i; ?>]" value="<?php _e( '+ Add another condition' ); ?>" /></li>
							</ul>
							<p>
								Modify query when <label><input type="radio" name="qe_match_all<?php echo "[$i]"; ?>" value="1" <?php checked( 1, $query['match_all'] ); ?> /> <span>all</span></label> /
								<label><input type="radio" name="qe_match_all<?php echo "[$i]"; ?>" value="0" <?php checked( 0, $query['match_all'] ); ?> /> <span>any</span></label> of the above rules are true.
							</p>
						</div>

						<!-- query editor interface -->
						<table class="query-editor form-table">
							<tbody>
						<?php

							// post type selection
							$this->editor_post_types( $query, $i );

							// taxonomy fields
							$this->editor_exclude_terms( $query, $i );

							// ordering
							$this->editor_orderby( $query, $i );
							$this->editor_order( $query, $i );

							// taxonomy ordering
							$this->editor_orderby_tax( $query, $i );
							$this->editor_orderby_tax_order( $query, $i );

							// paging
							$this->editor_per_page( $query, $i );
							$this->editor_nopaging( $query, $i );

							// offset
							$this->editor_offset( $query, $i );

							// meta
							$this->editor_meta( $query, $i );
							//$this->editor_meta_key( $query, $i );
							//$this->editor_meta_compare( $query, $i );
							//$this->editor_meta_value( $query, $i );

							// ignore stickies
							$this->editor_ignore_stickies( $query, $i );

						?>
							</tbody>
						</table>
					</div>
		<?php
	}

	// post types selection field
	function editor_post_types( $query, $index ) { ?>
		<tr class="post-types">
			<th><?php _e( 'Post types:' ); ?></th>
			<td><ul class="categorychecklist" style="max-height:220px;overflow:auto;margin:0 0 20px;"><?php
				$types = get_post_types( array( 'public' => true ), 'objects' );
				foreach( $types as $type ) {
					$field_id = $this->get_field_id( "post_type_{$type->name}", $index ); ?>
				<li><label for="<?php echo $field_id; ?>">
					<input id="<?php echo $field_id; ?>" type="checkbox" <?php if ( is_array( $query[ 'post_types' ] ) && in_array( $type->name, $query[ 'post_types' ] ) ) echo ' checked="checked"'; ?> name="<?php echo $this->get_field_name( "post_types", $index ); ?>[]" value="<?php echo $type->name; ?>" />
					<?php echo $type->labels->name; ?></label></li>
			<?php } ?>
			</ul>
			<p class="description"><?php _e( 'If none are selected the default value for post type in the main query is used.' ); ?></p>
			</td>
		</tr>
		<?php
	}

	function editor_exclude_terms( $query, $index ) {
		?>
		<tr class="exclude-terms">
			<th><?php _e( 'Terms to exclude:' ); ?></th>
			<td><?php
			foreach( get_taxonomies( array(  ), 'objects' ) as $taxonomy ) {
				$terms = get_terms( $taxonomy->name, array( 'hide_empty' => false ) );
				if ( empty( $terms ) ) continue;
				?>
				<h4 style="margin:0 0 5px;"><?php echo $taxonomy->labels->name; ?></h4>
				<div class="checklist">
					<ul class="categorychecklist" style="max-height:180px;overflow:auto;margin:0 0 20px;"><?php
						if ( count( $terms ) )  {
						foreach( $terms as $term ) {
							$field_id = $this->get_field_id( 'exclude-terms-' . $taxonomy->name . '-' . $term->term_id, $index ); ?>
						<li><label for="<?php echo $field_id; ?>">
							<input id="<?php echo $field_id; ?>" type="checkbox" <?php if ( isset( $query[ 'exclude_terms' ][ $taxonomy->name ] ) && is_array( $query[ 'exclude_terms' ][ $taxonomy->name ] ) && in_array( $term->term_id, $query[ 'exclude_terms' ][ $taxonomy->name ] ) ) echo ' checked="checked"'; ?> name="<?php echo $this->get_field_name( 'exclude_terms', $index ); ?>[<?php echo $taxonomy->name; ?>][]" value="<?php echo $term->term_id; ?>" />
							<?php echo $term->name; ?></label></li>
					<?php } } else { ?>
						<li><?php _e( 'None set' ); ?></li>
					<?php } ?>
					</ul>
				</div>
			<?php
		} ?>
			</td>
		</tr>
		<?php
	}

	function editor_per_page( $query, $index ) { ?>
		<tr>
			<th><?php _e( 'Posts per page:' ); ?></th>
			<td>
				<input size="5" type="number" name="<?php echo $this->get_field_name( 'per_page', $index ); ?>" value="<?php esc_attr_e( isset( $query[ 'per_page' ] ) ? $query[ 'per_page' ] : '' ); ?>" />
			</td>
		</tr>
		<?php
	}

	function editor_order( $query, $index ) { ?>
		<tr>
			<th><?php _e( 'Order:' ); ?></th>
			<td>
				<label for="<?php echo $this->get_field_id( 'order_asc', $index ); ?>"><input <?php checked( 'asc', $query[ 'order' ] ); ?> id="<?php echo $this->get_field_id( 'order_asc', $index ); ?>" type="radio" name="<?php echo $this->get_field_name( 'order', $index ); ?>" value="asc" /> <?php _e( 'Ascending' ); ?></label>
				<label for="<?php echo $this->get_field_id( 'order_desc', $index ); ?>"><input <?php checked( 'desc', $query[ 'order' ] ); ?> id="<?php echo $this->get_field_id( 'order_desc', $index ); ?>" type="radio" name="<?php echo $this->get_field_name( 'order', $index ); ?>" value="desc" /> <?php _e( 'Descending' ); ?></label>
			</td>
		</tr>
		<?php
	}

	function editor_orderby( $query, $index ) { ?>
		<tr>
			<th><?php _e( 'Order by:' ); ?></th>
			<td>
				<select name="<?php echo $this->get_field_name( 'orderby', $index ); ?>">
					<option <?php selected( 'date', $query[ 'orderby' ] ); ?> value="date"><?php _e( 'Date' ); ?></option>
					<option <?php selected( 'modified', $query[ 'orderby' ] ); ?> value="modified"><?php _e( 'Modified date' ); ?></option>
					<option <?php selected( 'title', $query[ 'orderby' ] ); ?> value="title"><?php _e( 'Title' ); ?></option>
					<option <?php selected( 'name', $query[ 'orderby' ] ); ?> value="name"><?php _e( 'Slug' ); ?></option>
					<option <?php selected( 'rand', $query[ 'orderby' ] ); ?> value="rand"><?php _e( 'Random' ); ?></option>
					<option <?php selected( 'ID', $query[ 'orderby' ] ); ?> value="ID"><?php _e( 'ID' ); ?></option>
					<option <?php selected( 'parent', $query[ 'orderby' ] ); ?> value="parent"><?php _e( 'Parent ID' ); ?></option>
					<option <?php selected( 'author', $query[ 'orderby' ] ); ?> value="author"><?php _e( 'Author' ); ?></option>
					<option <?php selected( 'comment_count', $query[ 'orderby' ] ); ?> value="comment_count"><?php _e( 'Comment count' ); ?></option>
					<option <?php selected( 'menu_order', $query[ 'orderby' ] ); ?> value="menu_order"><?php _e( 'Menu order' ); ?></option>
					<option <?php selected( 'meta_value', $query[ 'orderby' ] ); ?> value="meta_value"><?php _e( 'Meta value' ); ?></option>
					<option <?php selected( 'meta_value_num', $query[ 'orderby' ] ); ?> value="meta_value_num"><?php _e( 'Numeric meta value' ); ?></option>
					<option <?php if ( ! preg_match( "/^(date|modified|title|name|rand|comment_count|parent|ID|author|menu_order|meta_value|meta_value_num)$/", $query[ 'orderby' ] ) ) selected( 'custom', 'custom' ); ?> value="custom"><?php _e( 'Custom' ); ?></option>
				</select>
				<label class="custom-orderby" for="<?php echo $this->get_field_id( 'custom-orderby', $index ); ?>"><?php _e( 'Custom' ); ?> <input size="60" id="<?php echo $this->get_field_id( 'custom-orderby', $index ); ?>" type="text" name="<?php echo $this->get_field_name( 'orderby_custom', $index ); ?>" value="<?php esc_attr_e( $query[ 'orderby' ] ); ?>" /></label>
				<p class="description">If you set a custom value you can order by multiple fields by separating field names with a space eg: <code>meta_value ID parent</code></p>
			</td>
		</tr>
		<?php
	}

	function editor_orderby_tax_order( $query, $index ) { ?>
		<tr>
			<th><?php _e( 'Taxonomy term order:' ); ?></th>
			<td>
				<label for="<?php echo $this->get_field_id( 'orderby_tax_order_asc', $index ); ?>"><input <?php checked( 'asc', $query[ 'orderby_tax_order' ] ); ?> id="<?php echo $this->get_field_id( 'orderby_tax_order_asc', $index ); ?>" type="radio" name="<?php echo $this->get_field_name( 'orderby_tax_order', $index ); ?>" value="asc" /> <?php _e( 'Ascending' ); ?></label>
				<label for="<?php echo $this->get_field_id( 'orderby_tax_order_desc', $index ); ?>"><input <?php checked( 'desc', $query[ 'orderby_tax_order' ] ); ?> id="<?php echo $this->get_field_id( 'orderby_tax_order_desc', $index ); ?>" type="radio" name="<?php echo $this->get_field_name( 'orderby_tax_order', $index ); ?>" value="desc" /> <?php _e( 'Descending' ); ?></label>
			</td>
		</tr>
		<?php
	}

	function editor_orderby_tax( $query, $index ) { ?>
		<tr>
			<th><?php _e( 'Order by taxonomy terms:' ); ?></th>
			<td>
				<select name="<?php echo $this->get_field_name( 'orderby_tax', $index ); ?>">
					<option value=""><?php _e( 'Don\'t order by taxonomy terms' ); ?></option>
					<?php foreach( get_taxonomies( array(), 'objects' ) as $taxonomy ) { ?>
					<option <?php selected( $taxonomy->name, $query[ 'orderby_tax' ] ); ?> value="<?php esc_attr_e( $taxonomy->name ); ?>"><?php _e( $taxonomy->labels->name ); ?></option>
					<?php } ?>
				</select>
				<p class="description">Use this field to optionally order posts by the terms in a taxonomy.</p>
			</td>
		</tr>
		<?php
	}

	function editor_offset( $query, $index ) { ?>
		<tr>
			<th><?php _e( 'Offset posts:' ); ?></th>
			<td>
				<input size="5" type="number" name="<?php echo $this->get_field_name( 'offset', $index ); ?>" value="<?php esc_attr_e( $query[ 'offset' ] ); ?>" />
				<p class="description">This allows you to skip past any number of posts from the start of the query.</p>
			</td>
		</tr>
		<?php
	}

	function editor_nopaging( $query, $index ) { ?>
		<tr>
			<th><label for="<?php echo $this->get_field_id( 'nopaging', $index ); ?>"><?php _e( 'Disable paging:' ); ?></label></th>
			<td>
				<input <?php checked( true, $query[ 'nopaging' ] ); ?> id="<?php echo $this->get_field_id( 'nopaging', $index ); ?>" type="checkbox" name="<?php echo $this->get_field_name( 'nopaging', $index ); ?>" value="1" />
				<p class="description"><?php _e( 'Shows all found posts on a single page.' ); ?></p>
			</td>
		</tr>
		<?php
	}

	function editor_meta( $query, $index ) {
		$meta_comparisons = array(
						'=' => __( 'Equal to' ),
						'!=' => __( 'Not equal to' ),
						'>' => __( 'Greater than' ),
						'>=' => __( 'Greater than or equal to' ),
						'<' => __( 'Less than' ),
						'<=' => __( 'Less than or equal to' ),
						'LIKE' => __( 'Like (use % as a wildcard)' ),
						'NOT LIKE' => __( 'Not like (use % as a wildcard)' ),
						'IN' => __( 'In (comma separated values)' ),
						'NOT IN' => __( 'Not in (comma separated values)' ),
						'BETWEEN' => __( 'Between (comma separated values)' ),
						'NOT BETWEEN' => __( 'Not between (comma separated values)' )
						// 'NOT EXISTS' => __( 'Doesn\'t exist' ) // WP 3.5
		);
		$meta_types = array( 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED' );
		?>
		<tr>
			<th><label for="<?php echo $this->get_field_id( 'meta_key', $index ); ?>"><?php _e( 'Meta Key:' ); ?></label></th>
			<td>
				<input size="60" type="text" id="<?php echo $this->get_field_id( 'meta_key', $index ); ?>" name="<?php echo $this->get_field_name( 'meta_key', $index ); ?>" value="<?php esc_attr_e( $query[ 'meta_key' ] ); ?>" />
				<p class="description"><?php _e( 'Queries all posts that have this meta key regardless of its value.' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="<?php echo $this->get_field_id( 'meta_compare', $index ); ?>"><?php _e( 'Meta Comparison:' ); ?></label></th>
			<td>
				<select id="<?php echo $this->get_field_id( 'meta_compare', $index ); ?>" name="<?php echo $this->get_field_name( 'meta_compare', $index ); ?>">
				<?php foreach( $meta_comparisons as $op => $label ) { ?>
					<option value="<?php echo $op; ?>" <?php selected( $op, isset( $query[ 'meta_compare' ] ) ? $query[ 'meta_compare' ] : '=' ); ?>><?php echo $label; ?></option>
				<?php } ?>
				</select>
				<p class="description"><?php _e( 'Determines how the post meta should be compared to the value below if provided.' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="<?php echo $this->get_field_id( 'meta_value', $index ); ?>"><?php _e( 'Meta Value:' ); ?></label></th>
			<td>
				<input id="<?php echo $this->get_field_id( 'meta_value', $index ); ?>" size="60" type="text" name="<?php echo $this->get_field_name( 'meta_value', $index ); ?>" value="<?php esc_attr_e( $query[ 'meta_value' ] ); ?>" />
				<p class="description"><?php _e( 'Optional. Used in conjunction with a meta key you can query for posts with a specific value for the meta key.' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="<?php echo $this->get_field_id( 'meta_type', $index ); ?>"><?php _e( 'Meta Type:' ); ?></label></th>
			<td>
				<select name="<?php echo $this->get_field_name( 'meta_type', $index ); ?>" id="<?php echo $this->get_field_id( 'meta_type', $index ); ?>">
				<?php foreach( $meta_types as $mtype ) { ?>
					<option value="<?php echo $mtype; ?>" <?php selected( $mtype, isset( $query[ 'meta_type' ] ) ? $query[ 'meta_type' ] : 'CHAR' ); ?>><?php echo $mtype; ?></option>
				<?php } ?>
				</select>
				<p class="description"><?php _e( 'The data type of the meta value.' ); ?></p>
			</td>
		</tr>
		<?php
	}

	function editor_ignore_stickies( $query, $index ) { ?>
		<tr>
			<th><label for="<?php echo $this->get_field_id( 'ignore_stickies', $index ); ?>"><?php _e( 'Ignore stickies:' ); ?></label></th>
			<td>
				<input <?php checked( true, isset( $query[ 'ignore_stickies' ] ) && $query[ 'ignore_stickies' ] ); ?> id="<?php echo $this->get_field_id( 'ignore_stickies', $index ); ?>" type="checkbox" name="<?php echo $this->get_field_name( 'ignore_stickies', $index ); ?>" value="1" />
			</td>
		</tr>
		<?php
	}

	function save( $queries ) {
		if ( ! current_user_can( 'manage_options' ) )
			return false;

		$queries = array();

		for( $i = 0; $i < count( $_POST[ 'qe_use_condition' ] ); $i++ ) {

			// remove rule line
			if ( isset( $_POST[ 'deletequery' ] ) && isset( $_POST[ 'deletequery' ][ $i ] ) )
				continue;

			$queries[ $i ] = array(
				'title' => isset( $_POST[ 'qe_title' ] ) ? sanitize_text_field( $_POST[ 'qe_title' ][ $i ] ) : 'Query %s',
				'use_condition' => intval( $_POST[ 'qe_use_condition' ][ $i ] ),
				'ruleset' => array(),
				'match_all' => $_POST[ 'qe_match_all' ][ $i ],
				'post_types' => isset( $_POST[ 'qe_post_types' ][ $i ] ) ? $_POST[ 'qe_post_types' ][ $i ] : array(),
				'exclude_terms' => isset( $_POST[ 'qe_exclude_terms' ][ $i ] ) ? $_POST[ 'qe_exclude_terms' ][ $i ] : array(),
				'orderby' => $_POST[ 'qe_orderby' ][ $i ] == 'custom' ? $_POST[ 'qe_orderby_custom' ][ $i ] : $_POST[ 'qe_orderby' ][ $i ],
				'order' => $_POST[ 'qe_order' ][ $i ],
				'orderby_tax' => $_POST[ 'qe_orderby_tax' ][ $i ],
				'orderby_tax_order' => $_POST[ 'qe_orderby_tax_order' ][ $i ],
				'offset' => is_numeric( $_POST[ 'qe_offset' ][ $i ] ) ? $_POST[ 'qe_offset' ][ $i ] : '',
				'nopaging' => isset( $_POST[ 'qe_nopaging' ][ $i ] ) ? $_POST[ 'qe_nopaging' ][ $i ] : 0,
				'per_page' => is_numeric( $_POST[ 'qe_per_page' ][ $i ] ) ? intval( $_POST[ 'qe_per_page' ][ $i ] ) : '',
				'meta_key' => $_POST[ 'qe_meta_key' ][ $i ],
				'meta_compare' => $_POST[ 'qe_meta_compare' ][ $i ],
				'meta_value' => $_POST[ 'qe_meta_value' ][ $i ],
				'meta_type' => $_POST[ 'qe_meta_type' ][ $i ],
				'ignore_stickies' => isset( $_POST[ 'qe_ignore_stickies' ][ $i ] ) ? $_POST[ 'qe_ignore_stickies' ][ $i ] : 0
			);

			// rulesets
			for ( $j = 0; $j < count( $_POST[ 'qe_operator' ][ $i ] ); $j++ ) {

				// remove rule line
				if ( isset( $_POST[ 'deleterule' ] ) && isset( $_POST[ 'deleterule' ][ $i ][ $j ] ) )
					continue;

				$queries[ $i ][ 'ruleset' ][ $j ] = array(
					'operator' => $_POST[ 'qe_operator' ][ $i ][ $j ],
					'condition' => $_POST[ 'qe_condition' ][ $i ][ $j ],
					'is_post_type_archive' => $_POST[ 'qe_is_post_type_archive' ][ $i ][ $j ],
					'is_tax' => $_POST[ 'qe_is_tax' ][ $i ][ $j ],
					'is_author' => $_POST[ 'qe_is_author' ][ $i ][ $j ],
					'is_search' => $_POST[ 'qe_is_search' ][ $i ][ $j ],
					'request_key' => $_POST[ 'qe_request_key' ][ $i ][ $j ],
					'request_val' => $_POST[ 'qe_request_val' ][ $i ][ $j ]
				);

				// add a new rule line
				if ( isset( $_POST[ 'newrule' ] ) && isset( $_POST[ 'newrule' ][ $i ] ) )
					$queries[ $i ][ 'ruleset' ][] = $this->default_queries[ 0 ][ 'ruleset' ][ 0 ];

			}

		}

		// add another query
		if ( isset( $_POST[ 'addquery' ] ) )
			$queries[] = $this->default_queries[ 0 ];

		return $queries;
	}



	// if it's not the admin then run our filter
	function pre_get_posts( $query ) {

		if ( is_admin() )
			return $query;

		// no sense in doing anything on singular pages
		if ( $query->is_singular() || $query->is_single() || $query->is_page() )
			return $query;

		// patch default post types in query if a taxonomy is available to more than one
		if ( $query->is_category() || $query->is_tag() || $query->is_tax() ) {
			// allow multi post type query on categories
			$qo = get_queried_object();

			if ( $qo ) {
				$taxonomy = get_taxonomy( $qo->taxonomy );
				$post_types = $taxonomy->object_type;
				$query_types = array();
				foreach( get_post_types( array( 'public' => true ) ) as $type )
					if ( in_array( $type, $post_types ) ) $query_types[] = $type;
				$query->set( 'post_type', $query_types );
			}
		}

		// get and analyse queries option
		$queries = get_option( 'qe_queries' );
		if ( ! $queries )
			return $query;


		foreach( $queries as $i => $query_mod ) {

			if ( ! $query_mod[ 'use_condition' ] ) {
				$query = $this->modify_query( $query, $query_mod );
				continue; // run the normal query mod code everywhere it makes sense to
			}

			// if a condition fails don't apply those mods
			// match all?


			$temp_query = clone $query;
			$match_all = $query_mod[ 'match_all' ];
			$failed = false;
			//
			foreach( $query_mod[ 'ruleset' ] as $j => $rule ) {

				// operator

				// condition
				switch( $rule[ 'condition' ] ) {
					case 'is_home':
						if ( ( $rule[ 'operator' ] == 'is' && $query->is_home() )
							|| ( $rule[ 'operator' ] == 'not' && ! $query->is_home() ) )
							$temp_query = $this->modify_query( $query, $query_mod );
						else
							$failed = true;
						break;
					case 'is_archive':
						if ( ( $rule[ 'operator' ] == 'is' && $query->is_archive() )
							|| ( $rule[ 'operator' ] == 'not' && ! $query->is_archive() ) )
							$temp_query = $this->modify_query( $query, $query_mod );
						else
							$failed = true;
						break;
					case 'is_post_type_archive':
						if ( ( $rule[ 'operator' ] == 'is' && $query->is_post_type_archive( $rule[ 'is_post_type_archive' ] ) )
							|| ( $rule[ 'operator' ] == 'not' && ! $query->is_post_type_archive( $rule[ 'is_post_type_archive' ] ) ) )
							$temp_query = $this->modify_query( $query, $query_mod );
						else
							$failed = true;
						break;
					case 'is_tax':
						switch( $rule[ 'is_tax' ] ) {
							case 'category':
								if ( ( $rule[ 'operator' ] == 'is' && $query->is_category() )
									|| ( $rule[ 'operator' ] == 'not' && ! $query->is_category() ) )
									$temp_query = $this->modify_query( $query, $query_mod );
								else
									$failed = true;
								break;
							case 'post_tag':
								if ( ( $rule[ 'operator' ] == 'is' && $query->is_tag() )
									|| ( $rule[ 'operator' ] == 'not' && ! $query->is_tag() ) )
									$temp_query = $this->modify_query( $query, $query_mod );
								else
									$failed = true;
								break;
							default:
								if ( ( $rule[ 'operator' ] == 'is' && $query->is_tax( $rule[ 'is_tax' ] ) )
									|| ( $rule[ 'operator' ] == 'not' && ! $query->is_tax( $rule[ 'is_tax' ] ) ) ) {
									$temp_query = $this->modify_query( $query, $query_mod );
								} else
									$failed = true;
								break;
						}
						break;
					case 'is_author':
						if ( ( $rule[ 'operator' ] == 'is' && $query->is_author( $rule[ 'is_author' ] ) )
							|| ( $rule[ 'operator' ] == 'not' && ! $query->is_author( $rule[ 'is_author' ] ) ) )
							$temp_query = $this->modify_query( $query, $query_mod );
						else
							$failed = true;
						break;
					case 'is_search':
						if ( ( $rule[ 'operator' ] == 'is' && $query->is_search() && ( empty( $rule[ 'is_search' ] ) || $rule[ 'is_search' ] == get_search_query() ) )
							|| ( $rule[ 'operator' ] == 'not' && ! $query->is_search() ) )
							$temp_query = $this->modify_query( $query, $query_mod );
						else
							$failed = true;
						break;
					case 'is_date':
						if ( ( $rule[ 'operator' ] == 'is' && $query->is_date() )
							|| ( $rule[ 'operator' ] == 'not' && ! $query->is_date() ) )
							$temp_query = $this->modify_query( $query, $query_mod );
						else
							$failed = true;
						break;
					case 'get':
					case 'post':
					case 'request':
						switch( strtoupper( $rule[ 'condition' ] ) ) {
							case 'GET':
								if (
									( $rule[ 'operator' ] == 'is' && ! empty( $rule[ 'request_key' ] ) && isset( $_GET[ $rule[ 'request_key' ] ] ) && ( empty( $rule[ 'request_val' ] ) || $rule[ 'request_val' ] == $_GET[ $rule[ 'request_key' ] ] ) )
									||
									( $rule[ 'operator' ] == 'not' && ! empty( $rule[ 'request_key' ] ) &&
										(
											! isset( $_GET[ $rule[ 'request_key' ] ] )
											||
											( ! empty( $rule[ 'request_val' ] ) && $rule[ 'request_val' ] != $_GET[ $rule[ 'request_key' ] ] )
										)
									)
								) {
									$temp_query = $this->modify_query( $query, $query_mod );
								}
								else
									$failed = true;
								break;
							case 'POST':
								if (
									( $rule[ 'operator' ] == 'is' && ! empty( $rule[ 'request_key' ] ) && isset( $_POST[ $rule[ 'request_key' ] ] ) && ( empty( $rule[ 'request_val' ] ) || $rule[ 'request_val' ] == $_POST[ $rule[ 'request_key' ] ] ) )
									||
									( $rule[ 'operator' ] == 'not' && ! empty( $rule[ 'request_key' ] ) &&
										(
											! isset( $_POST[ $rule[ 'request_key' ] ] )
											||
											( ! empty( $rule[ 'request_val' ] ) && $rule[ 'request_val' ] != $_POST[ $rule[ 'request_key' ] ] )
										)
									)
								) {
									$temp_query = $this->modify_query( $query, $query_mod );
								}
								else
									$failed = true;
								break;
							case 'REQUEST':
								if (
									( $rule[ 'operator' ] == 'is' && ! empty( $rule[ 'request_key' ] ) && isset( $_REQUEST[ $rule[ 'request_key' ] ] ) && ( empty( $rule[ 'request_val' ] ) || $rule[ 'request_val' ] == $_REQUEST[ $rule[ 'request_key' ] ] ) )
									||
									( $rule[ 'operator' ] == 'not' && ! empty( $rule[ 'request_key' ] ) &&
										(
											! isset( $_REQUEST[ $rule[ 'request_key' ] ] )
											||
											( ! empty( $rule[ 'request_val' ] ) && $rule[ 'request_val' ] != $_REQUEST[ $rule[ 'request_key' ] ] )
										)
									)
								) {
									$temp_query = $this->modify_query( $query, $query_mod );
								}
								else
									$failed = true;
								break;
						}
						break;
				}

			}

			// if we haven't failed any criteria
			if ( ! $match_all || ( $match_all && ! $failed ) ) {
				$query = clone $temp_query;
			}
		}


		return $query;
	}

	function modify_query( $query, $query_mod ) {

		// post types
		if ( isset( $query_mod[ 'post_types' ] ) && ! empty( $query_mod[ 'post_types' ] ) && ! $query->is_post_type_archive() )
			$query->set( 'post_type' , $query_mod[ 'post_types' ] );

		// terms
		if ( isset( $query_mod[ 'exclude_terms' ] ) ) {
			$exclude_terms = $query_mod[ 'exclude_terms' ];
			$tax_query = array();
			foreach( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
				if ( ! empty( $exclude_terms[ $taxonomy->name ] ) ) {
					$tax_query[] = array( 'taxonomy' => $taxonomy->name, 'field' => 'id', 'terms' => $exclude_terms[ $taxonomy->name ], 'operator' => 'NOT IN' );
				}
			}
			if ( ! $query->is_tax() && ! $query->is_tag() && ! $query->is_category() )
				$query->set( 'tax_query', $tax_query );
		}

		// sorting
		if ( isset( $query_mod[ 'order' ] ) && ! empty( $query_mod[ 'order' ] ) )
			$query->set( 'order', strtoupper( $query_mod[ 'order' ] ) );
		if ( isset( $query_mod[ 'orderby' ] ) && ! empty( $query_mod[ 'orderby' ] ) )
			$query->set( 'orderby', $query_mod[ 'orderby' ] );

		// term sorting
		if ( isset( $query_mod[ 'orderby_tax' ] ) && $query_mod[ 'orderby_tax' ] ) {
			$this->orderby_tax = $query_mod[ 'orderby_tax' ];
			$this->orderby_tax_order = $query_mod[ 'orderby_tax_order' ];
			add_filter( 'posts_clauses', array( $this, 'orderby_taxonomy' ), 10, 2 );
		}

		// offset
		if ( isset( $query_mod[ 'offset' ] ) && ! empty( $query_mod[ 'offset' ] ) )
			$query->set( 'offset', $query_mod[ 'offset' ] );

		// paging
		if ( isset( $query_mod[ 'per_page' ] ) && $query_mod[ 'per_page' ] )
			$query->set( 'posts_per_page', $query_mod[ 'per_page' ] );
		if ( isset( $query_mod[ 'nopaging' ] ) )
			$query->set( 'nopaging', $query_mod[ 'nopaging' ] );

		// meta query
		if ( isset( $query_mod[ 'meta_key' ] ) && ! empty( $query_mod[ 'meta_key' ] ) && empty( $query->query_vars[ 'meta_key' ] ) && empty( $query->query_vars[ 'meta_query' ] ) ) {
			$meta_query = array( 'key' => $query_mod[ 'meta_key' ] );
			if ( isset( $query_mod[ 'meta_value' ] ) ) {
				if ( in_array( $query_mod[ 'meta_value' ], array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) )
					$meta_query[ 'value' ] = explode( ',', $query_mod[ 'meta_value' ] );
				else
					$meta_query[ 'value' ] = $query_mod[ 'meta_value' ];
				$meta_query[ 'compare' ] = isset( $query_mod[ 'meta_compare' ] ) ? $query_mod[ 'meta_compare' ] : '=';
				$meta_query[ 'type' ] = isset( $query_mod[ 'meta_type' ] ) ? $query_mod[ 'meta_type' ] : 'CHAR';
			}
			if ( strstr( $query_mod[ 'orderby' ], 'meta_value' ) )
				$query->set( 'meta_key', $query_mod[ 'meta_key' ] ); // needed for sorting
			$query->set( 'meta_query', array( $meta_query ) );
		}

		// stickies
		if ( isset( $query_mod[ 'ignore_stickies' ] ) )
			$query->set( 'ignore_sticky_posts', $query_mod[ 'ignore_stickies' ] );

		return $query;
	}

	// code form scribu and mike schinkel to enable sorting by taxonomy term
	function orderby_taxonomy( $clauses, $wp_query ) {
		global $wpdb;

		if ( ! is_admin() && $wp_query->is_main_query() && ! $wp_query->is_singular() ) {

			$taxonomy = $this->orderby_tax;

			if ( $taxonomy
				&& !( $taxonomy == 'category' && $wp_query->is_category() )
				&& !( $taxonomy == 'post_tag' && $wp_query->is_tag() )
				&& !( ! in_array( $taxonomy, array( 'category', 'post_tag' ) && $wp_query->is_tax( $taxonomy ) ) ) ) {

				$clauses['join'] .= <<<SQL
LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID={$wpdb->term_relationships}.object_id
LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
LEFT OUTER JOIN {$wpdb->terms} USING (term_id)
SQL;

				$clauses['where'] .= " AND (taxonomy = '$taxonomy' OR taxonomy IS NULL)";
				$clauses['groupby'] = "object_id";
				$clauses['orderby'] = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name " . $this->orderby_tax_order . ")" . ', ' . $clauses['orderby'];

			}

		}

		// remove self from other queries
		remove_filter( 'posts_clauses', array( $this, 'orderby_taxonomy' ), 10 );

		return $clauses;
	}

	// useful little helper that returns 'post type archive link' for the 'post' page too
	function post_type_archive_link( $link, $post_type ) {
		if ( $post_type == 'post' ) {
			if ( false != ( $blog_page = get_option( 'page_for_posts' ) ) && $blog_page )
				return get_permalink( $blog_page );
			return get_home_url();
		}
		return $link;
	}

}


?>
