<?php
/*
Plugin Name: Podcast Archive Page
Plugin URI: 
Description: Shortcode Plugin to display a Podcast Archive by category
Version: 0.1
Author: Eric Teubert
Author URI: ericteubert@googlemail.com
*/

define('PA_CSS_DEFAULT', '
.podcast_archive_wrapper {
	
}

.podcast_archive_wrapper .thumbnail {
	float: left;
}');

define('PA_TEMPLATE_DEFAULT', '
<div class="podcast_archive_element">
	<div class="thumbnail">%POST_THUMBNAIL|75x75%</div>
	<div class="head_info">
		<span class="episode_id">%POST_META|episode_id%</span> - <span class="release_date">%POST_META|release_date%</span>
	</div>
	<div class="title">
		<a href="%PERMALINK%">%TITLE%</a>
	</div>
	<div class="excerpt">
		%EXCERPT%
	</div>
	<div class="duration">
		%POST_META|duration%
	</div>
</div>');

define( 'PA_TEMPLATE_BEFORE_DEFAULT', '' );
define( 'PA_TEMPLATE_AFTER_DEFAULT', '' );

function podcast_archive_page_options()
{
	$template = get_option( 'podcast_archive_template', PA_TEMPLATE_DEFAULT );
	$css = get_option( 'podcast_archive_css', PA_CSS_DEFAULT );
	$template_before = get_option( 'podcast_archive_template_before', PA_TEMPLATE_BEFORE_DEFAULT );
	$template_after = get_option( 'podcast_archive_template_after', PA_TEMPLATE_AFTER_DEFAULT );
	?>
	
	<!-- TODO: extra css file -->
	<style type="text/css" media="screen">
		.inline-pre pre {
			display: inline !important;
		}
	</style>
	
	<div class="wrap">
		<div id="icon-options-general" class="icon32"></div>
		<h2><?php echo __( 'Podcast Archive Options', 'podcast_archive_page' ) ?></h2>
		
		<form action="options.php" method="post">
			<?php settings_fields( 'podcast-archive-option-group' ); ?>
			<?php do_settings_fields( 'podcast-archive-option-group' ); ?>
			
			<h3><?php echo __( 'Custom CSS', 'podcast_archive_page' ) ?></h3>
			
			<textarea name="podcast_archive_css" rows="16" cols="80"><?php echo $css ?></textarea>
			
			<h3><?php echo __( 'Template', 'podcast_archive_page' ) ?></h3>

			<h4><?php echo __( 'Before', 'podcast_archive_page' ) ?></h4>
			<p>
				<?php echo __( 'Add HTML to be displayed before the archive loop.', 'podcast_archive_page' ) ?>
			</p>
			<textarea name="podcast_archive_template_before" rows="6" cols="80"><?php echo $template_before ?></textarea>
			
			<h4><?php echo __( 'Element', 'podcast_archive_page' ) ?></h4>
			<div class="inline-pre">
				<p>
					<?php echo __( 'Add HTML for each archive element. Use placeholder tags to display post data.
					<br/><br/>
				  	<pre>%TITLE%</pre> - The post title. <br/>
				  	<pre>%PERMALINK%</pre> - The post permalink. <br/>
				  	<pre>%AUTHOR%</pre> - The post author. <br/>
				  	<pre>%CATEGORIES%</pre> - The post categories as unordered list. <br/>
				  	<pre>%CATEGORIES|...%</pre> - The post categories with a custom separator. Example: <pre>%CATEGORIES|, %</pre> <br/>
				  	<pre>%TAGS%</pre> - The post tags with default separator. <br/>
				  	<pre>%TAGS|...%</pre> - The post tags with a custom separator. Example: <pre>%TAGS|, %</pre> <br/>
				  	<pre>%EXCERPT%</pre> - The post excerpt. <br/>
				  	<pre>%POST_META|...%</pre> - Any post meta. Example: <pre>%POST_META|duration%</pre> <br/>
				  	<pre>%DATE%</pre> - The post date with default format. <br/>
				  	<pre>%DATE|...%</pre> - The post date with custom format. Example: <pre>%DATE|Y/m/d%</pre> <br/>
				  	<pre>%POST_THUMBNAIL|...x...%</pre> - The post thumbnail with certain dimensions. Example: <pre>%POST_THUMBNAIL|75x75%</pre> <br/>
				  	<pre>%COMMENTS%</pre> - The post comment count. <br/>
					', 'podcast_archive_page' ) ?>
				</p>
			</div>
			<textarea name="podcast_archive_template" rows="16" cols="80"><?php echo $template ?></textarea>
			
			<h4><?php echo __( 'After', 'podcast_archive_page' ) ?></h4>
			<p>
				<?php echo __( 'Add HTML to be displayed after the archive loop.', 'podcast_archive_page' ) ?>
			</p>
			<textarea name="podcast_archive_template_after" rows="6" cols="80"><?php echo $template_after ?></textarea>
			
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>" />
			</p>
		</form>

	</div>
<?php
}

if ( ! class_exists( 'podcast_archive_page' ) ) {
 
	if ( function_exists( 'add_action' ) && function_exists( 'register_activation_hook' ) ) {
		add_action( 'plugins_loaded', array( 'podcast_archive_page', 'get_object' ) );
		register_activation_hook( __FILE__, array( 'podcast_archive_page', 'activation_hook' ) );
	}

	class podcast_archive_page {
 
		static private $classobj = NULL;
		public $textdomain = 'podcast_archive_page';
 
		public function __construct() {
			$this->load_textdomain();
			add_shortcode( 'podcast-archive-page', array( $this, 'shortcode' ) );
			
			add_action( 'admin_menu', array( $this, 'add_menu_entry' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
		
		public function shortcode( $atts )
		{
			extract( shortcode_atts( array(
				'category' => 'podcast',
			), $atts ) );

			return $this->display_by_category( $category );
		}
		
		public function register_settings()
		{
			register_setting( 'podcast-archive-option-group', 'podcast_archive_template' );
			register_setting( 'podcast-archive-option-group', 'podcast_archive_template_before' );
			register_setting( 'podcast-archive-option-group', 'podcast_archive_template_after' );
			register_setting( 'podcast-archive-option-group', 'podcast_archive_css' );
		}
		
		public function add_menu_entry()
		{
			add_submenu_page( 'options-general.php', 'Podcast Archive', 'Podcast Archive', 'edit_post', 'podcast-archive', 'podcast_archive_page_options' );
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
			global $post;
			
			$parameters = array(
				'posts_per_page'	=> -1,
				'category_name'		=> $category
			);
			$query = new WP_Query( $parameters );
			
			$template = get_option( 'podcast_archive_template', PA_TEMPLATE_DEFAULT );
			$template_before = get_option( 'podcast_archive_template_before', PA_TEMPLATE_BEFORE_DEFAULT );
			$template_after = get_option( 'podcast_archive_template_after', PA_TEMPLATE_AFTER_DEFAULT );
			$css = get_option( 'podcast_archive_css', PA_CSS_DEFAULT );

			ob_start();
			?>
			<div class="podcast_archive_wrapper">
				
				<?php if ( $css ): ?>
					<style type="text/css" media="screen">
						<?php echo $css ?>
					</style>
				<?php endif ?>
				
				<?php echo $template_before; ?>
				<?php while ( $query->have_posts() ) : ?>
					<?php $query->the_post(); ?>
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
			load_plugin_textdomain( $this->get_textdomain(), FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}
 
		private function get_plugin_data( $value = 'Version' ) {
			$plugin_data = get_plugin_data( __FILE__ );
			return $plugin_data[ $value ];
		}
 
		static public function activation_hook() {
			global $wp_version;
 
			// Load Text-Domain
			$obj = podcast_archive_page::get_object();
			$obj->load_textdomain();
 
			// check wp version
			if ( ! version_compare( $wp_version, '3.0', '>=' ) ) {
				deactivate_plugins( __FILE__ );
				wp_die( wp_sprintf( '&lt;strong>%s:&lt;/strong> ' . __( 'Sorry, This plugin requires WordPress 3.0+', $obj->get_textdomain() ),  self::get_plugin_data( 'Name' ) ) );
			}
 
			// check php version
			if ( ! version_compare( PHP_VERSION, '5.3.0', '>=' ) ) {
				deactivate_plugins( __FILE__ ); // Deactivate ourself
				wp_die( wp_sprintf( '&lt;strong>%1s:&lt;/strong> ' . __( 'Sorry, This plugin has taken a bold step in requiring PHP 5.3.0+, Your server is currently running PHP %2s, Please bug your host to upgrade to a recent version of PHP which is less bug-prone. At last count, &lt;strong>over 80%% of WordPress installs are using PHP 5.2+&lt;/strong>.', $obj->get_textdomain() ), self::get_plugin_data( 'Name' ), PHP_VERSION ) );
			}
		}
	}
}