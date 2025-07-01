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
     * Run the scraper process
     *
     * @since    1.0.0
     */
    public function run() {
        // Get settings
        $settings = get_option('iqx_ai_settings', array());
        
        // Check if scraping is enabled
        if (empty($settings['enable_scraping']) || $settings['enable_scraping'] !== '1') {
            $this->log_message('Scraping is disabled in settings.');
            return;
        }
        
        // Fetch articles
        $articles = $this->fetch_articles();
        
        if (empty($articles)) {
            $this->log_message('No articles found or error fetching articles.');
            return;
        }
        
        // Process each article
        foreach ($articles as $article) {
            // Check if article already exists in our database
            if ($this->db->article_exists($article['url'])) {
                $this->log_message("Article already exists: {$article['url']}");
                continue;
            }
            
            // Kiểm tra xem URL có phải là trang bài viết hay không
            if (!$this->is_article_page($article['url'])) {
                $this->log_message("URL is not an article page: {$article['url']}");
                continue;
            }
            
            // Get full article content
            $full_article = $this->fetch_article_content($article['url']);
            
            if (empty($full_article) || empty($full_article['content'])) {
                $this->log_message("Failed to fetch content for: {$article['url']}");
                continue;
            }
            
            // Kiểm tra toàn diện bài viết trước khi lưu
            if (!$this->validate_article($full_article, $article['url'])) {
                continue;
            }
            
            // Save the article to our database
            $article_id = $this->db->save_article(array(
                'url' => $article['url'],
                'title' => $full_article['title'],
                'content' => $full_article['content'],
                'source' => 'cafef'
            ));
            
            $this->log_message("Saved article: {$article['url']} with ID: {$article_id}");
            
            // Send to API for rewriting if we have API credentials
            if (!empty($settings['api_token'])) {
                $rewritten = $this->api->rewrite_article($full_article['title'], $full_article['content']);
                
                if ($rewritten) {
                    $this->db->update_article($article_id, $rewritten);
                    $this->log_message("Rewritten article ID: {$article_id}");
                    
                    // Create WordPress post if auto-publishing is enabled
                    if (!empty($settings['auto_publish']) && $settings['auto_publish'] === '1') {
                        $post_id = $this->create_post($full_article['title'], $rewritten);
                        if ($post_id) {
                            $this->db->update_article($article_id, $rewritten, $post_id);
                            $this->log_message("Created post ID: {$post_id} for article ID: {$article_id}");
                        }
                    }
                } else {
                    $this->log_message("Failed to rewrite article ID: {$article_id}");
                }
            }
            
            // Respect the scraping limit
            $limit = !empty($settings['scraping_limit']) ? intval($settings['scraping_limit']) : 5;
            if (--$limit <= 0) {
                break;
            }
            
            // Add a small delay between requests to avoid overloading the server
            sleep(2);
        }
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
     * Kiểm tra xem một URL có phải là trang bài viết hay không
     *
     * @since    1.0.0
     * @param    string    $url    URL cần kiểm tra
     * @return   bool              True nếu là trang bài viết, ngược lại false
     */
    private function is_article_page($url) {
        // Danh sách các mẫu URL bài viết hợp lệ trên cafef.vn
        $valid_article_patterns = array(
            '/\d{4}\/\d{1,2}\/\d{1,2}\//', // Định dạng URL có ngày tháng năm
            '/-\d{8,}\.chn/', // Định dạng URL có ID bài viết
            '/-\d{1,}\.html/', // Định dạng khác có ID bài viết
            '/[a-z0-9-]{10,}\.chn/' // Định dạng bài viết thông thường
        );
        
        // Kiểm tra URL có khớp với mẫu bài viết hợp lệ không
        foreach ($valid_article_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        
        // Các từ khóa trong URL có thể chỉ ra đây là bài viết
        $article_keywords = array(
            'tin-tuc', 'bai-viet', 'news', 'article', '-nd-'
        );
        
        foreach ($article_keywords as $keyword) {
            if (strpos($url, $keyword) !== false) {
                return true;
            }
        }
        
        // Nếu không khớp với bất kỳ mẫu nào, thực hiện kiểm tra nhanh nội dung trang
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36'
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Kiểm tra các dấu hiệu của trang bài viết
        if (strpos($html, 'detail-content') !== false || 
            strpos($html, 'mainContent') !== false || 
            strpos($html, 'post-content') !== false ||
            strpos($html, 'articleContent') !== false) {
            return true;
        }
        
        // Nếu không có đủ dấu hiệu, coi như không phải trang bài viết
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
            $this->log_message("Article title is empty: $url");
            return false;
        }
        
        // Tiêu đề quá ngắn hoặc quá dài
        if (strlen($article['title']) < 10 || strlen($article['title']) > 200) {
            $this->log_message("Article title has invalid length (" . strlen($article['title']) . " chars): $url");
            return false;
        }
        
        // Kiểm tra nội dung
        if (empty($article['content'])) {
            $this->log_message("Article content is empty: $url");
            return false;
        }
        
        // Nội dung quá ngắn (dưới 200 ký tự)
        $content_text = strip_tags($article['content']);
        if (strlen($content_text) < 200) {
            $this->log_message("Article content is too short (" . strlen($content_text) . " chars): $url");
            return false;
        }
        
        // Kiểm tra xem nội dung có phải HTML hợp lệ hay không
        if (strpos($article['content'], '<') === false || strpos($article['content'], '>') === false) {
            $this->log_message("Article content does not contain HTML tags: $url");
            return false;
        }
        
        // Kiểm tra các dấu hiệu của nội dung không phải bài viết
        $spam_keywords = array(
            'login', 'register', 'sign in', 'sign up', 'password', 'username',
            'đăng nhập', 'đăng ký', 'mật khẩu', 'tên đăng nhập'
        );
        
        foreach ($spam_keywords as $keyword) {
            if (stripos($content_text, $keyword) !== false && strlen($content_text) < 1000) {
                $this->log_message("Article content contains spam keyword '$keyword': $url");
                return false;
            }
        }
        
        // Kiểm tra xem nội dung có quá nhiều liên kết hay không
        $link_count = substr_count(strtolower($article['content']), '<a ');
        $text_length = strlen($content_text);
        
        // Nếu mật độ liên kết quá cao (> 1 liên kết trên 100 ký tự)
        if ($text_length > 0 && ($link_count / ($text_length / 100)) > 1) {
            $this->log_message("Article content has too many links ($link_count links): $url");
            return false;
        }
        
        return true;
    }
} 