<?php
/**
 * The database-specific functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    IQX_AI
 * @subpackage IQX_AI/includes
 */

class IQX_AI_DB {

    /**
     * The table name for scraped articles
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $table_name    The name of the database table
     */
    private $table_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'iqx_ai_articles';
    }

    /**
     * Create the necessary database tables
     *
     * @since    1.0.0
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            article_url varchar(255) NOT NULL,
            article_title text NOT NULL,
            article_content longtext NOT NULL,
            article_rewritten longtext,
            source varchar(100) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            processed_at datetime NULL,
            wp_post_id bigint(20) NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY article_url (article_url)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Check if an article URL has already been scraped
     *
     * @since    1.0.0
     * @param    string    $url    The article URL to check
     * @return   bool              True if article exists, false otherwise
     */
    public function article_exists($url) {
        global $wpdb;
        
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE article_url = %s",
                $url
            )
        );
        
        return $result > 0;
    }

    /**
     * Save a scraped article to the database
     *
     * @since    1.0.0
     * @param    array    $article    The article data to save
     * @return   int                  The ID of the inserted article
     */
    public function save_article($article) {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_name,
            array(
                'article_url' => $article['url'],
                'article_title' => $article['title'],
                'article_content' => $article['content'],
                'source' => $article['source'],
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }

    /**
     * Update an article with the rewritten content
     *
     * @since    1.0.0
     * @param    int       $id              The article ID
     * @param    string    $rewritten       The rewritten content
     * @param    int       $wp_post_id      The WordPress post ID (optional)
     * @return   bool                       True on success, false on failure
     */
    public function update_article($id, $rewritten, $wp_post_id = null) {
        global $wpdb;
        
        $data = array(
            'article_rewritten' => $rewritten,
            'status' => 'completed',
            'processed_at' => current_time('mysql')
        );
        
        if ($wp_post_id) {
            $data['wp_post_id'] = $wp_post_id;
        }
        
        return $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );
    }

    /**
     * Get pending articles that need to be processed
     *
     * @since    1.0.0
     * @param    int       $limit    Maximum number of articles to retrieve
     * @return   array               Array of pending articles
     */
    public function get_pending_articles($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get all articles with pagination
     *
     * @since    1.0.0
     * @param    int       $page       The current page
     * @param    int       $per_page   Items per page
     * @return   array                 Array of articles and total count
     */
    public function get_all_articles($page = 1, $per_page = 20) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        $articles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            ),
            ARRAY_A
        );
        
        return array(
            'articles' => $articles,
            'total' => $total
        );
    }
} 