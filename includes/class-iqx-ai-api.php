<?php
/**
 * The API communication functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    IQX_AI
 * @subpackage IQX_AI/includes
 */

class IQX_AI_API {

    /**
     * API endpoint for rewriting content
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_endpoint    The API endpoint
     */
    private $api_endpoint;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->api_endpoint = IQX_AI_API_URL . '/v1/chat/completions';
    }

    /**
     * Rewrite an article using the API
     *
     * @since    1.0.0
     * @param    string    $title     The article title
     * @param    string    $content   The article content
     * @return   string|bool          The rewritten content or false on failure
     */
    public function rewrite_article($title, $content) {
        // Get API settings
        $settings = get_option('iqx_ai_settings', array());
        
        if (empty($settings['api_token'])) {
            $this->log_message('API token is not set');
            return false;
        }
        
        // Prepare the API request
        $model = !empty($settings['model']) ? $settings['model'] : 'gpt-4o';
        
        // Create a prompt that will instruct the AI to rewrite the article
        $system_prompt = "Bạn là chuyên gia biên tập nội dung với thế mạnh là viết lại các bài báo sao cho độc đáo, tối ưu SEO, cuốn hút người đọc. Giữ nguyên các thông tin cốt lõi nhưng đảm bảo nội dung hoàn toàn mới lạ. Hãy tuân thủ các hướng dẫn sau:\n";
        $system_prompt .= "1. Giữ nguyên ý nghĩa và các thông tin quan trọng của bài gốc.\n";
        $system_prompt .= "2. Cải thiện cấu trúc, trình bày logic và mạch lạc hơn.\n";
        $system_prompt .= "3. Tăng tính dễ đọc, lôi cuốn, phù hợp với độc giả Việt Nam.\n";
        $system_prompt .= "4. Sử dụng định dạng tối ưu SEO: có tiêu đề chính, phụ đề, đoạn văn ngắn, danh sách bullet khi cần thiết.\n";
        $system_prompt .= "5. Duy trì định dạng HTML cho tiêu đề (h1, h2, h3...), đoạn văn (<p>), danh sách (<ul>, <li>), v.v.\n";
        $system_prompt .= "6. Tuyệt đối không thêm chú thích, nguồn tham khảo, hoặc bất kỳ ghi chú nào không có trong bản gốc.\n";
        $system_prompt .= "7. Đặc biệt, cuối bài viết, hãy đưa ra nhận định và khuyến nghị về các mã cổ phiếu đang hoặc sẽ chịu ảnh hưởng lớn từ nội dung bài viết, kèm theo phân tích ngắn gọn về lý do tác động.\n";
        
        // Create the request payload
        $payload = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => "Rewrite the following article about stock market. Title: {$title}\n\nContent: {$content}"
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 4000
        );
        
        // Make the API request
        $response = wp_remote_post(
            $this->api_endpoint,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $settings['api_token'],
                ),
                'body' => json_encode($payload),
                'timeout' => 60,
            )
        );
        
        // Check for errors
        if (is_wp_error($response)) {
            $this->log_message('API request error: ' . $response->get_error_message());
            return false;
        }
        
        // Parse the response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check if we have a valid response
        if (empty($data) || !isset($data['choices'][0]['message']['content'])) {
            $this->log_message('Invalid API response: ' . $body);
            return false;
        }
        
        // Extract the rewritten content
        $rewritten_content = $data['choices'][0]['message']['content'];
        
        return $rewritten_content;
    }

    /**
     * Log a message to the plugin's log file
     *
     * @since    1.0.0
     * @param    string    $message    The message to log
     */
    private function log_message($message) {
        $log_file = IQX_AI_PLUGIN_DIR . 'logs/api.log';
        $timestamp = current_time('mysql');
        $log_message = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
} 