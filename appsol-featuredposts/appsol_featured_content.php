<?php
/*
  Plugin Name: Featured Content Slideshow
  Plugin URI: http://www.appropriatesolutions.co.uk/
  Description: Displays a selection of content in a Hero Block or Carousel.
  Author: Tim Ward and Stuart Laverick
  Version: 0.7
  Author URI: http://www.appropriatesolutions.co.uk/
 */
require_once plugin_dir_path(__FILE__) . 'multi-post-thumbnails.php';

class appsolFeaturedContent extends WP_Widget {

    public static $hero_width = 847;
    public static $hero_height = 300;
    public static $image_size_id = 'slide-image';
    private $layouts = array(
        'carousel' => 'Bootstrap Carousel',
        'tabsabove' => 'Bootstrap Tabs Above',
        'tabsleft' => 'Bootstrap Tabs Left',
        'tabsright' => 'Bootstrap Tabs Right',
        'tabsbelow' => 'Bootstrap Tabs Below'
    );

    function __construct() {
        parent::__construct(
                'appsol_featured_content', 'Featured Content', array('description' => __('Displays a selection of content in a Hero Block or Carousel.')));
        add_action("save_post", array('appsolFeaturedContent', 'delete_widget_transient'));
        add_action("trash_post", array('appsolFeaturedContent', 'delete_widget_transient'));
    }

    function init() {
        register_uninstall_hook(__FILE__, array('appsolFeaturedContent', 'delete_widget_transient'));
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            if (class_exists('MultiPostThumbnails')) {
                new MultiPostThumbnails(array(
                    'label' => 'Slideshow Image',
                    'id' => self::$image_size_id,
                    'post_type' => 'post'
                ));
            }
            register_widget('appsolFeaturedContent');
            self::setImageSize();
            if (!is_admin()) {
                wp_register_style('bootstrap-carousel', plugins_url('css/bootstrap-carousel.css', __FILE__));
                wp_register_script('bootstrap-carousel', plugins_url('js/libs/bootstrap-carousel.min.js', __FILE__), array('jquery'), '', true);
                wp_register_style('bootstrap-tabs', plugins_url('css/bootstrap-tabs.css', __FILE__));
                wp_register_script('bootstrap-tabs', plugins_url('js/libs/bootstrap-tabs.min.js', __FILE__), array('jquery'), '', true);
            } else {
                wp_register_script('featured_content_admin', plugins_url('js/admin.js', __FILE__), array('jquery'));
                wp_enqueue_script('featured_content_admin');
            }
        }
    }

    public static function setImageSize() {
        if (function_exists('add_theme_support')) {
            add_theme_support('post-thumbnails');
            if (function_exists('add_image_size'))
                add_image_size(self::$image_size_id, appsolFeaturedContent::$hero_width, appsolFeaturedContent::$hero_height, true);
        }
    }

    function form($instance) {
        $defaults = array(
            'title' => 'Featured Content',
            'layout' => 'carousel',
            'caption' => 'yes',
            'featured' => array(),
            'home_only' => '');
        $instance = wp_parse_args((array) $instance, $defaults);
        $title = $instance['title'];
        $layout = $instance['layout'];
        $featured = $instance['featured'];
        ?>
        <p><label for="<?php echo $this->get_field_id("title"); ?>"><?php _e('Title'); ?>:</label>
            <input id="<?php echo $this->get_field_id("title"); ?>"
                   name="<?php echo $this->get_field_name("title"); ?>"
                   value="<?php echo $title ?>" /></p>
        <p><label for="<?php echo $this->get_field_id("layout"); ?>"><?php _e('Layout'); ?>:</label>
            <select name="<?php echo $this->get_field_name("layout"); ?>" id="<?php echo $this->get_field_name("layout"); ?>">
                <option value="0">Select a layout</option>
                <?php foreach ($this->layouts as $key => $name): ?>
                    <?php $selected = $layout == $key ? ' selected="selected"' : ''; ?>
                    <option value="<?php echo $key; ?>"<?php echo $selected; ?>><?php echo $name ?></option>
                <?php endforeach; ?>
            </select></p>
        <p><input class="checkbox" type="checkbox" id="<?php echo $this->get_field_id("caption"); ?>"
                  name="<?php echo $this->get_field_name("caption"); ?>"
                  <?php if (esc_attr($instance['caption']) == 'yes') echo 'checked="checked"'; ?>
                  value="yes" /><label for="<?php echo $this->get_field_id("caption"); ?>"><?php _e('Show Captions'); ?></label></p>
        <p>Feature the following content:</p>
        <div id="slides">
            <?php
            foreach ($featured as $i => $feature) {
                switch ($feature['type']) {
                    case 'category':
                        $this->select_category($feature['order'], $feature['id'], $feature['tab']);
                        break;
                    case 'page':
                        $this->select_page($feature['order'], $feature['id'], $feature['tab']);
                        break;
                    case 'tag':
                        $this->select_tag($feature['order'], $feature['id'], $feature['tab']);
                        break;
                    case 'post':
                        $this->select_post($feature['order'], $feature['id'], $feature['tab']);
                    default:
                        break;
                }
            }
            ?>
        </div>
        <?php $this->new_buttons(); ?>
        <div id="new_slides" style="display:none;">
            <?php
            // Blank Category
            $this->select_category(100);
            // Blank Page
            $this->select_page(101);
            // Blank Tag
            $this->select_tag(102);
            //Blank Post
            $this->select_post(103);
            ?>
        </div>
        <input class="checkbox" id="<?php echo $this->get_field_id('home_only'); ?>" name="<?php echo $this->get_field_name('home_only'); ?>" type="checkbox" value="yes" <?php if (esc_attr($instance['home_only']) == 'yes') echo 'checked="checked"'; ?> />
        <label for="<?php echo $this->get_field_id('home_only'); ?>"><?php _e('Display on Home page only'); ?></label>
        <?php
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = $new_instance['title'];

        // Only show this on the home page?
        $instance['home_only'] = strip_tags($new_instance['home_only']);

        // Show the extracts as captions?
        $instance['caption'] = strip_tags($new_instance['caption']);

        // Get the layout style required
        $layout = strip_tags($new_instance['layout']);
        $instance['layout'] = $layout ? $layout : current(array_keys($this->layouts));

        // Simpler and cleaner to delete the old featured items and start again
        $instance['featured'] = array();
        foreach ($new_instance['featured'] as $index => $feature) {
            if ($feature['id']) {
                $instance['featured'][$feature['order']] = array(
                    'id' => strip_tags($feature['id']),
                    'type' => strip_tags($feature['type']),
                    'order' => strip_tags($feature['order']),
                    'tab' => strip_tags($feature['tab']),
                );
            }
        }
        ksort($instance['featured']);
        delete_transient('featured_content_' . $this->id);
        return $instance;
    }

    function select_category($index, $id = null, $tab = '') {
        ?>
        <div class="slide-select select-category">
            <p><label for="<?php echo $this->get_field_id('featured') . '_' . $index . '_cat'; ?>"><?php _e('Category'); ?>:</label>
                <select class="slide-id" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_cat'; ?>" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][id]'; ?>">
                    <option value="0"><?php echo esc_attr(__('Select a Category')); ?></option>
                    <?php
                    $categories = get_categories();
                    foreach ($categories as $category) {
                        $selected = $id == $category->term_id ? ' selected="selected"' : '';
                        $option = '<option value="' . $category->term_id . '"' . $selected . '>';
                        $option .= $category->cat_name;
                        $option .= ' (' . $category->category_count . ')';
                        $option .= '</option>';
                        echo $option;
                    }
                    ?>
                </select></p>
            <p><label for="<?php echo $this->get_field_id('featured') . '_' . $index . '_tab'; ?>"><?php _e('Category Tab Title'); ?>:</label>
                <input class="widefat slide-tab" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_tab'; ?>"
                       name="<?php echo $this->get_field_name('featured') . '[' . $index . '][tab]'; ?>"
                       value="<?php echo (string) $tab ?>"></p>
            <div class="alignright"><a class="remove-slide" href="#">Remove</a></div>
            <input class="slide-type" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_type'; ?>" type="hidden" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][type]'; ?>" value="category" />
            <input class="slide-order" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_order'; ?>" type="hidden" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][order]'; ?>" value="<?php echo $index; ?>" />
        </div>
        <?php
    }

    function select_page($index, $id = null, $tab = '') {
        ?>
        <div class="slide-select select-page">
            <p><label for="<?php echo $this->get_field_id('featured') . '_' . $index . '_page'; ?>"><?php _e('Page'); ?>:</label>
                <select class="slide-id" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_page'; ?>" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][id]'; ?>">
                    <option value="0"><?php echo esc_attr(__('Select a Page')); ?></option>
                    <?php
                    $pages = get_pages();
                    foreach ($pages as $page) {
                        $selected = $id == $page->ID ? ' selected="selected"' : '';
                        $option = '<option value="' . $page->ID . '"' . $selected . '>';
                        $option .= $page->post_title;
                        $option .= '</option>';
                        echo $option;
                    }
                    ?>
                </select></p>
            <p><label for="<?php echo $this->get_field_id('featured') . '_' . $index . '_tab'; ?>"><?php _e('Page Tab Title'); ?>:</label>
                <input class="widefat slide-tab" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_tab'; ?>"
                       name="<?php echo $this->get_field_name('featured') . '[' . $index . '][tab]'; ?>"
                       value="<?php echo (string) $tab ?>"></p>
            <div class="alignright"><a class="remove-slide" href="#">Remove</a></div>
            <input class="slide-type" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_type'; ?>" type="hidden" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][type]'; ?>" value="page" />
            <input class="slide-order" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_order'; ?>" type="hidden" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][order]'; ?>" value="<?php echo $index; ?>" />
        </div>
        <?php
    }

    function select_tag($index, $id = null, $tab = '') {
        ?>
        <div class="slide-select select-tag">
            <p><label for="<?php echo $this->get_field_id('featured') . '_' . $index . '_tag'; ?>"><?php _e('Tag'); ?>:</label>
                <select class="slide-id" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_tag'; ?>" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][id]'; ?>">
                    <option value="0"><?php echo esc_attr(__('Select a Tag')); ?></option>
                    <?php
                    $tags = get_tags();
                    foreach ($tags as $tag) {
                        $selected = $id == $tag->term_id ? ' selected="selected"' : '';
                        $option = '<option value="' . $tag->term_id . '"' . $selected . '>';
                        $option .= $tag->name;
                        $option .= ' (' . $tag->count . ')';
                        $option .= '</option>';
                        echo $option;
                    }
                    ?>
                </select></p>
            <p><label for="<?php echo $this->get_field_id('featured') . '_' . $index . '_tab'; ?>"><?php _e('Tag Tab Title'); ?>:</label>
                <input class="widefat slide-tab" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_tab'; ?>"
                       name="<?php echo $this->get_field_name('featured') . '[' . $index . '][tab]'; ?>"
                       value="<?php echo (string) $tab ?>"></p>
            <div class="alignright"><a class="remove-slide" href="#">Remove</a></div>
            <input class="slide-type" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_type'; ?>" type="hidden" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][type]'; ?>" value="tag" />
            <input class="slide-order" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_order'; ?>" type="hidden" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][order]'; ?>" value="<?php echo $index; ?>" />
        </div>
        <?php
    }

    public function select_post($index, $id = null, $tab = '') {
        ?>
        <div class="slide-select select-post">
            <p><label for="<?php echo $this->get_field_id('featured') . '_' . $index . '_post'; ?>"><?php _e('Post ID'); ?>:</label>
                <input class="slide-id" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_post'; ?>"
                       name="<?php echo $this->get_field_name('featured') . '[' . $index . '][id]'; ?>"
                       value="<?php echo (string) $id ?>"></p>
            <p><label for="<?php echo $this->get_field_id('featured') . '_' . $index . '_tab'; ?>"><?php _e('Post Tab Title'); ?>:</label>
                <input class="widefat slide-tab" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_tab'; ?>"
                       name="<?php echo $this->get_field_name('featured') . '[' . $index . '][tab]'; ?>"
                       value="<?php echo (string) $tab ?>"></p>
            <div class="alignright"><a class="remove-slide" href="#">Remove</a></div>
            <input class="slide-type" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_type'; ?>" type="hidden" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][type]'; ?>" value="post" />
            <input class="slide-order" id="<?php echo $this->get_field_id('featured') . '_' . $index . '_order'; ?>" name="<?php echo $this->get_field_name('featured') . '[' . $index . '][order]'; ?>" type="hidden" value="<?php echo $index; ?>" />
        </div>
        <?php
    }

    private function new_buttons() {
        ?>
        <p>New Slide</p>
        <p id="new_slide_buttons">
            <button id="new_category" class="button" value="category">Category</button>
            <button id="new_page" class="button" value="page">Page</button>
            <button id="new_tag" class="button" value="tag">Tag</button>
            <button id="new_post" class="button" value="post">Post</button>
        </p>
        <?php
    }

    /**
     * Displays the html output associated with this instance
     * @param array $args
     * @param array $instance
     * @return bool
     */
    function widget($args, $instance) {
        extract($args);
        // Only show on the Home page?
        if ($instance['home_only'] == 'yes' && !is_front_page())
            return;

        $title = apply_filters('widget_title', $instance['title']);
        // Use the cached transient if available
        $output = get_transient('featured_content_' . $widget_id);
        if ($output == false) {
            $content = '';
            $slideshow = $this->getSlideShow($instance['layout']);
            $slideshow->setWidgetId($widget_id);
            // Get an array of content references that are to be featured
            $featured = $instance['featured'];
            // Build an array of post content to display
            foreach ($featured as $item) {
                switch ($item['type']) {
                    case 'category':
                        $slideshow->addSlide($this->get_category($item['id'], $item['tab']));
                        break;
                    case 'page':
                        $slideshow->addSlide($this->get_page($item['id'], $item['tab']));
                        break;
                    case 'tag':
                        $slideshow->addSlide($this->get_tag($item['id'], $item['tab']));
                        break;
                    case 'post':
                        $slideshow->addSlide($this->get_post($item['id'], $item['tab']));
                        break;
                }
            }

            $output = $slideshow->createShow($widget_id, $instance);
            set_transient('featured_content_' . $widget_id, $output, 60 * 60 * 24);
        }
        echo $before_widget;
        if ($title)
            echo $before_title . $title . $after_title;
        echo $output;
        echo $after_widget;
        return true;
    }

    /**
     * Returns an array suitable to create a slide for the latest post in the category indicated by id
     * If a title is not passed for the slide the post title is used
     * If no posts are found returns false
     * @param int $id
     * @param string $title
     * @return array
     */
    private function get_category($id, $title = '') {
        $args = array(
            'numberposts' => 1,
            'category' => $id,
        );
        $posts = get_posts($args);
        if (empty($posts))
            return false;
        $slide = array(
            'id' => $posts[0]->ID,
            'title' => $title,
            'post_title' => $posts[0]->post_title,
            'caption' => $posts[0]->post_excerpt ? $posts[0]->post_excerpt : $this->get_excerpt($posts[0]->post_content)
        );
        return $slide;
    }

    /**
     * Returns an array suitable to create a slide for the page indicated by id
     * If a title is not passed for the slide the page title is used
     * If no page is found returns false
     * @param int $id
     * @param string $title
     * @return array
     */
    private function get_page($id, $title = '') {
        $page = get_page($id);
        if (empty($page))
            return false;
        $slide = array(
            'id' => $page->ID,
            'title' => $title,
            'post_title' => $page->post_title,
            'caption' => $page->post_excerpt ? $page->post_excerpt : $this->get_excerpt($page->post_content)
        );
        return $slide;
    }

    /**
     * Returns an array suitable to create a slide for the latest post tagged with the tag indicated by id
     * If a title is not passed for the slide the post title is used
     * If no posts are found returns false
     * @param int $id
     * @param int $title
     * @return array
     */
    private function get_tag($id, $title = '') {
        $args = array(
            'numberposts' => 1,
            'tag_id' => $id,
        );
        $posts = get_posts($args);
        if (empty($posts))
            return false;
        $slide = array(
            'id' => $posts[0]->ID,
            'title' => $title,
            'post_title' => $posts[0]->post_title,
            'caption' => $query->posts[0]->post_excerpt ? $query->posts[0]->post_excerpt : $this->get_excerpt($query->posts[0]->post_content)
        );
        return $slide;
    }

    /**
     * Returns an array suitable to create a slide for the post indicated by id
     * If a title is not passed for the slide the post title is used
     * If the post is not found returns false
     * @param type $id
     * @param type $title
     * @return boolean
     */
    private function get_post($id, $title = '') {
        $query = new WP_Query('p=' . $id);
        if (!$query->post_count)
            return false;
        $slide = array(
            'id' => $query->posts[0]->ID,
            'title' => $title,
            'post_title' => $posts[0]->post_title,
            'caption' => $query->posts[0]->post_excerpt ? $query->posts[0]->post_excerpt : $this->get_excerpt($query->posts[0]->post_content)
        );
        return $slide;
    }

    private function get_excerpt($text) {
        $raw_excerpt = $text;
        $text = strip_shortcodes($text);
        $text = apply_filters('the_content', $text);
        $text = str_replace(']]>', ']]&gt;', $text);
        $text = strip_tags($text);
        $excerpt_length = apply_filters('excerpt_length', 55);
        $excerpt_more = apply_filters('excerpt_more', ' ' . '[...]');
        $text = wp_trim_words($text, $excerpt_length, $excerpt_more);

        return apply_filters('wp_trim_excerpt', $text, $raw_excerpt);
    }

    /**
     * Returns an html string of the slideshow
     * Calls the method associated with the passed format
     * @param array $items
     * @param string $format
     * @return string
     */
    function getSlideShow($format = 'carousel') {
        $class = 'appsol' . ucfirst($format) . 'SlideShow';
        if (class_exists($class))
            return new $class;
        return false;
    }

    /**
     * Deletes all transients associated with this plugin when a post is saved
     * Prevents transients holding out of date data
     */
    function delete_widget_transient() {
        $results = array();
        foreach (wp_get_sidebars_widgets() as $array) {
            $results = preg_grep('/^appsolFeaturedContent.*/', $array);
            foreach ($results as $result) {
                delete_transient('featured_content_' . $result);
            }
        }
    }

}

abstract class appsolSlideShow {

    protected $widget_id;
    protected $slides = array();

    public function setWidgetId($widgetid) {
        $this->widget_id = $widgetid;
    }

    abstract protected function addSlide($item, $height = null, $width = null);

    protected function getNav() {
        $nav = array();
        $slide_count = count($this->slides);
        for ($i = 0; $i < $slide_count; $i++)
            $nav[] = $this->slides[$i]->createActivator($i);
        return $nav;
    }

    protected function getSlides($caption = true) {
        $slides = array();
        $slide_count = count($this->slides);
        for ($i = 0; $i < $slide_count; $i++)
            $slides[] = $this->slides[$i]->createSlide($i, $caption);
        return $slides;
    }

    /**
     * Returns html formatted to work with the selected layout method
     * @param string $widget_id
     * @param array $instance
     * @return string
     */
    abstract protected function createShow($widget_id, $instance);
}

class appsolCarouselSlideShow extends appsolSlideShow {

    public function addSlide($item, $height = null, $width = null) {
        $slide = new appsolCarouselSlide($item, $height, $width);
        $slide->setCarouselId($this->widget_id . '_carousel');
        $this->slides[] = $slide;
    }

    public function createShow($widget_id, $instance) {
        if (!wp_style_is('bootstrap'))
            wp_enqueue_style('bootstrap-carousel');
        if (!wp_script_is('bootstrap'))
            wp_enqueue_script('bootstrap-carousel');
        $html = array();
        $html[] = '<div id="' . $this->widget_id . '_carousel" class="carousel slide">';
        $html[] = '<ol class="carousel-indicators">';
        $html[] = implode("\n", $this->getNav());
        $html[] = '</ol>';
        $html[] = '<div class="carousel-inner">';
        $html[] = implode("\n", $this->getSlides($instance['caption'] == 'yes'));
        $html[] = '</div>';
        $html[] = '<a class="carousel-control left" href="#' . $widget_id . '_carousel" data-slide="prev">&lsaquo;</a>';
        $html[] = '<a class="carousel-control right" href="#' . $widget_id . '_carousel" data-slide="next">&rsaquo;</a>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

}

abstract class appsolTabbableSlideShow extends appsolSlideShow {

    public function addSlide($item, $height = null, $width = null) {
        $this->slides[] = new appsolTabbableSlide($item, $height, $width);
    }

    public function createShow($widget_id, $instance) {
        if (!wp_style_is('bootstrap'))
            wp_enqueue_style('bootstrap-tabs');
        if (!wp_script_is('bootstrap'))
            wp_enqueue_script('bootstrap-tabs');
    }

}

class appsolTabsaboveSlideShow extends appsolTabbableSlideShow {

    public function createShow($widget_id, $instance) {
        parent::createShow($widget_id);
        // Template for this layout style
        $html = array();
        $html[] = '<div id="' . $widget_id . '_tabbable" class="tabbable">';
        $html[] = '<ul class="nav nav-tabs">';
        $html[] = implode("\n", $this->getNav());
        $html[] = '</ul>';
        $html[] = '<div class="tab-content">';
        $html[] = implode("\n", $this->getSlides());
        $html[] = '</div>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

}

class appsolTabsrightSlideShow extends appsolTabbableSlideShow {

    public function createShow($widget_id, $instance) {
        parent::createShow($widget_id);
        // Template for this layout style
        $html = array();
        $html[] = '<div id="' . $widget_id . '_tabbable" class="tabbable tabs-right">';
        $html[] = '<ul class="nav nav-tabs">';
        $html[] = implode("\n", $this->getNav());
        $html[] = '</ul>';
        $html[] = '<div class="tab-content">';
        $html[] = implode("\n", $this->getSlides());
        $html[] = '</div>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

}

class appsolTabsleftSlideShow extends appsolTabbableSlideShow {

    public function createShow($widget_id, $instance) {
        parent::createShow($widget_id);
        // Template for this layout style
        $html = array();
        $html[] = '<div id="' . $widget_id . '_tabbable" class="tabbable tabs-left">';
        $html[] = '<ul class="nav nav-tabs">';
        $html[] = implode("\n", $this->getNav());
        $html[] = '</ul>';
        $html[] = '<div class="tab-content">';
        $html[] = implode("\n", $this->getSlides());
        $html[] = '</div>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

}

class appsolTabsbelowSlideShow extends appsolTabbableSlideShow {

    public function createShow($widget_id, $instance) {
        parent::createShow($widget_id);
        // Template for this layout style
        $html = array();
        $html[] = '<div id="' . $widget_id . '_tabbable" class="tabbable tabs-below">';
        $html[] = '<div class="tab-content">';
        $html[] = implode("\n", $this->getSlides());
        $html[] = '</div>';
        $html[] = '<ul class="nav nav-tabs">';
        $html[] = implode("\n", $this->getNav());
        $html[] = '</ul>';
        $html[] = '</div>';
        return implode("\n", $html);
    }

}

abstract class appsolContentSlide {

    protected $id;
    protected $link;
    protected $image;
    protected $title;
    protected $post_title;
    protected $caption;

    public function __construct($item) {
        $this->id = $item['id'];
        $this->link = get_permalink($this->id);
        $this->image = $this->getImage($this->id);
        $this->post_title = $item['post_title'];
        $this->title = empty($item['title']) ? $this->post_title : $item['title'];
        $this->caption = $item['caption'];
    }

    public function getId() {
        return $this->id;
    }

    public function getTitle() {
        return $this->title;
    }

    abstract protected function createActivator($index);

    /**
     * Returns an html string for a single slide
     * The slide is formatted to work with the Bootstrap Carousel method
     * Index is the position of the slide in the carousel
     * Caption is a flag as to whether the extract should be shown as a caption
     * @param int $index
     * @param bool $caption
     * @return string
     */
    abstract public function createSlide($index, $caption);

    /**
     * Returns an html img element of the first suitable image associated with the post
     * The post thumbnail is used if possible, if not the post images are checked in order and will be used if suitable
     * Returns false if no suitable image found
     * @param int $post_id
     * @return string
     */
    private function getImage($post_id) {
        $landscape = appsolFeaturedContent::$hero_height < appsolFeaturedContent::$hero_width;
        $tmb_id = false;
        if (class_exists('MultiPostThumbnails'))
            $tmb_id = MultiPostThumbnails::get_post_thumbnail_id('post', appsolFeaturedContent::$image_size_id, $post_id);
        
        // See if we can use the Post Thumbnail
        if (!$tmb_id)
            $tmb_id = get_post_thumbnail_id($post_id);
        
        if ($tmb_id) {
            $tmb_src = wp_get_attachment_image_src($tmb_id, appsolFeaturedContent::$image_size_id);
            
            // Will it fit?
            if (($landscape && $tmb_src[1] > $tmb_src[2]) || (!$landscape && $tmb_src[2] > $tmb_src[1]))
                return $tmb_src;
        }
        // Check the other images
        $post_imgs = get_children(array(
            "post_parent" => $post_id,
            'post_status' => 'inherit',
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'order' => 'ASC',
            'orderby' => 'menu_order ID'));
        // Return the first that will fit
        foreach ($post_imgs as $img) {
            $img_src = wp_get_attachment_image_src($img->ID, appsolFeaturedContent::$image_size_id);
            if (($landscape && $img_src[1] > $img_src[2]) || (!$landscape && $img_src[2] > $img_src[1]))
                return $img_src;
        }
        // Couldn't find a suitable image
        return false;
    }

}

class appsolCarouselSlide extends appsolContentSlide {

    private $carousel_id;

    public function setCarouselId($carousel_id) {
        $this->carousel_id = $carousel_id;
    }

    function createActivator($index) {
        return '<li data-target="#' . $this->carousel_id . '" data-slide-to="' . $index . '"' . ($index == 0 ? ' class="active"' : '') . '></li>';
    }

    function createSlide($index, $caption = true) {
        $html = array();
        $html[] = '<div class="item' . ($index == 0 ? ' active' : '') . '">';
        $html[] = '<a href="' . $this->link . '">';
        if (isset($this->picturefill))
            $html[] = $this->picturefill;
        else
            $html[] = '<img width="' . $this->image[1] . '" height="' . $this->image[2] . '" src="' . $this->image[0] . '" alt="' . $this->title . '" />';
        $html[] = '</a>';
        if ($caption) {
            $html[] = '<div class="caption">';
            $html[] = '<h4>' . $this->title . '</h4>';
            $html[] = '<p>' . $this->caption . '</p>';
            $html[] = '</div>';
        }
        $html[] = '</div>';
        return implode("\n", $html);
    }

}

class appsolTabbableSlide extends appsolContentSlide {

    function createActivator($index) {
        $html = array();
        $html[] = '<li' . ($index == 0 ? ' class="active"' : '') . ' id="tab_' . $this->id . '">';
        $html[] = '<a href="#tab_pane_' . $this->id . '" data-toggle="tab">' . $this->title . '</a>';
        $html[] = '</li>';
        return implode("\n", $html);
    }

    function createSlide($index, $caption = true) {
        $html = array();
        $html[] = '<div id="tab_pane_' . $this->id . '" class="tab-pane fade ' . ($index == 0 ? ' active in' : '') . '">';
        $html[] = '<a href="' . $this->link . '" class="read-more">';
        if (isset($this->picturefill))
            $html[] = $this->picturefill;
        else
            $html[] = '<img src="' . $this->image[0] . '" alt="' . $this->title . '" />';
        $html[] = '</a>';
        if ($caption) {
            $html[] = '<div class="caption">';
            $html[] = '<h4>' . $this->title . '</h4>';
            $html[] = '<p>' . $this->caption . '</p>';
            $html[] = '</div>';
        }
        $html[] = '</div>';
        return implode("\n", $html);
    }

}

add_action("widgets_init", array('appsolFeaturedContent', 'init'));
