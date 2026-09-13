<?php
/**
 * Get Term Tool — full details of a single taxonomy term by ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Get_Term_Tool extends WPA_Abstract_Tool {

    /**
     * Cap on returned child terms to keep the payload small.
     */
    const MAX_CHILDREN = 50;

    public function get_name() {
        return 'get_term';
    }

    public function get_title() {
        return 'دریافت ترم';
    }

    public function get_description() {
        return 'Get the full details of a single taxonomy term by its ID: name, slug, taxonomy, description, '
            . 'post count, archive link, parent term and child terms. Use list_terms first when you only know '
            . 'the term name, not the ID.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [
                'term_id'  => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'ID of the term to fetch.',
                ],
                'taxonomy' => [
                    'type'        => 'string',
                    'description' => 'Taxonomy slug of the term. Optional, but include it when known.',
                ],
            ],
            'required'   => [ 'term_id' ],
        ];
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function execute( array $args ) {
        $term_id  = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;
        $taxonomy = ! empty( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';

        $term = get_term( $term_id, $taxonomy );

        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'wpa_tool_error', sprintf( 'ترمی با شناسه %d یافت نشد.', $term_id ) );
        }

        $parent = null;
        if ( $term->parent ) {
            $parent_term = get_term( $term->parent, $term->taxonomy );
            if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                $parent = [
                    'id'   => (int) $parent_term->term_id,
                    'name' => $parent_term->name,
                ];
            }
        }

        $children = [];
        foreach ( array_slice( get_term_children( $term->term_id, $term->taxonomy ), 0, self::MAX_CHILDREN ) as $child_id ) {
            $child = get_term( $child_id, $term->taxonomy );
            if ( $child && ! is_wp_error( $child ) ) {
                $children[] = [
                    'id'   => (int) $child->term_id,
                    'name' => $child->name,
                ];
            }
        }

        $link            = get_term_link( $term );
        $taxonomy_object = get_taxonomy( $term->taxonomy );

        return [
            'id'             => (int) $term->term_id,
            'name'           => $term->name,
            'slug'           => $term->slug,
            'taxonomy'       => $term->taxonomy,
            'taxonomy_label' => $taxonomy_object ? $taxonomy_object->label : $term->taxonomy,
            'description'    => $term->description,
            'count'          => (int) $term->count,
            'link'           => is_wp_error( $link ) ? null : $link,
            'parent'         => $parent,
            'children'       => $children,
        ];
    }
}
