<?php
/**
 * Abstract Tool — the contract every agent tool implements.
 *
 * A tool describes itself to the model (name, description, JSON Schema) and
 * executes with WordPress capability checks. Registration happens only
 * through WPA_Tool_Registry; tools never hook themselves.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

abstract class WPA_Abstract_Tool {

    /**
     * Machine name the model calls (snake_case, e.g. "list_posts").
     */
    abstract public function get_name();

    /**
     * Persian title shown in the chat UI.
     */
    abstract public function get_title();

    /**
     * English description sent to the model. Precision here directly
     * determines how well the agent picks and uses the tool.
     */
    abstract public function get_description();

    /**
     * JSON Schema (WP-style array) describing the accepted arguments.
     */
    abstract public function get_parameters_schema();

    /**
     * WordPress capability required to execute this tool.
     */
    abstract public function get_required_capability();

    /**
     * Run the tool. Returns a result array for the model, or WP_Error.
     */
    abstract public function execute( array $args );

    /**
     * Destructive tools will require explicit user confirmation (phase 4).
     * Every phase 3 tool is read-only.
     */
    public function is_destructive() {
        return false;
    }

    /**
     * OpenAI-format tool definition sent along with each model request.
     */
    public function get_definition() {
        $parameters = $this->get_parameters_schema();

        // Empty PHP arrays JSON-encode as [] but OpenAI expects {} here.
        if ( isset( $parameters['properties'] ) && [] === $parameters['properties'] ) {
            $parameters['properties'] = new stdClass();
        }

        return [
            'type'     => 'function',
            'function' => [
                'name'        => $this->get_name(),
                'description' => $this->get_description(),
                'parameters'  => $parameters,
            ],
        ];
    }
}
