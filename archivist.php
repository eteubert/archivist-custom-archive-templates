<?php
/*
Plugin Name: Archivist - Custom Archive Templates
Plugin URI: https://wordpress.org/plugins/archivist-custom-archive-templates/
Description: Shortcode Plugin to display an archive by category, tag or custom query.
Version: 1.7.5
Author: Eric Teubert
Author URI: eric@ericteubert.de
License: MIT

Copyright (c) 2017 by Eric Teubert

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

/*
 * internal version number
 * Used to determine whether plugin has been updated
 */
define('ARCHIVIST_VERSION', '20');

// constants with default values
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

define('PA_TEMPLATE_BEFORE_DEFAULT', '
<table>
	<thead>
		<tr>
			<th>Thumb</th>
			<th>Title</th>
		</tr>
	</thead>
	<tbody>
');
define('PA_TEMPLATE_AFTER_DEFAULT', '
	</tbody>
</table>
');
define('PA_THUMB_DEFAULT', '');

if (!class_exists('archivist')) {
    if (function_exists('add_action') && function_exists('register_activation_hook')) {
        add_action('plugins_loaded', ['archivist', 'get_object']);
        add_action('activate_archivist-custom-archive-templates/archivist.php', ['archivist', 'activation_hook']);
    }

    class archivist
    {
        // current template settings
        public static $settings;

        private static $classobj;

        public function __construct()
        {
            $this->load_textdomain();
            add_shortcode('archivist', [$this, 'shortcode']);
            add_action('admin_menu', [$this, 'add_menu_entry']);
            add_action('admin_enqueue_scripts', [$this, 'load_scripts']);

            add_action('wp_ajax_archivist_paginate', [$this, 'ajax_page']);
            add_action('wp_ajax_nopriv_archivist_paginate', [$this, 'ajax_page']);

            add_action('wp_enqueue_scripts', function () {
                wp_register_script('archivist-pagination', plugins_url('js/archivist.js', __FILE__), ['jquery']);

                wp_localize_script('archivist-pagination', 'archivist', [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                ]);
            });

            // only run update hooks if the plugin is already active
            $active_plugins = get_option('active_plugins');
            if (in_array('archivist-custom-archive-templates/archivist.php', $active_plugins)) {
                $this->keep_backwards_compatibility();
            }
        }

        public function load_scripts()
        {
            if (filter_input(INPUT_GET, 'page') === 'archivist_options_handle') {
                wp_enqueue_script('archivist-ace', plugins_url('vendor/ace/ace.js', __FILE__));
            }
        }

        public static function activation_hook()
        {
            global $wp_version;

            // Load Text-Domain
            $obj = archivist::get_object();
            $obj->load_textdomain();

            // check wp version
            if (!version_compare($wp_version, '3.0', '>=')) {
                deactivate_plugins(__FILE__);
                wp_die(wp_sprintf('%s: '.__('Sorry, This plugin requires WordPress 3.0+', 'archivist'), self::get_plugin_data('Name')));
            }

            // check php version
            if (!version_compare(PHP_VERSION, '5.2.0', '>=')) {
                deactivate_plugins(__FILE__); // Deactivate ourself
                wp_die(wp_sprintf('%1s: '.__('Sorry, This plugin has taken a bold step in requiring PHP 5.3.0+, Your server is currently running PHP %2s, Please bug your host to upgrade to a recent version of PHP which is less bug-prone. At last count, &lt;strong>over 80%% of WordPress installs are using PHP 5.2+&lt;/strong>.', 'archivist'), self::get_plugin_data('Name'), PHP_VERSION));
            }

            // set default template name
            add_option('archivist_default_template_name', 'default');
            // create default template
            $obj->create_default_template();
        }

        public static function get_default_template_name()
        {
            $name = get_option('archivist_default_template_name');

            return (strlen($name) > 0) ? $name : 'default';
        }

        public function create_default_template()
        {
            $default_name = self::get_default_template_name();
            $settings = $this->get_template_options();
            if (!isset($settings[$default_name])) {
                // TODO: refactor model archivist_settings::new
                // TODO: refactor model archivist_settings::new_with_defaults
                $settings[$default_name] = [
                    'name' => $default_name,
                    'css' => PA_CSS_DEFAULT,
                    'default_thumb' => PA_THUMB_DEFAULT,
                    'template' => PA_TEMPLATE_DEFAULT,
                    'template_after' => PA_TEMPLATE_AFTER_DEFAULT,
                    'template_before' => PA_TEMPLATE_BEFORE_DEFAULT,
                ];
                update_option('archivist', $settings);
            }
        }

        public function ajax_page()
        {
            $shortcode_attributes = isset($_GET['shortcode_attributes']) ? $_GET['shortcode_attributes'] : null;

            if (is_null($shortcode_attributes) || empty($shortcode_attributes)) {
                exit;
            }

            $html = $this->shortcode($shortcode_attributes);

            echo $html;

            exit;
        }

        public function shortcode($atts)
        {
            $this->shortcode_attributes = shortcode_atts([
                'query' => '',
                'category' => '',
                'tag' => '',
                'template' => self::get_default_template_name(),
                'pagination' => false,
                'controls' => 'both', // controls for pagination: top / bottom / both
            ], $atts);

            extract($this->shortcode_attributes);

            $this->pagination = (int) $pagination;
            if ($this->pagination < 1) {
                $this->pagination = false;
            }

            $this->controls = $controls;

            if ($query !== '') {
                return $this->display_by_query($query, $template);
            }
            if ($category !== '') {
                return $this->display_by_category($category, $template);
            }

            return $this->display_by_tag($tag, $template);
        }

        public function add_menu_entry()
        {
            add_submenu_page('options-general.php', 'Archivist', 'Archivist', 'manage_options', 'archivist_options_handle', [$this, 'settings_page']);
        }

        public function render_element($post, $template)
        {
            require_once dirname(__FILE__).'/parser.php';
            $parser = new Archivist_Parser($post, $template);

            return $parser->render();
        }

        public function get_current_page_number()
        {
            return isset($_GET['archivist_page']) && $_GET['archivist_page'] ? (int) $_GET['archivist_page'] : 1;
        }

        public function add_pagination_parameters($parameters)
        {
            if (!$this->pagination) {
                return $parameters;
            }

            $parameters['posts_per_page'] = $this->pagination;
            $parameters['paged'] = $this->get_current_page_number();

            return $parameters;
        }

        public function set_post_status_parameters($parameters, $template)
        {
            $parameters['post_status'] = apply_filters('archivist_post_status', ['publish'], $template);

            return $parameters;
        }

        public function display_by_category($category, $template = false)
        {
            $parameters = [
                'posts_per_page' => -1,
                'category_name' => $category,
            ];

            $parameters = $this->add_pagination_parameters($parameters);
            $parameters = $this->set_post_status_parameters($parameters, $template);

            return $this->display_by_query_parameters($parameters, $template);
        }

        public function display_by_tag($tag, $template = false)
        {
            $parameters = [
                'posts_per_page' => -1,
                'tag' => $tag,
            ];

            $parameters = $this->add_pagination_parameters($parameters);
            $parameters = $this->set_post_status_parameters($parameters, $template);

            return $this->display_by_query_parameters($parameters, $template);
        }

        public function display_by_query($query, $template = false)
        {
            // sometimes WordPress does stupid stuff with ampersands
            $query = str_replace('&amp;', '&', $query);
            $query = str_replace('#038;', '&', $query);
            $query = str_replace('&&', '&', $query);

            if (!stristr($query, 'posts_per_page')) {
                $query .= '&posts_per_page=-1';
            }

            // turn query string into parameter array
            parse_str($query, $parameters);

            $parameters = $this->add_pagination_parameters($parameters);
            $parameters = $this->set_post_status_parameters($parameters, $template);

            return $this->display_by_query_parameters($parameters, $template);
        }

        public function display_pagination_controls($loop)
        {
            global $wp;

            $total_items = (int) $loop->found_posts;
            $items_per_page = (int) $this->pagination;

            if (!$items_per_page || !$total_items) {
                return;
            }

            $total_pages = ceil($total_items / $items_per_page);
            $current_page = $this->get_current_page_number();

            $current_url = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'."{$_SERVER['HTTP_HOST']}/{$_SERVER['REQUEST_URI']}";
            ?>
			<ul class="archivist-pagination">
			<?php for ($i = 1; $i <= $total_pages; ++$i) { ?>
				<li class="archivist-pagination-item">
					<?php if ($current_page === $i) { ?>
						<?php echo $i; ?>
					<?php } else { ?>
						<a
							href="<?php echo esc_attr(add_query_arg('archivist_page', $i, $current_url)); ?>"
							class="archivist-pagination-link"
							data-page="<?php echo esc_attr($i); ?>">
							<?php echo $i; ?>
						</a>
					<?php } ?>
				</li>
			<?php } ?>
			</ul>
<style type="text/css">
.archivist-pagination {
	text-align: center;
}
.archivist-pagination-item {
	display: inline-block;
}

/* animation generated with http://cssanimate.com/ */
.archivist-loading table {
  animation: fadeOut ease-out 1s;
  animation-iteration-count: 1;
  transform-origin: 50% 50%;
  -webkit-animation: fadeOut ease-out 1s;
  -webkit-animation-iteration-count: 1;
  -webkit-transform-origin: 50% 50%;
  -moz-animation: fadeOut ease-out 1s;
  -moz-animation-iteration-count: 1;
  -moz-transform-origin: 50% 50%;
  -o-animation: fadeOut ease-out 1s;
  -o-animation-iteration-count: 1;
  -o-transform-origin: 50% 50%;
  -ms-animation: fadeOut ease-out 1s;
  -ms-animation-iteration-count: 1;
  -ms-transform-origin: 50% 50%;
}

@keyframes fadeOut{
  0% {
    opacity:1;
  }
  100% {
    opacity:0.1;
  }
}

@-moz-keyframes fadeOut{
  0% {
    opacity:1;
  }
  100% {
    opacity:0.1;
  }
}

@-webkit-keyframes fadeOut {
  0% {
    opacity:1;
  }
  100% {
    opacity:0.1;
  }
}

@-o-keyframes fadeOut {
  0% {
    opacity:1;
  }
  100% {
    opacity:0.1;
  }
}

@-ms-keyframes fadeOut {
  0% {
    opacity:1;
  }
  100% {
    opacity:0.1;
  }
}
</style>
			<?php
        }

        public static function get_object()
        {
            if (null === self::$classobj) {
                self::$classobj = new self();
            }

            return self::$classobj;
        }

        public function load_textdomain()
        {
            $plugin_dir = basename(dirname(__FILE__));
            load_plugin_textdomain('archivist', false, $plugin_dir.'/languages');
        }

        public function settings_page()
        {
            $tab = (isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'add') ? 'add' : 'edit';
            $current_template = $this->get_current_template_name();
            $settings = $this->get_template_options();

            if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
                // strip slashes so HTML won't be escaped
                $_POST = array_map('stripslashes_deep', $_POST);
                $_GET = array_map('stripslashes_deep', $_GET);
                $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
            }

            // CHANGE DEFAULT action
            if (isset($_POST['change_default']) && strlen($_POST['choose_template_name']) > 0) {
                if (!wp_verify_nonce($_REQUEST['_archivist_nonce'], 'make_default')) {
                    return;
                }

                update_option('archivist_default_template_name', $_POST['choose_template_name']);
                ?>
					<div class="updated">
						<p><?php echo wp_sprintf(__('Template "%1s" is now your default. All [archivist ...] shortcodes without a template option will use this to display the archive.', 'archivist'), $_POST['choose_template_name']); ?>
						</p>
					</div>
				<?php
            }
            // DELETE action
            elseif (isset($_POST['delete']) && strlen($_POST['delete']) > 0) {
                if (!wp_verify_nonce($_REQUEST['_archivist_nonce'], 'edit')) {
                    return;
                }

                unset($settings[$current_template]);
                update_option('archivist', $settings);

                // if default template is deleted, make another one default
                if ($current_template == self::get_default_template_name() && count($settings) > 0) {
                    $first_template_name = array_shift(array_keys($settings));
                    update_option('archivist_default_template_name', $first_template_name);
                }

                ?>
					<div class="updated">
						<p>
							<?php echo wp_sprintf(__('Template "%1s" deleted.', 'archivist'), $current_template); ?>
						</p>
					</div>
				<?php
            }
            // EDIT action
            elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
                if (!wp_verify_nonce($_REQUEST['_archivist_nonce'], 'edit')) {
                    return;
                }

                foreach ($_POST['archivist'] as $key => $value) {
                    $template_name = $key;
                    // update name
                    if ($value['name'] != $template_name) {
                        $template_name = $value['name'];
                        // remove old settings enry
                        unset($settings[$key]);
                        // update default_template_name setting
                        update_option('archivist_default_template_name', $template_name);
                    }

                    // update all options
                    $settings[$template_name] = $value;
                }
                update_option('archivist', $settings);
            }
            // CREATE action
            elseif (isset($_POST['archivist_new_template_name'])) {
                if (!wp_verify_nonce($_REQUEST['_archivist_nonce'], 'create')) {
                    return;
                }

                if (isset($settings[$_POST['archivist_new_template_name']])) {
                    $success = false;
                } else {
                    $settings[$_POST['archivist_new_template_name']] = [
                        'name' => $_POST['archivist_new_template_name'], // FIXME: do I have to safeify this or does WP take care?
                        'css' => PA_CSS_DEFAULT,
                        'default_thumb' => PA_THUMB_DEFAULT,
                        'template' => PA_TEMPLATE_DEFAULT,
                        'template_after' => PA_TEMPLATE_AFTER_DEFAULT,
                        'template_before' => PA_TEMPLATE_BEFORE_DEFAULT,
                    ];

                    update_option('archivist', $settings);

                    // if it is the only template setting, that means the default has been deleted
                    // so we make the newly created one the new default
                    if (count($settings) === 1) {
                        update_option('archivist_default_template_name', $_POST['archivist_new_template_name']);
                    }

                    $success = true;
                }

                if ($success) {
                    $tab = 'edit'; // display edit-template-form for this template
                    ?>
						<div class="updated">
							<p>
								<?php echo wp_sprintf(__('Template "%1s" created.', 'archivist'), $_POST['archivist_new_template_name']); ?>
							</p>
						</div>
					<?php
                } else {
                    $tab = 'add'; // display add-template-form again
                    ?>
						<div class="error">
							<p>
								<?php echo wp_sprintf(__('Template "%1s" already exists.', 'archivist'), $_POST['archivist_new_template_name']); ?>
							</p>
						</div>
					<?php
                }
            }

            // disable "edit" tab if there is nothing to show
            if (count($settings) === 0) {
                $tab = 'add';
            }

            ?>

			<style type="text/css" media="screen">
				.inline-pre pre {
					display: inline !important;
				}
			</style>

			<div class="wrap">
				<div id="icon-options-general" class="icon32"></div>
				<h2 class="nav-tab-wrapper">
					<a href="<?php echo admin_url('options-general.php?page=archivist_options_handle'); ?>" class="nav-tab <?php echo ($tab == 'edit') ? 'nav-tab-active' : ''; ?>">
						<?php echo __('Edit Templates', 'archivist'); ?>
					</a>
					<a href="<?php echo admin_url('options-general.php?page=archivist_options_handle&tab=add'); ?>" class="nav-tab <?php echo ($tab == 'add') ? 'nav-tab-active' : ''; ?>">
						<?php echo __('Add Templates', 'archivist'); ?>
					</a>
				</h2>

				<div class="metabox-holder has-right-sidebar">
					<?php
                    $this->settings_page_sidebar();

            if ($tab == 'edit') {
                $this->settings_page_edit();
            } else {
                $this->settings_page_add();
            }
            ?>
				</div> <!-- .metabox-holder -->
			</div> <!-- .wrap -->
		<?php
        }

        private function do_plugin_update($old_version, $current_version)
        {
            // all updates before introduction of version number
            if (!$old_version) {
                $this->update_from_zero();
            }
            // if ( $old_version == 20 ) ...
            // if ( $old_version < 30 && $current_version == 40 ) ...
            // ...
        }

        private function update_from_zero()
        {
            // v1.1.0 -> v1.2.0
            // move from single template to multiple templates
            // if single template stuff exists, create a 'default'
            // template entry based on those values.
            // When finished, delete the old data
            $default_name = self::get_default_template_name();
            $option = get_option('archivist_template');
            if ($option) {
                $settings = [];
                $settings[$default_name] = [
                    'name' => $default_name,
                    'css' => get_option('archivist_css', PA_CSS_DEFAULT),
                    'template' => get_option('archivist_template', PA_TEMPLATE_DEFAULT),
                    'default_thumb' => get_option('archivist_default_thumb', PA_THUMB_DEFAULT),
                    'template_after' => get_option('archivist_template_after', PA_TEMPLATE_AFTER_DEFAULT),
                    'template_before' => get_option('archivist_template_before', PA_TEMPLATE_BEFORE_DEFAULT),
                ];
                update_option('archivist', $settings);

                delete_option('archivist_css');
                delete_option('archivist_template');
                delete_option('archivist_default_thumb');
                delete_option('archivist_template_after');
                delete_option('archivist_template_before');
            }

            // v1.2.3 -> 1.3.0
            // default template name is now an option in the database
            // if it's not set, it should be 'default' like in the prior versions
            add_option('archivist_default_template_name', 'default');

            // 1.3.x revalidate all settings
            $settings = $this->get_template_options();
            $new_settings = [];
            foreach ($settings as $template_name => $template) {
                if ($template_name != $template['name']) {
                    exit($template_name.$template_name['name']);

                    continue; // skip this setting
                }
                // now fix missing template parts
                if (!isset($template['css'])) {
                    $template['css'] = PA_CSS_DEFAULT;
                }
                if (!isset($template['template'])) {
                    $template['template'] = PA_TEMPLATE_DEFAULT;
                }
                if (!isset($template['default_thumb'])) {
                    $template['default_thumb'] = PA_THUMB_DEFAULT;
                }
                if (!isset($template['template_after'])) {
                    $template['template_after'] = PA_TEMPLATE_AFTER_DEFAULT;
                }
                if (!isset($template['template_before'])) {
                    $template['template_before'] = PA_TEMPLATE_BEFORE_DEFAULT;
                }
                // adopt template
                $new_settings[$template['name']] = $template;
            }
            update_option('archivist', $new_settings);

            // check if default template still exists
            $default_template = get_option('archivist_default_template_name');
            if (!isset($new_settings[$default_template])) {
                $first_template_name = array_shift(array_keys($new_settings));
                update_option('archivist_default_template_name', $first_template_name);
            }

            // strip slashes in front of quotes
            for ($i = 0; $i < 5; ++$i) {
                $new_settings = array_map('stripslashes_deep', $new_settings);
            }
            update_option('archivist', $new_settings);
        }

        private function get_template_options()
        {
            $settings = get_option('archivist');

            if (!is_array($settings)) {
                $settings = [];
            }

            return array_map('stripslashes_deep', $settings);
        }

        private function keep_backwards_compatibility()
        {
            if (!defined('ARCHIVIST_VERSION')) {
                return;
            }

            $current_version = (int) ARCHIVIST_VERSION;
            $old_version = (int) get_option(__CLASS__.'_version');

            // if versions are equal, there is nothing to do
            if ($current_version === $old_version) {
                return;
            }

            // do the updates based on old and current version
            $this->do_plugin_update($old_version, $current_version);

            // update internal version
            update_option(__CLASS__.'_version', $current_version);
        }

        private function display_by_query_parameters($parameters, $template)
        {
            $loop = new WP_Query($parameters);

            if (!$template) {
                $template = self::get_default_template_name();
            }

            return $this->display_by_loop($loop, $template);
        }

        private function display_by_loop($loop, $template = false)
        {
            global $post;

            $all_settings = $this->get_template_options();

            if (!$template) {
                $template = self::get_default_template_name();
            }

            $settings = $all_settings[$template];
            archivist::$settings = $settings;

            if (!$settings) {
                return '<div>'.wp_sprintf(__('Archivist Error: Unknown template "%1s"', 'archivist'), $template).'</div>';
            }

            if ($this->pagination) {
                wp_enqueue_script('archivist-pagination');
            }

            ob_start();
            ?>
			<div class="archivist_wrapper">

				<?php if ($settings['css']) { ?>
					<style type="text/css" media="screen">
						<?php echo $settings['css']; ?>
					</style>
				<?php } ?>

				<?php if ($this->pagination && in_array($this->controls, ['top', 'both'])) { ?>
					<?php $this->display_pagination_controls($loop); ?>
				<?php } ?>

				<?php echo $settings['template_before']; ?>
				<?php while ($loop->have_posts()) { ?>
					<?php $loop->the_post(); ?>
					<?php echo $this->render_element($post, $settings['template']); ?>
				<?php } ?>
				<?php echo $settings['template_after']; ?>

				<?php if ($this->pagination && in_array($this->controls, ['bottom', 'both'])) { ?>
					<?php $this->display_pagination_controls($loop); ?>
				<?php } ?>

			</div>
			<script type="text/javascript">
			var archivist_shortcode_attributes = <?php echo json_encode($this->shortcode_attributes); ?>
			</script>
			<?php
            $content = ob_get_contents();
            ob_end_clean();

            wp_reset_postdata();

            // surrounding div is required for pagination JS replacement
            return '<div class="archivist-outer-wrapper">'.$content.'</div>';
        }

        private function get_plugin_data($value = 'Version')
        {
            $plugin_data = get_plugin_data(__FILE__);

            return $plugin_data[$value];
        }

        private function settings_page_sidebar()
        {
            ?>
				<!-- Sidebar -->
				<div class="inner-sidebar">

					<?php
                    $name = $this->get_current_template_name();
            if ($name == self::get_default_template_name()) {
                $template_part = ' ';
            } else {
                $template_part = ' template="'.$name.'" ';
            }
            ?>
					<div id="wp-archivist-usagebox" class="postbox">
						<h3><span><?php _e('Examples', 'archivist'); ?></span></h3>
						<div class="inside">
							<p>
								<?php echo __('Here are some example shortcodes. Copy them into any of your posts or pages and modify to your liking.', 'archivist'); ?>
							</p>
							<p>
								<input type="text" name="example1" class="large-text" value='[archivist<?php echo esc_attr($template_part); ?>category="kitten"]'>
								<?php echo __('Display all posts in the "kitten" category.', 'archivist'); ?>
							</p>
							<p>
								<input type="text" name="example2" class="large-text" value='[archivist<?php echo esc_attr($template_part); ?>tag="kitten"]'>
								<?php echo __('Display all posts tagged with "kitten".', 'archivist'); ?>
							</p>
							<p>
								<input type="text" name="example3" class="large-text" value='[archivist<?php echo esc_attr($template_part); ?>query="year=1984"]'>
								<?php echo __(wp_sprintf('Display all posts published in year 1984. See %1s for all options.', '<a href="http://codex.wordpress.org/Class_Reference/WP_Query">WordPress Codex</a>'), 'archivist'); ?>
							</p>
						</div>
					</div>

					<div id="wp-archivist-placeholders" class="postbox">
						<h3><span><?php _e('Placeholders', 'archivist'); ?></span></h3>
						<div class="inside">
							<div class="inline-pre">
								<p>
								  	<pre>%TITLE%</pre><br/><?php echo __('The post title.', 'archivist'); ?> <br/><br/>
								  	<pre>%PERMALINK%</pre><br/><?php echo __('The post permalink.', 'archivist'); ?> <br/><br/>
								  	<pre>%AUTHOR%</pre><br/><?php echo __('The post author.', 'archivist'); ?> <br/><br/>
								  	<pre>%CATEGORIES%</pre><br/><?php echo __('The post categories as unordered list.', 'archivist'); ?> <br/><br/>
								  	<pre>%CATEGORIES|...%</pre><br/><?php echo __('The post categories with a custom separator. Example: <pre>%CATEGORIES|, %</pre>', 'archivist'); ?> <br/><br/>
								  	<pre>%TAGS%</pre><br/><?php echo __('The post tags with default separator.', 'archivist'); ?> <br/><br/>
								  	<pre>%TAGS|...%</pre><br/><?php echo __('The post tags with a custom separator. Example: <pre>%TAGS|, %</pre>', 'archivist'); ?> <br/><br/>
								  	<pre>%EXCERPT%</pre><br/><?php echo __('The post excerpt.', 'archivist'); ?> <br/><br/>
								  	<pre>%POST_META|...%</pre><br/><?php echo __('Any post meta. Example: <pre>%POST_META|duration%</pre>', 'archivist'); ?> <br/><br/>
								  	<pre>%POST_META|...|...%</pre><br/><?php echo __('Any post meta list, separated by custom HTML. Example: <pre>%POST_META|guest|&lt;br&gt;%</pre>', 'archivist'); ?> <br/><br/>
								  	<pre>%DATE%</pre><br/><?php echo __('The post date with default format.', 'archivist'); ?> <br/><br/>
								  	<pre>%DATE|...%</pre><br/><?php echo __('The post date with custom format. Example: <pre>%DATE|Y/m/d%</pre>', 'archivist'); ?> <br/><br/>
								  	<pre>%POST_THUMBNAIL|...x...%</pre><br/><?php echo __('The post thumbnail with certain dimensions. Example: <pre>%POST_THUMBNAIL|75x75%</pre>', 'archivist'); ?> <br/><br/>
								  	<pre>%COMMENTS%</pre><br/><?php echo __('The post comment count.', 'archivist'); ?> <br/><br/>
								  	<pre>%ACF|field_name%</pre><br/><?php echo sprintf(
                __('Display %s field. Uses the %s function.', 'archivist'),
                '<a href="https://www.advancedcustomfields.com" target="_blank">ACF</a>',
                '<a href="https://www.advancedcustomfields.com/resources/get_field/" target="_blank"><pre>get_field()</pre></a>'
            ); ?> <br/>
								</p>
							</div>

						</div>
					</div>

				</div> <!-- side-info-column -->
			<?php
        }

        private function settings_page_add()
        {
            ?>
				<!-- Main Column -->
				<div id="post-body">
					<div id="post-body-content">
						<div class="postbox">
							<h3><span><?php _e('Add Template', 'archivist'); ?></span></h3>

							<div class="inside">
								<form action="" method="post">
                                    <?php wp_nonce_field('create', '_archivist_nonce'); ?>

									<table class="form-table">
										<tbody>
											<tr>
												<th scope="row">
													<?php echo __('New Template Name', 'archivist'); ?>
												</th>
												<td>
													<input type="text" name="archivist_new_template_name" value="" id="archivist_new_template_name" class="large-text">
													<p>
														<small><?php echo __('This name will be used in the shortcode to identify the template.<br/>Example: If you name the template "rockstar", then you can use it with a shortcode like <em>[archivist template="rockstar" category="..."]</em>', 'archivist'); ?></small>
													</p>
												</td>
											</tr>
										</tbody>
									</table>

									<p class="submit">
										<input type="submit" class="button-primary" value="<?php _e('Add New Template', 'archivist'); ?>" />
									</p>

									<br class="clear" />

								</form>
							</div> <!-- .inside -->

						</div> <!-- #add_template -->
					</div> <!-- #post-body-content -->
				</div> <!-- #post-body -->
			<?php
        }

        private function get_current_template_name()
        {
            // check template chooser
            $name = (isset($_REQUEST['choose_template_name'])) ? $_REQUEST['choose_template_name'] : false;
            // check if a new template has been created
            $name = (!$name && isset($_POST['archivist_new_template_name'])) ? $_POST['archivist_new_template_name'] : $name;
            // fallback to 'default' template
            $name = (!$name) ? self::get_default_template_name() : $name;

            // check if template has been renamed
            if (isset($_POST['archivist'][$name]) && $_POST['archivist'][$name]['name'] != $name) {
                $name = $_POST['archivist'][$name]['name'];
            }

            // does it still exist? might be deleted
            $all_settings = $settings = $this->get_template_options();
            $settings = $all_settings[$name];
            // if the setting does not exist, take the first you can get
            if (!$settings) {
                $name = array_shift(array_keys($all_settings));
            }

            return $name;
        }

        private function settings_page_edit()
        {
            $name = $this->get_current_template_name();
            $field_name = 'archivist['.$name.']';

            $all_template_settings = $settings = $this->get_template_options();
            $settings = $all_template_settings[$name];
            $default_template = get_option('archivist_default_template_name');
            ?>
				<!-- Main Column -->
				<div id="post-body">
					<div id="post-body-content">

						<?php // only allow template switching when there is more than one?>
						<?php if (count($all_template_settings) > 1) { ?>
							<div id="switch_template" class="postbox">
								<h3><span><?php _e('Choose Template', 'archivist'); ?></span></h3>
								<div class="inside">
									<form action="<?php echo admin_url('options-general.php'); ?>" method="get">
                                        <?php wp_nonce_field('save', '_archivist_nonce'); ?>
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
														<?php echo __('Template to edit', 'archivist'); ?>
													</th>
													<td>
														<?php // TODO: move style stuff to css block/file?>
														<select name="choose_template_name" id="choose_template_name" style="width:99%">
															<?php foreach ($all_template_settings as $template_name => $template_settings) { ?>
																<option value="<?php echo $template_name; ?>" <?php echo ($template_name == $name) ? 'selected="selected"' : ''; ?>><?php echo $template_name.(($template_name == $default_template) ? ' '.__('(default)', 'archivist') : ''); ?></option>
															<?php } ?>
														</select>
													</td>
												</tr>
											</tbody>
										</table>

										<p class="submit" id="choose_template_button">
											<input type="submit" class="button-primary" value="<?php _e('Choose Template', 'archivist'); ?>" />
										</p>

										<br class="clear" />

									</form>
								</div> <!-- .inside -->

							</div> <!-- #switch_template -->
						<?php } ?>

						<div id="settings" class="postbox">
							<h3>
								<span><?php echo wp_sprintf(__('Settings for "%1s" Template', 'archivist'), $name); ?></span>
								<span style="float: right; font-weight: bold">
									<?php if ($name == self::get_default_template_name()) { ?>
										<?php _e('Default Template', 'archivist'); ?>
									<?php } else { ?>
										<form action="<?php echo admin_url('options-general.php?page=archivist_options_handle'); ?>" method="post">
                                            <?php wp_nonce_field('make_default', '_archivist_nonce'); ?>
											<input type="hidden" name="choose_template_name" value="<?php echo $name; ?>">
											<input type="hidden" name="tab" value="edit">
											<input type="hidden" name="action" value="change_default">
											<input type="submit" class="button-secondary" name="change_default" value="<?php _e('Set to Default', 'archivist'); ?>" style="position:relative; bottom:3px">

										</form>
									<?php } ?>
								</span>
							</h3>
							<div class="inside">
								<form action="<?php echo admin_url('options-general.php?page=archivist_options_handle'); ?>" method="post">
									<?php wp_nonce_field('edit', '_archivist_nonce'); ?>
									<input type="hidden" name="choose_template_name" value="<?php echo $name; ?>">
									<input type="hidden" name="tab" value="edit">
									<input type="hidden" name="action" value="edit">


									<table class="form-table">
										<tbody>
											<tr valign="top">
												<th scope="row" colspan="2">
													<h4><?php echo __('Template', 'archivist'); ?></h4>
												</th>
											</tr>
											<tr>
												<th scope="row">
													<?php echo __('Before', 'archivist'); ?>
												</th>
												<td valign="top">
													<div id="archivist_template_before_editor" style="height: 200px"><?php echo htmlentities($settings['template_before']); ?></div>
													<textarea name="<?php echo $field_name; ?>[template_before]" rows="6" class="large-text" id="archivist_template_before"><?php echo $settings['template_before']; ?></textarea>
													<p>
														<small><?php echo __('Add HTML to be displayed before the archive loop.', 'archivist'); ?></small>
													</p>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<?php echo __('Element', 'archivist'); ?>
												</th>
												<td valign="top">
													<div id="archivist_template_editor" style="height: 300px"><?php echo htmlentities($settings['template']); ?></div>
													<textarea name="<?php echo $field_name; ?>[template]" rows="10" class="large-text" id="archivist_template"><?php echo $settings['template']; ?></textarea>
													<p>
														<small><?php echo __('Add HTML for each archive element. Use placeholder tags to display post data.', 'archivist'); ?></small>
													</p>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<?php echo __('After', 'archivist'); ?>
												</th>
												<td valign="top">
													<div id="archivist_template_after_editor" style="height: 200px"><?php echo htmlentities($settings['template_after']); ?></div>
													<textarea name="<?php echo $field_name; ?>[template_after]" rows="6" class="large-text" id="archivist_template_after"><?php echo $settings['template_after']; ?></textarea>
													<p>
														<small><?php echo __('Add HTML to be displayed after the archive loop.', 'archivist'); ?></small>
													</p>
												</td>
											</tr>
											<tr valign="top">
												<th scope="row" colspan="2">
													<h4><?php echo __('Other', 'archivist'); ?></h4>
												</th>
											</tr>
											<tr valign="top">
												<th scope="row">
													<?php echo __('Custom CSS', 'archivist'); ?>
												</th>
												<td>
													<div id="archivist_css_editor" style="height: 300px"><?php echo htmlentities($settings['css']); ?></div>
													<textarea data-mode="css" name="<?php echo esc_attr($field_name); ?>[css]" rows="10" class="large-text" id="archivist_css"><?php echo $settings['css']; ?></textarea>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<?php echo __('Default Thumbnail url', 'archivist'); ?>
												</th>
												<td valign="top">
													<input type="text" name="<?php echo esc_attr($field_name); ?>[default_thumb]" value="<?php echo esc_attr($settings['default_thumb']); ?>" id="archivist_default_thumb" class="large-text">
													<p>
														<small><?php echo __('If you are using the <em>%POST_THUMBNAIL|...x...%</em> placeholder and the post has no thumbnail, then this image will be used.', 'archivist'); ?></small>
													</p>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<?php echo __('Template Name', 'archivist'); ?>
												</th>
												<td valign="top">
													<input type="text" name="<?php echo esc_attr($field_name); ?>[name]" value="<?php echo esc_attr($settings['name']); ?>" id="archivist_name" class="large-text">
													<p>
														<small><?php echo __('This name will be used in the shortcode to identify the template.<br/>Example: If you name the template "rockstar", then you can use it with a shortcode like <em>[archivist template="rockstar" category="..."]</em>', 'archivist'); ?></small>
													</p>
												</td>
											</tr>
										</tbody>
									</table>

									<p class="submit">
										<input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>" style="float:right" />
										<input type="submit" class="button-secondary" style="color:#BC0B0B; margin-right:20px; float: right" name="delete" value="<?php _e('delete permanently', 'archivist'); ?>">
									</p>

									<br class="clear" />

								</form>
							</div> <!-- .inside -->

							<script type="text/javascript">
							jQuery(document).ready(function($) {
								var edit_fields = [
									'archivist_template_before',
									'archivist_template',
									'archivist_template_after',
									'archivist_css'
								];

								$.each(edit_fields, function(index, editor_field_id) {
									var editor = ace.edit(editor_field_id + "_editor");
									var textarea = $('#' + editor_field_id).hide();
									var mode = 'html';

									if (textarea.data('mode')) {
										mode = textarea.data('mode');
									};

									// fix console deprecation warning
									editor.$blockScrolling = Infinity;

									editor.getSession().setValue(textarea.val());
									editor.getSession().on('change', function(){
										textarea.val(editor.getSession().getValue());
									});

									editor.setTheme("ace/theme/chrome");
									editor.getSession().setMode("ace/mode/" + mode);
									editor.session.setUseWorker(false); // disable warnings/errors
								});
							});
							</script>

						</div> <!-- #settings -->
					</div> <!-- #post-body-content -->
				</div> <!-- #post-body -->
			<?php
        }
    }
}
