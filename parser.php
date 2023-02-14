<?php

class Archivist_Parser
{
    public $post;
    public $template;

    public function __construct($post, $template)
    {
        $this->post = $post;
        $this->template = $template;
    }

    public function render()
    {
        $this->template = str_replace('%DATE%', get_the_date(), $this->template);
        $this->template = str_replace('%TITLE%', get_the_title(), $this->template);
        $this->template = str_replace('%AUTHOR%', get_the_author(), $this->template);
        $this->template = str_replace('%TAGS%', get_the_tag_list(), $this->template);
        $this->template = str_replace('%PERMALINK%', get_permalink(), $this->template);
        $this->template = str_replace('%EXCERPT%', get_the_excerpt(), $this->template);
        $this->template = str_replace('%COMMENTS%', get_comments_number(), $this->template);
        $this->template = str_replace('%CATEGORIES%', get_the_category_list(), $this->template);

        // categories with custom separator
        $this->template = preg_replace_callback(
            '/%TAGS\|(.*)%/',
            [$this, 'replace_tags'],
            $this->template
        );

        // categories with custom separator
        $this->template = preg_replace_callback(
            '/%CATEGORIES\|(.*)%/',
            [$this, 'replace_categories'],
            $this->template
        );

        // custom post meta with separator
        $this->template = preg_replace_callback(
            '/%POST_META\|(.*?)\|(.*)%/',
            [$this, 'replace_post_meta_with_separator'],
            $this->template
        );

        // custom post meta
        $this->template = preg_replace_callback(
            '/%POST_META\|(.*)%/',
            [$this, 'replace_post_meta'],
            $this->template
        );

        // custom date format
        $this->template = preg_replace_callback(
            '/%DATE\|(.*)%/',
            [$this, 'replace_date'],
            $this->template
        );

        // custom post thumbnails
        $this->template = preg_replace_callback(
            '/%POST_THUMBNAIL\|(\d+)x(\d+)%/',
            [$this, 'replace_thumbnails'],
            $this->template
        );

        // acf field
        $this->template = preg_replace_callback(
            '/%ACF\|(.*)%/',
            [$this, 'replace_acf_field'],
            $this->template
        );

        $this->template = apply_filters('archivist_template_render', $this->template, $this->post);

        return do_shortcode($this->template);
    }

    private function replace_tags($matches)
    {
        return get_the_tag_list('', $matches[1], '');
    }

    private function replace_categories($matches)
    {
        return get_the_category_list($matches[1]);
    }

    private function replace_post_meta_with_separator($matches)
    {
        $list = get_post_meta($this->post->ID, $matches[1], false);

        return implode($matches[2], $list);
    }

    private function replace_post_meta($matches)
    {
        return get_post_meta($this->post->ID, $matches[1], true);
    }

    private function replace_acf_field($matches)
    {
        if (!function_exists('get_field')) {
            return;
        }

        return get_field($matches[1], $this->post->ID);
    }

    private function replace_date($matches)
    {
        return get_the_date($matches[1]);
    }

    private function replace_thumbnails($matches)
    {
        $thumb = get_the_post_thumbnail($this->post->ID, [$matches[1], $matches[2]]);

        if (!$thumb) {
            $default_thumb = archivist::$settings['default_thumb'];
            if ($default_thumb) {
                $thumb = "<img src=\"{$default_thumb}\" alt=\"Archive Thumb\" width=\""
                       .$matches[1]
                       .'" height="'.$matches[2].'">';
            }
        }

        return $thumb;
    }
}
