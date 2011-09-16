<?php
/*
Plugin Name: Archivist - Custom Archive Templates
Plugin URI: http://www.FarBeyondProgramming.com/wordpress/plugin-archivist-custom-archive
Description: Shortcode Plugin to display an archive by category, tag or custom query.
Version: 1.1.0
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

// TODO: enable multiple archive templates
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
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
		
		public function shortcode( $atts ) {
			extract( shortcode_atts( array(
				'query'		=> '',
				'category'	=> '',
				'tag'		=> '',
			), $atts ) );			
			
			if ( $query !== '' ) {
				return $this->display_by_query( $query );
			}
			elseif ( $category !== '' ) {
				return $this->display_by_category( $category );
			}
			else {
				return $this->display_by_tag( $tag );
			}
		}
		
		public function register_settings() {
			register_setting( 'archivist-option-group', 'archivist_css' );
			register_setting( 'archivist-option-group', 'archivist_template' );
			register_setting( 'archivist-option-group', 'archivist_default_thumb' );
			register_setting( 'archivist-option-group', 'archivist_template_after' );			
			register_setting( 'archivist-option-group', 'archivist_template_before' );
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
		
		public function display_by_category( $category ) {
			$parameters = array(
				'posts_per_page' => -1,
				'category_name'  => $category
			);
			$loop = new WP_Query( $parameters );
			
			return $this->display_by_loop( $loop );
		}
		
		public function display_by_tag( $tag ) {
			$parameters = array(
				'posts_per_page' => -1,
				'tag'            => $tag
			);
			$loop = new WP_Query( $parameters );
			
			return $this->display_by_loop( $loop );
		}
		
		public function display_by_query( $query ) {
			$loop = new WP_Query( $query );
			
			return $this->display_by_loop( $loop );
		}
		
		private function display_by_loop( $loop ) {
			$css = get_option( 'archivist_css', PA_CSS_DEFAULT );
			$template = get_option( 'archivist_template', PA_TEMPLATE_DEFAULT );
			$template_after = get_option( 'archivist_template_after', PA_TEMPLATE_AFTER_DEFAULT );
			$template_before = get_option( 'archivist_template_before', PA_TEMPLATE_BEFORE_DEFAULT );

			ob_start();
			?>
			<div class="archivist_wrapper">
				
				<?php if ( $css ): ?>
					<style type="text/css" media="screen">
						<?php echo $css ?>
					</style>
				<?php endif ?>
				
				<?php echo $template_before; ?>
				<?php while ( $loop->have_posts() ) : ?>
					<?php $loop->the_post(); ?>
					<?php echo $this->render_element( $post, $template ); ?>
				<?php endwhile; ?>
				<?php echo $template_after; ?>
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
		}
		
		function settings_page() {
			$css = get_option( 'archivist_css', PA_CSS_DEFAULT );
			$template = get_option( 'archivist_template', PA_TEMPLATE_DEFAULT );
			$default_thumb = get_option( 'archivist_default_thumb', PA_THUMB_DEFAULT );
			$template_after = get_option( 'archivist_template_after', PA_TEMPLATE_AFTER_DEFAULT );
			$template_before = get_option( 'archivist_template_before', PA_TEMPLATE_BEFORE_DEFAULT );
			?>

			<!-- TODO: extra css file -->
			<style type="text/css" media="screen">
				.inline-pre pre {
					display: inline !important;
				}
			</style>

			<div class="wrap">
				<div id="icon-options-general" class="icon32"></div>
				<h2><?php echo __( 'Archivist Options', archivist::get_textdomain() ) ?></h2>

				<div id="poststuff" class="metabox-holder has-right-sidebar">
					
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

					<!-- Main Column -->
					<div id="post-body">
						<div id="post-body-content">
							<div id="normal-sortables" class="meta-box-sortables ui-sortable">
						
								<div id="settings" class="postbox">
									<h3 class="hndle"><span><?php _e( 'General Settings', archivist::get_textdomain() ); ?></span></h3>
									<div class="inside">
										<form action="options.php" method="post">
											<?php settings_fields( 'archivist-option-group' ); ?>
											<?php do_settings_fields( 'archivist-option-group' ); ?>


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
															<textarea name="archivist_template_before" rows="6" class="large-text"><?php echo $template_before ?></textarea>
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
															<textarea name="archivist_template" rows="10" class="large-text"><?php echo $template ?></textarea>
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
															<textarea name="archivist_template_after" rows="6" class="large-text"><?php echo $template_after ?></textarea>
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
															<textarea name="archivist_css" rows="10" class="large-text"><?php echo $css ?></textarea>
														</td>
													</tr>
													<tr>	
														<th scope="row">
															<?php echo __( 'Default Thumbnail url', archivist::get_textdomain() ) ?>
														</th>
														<td valign="top">
															<input type="text" name="archivist_default_thumb" value="<?php echo $default_thumb ?>" id="archivist_default_thumb" class="large-text">
															<p>
																<small><?php echo __( 'If you are using the <em>%POST_THUMBNAIL|...x...%</em> placeholder and the post has no thumbnail, then this image will be used.', archivist::get_textdomain() ) ?></small>
															</p>
														</td>
													</tr>
												</tbody>
											</table>

											<p class="submit">
												<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>" />
											</p>
											
											<br class="clear" />
											
										</form>
									</div> <!-- .inside -->
									
								</div> <!-- #settings -->
								
							</div> <!-- #normal-sortables -->
						</div> <!-- #post-body-content -->
					</div> <!-- #post-body -->
					
				</div> <!-- #poststuff -->
			</div> <!-- .wrap -->
		<?php
		}
	}
}