<?php
/**
 * The scraper functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    IQX_AI
 * @subpackage IQX_AI/includes
 */

class IQX_AI_Scraper {

    /**
     * The database instance
     *
     * @since    1.0.0
     * @access   private
     * @var      IQX_AI_DB    $db    The database handler
     */
    private $db;

    /**
     * The API handler instance
     *
     * @since    1.0.0
     * @access   private
     * @var      IQX_AI_API    $api    The API handler
     */
    private $api;

    /**
     * Target URL for scraping
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $target_url    The URL to scrape
     */
    private $target_url = 'https://cafef.vn/thi-truong-chung-khoan.chn';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->db = new IQX_AI_DB();
        $this->api = new IQX_AI_API();
    }

    /**
     * Run the article scraping, rewriting and posting process
     *
     * @since    1.0.0
     */
    public function run() {
        // Tạo thư mục logs nếu chưa tồn tại
        $logs_dir = IQX_AI_PLUGIN_DIR . 'logs';
        if (!file_exists($logs_dir)) {
            mkdir($logs_dir, 0755, true);
        }
        
        $this->log_message('===== BẮT ĐẦU QUÁ TRÌNH TỰ ĐỘNG CÀO DỮ LIỆU =====');
        $this->log_message('Thời gian bắt đầu: ' . current_time('mysql'));
        
        // Get settings
        $settings = get_option('iqx_ai_settings', array());
        
        // Check if the target URL is set
        if (empty($settings['target_url'])) {
            $this->log_message('Lỗi: URL nguồn không được cấu hình');
            return;
        }
        
        // Check if the API key and model are set
        if (empty($settings['api_key']) || empty($settings['api_model'])) {
            $this->log_message('Lỗi: API key hoặc model không được cấu hình');
            return;
        }
        
        // Set target URL
        $this->target_url = esc_url($settings['target_url']);
        $this->log_message('URL nguồn: ' . $this->target_url);
        
        // Get articles
        $this->log_message('Đang tìm kiếm bài viết từ nguồn...');
        $articles = $this->fetch_articles();
        
        if (empty($articles)) {
            $this->log_message('Không tìm thấy bài viết nào từ nguồn. Vui lòng kiểm tra lại URL nguồn.');
            return;
        }
        
        $this->log_message('Tìm thấy ' . count($articles) . ' bài viết tiềm năng. Đang xử lý...');
        
        // Initialize the API class
        $api = new IQX_AI_API($settings['api_key'], $settings['api_model']);
        
        // Process each article
        $processed_count = 0;
        $success_count = 0;
        
        foreach ($articles as $article_info) {
            $url = $article_info['url'];
            $processed_count++;
            
            $this->log_message("Đang xử lý bài viết #$processed_count: {$article_info['title']} ($url)");
            
            // Check if the URL is an article page
            if (!$this->is_article_page($url)) {
                $this->log_message("Bỏ qua: URL không phải là trang bài viết: $url");
                continue;
            }
            
            // Fetch article content
            $this->log_message("Đang trích xuất nội dung từ: $url");
            $article = $this->fetch_article_content($url);
            
            if (empty($article)) {
                $this->log_message("Bỏ qua: Không thể trích xuất nội dung từ: $url");
                continue;
            }
            
            // Validate the article
            if (!$this->validate_article($article, $url)) {
                $this->log_message("Bỏ qua: Bài viết không đạt tiêu chí kiểm duyệt: $url");
                continue;
            }
            
            // Rewrite article content with AI
            $this->log_message("Đang gửi nội dung đến API AI để viết lại...");
            $rewritten_content = $api->rewrite_content($article['content']);
            
            if (empty($rewritten_content)) {
                $this->log_message("Lỗi: Không thể viết lại nội dung bài viết: $url");
                continue;
            }
            
            $this->log_message("Đã nhận nội dung viết lại từ API AI. Kích thước: " . strlen($rewritten_content) . " ký tự");
            
            // Create post
            $post_id = $this->create_post($article['title'], $rewritten_content);
            
            if ($post_id) {
                $success_count++;
                $post_edit_link = admin_url('post.php?post=' . $post_id . '&action=edit');
                $this->log_message("Thành công: Đã tạo bài viết mới (ID: $post_id): $post_edit_link");
            } else {
                $this->log_message("Lỗi: Không thể tạo bài viết từ: $url");
            }
            
            // Respect the scraping limit
            $limit = !empty($settings['scraping_limit']) ? intval($settings['scraping_limit']) : 5;
            if ($success_count >= $limit) {
                $this->log_message("Đã đạt giới hạn số bài viết cần lấy ($limit bài). Dừng quá trình.");
                break;
            }
            
            // Add a small delay between requests to avoid overloading the server
            $this->log_message("Chờ 3 giây trước khi xử lý bài viết tiếp theo...");
            sleep(3);
        }
        
        $this->log_message("===== KẾT THÚC QUÁ TRÌNH TỰ ĐỘNG CÀO DỮ LIỆU =====");
        $this->log_message("Thời gian kết thúc: " . current_time('mysql'));
        $this->log_message("Tổng số bài viết đã xử lý: $processed_count");
        $this->log_message("Số bài viết đã tạo thành công: $success_count");
    }

    /**
     * Fetch article listings from the target URL
     *
     * @since    1.0.0
     * @return   array    Array of articles with title and URL
     */
    private function fetch_articles() {
        // Get the HTML content of the page
        $response = wp_remote_get($this->target_url, array(
            'timeout' => 30, // Tăng thời gian timeout
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36', // User agent để giả lập trình duyệt
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->log_message('Failed to fetch article listings: ' . wp_remote_retrieve_response_message($response));
            return array();
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Debug: Lưu HTML để phân tích
        $debug_file = IQX_AI_PLUGIN_DIR . 'logs/debug_html.txt';
        file_put_contents($debug_file, $html);
        $this->log_message('Đã lưu HTML để debug vào file: ' . $debug_file);
        
        // Load HTML with DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $articles = array();
        $all_links = array();
        
        // Các bộ chọn XPath cụ thể cho bài viết trên cafef.vn
        $article_selectors = array(
            // Bộ chọn chi tiết cho cafef.vn - thứ tự từ cụ thể đến tổng quát
            '//div[contains(@class, "box-content")]//a[contains(@class, "title")]',
            '//h3[contains(@class, "title")]/a',
            '//h2[contains(@class, "title")]/a',
            '//h1[contains(@class, "title")]/a',
            '//div[contains(@class, "list-news")]//a[@title]',
            '//div[contains(@class, "left-content")]//a[@title]',
            '//h2[contains(@class, "news-item-title")]/a',
            '//h3[contains(@class, "news-item-title")]/a',
            '//div[contains(@class, "tlitem")]//a[contains(@class, "title")]',
            '//div[contains(@class, "knswli-right")]//a',
            '//ul[contains(@class, "list-news")]//a',
            '//div[contains(@class, "top-content")]//a[@title]',
            '//div[contains(@class, "featured")]//a[@title]',
            '//div[contains(@class, "main-story")]//a',
            '//div[contains(@class, "item")]//a[contains(@class, "title")]',
            '//div[contains(@class, "box-category")]//a[@title]',
            // Bộ chọn chung chung hơn - chỉ dùng khi các bộ chọn trên không tìm được gì
            '//a[@title]',
            '//a[contains(@href, ".chn")]'
        );
        
        // Lấy tất cả các link từ các bộ chọn khác nhau
        foreach ($article_selectors as $selector) {
            $nodes = $xpath->query($selector);
            $this->log_message("Bộ chọn '$selector': tìm thấy " . $nodes->length . " phần tử");
            
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $url = $node->getAttribute('href');
                    $title = trim($node->textContent);
                    
                    // Bỏ qua các URL rỗng hoặc chỉ có dấu #
                    if (empty($url) || $url == '#') {
                        continue;
                    }
                    
                    $all_links[] = array(
                        'url' => $url,
                        'title' => $title
                    );
                }
            }
        }
        
        // Ghi log số lượng link tìm thấy
        $this->log_message('Tìm thấy tổng cộng ' . count($all_links) . ' link từ tất cả các bộ chọn');
        
        // Các từ khóa URL nên loại trừ (không phải bài viết)
        $exclude_keywords = array(
            '/lien-he.chn',
            '/quang-cao.chn',
            '/sitemap',
            '/rss',
            '/ajax/',
            'facebook.com',
            'google.com',
            'twitter.com',
            'javascript:',
            '#',
        );
        
        // Các từ khóa trong URL có thể chỉ ra đây là bài viết
        $article_keywords = array(
            'tin-tuc', 
            'bai-viet', 
            'news', 
            'article', 
            '-nd-',
            '-id',
            '.html',
            '.chn'
        );
        
        // Xử lý tất cả các link tìm thấy
        foreach ($all_links as $item) {
            $url = $item['url'];
            $title = $item['title'];
            
            // Make sure URL is absolute
            if (strpos($url, 'http') !== 0) {
                if (strpos($url, '/') === 0) {
                    $url = 'https://cafef.vn' . $url;
                } else {
                    $url = 'https://cafef.vn/' . $url;
                }
            }
            
            // Kiểm tra có phải URL của cafef.vn không
            if (strpos($url, 'cafef.vn') === false) {
                continue;
            }
            
            // Kiểm tra URL có trong danh sách loại trừ không
            $should_exclude = false;
            foreach ($exclude_keywords as $keyword) {
                if (strpos($url, $keyword) !== false) {
                    $should_exclude = true;
                    break;
                }
            }
            
            if ($should_exclude) {
                continue;
            }
            
            // Kiểm tra URL có từ khóa của bài viết không
            $is_article = false;
            foreach ($article_keywords as $keyword) {
                if (strpos($url, $keyword) !== false) {
                    $is_article = true;
                    break;
                }
            }
            
            if (!$is_article) {
                continue;
            }
            
            // Kiểm tra tiêu đề bài viết có hợp lệ không
            if (empty($title) || strlen($title) < 5 || strlen($title) > 300) {
                continue;
            }
            
            // Kiểm tra xem URL này đã được thêm vào danh sách chưa (tránh trùng lặp)
            $is_duplicate = false;
            foreach ($articles as $article) {
                if ($article['url'] === $url) {
                    $is_duplicate = true;
                    break;
                }
            }
            
            if ($is_duplicate) {
                continue;
            }
            
            $articles[] = array(
                'url' => $url,
                'title' => $title
            );
        }
        
        // Log số lượng bài viết hợp lệ đã tìm thấy
        $this->log_message('Đã lọc ra ' . count($articles) . ' bài viết hợp lệ.');
        
        if (count($articles) > 0) {
            // Log chi tiết các bài viết đã tìm thấy
            $log_articles = array_slice($articles, 0, min(5, count($articles)));
            foreach ($log_articles as $index => $article) {
                $this->log_message("Bài viết #$index: " . $article['title'] . " - " . $article['url']);
            }
        } else {
            $this->log_message('Không tìm thấy bài viết nào hợp lệ. Vui lòng kiểm tra lại bộ chọn XPath.');
        }
        
        return $articles;
    }

    /**
     * Fetch full article content from an article URL
     *
     * @since    1.0.0
     * @param    string    $url    The article URL
     * @return   array             Array with title and content
     */
    private function fetch_article_content($url) {
        // Get the HTML content of the page
        $response = wp_remote_get($url, array(
            'timeout' => 30, // Tăng thời gian timeout
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36', // User agent để giả lập trình duyệt
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->log_message('Failed to fetch article content: ' . wp_remote_retrieve_response_message($response));
            return array();
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Debug: Lưu HTML để phân tích
        $debug_file = IQX_AI_PLUGIN_DIR . 'logs/debug_article_' . md5($url) . '.txt';
        file_put_contents($debug_file, $html);
        $this->log_message('Đã lưu HTML bài viết để debug vào file: ' . $debug_file);
        
        // Load HTML with DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        // Tìm tiêu đề bài viết
        $title_selectors = array(
            '//h1[contains(@class, "title")]',
            '//h1[contains(@class, "article-title")]',
            '//h1[contains(@class, "article_title")]',
            '//h1[contains(@class, "news-title")]',
            '//h1[contains(@class, "post-title")]',
            '//h1',
            '//meta[@property="og:title"]/@content',
            '//title'
        );
        
        $title = '';
        foreach ($title_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $title = trim($nodes->item(0)->textContent);
                if (!empty($title)) {
                    $this->log_message("Đã tìm thấy tiêu đề bài viết: $title (sử dụng bộ chọn: $selector)");
                    break;
                }
            }
        }
        
        // Không tìm thấy tiêu đề
        if (empty($title)) {
            $this->log_message('Không tìm thấy tiêu đề bài viết tại ' . $url);
            return array();
        }
        
        // Tìm nội dung bài viết
        $content_selectors = array(
            '//div[contains(@class, "detail-content")]',
            '//div[contains(@class, "article-body")]',
            '//div[contains(@class, "article_content")]',
            '//div[contains(@class, "news-content")]',
            '//div[contains(@class, "detail-content-news")]',
            '//div[@id="mainContent"]',
            '//article',
            '//div[contains(@class, "content")]'
        );
        
        $content = '';
        foreach ($content_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                // Lọc ra các thẻ không mong muốn
                $node = $nodes->item(0);
                $content_dom = new DOMDocument();
                $content_dom->appendChild($content_dom->importNode($node, true));
                
                // Xóa các phần tử không mong muốn
                $unwanted_selectors = array(
                    '//script',
                    '//style',
                    '//iframe',
                    '//div[contains(@class, "banner")]',
                    '//div[contains(@class, "advertisement")]',
                    '//div[contains(@class, "related")]',
                    '//div[contains(@class, "comment")]',
                    '//div[contains(@class, "social")]',
                    '//div[contains(@class, "share")]',
                    '//div[contains(@class, "author")]',
                    '//div[contains(@class, "tags")]',
                    '//div[contains(@class, "recommendation")]'
                );
                
                $content_xpath = new DOMXPath($content_dom);
                foreach ($unwanted_selectors as $unwanted_selector) {
                    $unwanted_nodes = $content_xpath->query($unwanted_selector);
                    foreach ($unwanted_nodes as $unwanted_node) {
                        $unwanted_node->parentNode->removeChild($unwanted_node);
                    }
                }
                
                $content = trim($content_dom->saveHTML());
                if (!empty($content)) {
                    $this->log_message("Đã tìm thấy nội dung bài viết (sử dụng bộ chọn: $selector)");
                    break;
                }
            }
        }
        
        // Nếu không tìm thấy nội dung với bộ chọn cụ thể, thử phương pháp khác
        if (empty($content)) {
            $this->log_message('Thử phương pháp thay thế để lấy nội dung bài viết');
            
            // Lấy phần tử body
            $body = $xpath->query('//body')->item(0);
            if ($body) {
                // Loại bỏ header, footer và các phần không liên quan
                $unwanted_tags = array('header', 'footer', 'nav', 'aside', 'script', 'style', 'iframe');
                foreach ($unwanted_tags as $tag) {
                    $elements = $xpath->query('//' . $tag);
                    foreach ($elements as $element) {
                        if ($element->parentNode) {
                            $element->parentNode->removeChild($element);
                        }
                    }
                }
                
                // Tìm các đoạn văn bản dài
                $paragraphs = $xpath->query('//p');
                $article_content = '';
                foreach ($paragraphs as $paragraph) {
                    $text = trim($paragraph->textContent);
                    if (strlen($text) > 100) {  // Đoạn văn có ít nhất 100 ký tự
                        $article_content .= '<p>' . $text . '</p>';
                    }
                }
                
                if (!empty($article_content)) {
                    $content = $article_content;
                    $this->log_message('Đã trích xuất nội dung từ các đoạn văn bản dài');
                }
            }
        }
        
        // Không tìm thấy nội dung
        if (empty($content)) {
            $this->log_message('Không tìm thấy nội dung bài viết tại ' . $url);
            return array();
        }
        
        // Làm sạch nội dung
        $content = $this->clean_content($content);
        
        // Log kích thước nội dung
        $this->log_message('Kích thước nội dung: ' . strlen($content) . ' ký tự');
        
        return array(
            'title' => $title,
            'content' => $content
        );
    }
    
    /**
     * Clean HTML content
     *
     * @since    1.0.0
     * @param    string    $content    HTML content to clean
     * @return   string                Cleaned HTML content
     */
    private function clean_content($content) {
        // Loại bỏ các đoạn script và style
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
        
        // Loại bỏ các thẻ HTML không mong muốn
        $content = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $content);
        $content = preg_replace('/<object\b[^>]*>(.*?)<\/object>/is', '', $content);
        $content = preg_replace('/<embed\b[^>]*>(.*?)<\/embed>/is', '', $content);
        
        // Loại bỏ các thuộc tính không cần thiết
        $content = preg_replace('/\s(id|class|style|onclick|onload|data-[^\s=]*)="[^"]*"/i', '', $content);
        
        // Loại bỏ khoảng trắng thừa
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Loại bỏ các dòng trống
        $content = preg_replace('/(<br\s*\/?>(\s*<br\s*\/?>)+)/', '<br />', $content);
        $content = preg_replace('/^[\s\r\n]+|[\s\r\n]+$/m', '', $content);
        
        return $content;
    }

    /**
     * Create a WordPress post from rewritten content
     *
     * @since    1.0.0
     * @param    string    $title     The post title
     * @param    string    $content   The post content
     * @return   int|bool             The post ID or false on failure
     */
    private function create_post($title, $content) {
        // Get settings
        $settings = get_option('iqx_ai_settings', array());
        
        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
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
            $this->log_message('Failed to create post: ' . $post_id->get_error_message());
            return false;
        }
        
        return $post_id;
    }

    /**
     * Log a message to the plugin's log file
     *
     * @since    1.0.0
     * @param    string    $message    The message to log
     */
    private function log_message($message) {
        $log_file = IQX_AI_PLUGIN_DIR . 'logs/scraper.log';
        $timestamp = current_time('mysql');
        $log_message = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }

    /**
     * Check if the URL is an article page
     *
     * @since    1.0.0
     * @param    string    $url    The URL to check
     * @return   boolean           True if the URL is an article page, false otherwise
     */
    private function is_article_page($url) {
        // Kiểm tra URL có thuộc về cafef.vn không
        if (strpos($url, 'cafef.vn') === false) {
            $this->log_message("URL không thuộc cafef.vn: $url");
            return false;
        }
        
        // Các URL cần loại trừ (không phải trang bài viết)
        $exclude_patterns = array(
            '/trang-chu',
            '/lien-he',
            '/quang-cao',
            '/sitemap',
            '/rss',
            '/tim-kiem',
            '/ajax/',
            '/api/',
            '/tag/',
            '/tags/',
            '/search/',
            '/login/',
            '/register/',
            '/dang-nhap',
            '/dang-ky',
            '.jpg',
            '.png',
            '.gif',
            '.mp4',
            '.pdf',
            'facebook.com',
            'google.com',
            'twitter.com',
            'javascript:'
        );
        
        // Kiểm tra có trong danh sách loại trừ không
        foreach ($exclude_patterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                $this->log_message("URL bị loại trừ theo mẫu '$pattern': $url");
                return false;
            }
        }
        
        // Mẫu URL bài viết thông thường (chấp nhận nhiều mẫu hơn)
        $article_patterns = array(
            // Mẫu theo ngày tháng năm
            '/\d{4}\/\d{1,2}\/\d{1,2}\//',
            // Mẫu theo ID bài viết
            '/-\d+\.chn/',
            '/-\d+\.html/',
            // Các mẫu bài viết thông thường
            '/tin-tuc/',
            '/bai-viet/',
            '/news/',
            '/article/',
            '/[a-z0-9-]{10,}\.chn/',
            '/[a-z0-9-]{10,}\.html/'
        );
        
        // Kiểm tra có khớp với mẫu bài viết không
        foreach ($article_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                $this->log_message("URL khớp với mẫu bài viết '$pattern': $url");
                return true;
            }
        }
        
        // Nếu không khớp với bất kỳ mẫu nào, kiểm tra thêm các dấu hiệu khác
        $article_keywords = array(
            'tin-tuc', 
            'bai-viet', 
            'news', 
            'article', 
            '-nd-',
            '-id',
            '.html',
            '.chn'
        );
        
        foreach ($article_keywords as $keyword) {
            if (strpos($url, $keyword) !== false) {
                $this->log_message("URL có chứa từ khóa bài viết '$keyword': $url");
                return true;
            }
        }
        
        // Kiểm tra URL có quá ngắn không (có thể là trang chuyên mục)
        $url_path = parse_url($url, PHP_URL_PATH);
        if ($url_path && substr_count($url_path, '/') <= 1) {
            $this->log_message("URL có thể là trang chuyên mục, không phải bài viết: $url");
            return false;
        }
        
        $this->log_message("URL không phù hợp với các tiêu chí bài viết: $url");
        return false;
    }

    /**
     * Kiểm tra toàn diện bài viết trước khi lưu
     *
     * @since    1.0.0
     * @param    array     $article    Mảng chứa thông tin bài viết
     * @param    string    $url        URL của bài viết
     * @return   bool                  True nếu bài viết hợp lệ, ngược lại false
     */
    private function validate_article($article, $url) {
        // Kiểm tra tiêu đề
        if (empty($article['title'])) {
            $this->log_message("Tiêu đề bài viết trống: $url");
            return false;
        }
        
        // Tiêu đề quá ngắn hoặc quá dài - nới lỏng điều kiện chiều dài
        if (strlen($article['title']) < 5 || strlen($article['title']) > 300) {
            $this->log_message("Tiêu đề bài viết có độ dài không hợp lệ (" . strlen($article['title']) . " ký tự): $url");
            return false;
        }
        
        // Kiểm tra nội dung
        if (empty($article['content'])) {
            $this->log_message("Nội dung bài viết trống: $url");
            return false;
        }
        
        // Nội dung quá ngắn - nới lỏng điều kiện về độ dài
        $content_length = strlen(strip_tags($article['content']));
        if ($content_length < 100) {
            $this->log_message("Nội dung bài viết quá ngắn (" . $content_length . " ký tự): $url");
            return false;
        }
        
        // Kiểm tra xem nội dung có chứa một số đoạn văn hợp lệ không
        $paragraphs = substr_count($article['content'], '</p>');
        if ($paragraphs < 2) {
            $this->log_message("Nội dung bài viết không chứa đủ đoạn văn (chỉ có $paragraphs đoạn): $url");
            // Không loại trừ ngay mà thử kiểm tra xem có dấu xuống dòng không
            $line_breaks = substr_count($article['content'], "\n") + substr_count($article['content'], '<br');
            if ($line_breaks < 3) {
                $this->log_message("Nội dung bài viết không chứa đủ dòng (chỉ có $line_breaks dòng): $url");
                return false;
            }
        }
        
        // Kiểm tra có phải nội dung quảng cáo không
        $ad_keywords = array(
            'quảng cáo', 
            'liên hệ mua hàng', 
            'hotline:', 
            'mua ngay', 
            'đặt hàng', 
            'giảm giá',
            'khuyến mãi',
            'sale off'
        );
        
        $content_lower = strtolower(strip_tags($article['content']));
        $ad_keyword_count = 0;
        foreach ($ad_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $ad_keyword_count++;
            }
        }
        
        // Nếu có quá nhiều từ khóa quảng cáo
        if ($ad_keyword_count >= 3) {
            $this->log_message("Nội dung bài viết có vẻ là quảng cáo ($ad_keyword_count từ khóa quảng cáo): $url");
            return false;
        }
        
        // Kiểm tra tỷ lệ nội dung có ý nghĩa (tránh trường hợp chỉ toàn link, thẻ HTML)
        $text_content = strip_tags($article['content']);
        $html_length = strlen($article['content']);
        if ($html_length > 0) {
            $text_ratio = strlen($text_content) / $html_length;
            if ($text_ratio < 0.2) {
                $this->log_message("Tỷ lệ văn bản/HTML quá thấp ($text_ratio): $url");
                // Không loại trừ ngay, chỉ ghi log cảnh báo
            }
        }
        
        // Log thông tin chi tiết về bài viết hợp lệ để theo dõi
        $this->log_message("Bài viết hợp lệ: {$article['title']} | Độ dài: $content_length ký tự | $paragraphs đoạn văn | URL: $url");
        
        return true;
    }
} 