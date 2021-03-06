<?php
/**
 * the Title To Tags main class.
 */
namespace TitleToTerms;

class TitleToTerms
{
   
    // List of WP-specific stop words (draft, etc)
    private $wp_stop    = array('draft', 'auto');
    private $version    = "4.0";
    
    // Convert titles to tags on save:
    public function convert($post_id)
    {
        $stopwords  = $this->getStopWords();
        $append     = get_option('t2t_append');
        $types      = get_option('t2t_taxonomies');
        
        $post       = get_post(wp_is_post_revision($post_id) ? wp_is_post_revision($post_id) : $post_id);
        // Check to see if the post type has title-to-tags settings:
        $tax        = isset($types[$post->post_type]) ? $types[$post->post_type] : 'post_tag';
        // No title? No point in going any further:
        if (isset($post->post_title)) :
            // Setup our tag data:
            $terms          = array();
            $title_words    = explode(' ', $post->post_title);
            foreach ($title_words as $word) :
                $term   = preg_replace('/[^a-z\d]+/i', '', $word);
                $slug   = $this->lowerNoPunc($word);
                if (!in_array($slug, $stopwords) && !in_array($slug, $this->wp_stop)) :
                    wp_insert_term(
                        $term,
                        $tax,
                        array(
                            'slug'  => $slug
                        )
                    );
                    $terms[] = $slug;
                endif;
            endforeach;
            // Append or complete. Do not replace:
            if ($append) :
                wp_set_object_terms($post_id, $terms, $tax, true);
            elseif (!$this->hasTerms($post_id, $tax)) :
                wp_set_object_terms($post_id, $terms, $tax, true);
            endif;
        endif;
    }
    
    private function hasTerms($post_id, $tax)
    {
        $terms  = wp_get_post_terms($post_id, $tax);
        if (empty($terms)) :
            return false;
        elseif ($tax == 'category') :
            $default_cat    = get_option('default_category');
            if (count($terms) == 1 && $terms[0]->term_id == $default_cat) :
                wp_set_object_terms($post_id, array(), $tax);
                return false;
            endif;
        endif;
        return true;
    }
    
    // Display options page:
    public function addMenu()
    {
        add_settings_field(
            'stopWords',
            "Title to Terms: Ignored Words",
            [&$this, 'stopWords'],
            'writing'
        );
        add_settings_field(
            't2t_append',
            "Title to Terms: Append Tags",
            [&$this, 'append'],
            'writing'
        );
        add_settings_field(
            't2t_taxonomies',
            "Title to Terms: Taxonomies and Post Types",
            [&$this, 'taxonomies'],
            'writing'
        );
        register_setting('writing', 'stopWords');
        register_setting('writing', 't2t_append');
        register_setting('writing', 't2t_taxonomies');
        register_setting('writing', 't2t_version');
    }
    
    public function stopWords()
    {
        $values     = get_option('stopWords');
        if (empty($values)) :
            $values     = implode(',', $this->getStopWords());
        endif;
        echo '
		<style type="text/css">.t2t_settings { width: 0; height: 0; }</style>
		<p><a name="t2t_settings" class="t2t_settings">&nbsp;</a>These words will be ignored by Title to Terms
		 (punctuation removed). <em>To reset, simply delete all values here and the default list will be
		 restored.</em></p>
		<textarea rows="6" cols="100" name="stopWords" id="stopWords">' . $values . '</textarea>
		';
        echo sprintf(
            '<input type="hidden" name="t2t_version" value="%s" />',
            $this->version
        );
    }
    
    public function append()
    {
        $value      = get_option('t2t_append');
        $checked    = ( $value ) ? 'checked="checked"' : '';
        echo '<p>Choose whether to add tags to untagged content, or to append new Title 2 Tags, even if there are tags 
            already present.</p>
		<input type="checkbox" name="t2t_append" id="t2t_append" ' . $checked . ' /> append Title to Terms to 
		    preexisting tags.';
    }
    
    public function taxonomies()
    {
        $types      = get_post_types(null, 'objects');
        $settings   = get_option('t2t_taxonomies');
        // print_r( $settings );
        echo '<style type="text/css">
			fieldset.t2t_cpt {
				margin: 20px;
				border: 2px solid #aaa;
				padding: 8px;
			}
		</style>';
        foreach ($types as $type) :
            if (!in_array($type->name, array( 'revision', 'nav_menu_item' ))) :
                echo '<fieldset class="t2t_cpt"><legend>' . $type->labels->name . '</legend>';
                $taxes  = get_object_taxonomies($type->name, 'objects');
                if (!empty($taxes)) :
                    $none   = empty($settings[$type->name]) ? 'checked="checked"' : '';
                    echo sprintf(
                        '<input %s type="radio" value="" id="%s-none" name="t2t_taxonomies[%s]"><label 
                            for="%s-none">none</label><br />',
                        $none,
                        $type->name,
                        $type->name,
                        $type->name
                    );
                    foreach ($taxes as $tax) :
                        if (!in_array($tax->name, array( 'post_format' ))) :
                            $checked    = $settings[$type->name] == $tax->name ? 'checked="checked"' : '';
                            echo sprintf(
                                '<input %s type="radio" value="%s" id="%s-%s" name="t2t_taxonomies[%s]"><label 
                                    for="%s-%s">%s</label><br />',
                                $checked,
                                $tax->name,
                                $type->name,
                                $tax->name,
                                $type->name,
                                $type->name,
                                $tax->name,
                                $tax->labels->name
                            );
                        endif;
                    endforeach;
                else :
                    echo 'No taxonomies for this post type';
                endif;
                echo '</fieldset>';
            endif;
        endforeach;
    }
    
    // Gets the stop word list:
    private function getStopWords()
    {
        $stopwords  = array();
        // Try the current options first:
        $vals       = get_option('stopWords');
        // Otherwise, grab the default list:
        if (empty($vals)) :
            $file           = dirname(__FILE__) . '/stopwords.txt';
            $vals           = file_get_contents($file);
        endif;
        
        // Explode the list and trim values:
        $vals       = explode(',', $vals);
        foreach ($vals as $word) :
            $stopwords[]    = $this->lowerNoPunc($word);
        endforeach;
        
        return $stopwords;
    }
    
    // Converts all words into lower-case words, sans punctuation or possessives.
    private function lowerNoPunc($werd)
    {
        $werd = strtolower(trim(preg_replace('#[^\p{L}\p{N}]+#u', '', $werd)));
        return $werd;
    }
    
    // Version update messages:
    public function versionCheck()
    {
        if (get_site_option('t2t_version') != $this->version) {
            include plugin_dir_path( __FILE__ ) . 'lib/fragments/update.php';
            update_site_option('t2t_version', $this->version);
        }
    }
    
    // Get out there and rock and roll the bones:
    public function __construct()
    {
        add_action('save_post', [&$this, 'convert']);
        // add_action( 'edit_post', [&$this, 'convert']);
        add_action('admin_menu', [&$this, 'addMenu']);
        add_action('admin_notices', [&$this, 'versionCheck']);
    }
}
