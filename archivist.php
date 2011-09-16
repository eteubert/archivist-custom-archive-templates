<?php
/*
Plugin Name: Archivist - Custom Archive Templates
Plugin URI: http://www.FarBeyondProgramming.com/wordpress/plugin-archivist-custom-archive
Description: Shortcode Plugin to display an archive by category, tag or custom query.
Version: 1.2.0
Author: Eric Teubert
Author URI: ericteubert@googlemail.com
License: MIT

Copyright (c) 2011 by Eric Teubert

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

// TODO: enable import & export of templates
// TODO: maybe an image picker for the default thumbnail?

define('PA_CSS_DEFAULT', '
.archivist_wrapper .permalink {
	font-weight: bold;
}

.archivist_wrapper td {
	vertical-align: top;
}

.archivist_wrapper img {
	padding: 5px
}');

define('PA_TEMPLATE_DEFAULT', '
<tr>
	<td>%POST_THUMBNAIL|50x50%</td>
	<td>
		<a href="%PERMALINK%" class="permalink">%TITLE%</a> <br/>
		<em>%DATE%</em> by <em>%AUTHOR%</em> <br/>
		Filed as: %CATEGORIES|, %
	</td>
</tr>');

define( 'PA_TEMPLATE_BEFORE_DEFAULT', '
<table>
	<thead>
		<tr>
			<th>Thumb</th>
			<th>Title</th>
		</tr>
	</thead>
	<tbody>
' );
define( 'PA_TEMPLATE_AFTER_DEFAULT', '
	</tbody>
</table>
' );
define( 'PA_THUMB_DEFAULT', '' );

if ( ! class_exists( 'archivist' ) ) {
 
	if ( function_exists( 'add_action' ) && function_exists( 'register_activation_hook' ) ) {
		add_action( 'plugins_loaded', array( 'archivist', 'get_object' ) );
		register_activation_hook( __FILE__, array( 'archivist', 'activation_hook' ) );
	}

	class archivist {
 
		static private $classobj = NULL;
		public $textdomain = 'archivist';
 
		public function __construct() {
			$this->load_textdomain();
			add_shortcode( 'archivist', array( $this, 'shortcode' ) );
			add_action( 'admin_menu', array( $this, 'add_menu_entry' ) );
			
			$this->keep_backwards_compatibility();
		}
		
		private function keep_backwards_compatibility() {
			// v1.1.0 -> v1.2.0
			// move from single template to multiple templates
			// if single template stuff exists, create a 'default'
			// template entry based on those values.
			// When finished, delete the old data
			$option = get_option( 'archivist_template' );
			if ( $option ) {
				$settings = array();
				$settings[ 'default' ] = array(
					'name'            => 'default',
					'css'             => get_option( 'archivist_css', PA_CSS_DEFAULT ),
					'template'        => get_option( 'archivist_template', PA_TEMPLATE_DEFAULT ),
					'default_thumb'   => get_option( 'archivist_default_thumb', PA_THUMB_DEFAULT ),
					'template_after'  => get_option( 'archivist_template_after', PA_TEMPLATE_AFTER_DEFAULT ),
					'template_before' => get_option( 'archivist_template_before', PA_TEMPLATE_BEFORE_DEFAULT )
				);
				update_option( 'archivist', $settings);
				
				delete_option( 'archivist_css' );
				delete_option( 'archivist_template' );
				delete_option( 'archivist_default_thumb' );
				delete_option( 'archivist_template_after' );
				delete_option( 'archivist_template_before' );
			}
		}
		
		public function shortcode( $atts ) {
			extract( shortcode_atts( array(
				'query'		=> '',
				'category'	=> '',
				'tag'		=> '',
				'template'  => 'default'
			), $atts ) );			
			
			if ( $query !== '' ) {
				return $this->display_by_query( $query, $template );
			}
			elseif ( $category !== '' ) {
				return $this->display_by_category( $category, $template );
			}
			else {
				return $this->display_by_tag( $tag, $template );
			}
		}
		
		public function add_menu_entry() {
			add_submenu_page( 'options-general.php', 'Archivist', 'Archivist', 'edit_post', 'archivist_options_handle', array( $this, 'settings_page' ) );
		}
		
		function render_element( $post, $template ) {
			$template = str_replace( '%DATE%', get_the_date(), $template );
			$template = str_replace( '%TITLE%', get_the_title(), $template );
			$template = str_replace( '%AUTHOR%', get_the_author(), $template );
			$template = str_replace( '%TAGS%', get_the_tag_list(), $template );			
			$template = str_replace( '%PERMALINK%', get_permalink(), $template );
			$template = str_replace( '%EXCERPT%', get_the_excerpt(), $template );
			$template = str_replace( '%COMMENTS%', get_comments_number(), $template );
			$template = str_replace( '%CATEGORIES%', get_the_category_list(), $template );

			// categories with custom separator
			$template = preg_replace_callback(
			    '/%TAGS\|(.*)%/',
			    create_function(
					'$matches',
					'return get_the_tag_list( "", $matches[1], "" );'
				),
			 	$template
			 );

			// categories with custom separator
			$template = preg_replace_callback(
			    '/%CATEGORIES\|(.*)%/',
			    create_function(
					'$matches',
					'return get_the_category_list( $matches[1] );'
				),
			 	$template
			 );

			// custom post meta
			$template = preg_replace_callback(
			    '/%POST_META\|(.*)%/',
			    create_function(
					'$matches',
					'global $post; return get_post_meta( $post->ID, "$matches[1]", true );'
				),
			 	$template
			 );			
			
			// custom date format
			$template = preg_replace_callback(
			    '/%DATE\|(.*)%/',
			    create_function(
					'$matches',
					'return get_the_date($matches[1]);'
				),
			 	$template
			 );
			
			// custom post thumbnails
			$template = preg_replace_callback(
			    '/%POST_THUMBNAIL\|(\d+)x(\d+)%/',
			    create_function(
					'$matches',
					'
					$thumb = get_the_post_thumbnail( $post->ID, array( $matches[ 1 ], $matches[ 2 ] ) );
					
					
					if ( ! $thumb ) {
						$default_thumb = get_option( "archivist_default_thumb", PA_THUMB_DEFAULT );
						if ( $default_thumb ) {
							$thumb = "<img src=\"$default_thumb\" alt=\"Archive Thumb\" width=\"" . $matches[ 1 ] . "\" height=\"" . $matches[ 2 ] . "\">";
						}
					}
					
					return $thumb;'
				),
			 	$template
			 );
			
			return $template;
		}
		
		public function display_by_category( $category, $template = 'default' ) {
			$parameters = array(
				'posts_per_page' => -1,
				'category_name'  => $category
			);
			$loop = new WP_Query( $parameters );
			
			return $this->display_by_loop( $loop, $template );
		}
		
		public function display_by_tag( $tag, $template = 'default' ) {
			$parameters = array(
				'posts_per_page' => -1,
				'tag'            => $tag
			);
			$loop = new WP_Query( $parameters );
			
			return $this->display_by_loop( $loop, $template );
		}
		
		public function display_by_query( $query, $template = 'default' ) {
			$loop = new WP_Query( $query );
			
			return $this->display_by_loop( $loop, $template );
		}
		
		private function display_by_loop( $loop, $template = 'default' ) {
			$all_settings = get_option( 'archivist' );
			$settings = $all_settings[ $template ];
			
			if ( ! $settings ) {
				return '<div>' . wp_sprintf( __( 'Archivist Error: Unknown template "%1s"', archivist::get_textdomain() ), $template ) . '</div>';
			}

			ob_start();
			?>
			<div class="archivist_wrapper">
				
				<?php if ( $css ): ?>
					<style type="text/css" media="screen">
						<?php echo $settings['css'] ?>
					</style>
				<?php endif ?>
				
				<?php echo $settings[ 'template_before' ]; ?>
				<?php while ( $loop->have_posts() ) : ?>
					<?php $loop->the_post(); ?>
					<?php echo $this->render_element( $post, $settings[ 'template' ] ); ?>
				<?php endwhile; ?>
				<?php echo $settings[ 'template_after' ]; ?>
			</div>
			<?php
			$content = ob_get_contents();
			ob_end_clean();
			
			wp_reset_postdata();
			
			return $content;
		}
 
		public function get_object() {
			if ( NULL === self::$classobj ) {
				self::$classobj = new self;
			}
			return self::$classobj;
		}
 
		public function get_textdomain() {
			return $this->textdomain;
		}
 
		public function load_textdomain() {
			$plugin_dir = basename(dirname(__FILE__));
			load_plugin_textdomain( $this->get_textdomain(), FALSE, $plugin_dir . '/languages' );
		}
 
		private function get_plugin_data( $value = 'Version' ) {
			$plugin_data = get_plugin_data( __FILE__ );
			return $plugin_data[ $value ];
		}
 
		static public function activation_hook() {
			global $wp_version;
 
			// Load Text-Domain
			$obj = archivist::get_object();
			$obj->load_textdomain();
 
			// check wp version
			if ( ! version_compare( $wp_version, '3.0', '>=' ) ) {
				deactivate_plugins( __FILE__ );
				wp_die( wp_sprintf( '%s: ' . __( 'Sorry, This plugin requires WordPress 3.0+', $obj->get_textdomain() ),  self::get_plugin_data( 'Name' ) ) );
			}
 
			// check php version
			if ( ! version_compare( PHP_VERSION, '5.3.0', '>=' ) ) {
				deactivate_plugins( __FILE__ ); // Deactivate ourself
				wp_die( wp_sprintf( '%1s: ' . __( 'Sorry, This plugin has taken a bold step in requiring PHP 5.3.0+, Your server is currently running PHP %2s, Please bug your host to upgrade to a recent version of PHP which is less bug-prone. At last count, &lt;strong>over 80%% of WordPress installs are using PHP 5.2+&lt;/strong>.', $obj->get_textdomain() ), self::get_plugin_data( 'Name' ), PHP_VERSION ) );
			}
			
			// create default template
			$this->create_default_template();
		}
		
		private function create_default_template() {
			$settings = get_option( 'archivist' );
			if ( ! isset( $settings[ 'default' ] ) ) {
				// TODO: refactor model archivist_settings::new
				// TODO: refactor model archivist_settings::new_with_defaults
				$settings[ 'default' ] = array(
					'name'            => 'default',
					'css'             => PA_CSS_DEFAULT,
					'default_thumb'   => PA_THUMB_DEFAULT,
					'template'        => PA_TEMPLATE_DEFAULT,
					'template_after'  => PA_TEMPLATE_AFTER_DEFAULT,
					'template_before' => PA_TEMPLATE_BEFORE_DEFAULT
				);
				update_option( 'archivist', $settings);
			}
		}
		
		public function settings_page() {
			$tab = ( $_REQUEST[ 'tab' ] == 'add' ) ? 'add' : 'edit';
			$current_template = $this->get_current_template_name();
			
			// DELETE action
			if ( isset( $_POST[ 'delete' ] ) && strlen( $_POST[ 'delete' ] ) > 0 ) {
				$settings = get_option( 'archivist' );
				unset( $settings[ $current_template ] );
				update_option( 'archivist', $settings );
				?>
					<div class="updated">
						<p>
							<strong><?php echo wp_sprintf( __( 'Template "%1s" deleted.', archivist::get_textdomain() ), $current_template ) ?></strong>
						</p>
					</div>
				<?php				
			}
			// EDIT action
			elseif ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'edit' ) {
				if ( get_magic_quotes_gpc() ) {
					// strip slashes so HTML won't be escaped
				    $_POST = array_map( 'stripslashes_deep', $_POST );
				}
				$settings = get_option( 'archivist' );
				foreach ( $_POST[ 'archivist' ] as $key => $value ) {
					$settings[ $key ] = $value;
				}
				update_option( 'archivist', $settings);
			}
			// CREATE action
			elseif ( isset( $_POST[ 'archivist_new_template_name' ] ) ) {
				$settings = get_option( 'archivist' );
				
				if ( isset( $settings[ $_POST[ 'archivist_new_template_name' ] ] ) ) {
					$success = false;
				} else {
					$settings[ $_POST[ 'archivist_new_template_name' ] ] = array(
						'name'            => $_POST[ 'archivist_new_template_name' ], // FIXME: do I have to safeify this or does WP take care?
						'css'             => PA_CSS_DEFAULT,
						'default_thumb'   => PA_THUMB_DEFAULT,
						'template'        => PA_TEMPLATE_DEFAULT,
						'template_after'  => PA_TEMPLATE_AFTER_DEFAULT,
						'template_before' => PA_TEMPLATE_BEFORE_DEFAULT
					);

					update_option( 'archivist', $settings);
					$success = true;
				}

				if ( $success ) {
					$tab = 'edit'; // display edit-template-form for this template
					?>
						<div class="updated">
							<p>
								<strong><?php echo wp_sprintf( __( 'Template "%1s" created.', archivist::get_textdomain() ), $_POST[ 'archivist_new_template_name' ] ) ?></strong>
							</p>
						</div>
					<?php
				} else {
					$tab = 'add'; // display add-template-form again
					?>
						<div class="updated">
							<p>
								<strong><?php echo wp_sprintf( __( 'Template "%1s" already exists.', archivist::get_textdomain() ), $_POST[ 'archivist_new_template_name' ] ) ?></strong>
							</p>
						</div>
					<?php
				}
			}
			
			?>

			<!-- TODO: extra css file -->
			<style type="text/css" media="screen">
				.inline-pre pre {
					display: inline !important;
				}
			</style>

			<div class="wrap">
				<div id="icon-options-general" class="icon32"></div>
				<h2 class="nav-tab-wrapper">
					<a href="<?php echo admin_url( 'options-general.php?page=archivist_options_handle' ) ?>" class="nav-tab <?php echo ( $tab == 'edit' ) ? 'nav-tab-active' : '' ?>">
						<?php echo __( 'Edit Templates', archivist::get_textdomain() ) ?>
					</a>
					<a href="<?php echo admin_url( 'options-general.php?page=archivist_options_handle&tab=add' ) ?>" class="nav-tab <?php echo ( $tab == 'add' ) ? 'nav-tab-active' : '' ?>">
						<?php echo __( 'Add Templates', archivist::get_textdomain() ) ?>
					</a>
				</h2>
				
				<div id="poststuff" class="metabox-holder has-right-sidebar">
					<?php
					$this->settings_page_sidebar();
					
					if ( $tab == 'edit' ) {
						$this->settings_page_edit();
					} else {
						$this->settings_page_add();
					}
					?>
				</div> <!-- #poststuff -->
			</div> <!-- .wrap -->
		<?php
		}
		
		private function settings_page_sidebar() {
			?>
				<!-- Sidebar -->
				<div id="side-info-column" class="inner-sidebar">
					<div id="side-sortables" class="meta-box-sortables ui-sortable">
						
						<div id="wp-archivist-infobox" class="postbox">
							<h3 class="hndle"><span><?php _e( 'Creator', archivist::get_textdomain() ); ?></span></h3>
							<div class="inside">
								<p>
									<?php _e( 'Hey, I\'m Eric. I created this plugin.<br/> If you like it, consider to flattr me a beer.', archivist::get_textdomain() ); ?>
								</p>
								<script type="text/javascript">
								/* <![CDATA[ */
								    (function() {
								        var s = document.createElement('script'), t = document.getElementsByTagName('script')[0];
								        s.type = 'text/javascript';
								        s.async = true;
								        s.src = 'http://api.flattr.com/js/0.6/load.js?mode=auto';
								        t.parentNode.insertBefore(s, t);
								    })();
								/* ]]> */
								</script>
								<p>
									<a class="FlattrButton" style="display:none;" rev="flattr;button:compact;" href="http://www.FarBeyondProgramming.com/wordpress/plugin-archivist-custom-archive"></a>
									<noscript><a href="http://flattr.com/thing/396382/WordPress-Plugin-Archivist-Custom-Archive-Templates" target="_blank">
									<img src="http://api.flattr.com/button/flattr-badge-large.png" alt="Flattr this" title="Flattr this" border="0" /></a></noscript>
								</p>
								<p>
									<?php echo wp_sprintf( __( 'Get in touch: Visit my <a href="%1s">Homepage</a>, follow me on <a href="%2s">Twitter</a> or look at my projects on <a href="%3s">GitHub</a>.', archivist::get_textdomain() ), 'http://www.FarBeyondProgramming.com/', 'http://www.twitter.com/ericteubert', 'https://github.com/eteubert' ) ?>
								</p>
							</div>
						</div>
								
						<?php
						$name = $this->get_current_template_name();
						if ( $name == 'default' ) {
							$template_part = ' ';
						} else {
							$template_part = ' template="' . $name . '" ';
						}
						?>
						<div id="wp-archivist-usagebox" class="postbox">
							<h3 class="hndle"><span><?php _e( 'Examples', archivist::get_textdomain() ); ?></span></h3>
							<div class="inside">
								<p>
									<?php echo __( 'Here are some example shortcodes. Copy them into any of your posts or pages and modify to your liking.', archivist::get_textdomain() ) ?>
								</p>
								<p>
									<input type="text" name="example1" class="large-text" value='[archivist<?php echo $template_part  ?>category="kitten"]'>
									<?php echo __( 'Display all posts in the "kitten" category.', archivist::get_textdomain() ) ?>
								</p>
								<p>
									<input type="text" name="example2" class="large-text" value='[archivist<?php echo $template_part  ?>tag="kitten"]'>
									<?php echo __( 'Display all posts tagged with "kitten".', archivist::get_textdomain() ) ?>
								</p>
								<p>
									<input type="text" name="example3" class="large-text" value='[archivist<?php echo $template_part  ?>query="year=1984"]'>
									<?php echo __( wp_sprintf( 'Display all posts published in year 1984. See %1s for all options.', '<a href="http://codex.wordpress.org/Class_Reference/WP_Query">WordPress Codex</a>' ), archivist::get_textdomain() ) ?>
								</p>
							</div>
						</div>
						
						<div id="wp-archivist-placeholders" class="postbox">
							<h3 class="hndle"><span><?php _e( 'Placeholders', archivist::get_textdomain() ); ?></span></h3>
							<div class="inside">
								<div class="inline-pre">
									<p>
									  	<pre>%TITLE%</pre><br/><?php echo __( 'The post title.', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%PERMALINK%</pre><br/><?php echo __( 'The post permalink.', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%AUTHOR%</pre><br/><?php echo __( 'The post author.', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%CATEGORIES%</pre><br/><?php echo __( 'The post categories as unordered list.', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%CATEGORIES|...%</pre><br/><?php echo __( 'The post categories with a custom separator. Example: <pre>%CATEGORIES|, %</pre>', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%TAGS%</pre><br/><?php echo __( 'The post tags with default separator.', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%TAGS|...%</pre><br/><?php echo __( 'The post tags with a custom separator. Example: <pre>%TAGS|, %</pre>', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%EXCERPT%</pre><br/><?php echo __( 'The post excerpt.', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%POST_META|...%</pre><br/><?php echo __( 'Any post meta. Example: <pre>%POST_META|duration%</pre>', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%DATE%</pre><br/><?php echo __( 'The post date with default format.', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%DATE|...%</pre><br/><?php echo __( 'The post date with custom format. Example: <pre>%DATE|Y/m/d%</pre>', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%POST_THUMBNAIL|...x...%</pre><br/><?php echo __( 'The post thumbnail with certain dimensions. Example: <pre>%POST_THUMBNAIL|75x75%</pre>', archivist::get_textdomain() ) ?> <br/><br/>
									  	<pre>%COMMENTS%</pre><br/><?php echo __( 'The post comment count.', archivist::get_textdomain() ) ?> <br/>
									</p>
								</div>

							</div>
						</div>
						
					</div> <!-- side-sortables -->
				</div> <!-- side-info-column -->
			<?php
		}
		
		private function settings_page_add() {
			?>
				<!-- Main Column -->
				<div id="post-body">
					<div id="post-body-content">
						<div id="normal-sortables" class="meta-box-sortables ui-sortable">
					
							<div id="add_template" class="postbox">
								<h3 class="hndle"><span><?php _e( 'Add Template', archivist::get_textdomain() ); ?></span></h3>
								<div class="inside">
									<form action="" method="post">

										<table class="form-table">
											<tbody>
												<tr>
													<th scope="row">
														<?php echo __( 'New Template Name', archivist::get_textdomain() ) ?>
													</th>
													<td>
														<input type="text" name="archivist_new_template_name" value="" id="archivist_new_template_name" class="large-text">
														<p>
															<small><?php echo __( 'This name will be used in the shortcode to identify the template.<br/>Example: If you name the template "rockstar", then you can use it with a shortcode like <em>[archivist template="rockstar" category="..."]</em>', archivist::get_textdomain() ) ?></small>
														</p>
													</td>
												</tr>
											</tbody>
										</table>

										<p class="submit">
											<input type="submit" class="button-primary" value="<?php _e( 'Add New Template', archivist::get_textdomain() ) ?>" />
										</p>
										
										<br class="clear" />
										
									</form>
								</div> <!-- .inside -->
								
							</div> <!-- #add_template -->
							
						</div> <!-- #normal-sortables -->
					</div> <!-- #post-body-content -->
				</div> <!-- #post-body -->
			<?php
		}
		
		private function get_current_template_name() {
			// check template chooser
			$name       = ( isset( $_REQUEST[ 'choose_template_name' ] ) ) ? $_REQUEST[ 'choose_template_name' ] : false;
			// check if a new template has been created
			$name       = ( ! $name && isset( $_POST[ 'archivist_new_template_name' ] ) ) ? $_POST[ 'archivist_new_template_name' ] : $name;
			// fallback to 'default' template
			$name       = ( ! $name ) ? 'default' : $name;
			
			// does it still exist? might be deleted
			$all_settings = get_option( 'archivist' );
			$settings     = $all_settings[ $name ];
			// if the setting does not exist, take the first you can get
			if ( ! $settings ) {
				$name = array_shift(array_keys($all_settings));
			}
			
			return $name;
		}
		
		private function settings_page_edit() {
			$name       = $this->get_current_template_name();
			$field_name = 'archivist[' . $name . ']';
			
			$all_template_settings = get_option( 'archivist' );
			$settings              = $all_template_settings[ $name ];
			?>
				<!-- Main Column -->
				<div id="post-body">
					<div id="post-body-content">
						<div id="normal-sortables" class="meta-box-sortables ui-sortable">
							
							<?php // only allow template switching when there is more than one ?>
							<?php if ( count( $all_template_settings ) > 1 ): ?>
								<div id="switch_template" class="postbox">
									<h3 class="hndle"><span><?php _e( 'Choose Template', archivist::get_textdomain() ); ?></span></h3>
									<div class="inside">
										<form action="<?php echo admin_url( 'options-general.php' ) ?>" method="get">
											<input type="hidden" name="tab" value="edit" />
											<input type="hidden" name="page" value="archivist_options_handle">

											<script type="text/javascript" charset="utf-8">
												jQuery( document ).ready( function() {
													// hide button only if js is enabled
													jQuery( '#choose_template_button' ).hide();
													// if js is enabled, auto-submit form on change
													jQuery( '#choose_template_name' ).change( function() {
														this.form.submit();
													} );
												});
											</script>

											<table class="form-table">
												<tbody>
													<tr>
														<th scope="row">
															<?php echo __( 'Template to edit', archivist::get_textdomain() ) ?>
														</th>
														<td>
															<?php // TODO: move style stuff to css block/file ?>
															<select name="choose_template_name" id="choose_template_name" style="width:99%">
																<?php foreach ( $all_template_settings as $template_name => $template_settings ): ?>
																	<option value="<?php echo $template_name ?>" <?php echo ($template_name == $name) ? 'selected="selected"' : '' ?>><?php echo $template_name ?></option>
																<?php endforeach ?>
															</select>
														</td>
													</tr>
												</tbody>
											</table>

											<p class="submit" id="choose_template_button">
												<input type="submit" class="button-primary" value="<?php _e( 'Choose Template', archivist::get_textdomain() ) ?>" />
											</p>

											<br class="clear" />

										</form>
									</div> <!-- .inside -->

								</div> <!-- #switch_template -->
							<?php endif ?>
							
							<div id="settings" class="postbox">
								<h3 class="hndle"><span><?php echo wp_sprintf( __( 'Settings for "%1s" Template', archivist::get_textdomain() ), $name ); ?></span></h3>
								<div class="inside">
									<form action="<?php echo admin_url( 'options-general.php?page=archivist_options_handle' ) ?>" method="post">
										<?php // settings_fields( 'archivist-options' ); ?>
										<?php // do_settings_fields( 'archivist-options' ); ?>
										<input type="hidden" name="choose_template_name" value="<?php echo $name ?>">
										<input type="hidden" name="tab" value="edit">
										<input type="hidden" name="action" value="edit">
										

										<table class="form-table">
											<tbody>
												<tr valign="top">
													<th scope="row" colspan="2">
														<h4><?php echo __( 'Template', archivist::get_textdomain() ) ?></h4>
													</th>
												</tr>
												<tr>	
													<th scope="row">
														<?php echo __( 'Before', archivist::get_textdomain() ) ?>
													</th>
													<td valign="top">
														<textarea name="<?php echo $field_name ?>[template_before]" rows="6" class="large-text"><?php echo $settings[ 'template_before' ] ?></textarea>
														<p>
															<small><?php echo __( 'Add HTML to be displayed before the archive loop.', archivist::get_textdomain() ) ?></small>
														</p>
													</td>
												</tr>
												<tr>	
													<th scope="row">
														<?php echo __( 'Element', archivist::get_textdomain() ) ?>
													</th>
													<td valign="top">
														<textarea name="<?php echo $field_name ?>[template]" rows="10" class="large-text"><?php echo $settings[ 'template' ] ?></textarea>
														<p>
															<small><?php echo __( 'Add HTML for each archive element. Use placeholder tags to display post data.', archivist::get_textdomain() ) ?></small>
														</p>
													</td>
												</tr>
												<tr>	
													<th scope="row">
														<?php echo __( 'After', archivist::get_textdomain() ) ?>
													</th>
													<td valign="top">
														<textarea name="<?php echo $field_name ?>[template_after]" rows="6" class="large-text"><?php echo $settings[ 'template_after' ] ?></textarea>
														<p>
															<small><?php echo __( 'Add HTML to be displayed after the archive loop.', archivist::get_textdomain() ) ?></small>
														</p>
													</td>
												</tr>
												<tr valign="top">
													<th scope="row" colspan="2">
														<h4><?php echo __( 'Other', archivist::get_textdomain() ) ?></h4>
													</th>
												</tr>
												<tr valign="top">
													<th scope="row">
														<?php echo __( 'Custom CSS', archivist::get_textdomain() ) ?>
													</th>
													<td>
														<textarea name="<?php echo $field_name ?>[css]" rows="10" class="large-text"><?php echo $settings[ 'css' ] ?></textarea>
													</td>
												</tr>
												<tr>	
													<th scope="row">
														<?php echo __( 'Default Thumbnail url', archivist::get_textdomain() ) ?>
													</th>
													<td valign="top">
														<input type="text" name="<?php echo $field_name ?>[default_thumb]" value="<?php echo $settings[ 'default_thumb' ] ?>" id="archivist_default_thumb" class="large-text">
														<p>
															<small><?php echo __( 'If you are using the <em>%POST_THUMBNAIL|...x...%</em> placeholder and the post has no thumbnail, then this image will be used.', archivist::get_textdomain() ) ?></small>
														</p>
													</td>
												</tr>
											</tbody>
										</table>

										<p class="delete">
											
										</p>

										<p class="submit">
											<!-- <span class="delete">
												<a href="" class="submitdelete" style="color:#BC0B0B">delete permanently</a>
											</span> -->
											<input type="submit" class="button-secondary" style="color:#BC0B0B;margin-right:20px" name="delete" value="<?php _e( 'delete permanently', archivist::get_textdomain() ) ?>">
											<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>" />
										</p>
										
										<br class="clear" />
										
									</form>
								</div> <!-- .inside -->
								
							</div> <!-- #settings -->
							
						</div> <!-- #normal-sortables -->
					</div> <!-- #post-body-content -->
				</div> <!-- #post-body -->
			<?php
		}
	}
}