<?php
/**
 * Tool Registry — loads all tools and is the single security gate for
 * executing them: existence, capability and schema validation checks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Tool_Registry {

    /**
     * Registered tools keyed by tool name.
     */
    private $tools = [];

    public function __construct() {
        require_once WPA_PATH . 'includes/tools/class-wpa-abstract-tool.php';

        /**
         * Filter the registered tool instances. Third-party plugins can add
         * their own WPA_Abstract_Tool implementations here.
         *
         * @param WPA_Abstract_Tool[] $tools Default tool instances.
         */
        $tools = apply_filters( 'wpa_register_tools', $this->get_default_tools() );

        foreach ( $tools as $tool ) {
            if ( $tool instanceof WPA_Abstract_Tool ) {
                $this->tools[ $tool->get_name() ] = $tool;
            }
        }
    }

    /**
     * Load and instantiate the built-in tools.
     */
    private function get_default_tools() {
        $map = [
            'class-wpa-site-info-tool.php'     => 'WPA_Site_Info_Tool',
            'class-wpa-list-posts-tool.php'    => 'WPA_List_Posts_Tool',
            'class-wpa-get-post-tool.php'      => 'WPA_Get_Post_Tool',
            'class-wpa-list-comments-tool.php' => 'WPA_List_Comments_Tool',
            'class-wpa-list-terms-tool.php'    => 'WPA_List_Terms_Tool',
            'class-wpa-get-term-tool.php'      => 'WPA_Get_Term_Tool',
            'class-wpa-create-post-tool.php'      => 'WPA_Create_Post_Tool',
            'class-wpa-update-post-tool.php'      => 'WPA_Update_Post_Tool',
            'class-wpa-trash-post-tool.php'       => 'WPA_Trash_Post_Tool',
            'class-wpa-set-post-terms-tool.php'   => 'WPA_Set_Post_Terms_Tool',
            'class-wpa-create-term-tool.php'      => 'WPA_Create_Term_Tool',
            'class-wpa-update-term-tool.php'      => 'WPA_Update_Term_Tool',
            'class-wpa-moderate-comment-tool.php' => 'WPA_Moderate_Comment_Tool',
            'class-wpa-upload-media-tool.php'     => 'WPA_Upload_Media_Tool',
        ];

        $tools = [];

        foreach ( $map as $file => $class ) {
            require_once WPA_PATH . 'includes/tools/' . $file;
            $tools[] = new $class();
        }

        return $tools;
    }

    /**
     * Get a tool instance by name, or null.
     */
    public function get( $name ) {
        return isset( $this->tools[ $name ] ) ? $this->tools[ $name ] : null;
    }

    /**
     * OpenAI-format definitions of all registered tools.
     */
    public function get_definitions() {
        $definitions = [];

        foreach ( $this->tools as $tool ) {
            $definitions[] = $tool->get_definition();
        }

        return $definitions;
    }

    /**
     * Execute a tool by name. Every failure is returned as a plain
     * [ 'error' => ... ] array so the model can read it and recover.
     */
    public function execute( $name, array $args ) {
        $tool = $this->get( $name );

        if ( ! $tool ) {
            return [ 'error' => sprintf( 'ابزار «%s» وجود ندارد.', $name ) ];
        }

        // Capability is checked per tool at execution time, not only at the REST gate.
        if ( ! current_user_can( $tool->get_required_capability() ) ) {
            return [ 'error' => sprintf( 'شما دسترسی لازم برای اجرای ابزار «%s» را ندارید.', $tool->get_title() ) ];
        }

        $schema = $tool->get_parameters_schema();

        $valid = rest_validate_value_from_schema( $args, $schema, 'arguments' );
        if ( is_wp_error( $valid ) ) {
            return [ 'error' => 'ورودی ابزار نامعتبر است: ' . $valid->get_error_message() ];
        }

        $args   = rest_sanitize_value_from_schema( $args, $schema );
        $result = $tool->execute( is_array( $args ) ? $args : [] );

        if ( is_wp_error( $result ) ) {
            return [ 'error' => $result->get_error_message() ];
        }

        return $result;
    }
}
