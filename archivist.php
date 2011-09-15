<?php
/*
Plugin Name: Archivist - Custom Archive Templates
Plugin URI: http://www.FarBeyondProgramming.com/wordpress/plugin-archivist-custom-archive
Description: Shortcode Plugin to display an archive by category, tag or custom query.
Version: 1.0.1
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
// TODO: fallback thumbnail

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

function archivist_options()
{
	$template = get_option( 'archivist_template', PA_TEMPLATE_DEFAULT );
	$css = get_option( 'archivist_css', PA_CSS_DEFAULT );
	$template_before = get_option( 'archivist_template_before', PA_TEMPLATE_BEFORE_DEFAULT );
	$template_after = get_option( 'archivist_template_after', PA_TEMPLATE_AFTER_DEFAULT );
	?>
	
	<!-- TODO: extra css file -->
	<style type="text/css" media="screen">
		.inline-pre pre {
			display: inline !important;
		}
	</style>
	
	<div class="wrap">
		<div id="icon-options-general" class="icon32"></div>
		<h2><?php echo __( 'Archivist Options', 'archivist' ) ?></h2>
		
		<form action="options.php" method="post">
			<?php settings_fields( 'archivist-option-group' ); ?>
			<?php do_settings_fields( 'archivist-option-group' ); ?>
			
			<h3><?php echo __( 'Custom CSS', 'archivist' ) ?></h3>
			
			<textarea name="archivist_css" rows="16" cols="80"><?php echo $css ?></textarea>
			
			<h3><?php echo __( 'Template', 'archivist' ) ?></h3>

			<h4><?php echo __( 'Before', 'archivist' ) ?></h4>
			<p>
				<?php echo __( 'Add HTML to be displayed before the archive loop.', 'archivist' ) ?>
			</p>
			<textarea name="archivist_template_before" rows="6" cols="80"><?php echo $template_before ?></textarea>
			
			<h4><?php echo __( 'Element', 'archivist' ) ?></h4>
			<div class="inline-pre">
				<p>
					<?php echo __( 'Add HTML for each archive element. Use placeholder tags to display post data.', 'archivist' ) ?>
					<br/><br/>
				  	<pre>%TITLE%</pre> - <?php echo __( 'The post title.', 'archivist' ) ?> <br/>
				  	<pre>%PERMALINK%</pre> - <?php echo __( 'The post permalink.', 'archivist' ) ?> <br/>
				  	<pre>%AUTHOR%</pre> - <?php echo __( 'The post author.', 'archivist' ) ?> <br/>
				  	<pre>%CATEGORIES%</pre> - <?php echo __( 'The post categories as unordered list.', 'archivist' ) ?> <br/>
				  	<pre>%CATEGORIES|...%</pre> - <?php echo __( 'The post categories with a custom separator. Example: <pre>%CATEGORIES|, %</pre>', 'archivist' ) ?> <br/>
				  	<pre>%TAGS%</pre> - <?php echo __( 'The post tags with default separator.', 'archivist' ) ?> <br/>
				  	<pre>%TAGS|...%</pre> - <?php echo __( 'The post tags with a custom separator. Example: <pre>%TAGS|, %</pre>', 'archivist' ) ?> <br/>
				  	<pre>%EXCERPT%</pre> - <?php echo __( 'The post excerpt.', 'archivist' ) ?> <br/>
				  	<pre>%POST_META|...%</pre> - <?php echo __( 'Any post meta. Example: <pre>%POST_META|duration%</pre>', 'archivist' ) ?> <br/>
				  	<pre>%DATE%</pre> - <?php echo __( 'The post date with default format.', 'archivist' ) ?> <br/>
				  	<pre>%DATE|...%</pre> - <?php echo __( 'The post date with custom format. Example: <pre>%DATE|Y/m/d%</pre>', 'archivist' ) ?> <br/>
				  	<pre>%POST_THUMBNAIL|...x...%</pre> - <?php echo __( 'The post thumbnail with certain dimensions. Example: <pre>%POST_THUMBNAIL|75x75%</pre>', 'archivist' ) ?> <br/>
				  	<pre>%COMMENTS%</pre> - <?php echo __( 'The post comment count.', 'archivist' ) ?> <br/>
				</p>
			</div>
			<textarea name="archivist_template" rows="16" cols="80"><?php echo $template ?></textarea>
			
			<h4><?php echo __( 'After', 'archivist' ) ?></h4>
			<p>
				<?php echo __( 'Add HTML to be displayed after the archive loop.', 'archivist' ) ?>
			</p>
			<textarea name="archivist_template_after" rows="6" cols="80"><?php echo $template_after ?></textarea>
			
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>" />
			</p>
		</form>

	</div>
<?php
}

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
		
		public function shortcode( $atts )
		{
			extract( shortcode_atts( array(
				'query'		=> '',
				'category'	=> '',
				'tag'		=> '',
			), $atts ) );			
			
			if ( $query !== '' ) {
				return $this->display_by_query( $query );
			} elseif ( $category !== '' ) {
				return $this->display_by_category( $category );
			} else {
				return $this->display_by_tag( $tag );
			}
		}
		
		public function register_settings()
		{
			register_setting( 'archivist-option-group', 'archivist_template' );
			register_setting( 'archivist-option-group', 'archivist_template_before' );
			register_setting( 'archivist-option-group', 'archivist_template_after' );
			register_setting( 'archivist-option-group', 'archivist_css' );
		}
		
		public function add_menu_entry()
		{
			add_submenu_page( 'options-general.php', 'Archivist', 'Archivist', 'edit_post', 'archivist_options_handle', 'archivist_options' );
		}
		
		function render_element( $post, $template )
		{
			$template = str_replace( '%AUTHOR%', get_the_author(), $template );
			$template = str_replace( '%CATEGORIES%', get_the_category_list(), $template );
			$template = str_replace( '%TAGS%', get_the_tag_list(), $template );
			$template = str_replace( '%DATE%', get_the_date(), $template );
			$template = str_replace( '%TITLE%', get_the_title(), $template );
			$template = str_replace( '%PERMALINK%', get_permalink(), $template );
			$template = str_replace( '%EXCERPT%', get_the_excerpt(), $template );
			$template = str_replace( '%COMMENTS%', get_comments_number(), $template );

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
					'return get_the_post_thumbnail( $post->ID, array( $matches[1], $matches[2] ) );'
				),
			 	$template
			 );
			
			return $template;
		}
		
		public function display_by_category( $category )
		{
			$parameters = array(
				'posts_per_page'	=> -1,
				'category_name'		=> $category
			);
			$loop = new WP_Query( $parameters );
			
			return $this->display_by_loop( $loop );
		}
		
		public function display_by_tag( $tag )
		{
			$parameters = array(
				'posts_per_page'	=> -1,
				'tag'				=> $tag
			);
			$loop = new WP_Query( $parameters );
			
			return $this->display_by_loop( $loop );
		}
		
		public function display_by_query( $query )
		{
			$loop = new WP_Query( $query );
			
			return $this->display_by_loop( $loop );
		}
		
		private function display_by_loop( $loop )
		{
			$template = get_option( 'archivist_template', PA_TEMPLATE_DEFAULT );
			$template_before = get_option( 'archivist_template_before', PA_TEMPLATE_BEFORE_DEFAULT );
			$template_after = get_option( 'archivist_template_after', PA_TEMPLATE_AFTER_DEFAULT );
			$css = get_option( 'archivist_css', PA_CSS_DEFAULT );

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
	}
}