<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    IQX_AI
 * @subpackage IQX_AI/includes
 */

class IQX_AI_Admin {

    /**
     * The database instance
     *
     * @since    1.0.0
     * @access   private
     * @var      IQX_AI_DB    $db    The database handler
     */
    private $db;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->db = new IQX_AI_DB();
    }

    /**
     * Initialize the admin hooks
     *
     * @since    1.0.0
     */
    public function init() {
        // Add menu items
        add_action('admin_menu', array($this, 'add_menu_pages'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add AJAX handlers
        add_action('wp_ajax_iqx_ai_run_scraper', array($this, 'ajax_run_scraper'));
        add_action('wp_ajax_iqx_ai_rewrite_article', array($this, 'ajax_rewrite_article'));
        add_action('wp_ajax_iqx_ai_create_post', array($this, 'ajax_create_post'));
        add_action('wp_ajax_iqx_ai_rewrite_seo_title', array($this, 'ajax_rewrite_seo_title'));
        add_action('wp_ajax_iqx_ai_delete_all_data', array($this, 'ajax_delete_all_data'));
    }

    /**
     * Add menu pages
     *
     * @since    1.0.0
     */
    public function add_menu_pages() {
        // Main menu item
        add_menu_page(
            'IQX AI',
            'IQX AI',
            'manage_options',
            'iqx-ai',
            array($this, 'display_dashboard_page'),
            'dashicons-admin-generic',
            80
        );
        
        // Dashboard submenu
        add_submenu_page(
            'iqx-ai',
            'Bảng điều khiển',
            'Bảng điều khiển',
            'manage_options',
            'iqx-ai',
            array($this, 'display_dashboard_page')
        );
        
        // Articles submenu
        add_submenu_page(
            'iqx-ai',
            'Bài viết',
            'Bài viết',
            'manage_options',
            'iqx-ai-articles',
            array($this, 'display_articles_page')
        );
        
        // Bulk actions submenu
        add_submenu_page(
            'iqx-ai',
            'Thao tác hàng loạt',
            'Thao tác hàng loạt',
            'manage_options',
            'iqx-ai-bulk-actions',
            array($this, 'display_bulk_actions_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'iqx-ai',
            'Cài đặt',
            'Cài đặt',
            'manage_options',
            'iqx-ai-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Register plugin settings
     *
     * @since    1.0.0
     */
    public function register_settings() {
        register_setting('iqx_ai_settings', 'iqx_ai_settings');
        
        // API Settings section
        add_settings_section(
            'iqx_ai_api_settings',
            'Cài đặt API',
            array($this, 'render_api_settings_section'),
            'iqx-ai-settings'
        );
        
        add_settings_field(
            'api_token',
            'API Token',
            array($this, 'render_api_token_field'),
            'iqx-ai-settings',
            'iqx_ai_api_settings'
        );
        
        add_settings_field(
            'model',
            'Model AI',
            array($this, 'render_model_field'),
            'iqx-ai-settings',
            'iqx_ai_api_settings'
        );
        
        // Scraping Settings section
        add_settings_section(
            'iqx_ai_scraping_settings',
            'Cài đặt cào dữ liệu',
            array($this, 'render_scraping_settings_section'),
            'iqx-ai-settings'
        );
        
        add_settings_field(
            'enable_scraping',
            'Bật cào dữ liệu',
            array($this, 'render_enable_scraping_field'),
            'iqx-ai-settings',
            'iqx_ai_scraping_settings'
        );
        
        add_settings_field(
            'scraping_frequency',
            'Tần suất cào dữ liệu',
            array($this, 'render_scraping_frequency_field'),
            'iqx-ai-settings',
            'iqx_ai_scraping_settings'
        );
        
        add_settings_field(
            'scraping_limit',
            'Số bài viết mỗi lần',
            array($this, 'render_scraping_limit_field'),
            'iqx-ai-settings',
            'iqx_ai_scraping_settings'
        );
        
        // Post Settings section
        add_settings_section(
            'iqx_ai_post_settings',
            'Cài đặt bài viết',
            array($this, 'render_post_settings_section'),
            'iqx-ai-settings'
        );
        
        add_settings_field(
            'auto_publish',
            'Tự động đăng bài',
            array($this, 'render_auto_publish_field'),
            'iqx-ai-settings',
            'iqx_ai_post_settings'
        );
        
        add_settings_field(
            'post_status',
            'Trạng thái bài viết',
            array($this, 'render_post_status_field'),
            'iqx-ai-settings',
            'iqx_ai_post_settings'
        );
        
        add_settings_field(
            'post_author',
            'Tác giả bài viết',
            array($this, 'render_post_author_field'),
            'iqx-ai-settings',
            'iqx_ai_post_settings'
        );
        
        add_settings_field(
            'post_category',
            'Chuyên mục bài viết',
            array($this, 'render_post_category_field'),
            'iqx-ai-settings',
            'iqx_ai_post_settings'
        );
    }

    /**
     * Render the dashboard page
     *
     * @since    1.0.0
     */
    public function display_dashboard_page() {
        // Get statistics
        global $wpdb;
        $table_name = $wpdb->prefix . 'iqx_ai_articles';
        
        $total_articles = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $pending_articles = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        $completed_articles = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        $published_posts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE wp_post_id IS NOT NULL");
        
        // Get settings
        $settings = get_option('iqx_ai_settings', array());
        $api_token = !empty($settings['api_token']) ? 'Đã thiết lập' : 'Chưa thiết lập';
        $scraping_enabled = !empty($settings['enable_scraping']) && $settings['enable_scraping'] === '1' ? 'Đã bật' : 'Đã tắt';
        
        ?>
        <div class="wrap">
            <h1>Bảng điều khiển IQX AI</h1>
            
            <div class="iqx-ai-dashboard">
                <div class="iqx-ai-stats">
                    <h2>Thống kê</h2>
                    <div class="iqx-ai-stat-grid">
                        <div class="iqx-ai-stat-box">
                            <h3>Tổng số bài viết</h3>
                            <p class="iqx-ai-stat-number"><?php echo esc_html($total_articles); ?></p>
                        </div>
                        <div class="iqx-ai-stat-box">
                            <h3>Bài viết đang chờ</h3>
                            <p class="iqx-ai-stat-number"><?php echo esc_html($pending_articles); ?></p>
                        </div>
                        <div class="iqx-ai-stat-box">
                            <h3>Bài viết hoàn thành</h3>
                            <p class="iqx-ai-stat-number"><?php echo esc_html($completed_articles); ?></p>
                        </div>
                        <div class="iqx-ai-stat-box">
                            <h3>Bài viết đã đăng</h3>
                            <p class="iqx-ai-stat-number"><?php echo esc_html($published_posts); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="iqx-ai-status">
                    <h2>Trạng thái hệ thống</h2>
                    <div class="iqx-ai-status-grid">
                        <div class="iqx-ai-status-item">
                            <span class="iqx-ai-status-label">API Token:</span>
                            <span class="iqx-ai-status-value"><?php echo esc_html($api_token); ?></span>
                        </div>
                        <div class="iqx-ai-status-item">
                            <span class="iqx-ai-status-label">Cào dữ liệu:</span>
                            <span class="iqx-ai-status-value"><?php echo esc_html($scraping_enabled); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="iqx-ai-actions">
                    <h2>Hành động</h2>
                    <button id="iqx-ai-run-scraper" class="button button-primary">Chạy cào dữ liệu ngay</button>
                    <span id="iqx-ai-scraper-status"></span>
                </div>
                
                <div class="iqx-ai-recent">
                    <h2>Bài viết gần đây</h2>
                    <?php $this->display_recent_articles(5); ?>
                    <p><a href="<?php echo admin_url('admin.php?page=iqx-ai-articles'); ?>" class="button">Xem tất cả bài viết</a></p>
                </div>
            </div>
        </div>
        
        <style>
            .iqx-ai-stat-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                margin: 20px 0;
            }
            
            .iqx-ai-stat-box {
                background: #fff;
                border: 1px solid #ccc;
                padding: 15px;
                border-radius: 5px;
                text-align: center;
            }
            
            .iqx-ai-stat-number {
                font-size: 24px;
                font-weight: bold;
                margin: 10px 0;
            }
            
            .iqx-ai-status-grid {
                background: #fff;
                border: 1px solid #ccc;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            
            .iqx-ai-status-item {
                margin-bottom: 10px;
            }
            
            .iqx-ai-status-label {
                font-weight: bold;
                margin-right: 10px;
            }
            
            .iqx-ai-actions {
                margin: 20px 0;
            }
            
            .iqx-ai-recent {
                background: #fff;
                border: 1px solid #ccc;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                $('#iqx-ai-run-scraper').on('click', function() {
                    var button = $(this);
                    var status = $('#iqx-ai-scraper-status');
                    
                    button.prop('disabled', true);
                    status.text('Đang chạy cào dữ liệu...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'iqx_ai_run_scraper',
                            nonce: '<?php echo wp_create_nonce('iqx_ai_run_scraper'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                status.text('Cào dữ liệu hoàn thành thành công!');
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                status.text('Lỗi: ' + response.data);
                            }
                        },
                        error: function() {
                            status.text('Đã xảy ra lỗi máy chủ.');
                        },
                        complete: function() {
                            button.prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Display recent articles table
     *
     * @since    1.0.0
     * @param    int    $limit    Number of articles to display
     */
    private function display_recent_articles($limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iqx_ai_articles';
        
        $articles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        
        if (empty($articles)) {
            echo '<p>No articles found.</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Tiêu đề</th>
                    <th>Nguồn</th>
                    <th>Trạng thái</th>
                    <th>Ngày</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($articles as $article) : ?>
                    <tr>
                        <td>
                            <?php echo esc_html($article['article_title']); ?>
                        </td>
                        <td><?php echo esc_html($article['source']); ?></td>
                        <td><?php echo ucfirst(esc_html($article['status'])); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($article['created_at']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render the articles page
     *
     * @since    1.0.0
     */
    public function display_articles_page() {
        // Get current page
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // Get articles
        $result = $this->db->get_all_articles($page, $per_page);
        $articles = $result['articles'];
        $total = $result['total'];
        
        // Calculate pagination
        $total_pages = ceil($total / $per_page);
        
        ?>
        <div class="wrap">
            <h1>Bài viết IQX AI</h1>
            
            <?php if (empty($articles)) : ?>
                <p>Không tìm thấy bài viết.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Tiêu đề</th>
                            <th>Nguồn</th>
                            <th>Trạng thái</th>
                            <th>Ngày</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $article) : ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($article['article_title']); ?>
                                    <?php if (!empty($article['wp_post_id'])) : ?>
                                        <br>
                                        <small>
                                            <a href="<?php echo get_edit_post_link($article['wp_post_id']); ?>" target="_blank">
                                                Xem bài viết
                                            </a>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($article['source']); ?></td>
                                <td><?php echo ucfirst(esc_html($article['status'])); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($article['created_at']))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($article['article_url']); ?>" target="_blank" class="button button-small">Xem bản gốc</a>
                                    
                                    <?php if ($article['status'] === 'pending') : ?>
                                        <button class="button button-small iqx-ai-rewrite" data-id="<?php echo esc_attr($article['id']); ?>">Viết lại</button>
                                    <?php elseif ($article['status'] === 'completed') : ?>
                                        <?php if (empty($article['wp_post_id'])) : ?>
                                            <button class="button button-small iqx-ai-create-post" data-id="<?php echo esc_attr($article['id']); ?>">Tạo bài viết</button>
                                        <?php endif; ?>
                                        <button class="button button-small iqx-ai-rewrite-seo-title" data-id="<?php echo esc_attr($article['id']); ?>">Viết lại tiêu đề SEO</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo esc_html($total); ?> mục</span>
                            
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $page,
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                $('.iqx-ai-rewrite').on('click', function() {
                    var button = $(this);
                    var id = button.data('id');
                    
                    button.prop('disabled', true);
                    button.text('Đang xử lý...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'iqx_ai_rewrite_article',
                            id: id,
                            nonce: '<?php echo wp_create_nonce('iqx_ai_rewrite_article'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Lỗi: ' + response.data);
                                button.prop('disabled', false);
                                button.text('Viết lại');
                            }
                        },
                        error: function() {
                            alert('Đã xảy ra lỗi máy chủ.');
                            button.prop('disabled', false);
                            button.text('Viết lại');
                        }
                    });
                });
                
                $('.iqx-ai-create-post').on('click', function() {
                    var button = $(this);
                    var id = button.data('id');
                    
                    button.prop('disabled', true);
                    button.text('Đang xử lý...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'iqx_ai_create_post',
                            id: id,
                            nonce: '<?php echo wp_create_nonce('iqx_ai_create_post'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Lỗi: ' + response.data);
                                button.prop('disabled', false);
                                button.text('Tạo bài viết');
                            }
                        },
                        error: function() {
                            alert('Đã xảy ra lỗi máy chủ.');
                            button.prop('disabled', false);
                            button.text('Tạo bài viết');
                        }
                    });
                });
                
                $('.iqx-ai-rewrite-seo-title').on('click', function() {
                    var button = $(this);
                    var id = button.data('id');
                    
                    button.prop('disabled', true);
                    button.text('Đang xử lý...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'iqx_ai_rewrite_seo_title',
                            id: id,
                            nonce: '<?php echo wp_create_nonce('iqx_ai_rewrite_seo_title'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Thành công: ' + response.data.message + '\nTiêu đề mới: ' + response.data.new_title);
                                location.reload();
                            } else {
                                alert('Lỗi: ' + response.data);
                                button.prop('disabled', false);
                                button.text('Viết lại tiêu đề SEO');
                            }
                        },
                        error: function() {
                            alert('Đã xảy ra lỗi máy chủ.');
                            button.prop('disabled', false);
                            button.text('Viết lại tiêu đề SEO');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Render the settings page
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        // Save settings if form is submitted
        if (isset($_POST['iqx_ai_settings_submit'])) {
            $this->save_settings();
        }
        
        // Test scraper if button is clicked
        if (isset($_POST['iqx_ai_test_scraper'])) {
            $this->test_scraper();
        }
        
        // Get current settings
        $settings = get_option('iqx_ai_settings', array());
        
        // Get all users
        $users = get_users(array('role__in' => array('administrator', 'editor', 'author')));
        
        // Get all categories
        $categories = get_categories(array('hide_empty' => false));
        
        ?>
        <div class="wrap">
            <h1>IQX AI Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('iqx_ai_save_settings', 'iqx_ai_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="target_url">URL Nguồn (CafeF)</label></th>
                        <td>
                            <input type="url" name="target_url" id="target_url" class="regular-text"
                                   value="<?php echo isset($settings['target_url']) ? esc_url($settings['target_url']) : ''; ?>" />
                            <p class="description">URL trang web CafeF để lấy bài viết (ví dụ: https://cafef.vn/thoi-su.chn)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="api_key">API Key (Yescale.io)</label></th>
                        <td>
                            <input type="password" name="api_key" id="api_key" class="regular-text"
                                   value="<?php echo isset($settings['api_key']) ? esc_attr($settings['api_key']) : ''; ?>" />
                            <p class="description">API key từ trang web yescale.io</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="api_model">API Model</label></th>
                        <td>
                            <input type="text" name="api_model" id="api_model" class="regular-text"
                                   value="<?php echo isset($settings['api_model']) ? esc_attr($settings['api_model']) : 'gpt-4o-mini'; ?>" />
                            <p class="description">Tên model AI sẽ sử dụng (ví dụ: gpt-4o-mini, gpt-4o, claude-3-haiku, claude-3-sonnet...)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="scraping_interval">Tần suất cào dữ liệu</label></th>
                        <td>
                            <select name="scraping_interval" id="scraping_interval">
                                <option value="hourly" <?php selected(isset($settings['scraping_interval']) ? $settings['scraping_interval'] : 'daily', 'hourly'); ?>>Mỗi giờ</option>
                                <option value="twicedaily" <?php selected(isset($settings['scraping_interval']) ? $settings['scraping_interval'] : 'daily', 'twicedaily'); ?>>Hai lần một ngày</option>
                                <option value="daily" <?php selected(isset($settings['scraping_interval']) ? $settings['scraping_interval'] : 'daily', 'daily'); ?>>Mỗi ngày</option>
                            </select>
                            <p class="description">Tần suất tự động cào dữ liệu và đăng bài</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="scraping_limit">Số lượng bài viết</label></th>
                        <td>
                            <input type="number" name="scraping_limit" id="scraping_limit" class="small-text"
                                   value="<?php echo isset($settings['scraping_limit']) ? intval($settings['scraping_limit']) : 5; ?>" min="1" max="50" />
                            <p class="description">Số lượng bài viết sẽ cào trong mỗi lần chạy</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="post_status">Trạng thái bài viết</label></th>
                        <td>
                            <select name="post_status" id="post_status">
                                <option value="publish" <?php selected(isset($settings['post_status']) ? $settings['post_status'] : 'draft', 'publish'); ?>>Xuất bản</option>
                                <option value="draft" <?php selected(isset($settings['post_status']) ? $settings['post_status'] : 'draft', 'draft'); ?>>Bản nháp</option>
                                <option value="pending" <?php selected(isset($settings['post_status']) ? $settings['post_status'] : 'draft', 'pending'); ?>>Chờ duyệt</option>
                            </select>
                            <p class="description">Trạng thái của bài viết sau khi tạo</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="post_author">Tác giả bài viết</label></th>
                        <td>
                            <select name="post_author" id="post_author">
                                <?php foreach ($users as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected(isset($settings['post_author']) ? $settings['post_author'] : 1, $user->ID); ?>>
                                    <?php echo esc_html($user->display_name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Tác giả sẽ được gán cho các bài viết mới</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="post_category">Chuyên mục</label></th>
                        <td>
                            <select name="post_category" id="post_category">
                                <option value="">Chuyên mục mặc định</option>
                                <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected(isset($settings['post_category']) ? $settings['post_category'] : '', $category->term_id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Chuyên mục sẽ được gán cho các bài viết mới</p>
                        </td>
                    </tr>
                </table>
                
                <div class="submit-buttons" style="display: flex; gap: 10px; margin-top: 20px;">
                    <p class="submit">
                        <input type="submit" name="iqx_ai_settings_submit" class="button button-primary" value="Lưu Cài Đặt" />
                    </p>
                    
                    <p class="submit">
                        <input type="submit" name="iqx_ai_test_scraper" class="button button-secondary" value="Kiểm Tra Cào Dữ Liệu" />
                    </p>
                </div>
                
                <?php if (isset($_POST['iqx_ai_test_scraper'])) : ?>
                <div class="notice notice-info">
                    <p>Đang chạy quá trình cào dữ liệu. Vui lòng kiểm tra <a href="<?php echo esc_url(admin_url('admin.php?page=iqx_ai&action=view_log')); ?>">file log</a> để xem kết quả.</p>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['action']) && $_GET['action'] === 'view_log') : ?>
                <div class="log-viewer" style="margin-top: 20px;">
                    <h2>Nội dung file log:</h2>
                    <textarea readonly style="width: 100%; height: 500px; font-family: monospace; font-size: 12px; white-space: pre;"><?php echo esc_textarea(file_exists(IQX_AI_PLUGIN_DIR . 'logs/scraper.log') ? file_get_contents(IQX_AI_PLUGIN_DIR . 'logs/scraper.log') : 'Không tìm thấy file log.'); ?></textarea>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Run the scraper process as a test
     *
     * @since    1.0.0
     */
    public function test_scraper() {
        // Run the scraper
        $scraper = new IQX_AI_Scraper();
        $scraper->run();
    }
    
    /**
     * Render API settings section description
     *
     * @since    1.0.0
     */
    public function render_api_settings_section() {
        echo '<p>Cấu hình các thiết lập API để kết nối với yescale.io API.</p>';
    }
    
    /**
     * Render API token field
     *
     * @since    1.0.0
     */
    public function render_api_token_field() {
        $settings = get_option('iqx_ai_settings', array());
        $value = isset($settings['api_token']) ? $settings['api_token'] : '';
        
        echo '<input type="password" name="iqx_ai_settings[api_token]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">API token của bạn từ yescale.io.</p>';
    }
    
    /**
     * Render model field
     *
     * @since    1.0.0
     */
    public function render_model_field() {
        $settings = get_option('iqx_ai_settings', array());
        $value = isset($settings['model']) ? $settings['model'] : 'gpt-4o';
        
        echo '<input type="text" name="iqx_ai_settings[model]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">Nhập tên model AI bạn muốn sử dụng (ví dụ: gpt-4o, gpt-3.5-turbo, gpt-4-turbo).</p>';
    }
    
    /**
     * Render scraping settings section description
     *
     * @since    1.0.0
     */
    public function render_scraping_settings_section() {
        echo '<p>Cấu hình cách plugin cào dữ liệu bài viết từ cafef.vn.</p>';
    }
    
    /**
     * Render enable scraping field
     *
     * @since    1.0.0
     */
    public function render_enable_scraping_field() {
        $settings = get_option('iqx_ai_settings', array());
        $value = isset($settings['enable_scraping']) ? $settings['enable_scraping'] : '0';
        
        echo '<input type="checkbox" name="iqx_ai_settings[enable_scraping]" value="1" ' . checked('1', $value, false) . '>';
        echo '<p class="description">Bật tự động cào dữ liệu bài viết.</p>';
    }
    
    /**
     * Render scraping frequency field
     *
     * @since    1.0.0
     */
    public function render_scraping_frequency_field() {
        $settings = get_option('iqx_ai_settings', array());
        $value = isset($settings['scraping_frequency']) ? $settings['scraping_frequency'] : 'hourly';
        
        $frequencies = array(
            'hourly' => 'Mỗi giờ',
            'twicedaily' => 'Hai lần mỗi ngày',
            'daily' => 'Mỗi ngày',
        );
        
        echo '<select name="iqx_ai_settings[scraping_frequency]">';
        foreach ($frequencies as $freq_id => $freq_name) {
            echo '<option value="' . esc_attr($freq_id) . '" ' . selected($value, $freq_id, false) . '>' . esc_html($freq_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Tần suất plugin kiểm tra bài viết mới.</p>';
    }
    
    /**
     * Render scraping limit field
     *
     * @since    1.0.0
     */
    public function render_scraping_limit_field() {
        $settings = get_option('iqx_ai_settings', array());
        $value = isset($settings['scraping_limit']) ? $settings['scraping_limit'] : '5';
        
        echo '<input type="number" name="iqx_ai_settings[scraping_limit]" value="' . esc_attr($value) . '" min="1" max="20" class="small-text">';
        echo '<p class="description">Số lượng bài viết tối đa cào trong mỗi lần chạy.</p>';
    }
    
    /**
     * Render post settings section description
     *
     * @since    1.0.0
     */
    public function render_post_settings_section() {
        echo '<p>Cấu hình cách plugin tạo bài viết WordPress từ nội dung đã viết lại.</p>';
    }
    
    /**
     * Render auto publish field
     *
     * @since    1.0.0
     */
    public function render_auto_publish_field() {
        $settings = get_option('iqx_ai_settings', array());
        $value = isset($settings['auto_publish']) ? $settings['auto_publish'] : '0';
        
        echo '<input type="checkbox" name="iqx_ai_settings[auto_publish]" value="1" ' . checked('1', $value, false) . '>';
        echo '<p class="description">Tự động tạo bài viết WordPress từ nội dung đã viết lại.</p>';
    }
    
    /**
     * Render post status field
     *
     * @since    1.0.0
     */
    public function render_post_status_field() {
        $settings = get_option('iqx_ai_settings', array());
        $value = isset($settings['post_status']) ? $settings['post_status'] : 'draft';
        
        $statuses = array(
            'draft' => 'Bản nháp',
            'publish' => 'Xuất bản',
            'pending' => 'Chờ duyệt',
        );
        
        echo '<select name="iqx_ai_settings[post_status]">';
        foreach ($statuses as $status_id => $status_name) {
            echo '<option value="' . esc_attr($status_id) . '" ' . selected($value, $status_id, false) . '>' . esc_html($status_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Trạng thái mặc định cho bài viết tạo mới.</p>';
    }
    
    /**
     * Render post author field
     *
     * @since    1.0.0
     */
    public function render_post_author_field() {
        $settings = get_option('iqx_ai_settings', array());
        $value = isset($settings['post_author']) ? $settings['post_author'] : '1';
        
        $users = get_users(array(
            'role__in' => array('administrator', 'editor', 'author'),
            'orderby' => 'display_name',
        ));
        
        echo '<select name="iqx_ai_settings[post_author]">';
        foreach ($users as $user) {
            echo '<option value="' . esc_attr($user->ID) . '" ' . selected($value, $user->ID, false) . '>' . esc_html($user->display_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Tác giả mặc định cho bài viết tạo mới.</p>';
    }
    
    /**
     * Render post category field
     *
     * @since    1.0.0
     */
    public function render_post_category_field() {
        $settings = get_option('iqx_ai_settings', array());
        $value = isset($settings['post_category']) ? $settings['post_category'] : '';
        
        $categories = get_categories(array(
            'hide_empty' => false,
            'orderby' => 'name',
        ));
        
        echo '<select name="iqx_ai_settings[post_category]">';
        echo '<option value="">— Chọn chuyên mục —</option>';
        foreach ($categories as $category) {
            echo '<option value="' . esc_attr($category->term_id) . '" ' . selected($value, $category->term_id, false) . '>' . esc_html($category->name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Chuyên mục mặc định cho bài viết tạo mới.</p>';
    }
    
    /**
     * AJAX handler for running the scraper
     *
     * @since    1.0.0
     */
    public function ajax_run_scraper() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'iqx_ai_run_scraper')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Run the scraper
        $scraper = new IQX_AI_Scraper();
        $scraper->run();
        
        wp_send_json_success('Scraper completed successfully');
    }
    
    /**
     * AJAX handler for rewriting an article
     *
     * @since    1.0.0
     */
    public function ajax_rewrite_article() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'iqx_ai_rewrite_article')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Check article ID
        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            wp_send_json_error('Invalid article ID');
        }
        
        // Get the article
        global $wpdb;
        $table_name = $wpdb->prefix . 'iqx_ai_articles';
        
        $article = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                intval($_POST['id'])
            ),
            ARRAY_A
        );
        
        if (!$article) {
            wp_send_json_error('Article not found');
        }
        
        // Rewrite the article
        $api = new IQX_AI_API();
        $rewritten = $api->rewrite_article($article['article_title'], $article['article_content']);
        
        if (!$rewritten) {
            wp_send_json_error('Failed to rewrite article');
        }
        
        // Update the article
        $this->db->update_article($article['id'], $rewritten);
        
        wp_send_json_success('Article rewritten successfully');
    }
    
    /**
     * AJAX handler for creating a post from an article
     *
     * @since    1.0.0
     */
    public function ajax_create_post() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'iqx_ai_create_post')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Check article ID
        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            wp_send_json_error('Invalid article ID');
        }
        
        // Get the article
        global $wpdb;
        $table_name = $wpdb->prefix . 'iqx_ai_articles';
        
        $article = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                intval($_POST['id'])
            ),
            ARRAY_A
        );
        
        if (!$article) {
            wp_send_json_error('Article not found');
        }
        
        // Check if the article has been rewritten
        if (empty($article['article_rewritten'])) {
            wp_send_json_error('Article has not been rewritten yet');
        }
        
        // Get settings
        $settings = get_option('iqx_ai_settings', array());
        
        // Add source attribution at the end of the article content
        $content_with_source = $article['article_rewritten'];
        
        // Check if we have a source to attribute
        if (!empty($article['source']) && !empty($article['article_url'])) {
            $source_attribution = "\n\n<div class=\"iqx-source-attribution\">";
            $source_attribution .= "<hr>";
            $source_attribution .= "<p><small>Nguồn: <a href=\"" . esc_url($article['article_url']) . "\" target=\"_blank\" rel=\"nofollow\">" . esc_html($article['source']) . "</a></small></p>";
            $source_attribution .= "</div>";
            
            $content_with_source .= $source_attribution;
        }
        
        // Use SEO title if available, otherwise use the original title
        $post_title = !empty($article['seo_title']) ? $article['seo_title'] : $article['article_title'];
        
        // Create the post
        $post_data = array(
            'post_title'   => $post_title,
            'post_content' => $content_with_source,
            'post_status'  => !empty($settings['post_status']) ? $settings['post_status'] : 'draft',
            'post_author'  => !empty($settings['post_author']) ? $settings['post_author'] : 1,
            'post_type'    => 'post',
        );
        
        // Set post category if specified
        if (!empty($settings['post_category'])) {
            $post_data['post_category'] = array(intval($settings['post_category']));
        }
        
        // Insert the post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error('Failed to create post: ' . $post_id->get_error_message());
        }
        
        // Update the article with the post ID
        $this->db->update_article($article['id'], $article['article_rewritten'], $post_id);
        
        wp_send_json_success('Post created successfully');
    }

    /**
     * AJAX handler for rewriting SEO title
     *
     * @since    1.0.0
     */
    public function ajax_rewrite_seo_title() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'iqx_ai_rewrite_seo_title')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Check article ID
        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            wp_send_json_error('Invalid article ID');
        }
        
        // Get the article
        global $wpdb;
        $table_name = $wpdb->prefix . 'iqx_ai_articles';
        
        $article = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                intval($_POST['id'])
            ),
            ARRAY_A
        );
        
        if (!$article) {
            wp_send_json_error('Article not found');
        }
        
        // Rewrite the SEO title
        $api = new IQX_AI_API();
        $rewritten_title = $api->rewrite_seo_title($article['article_title']);
        
        if (!$rewritten_title) {
            wp_send_json_error('Failed to rewrite SEO title');
        }
        
                 // Update the article with SEO title
         $this->db->update_article($article['id'], $article['article_rewritten'], $article['wp_post_id'], $rewritten_title);
         
         // If this article has a WordPress post, update its title
         if (!empty($article['wp_post_id'])) {
             $post_id = $article['wp_post_id'];
             wp_update_post(array(
                 'ID' => $post_id,
                 'post_title' => $rewritten_title
             ));
         }
         
         wp_send_json_success(array(
             'message' => 'Tiêu đề SEO đã được viết lại thành công',
             'new_title' => $rewritten_title
         ));
    }

         /**
      * AJAX handler for deleting all data
      *
      * @since    1.0.0
      */
     public function ajax_delete_all_data() {
         // Check nonce
         if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'iqx_ai_delete_all_data')) {
             wp_send_json_error('Invalid nonce');
         }
         
         // Check permissions
         if (!current_user_can('manage_options')) {
             wp_send_json_error('Insufficient permissions');
         }
         
         // Get the option to delete posts as well
         $delete_posts = isset($_POST['delete_posts']) && $_POST['delete_posts'] === 'true';
         
         // Delete all data
         $count = $this->db->delete_all_articles($delete_posts);
         
         wp_send_json_success(array(
             'message' => 'Đã xóa thành công tất cả dữ liệu',
             'count' => $count
         ));
     }

    /**
     * Display the bulk actions page
     *
     * @since    1.0.0
     */
    public function display_bulk_actions_page() {
        ?>
        <div class="wrap">
            <h1>Thao tác hàng loạt IQX AI</h1>
            
            <div class="iqx-ai-bulk-actions">
                <div class="iqx-ai-card">
                    <h2>Xóa toàn bộ dữ liệu</h2>
                    <p>Thao tác này sẽ xóa tất cả dữ liệu đã cào về từ các nguồn. Hành động này không thể hoàn tác.</p>
                    
                    <div class="iqx-ai-settings-field">
                        <label>
                            <input type="checkbox" id="iqx-ai-delete-posts" value="1">
                            Đồng thời xóa các bài viết WordPress đã tạo
                        </label>
                        <p class="description">Chọn tùy chọn này nếu bạn cũng muốn xóa các bài viết WordPress đã được tạo từ dữ liệu.</p>
                    </div>
                    
                    <button id="iqx-ai-delete-all" class="button button-danger">Xóa toàn bộ dữ liệu</button>
                    <div id="iqx-ai-delete-result" class="iqx-ai-result" style="display: none;"></div>
                </div>
                
                <div class="iqx-ai-card">
                    <h2>Viết lại tiêu đề SEO hàng loạt</h2>
                    <p>Thao tác này sẽ sử dụng AI để viết lại tiêu đề SEO cho tất cả các bài viết đã được cào về.</p>
                    <p>Quá trình này có thể mất nhiều thời gian, tùy thuộc vào số lượng bài viết.</p>
                    
                    <button id="iqx-ai-rewrite-titles" class="button button-primary">Viết lại tất cả tiêu đề</button>
                    <div id="iqx-ai-rewrite-result" class="iqx-ai-result" style="display: none;"></div>
                </div>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    // Delete all data
                    $('#iqx-ai-delete-all').on('click', function() {
                        if (!confirm('Bạn có chắc chắn muốn xóa tất cả dữ liệu? Hành động này không thể hoàn tác.')) {
                            return;
                        }
                        
                        var button = $(this);
                        var resultBox = $('#iqx-ai-delete-result');
                        var deletePosts = $('#iqx-ai-delete-posts').is(':checked');
                        
                        button.prop('disabled', true);
                        button.text('Đang xử lý...');
                        resultBox.html('<p>Đang xóa dữ liệu...</p>').show();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'iqx_ai_delete_all_data',
                                delete_posts: deletePosts ? 'true' : 'false',
                                nonce: '<?php echo wp_create_nonce('iqx_ai_delete_all_data'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    resultBox.html('<p class="iqx-ai-success">' + response.data.message + '</p>');
                                } else {
                                    resultBox.html('<p class="iqx-ai-error">Lỗi: ' + response.data + '</p>');
                                }
                                button.prop('disabled', false);
                                button.text('Xóa toàn bộ dữ liệu');
                            },
                            error: function() {
                                resultBox.html('<p class="iqx-ai-error">Đã xảy ra lỗi máy chủ.</p>');
                                button.prop('disabled', false);
                                button.text('Xóa toàn bộ dữ liệu');
                            }
                        });
                    });
                    
                    // Rewrite all titles
                    $('#iqx-ai-rewrite-titles').on('click', function() {
                        alert('Tính năng này sẽ được triển khai trong phiên bản tiếp theo.');
                    });
                });
            </script>
            
            <style>
                .iqx-ai-bulk-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 20px;
                    margin-top: 20px;
                }
                
                .iqx-ai-card {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    box-shadow: 0 1px 1px rgba(0,0,0,.04);
                    padding: 20px;
                    margin-bottom: 20px;
                    min-width: 300px;
                    flex: 1;
                }
                
                .iqx-ai-card h2 {
                    margin-top: 0;
                }
                
                .iqx-ai-result {
                    margin-top: 15px;
                    padding: 10px;
                    background: #f8f8f8;
                    border-left: 4px solid #ccc;
                }
                
                .iqx-ai-success {
                    color: green;
                }
                
                .iqx-ai-error {
                    color: red;
                }
                
                .button-danger {
                    background: #dc3232;
                    border-color: #dc3232;
                    color: white;
                }
                
                .button-danger:hover {
                    background: #b32d2e;
                    border-color: #b32d2e;
                    color: white;
                }
                
                .iqx-ai-settings-field {
                    margin-bottom: 15px;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Save settings from the settings form
     *
     * @since    1.0.0
     */
    private function save_settings() {
        // Check nonce field
        if (!isset($_POST['iqx_ai_settings_nonce']) || !wp_verify_nonce($_POST['iqx_ai_settings_nonce'], 'iqx_ai_save_settings')) {
            add_settings_error('iqx_ai_settings', 'settings_error', 'Lỗi xác thực. Vui lòng thử lại.', 'error');
            return;
        }
        
        // Get existing settings
        $settings = get_option('iqx_ai_settings', array());
        
                 // Update settings from form data
         $settings['target_url'] = isset($_POST['target_url']) ? esc_url_raw($_POST['target_url']) : '';
         $settings['api_key'] = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
         $settings['api_model'] = isset($_POST['api_model']) ? sanitize_text_field($_POST['api_model']) : 'gpt-4o';
         $settings['enable_scraping'] = isset($_POST['enable_scraping']) ? '1' : '0';
         $settings['scraping_interval'] = isset($_POST['scraping_interval']) ? sanitize_text_field($_POST['scraping_interval']) : 'daily';
         $settings['scraping_limit'] = isset($_POST['scraping_limit']) ? absint($_POST['scraping_limit']) : 5;
        $settings['auto_publish'] = isset($_POST['auto_publish']) ? '1' : '0';
        $settings['post_status'] = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft';
        $settings['post_author'] = isset($_POST['post_author']) ? absint($_POST['post_author']) : 1;
        $settings['post_category'] = isset($_POST['post_category']) ? sanitize_text_field($_POST['post_category']) : '';
        
        // Save updated settings
        update_option('iqx_ai_settings', $settings);
        
        // Add success message
        add_settings_error('iqx_ai_settings', 'settings_updated', 'Cài đặt đã được lưu thành công.', 'success');
    }
} 