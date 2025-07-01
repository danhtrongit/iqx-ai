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
        $system_prompt = "You are an expert content writer who specializes in rewriting articles to make them original, SEO-friendly, and engaging. Maintain the core information but make the content unique. Follow these guidelines:\n";
        $system_prompt .= "1. Keep the original meaning and key facts intact\n";
        $system_prompt .= "2. Improve the structure and flow\n";
        $system_prompt .= "3. Enhance readability and engagement\n";
        $system_prompt .= "4. Use SEO-friendly formatting with proper headings, subheadings, and paragraphs\n";
        $system_prompt .= "5. Maintain HTML formatting for headings, paragraphs, and lists\n";
        $system_prompt .= "6. Do not add any attribution, sources, or disclaimers that weren't in the original\n";
        
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