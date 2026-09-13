<?php
/**
 * Site Info Tool — read-only snapshot of the site: identity, versions,
 * content counts, public post types and taxonomies.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPA_Site_Info_Tool extends WPA_Abstract_Tool {

    public function get_name() {
        return 'get_site_info';
    }

    public function get_title() {
        return 'اطلاعات سایت';
    }

    public function get_description() {
        return 'Get general information about this WordPress site: name, URL, WordPress version, active theme, '
            . 'language, timezone, content counts (posts, pages, comments, users) and the list of public post types '
            . 'and taxonomies. Call this first when you need to discover which post types or taxonomies exist.';
    }

    public function get_parameters_schema() {
        return [
            'type'       => 'object',
            'properties' => [],
        ];
    }

    public function get_required_capability() {
        return 'manage_options';
    }

    public function execute( array $args ) {
        $post_counts    = wp_count_posts( 'post' );
        $page_counts    = wp_count_posts( 'page' );
        $comment_counts = wp_count_comments();
        $user_counts    = count_users();

        $taxonomies = [];
        foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $taxonomy ) {
            $taxonomies[] = [
                'slug'  => $taxonomy->name,
                'label' => $taxonomy->label,
            ];
        }

        return [
            'site_name'         => get_bloginfo( 'name' ),
            'tagline'           => get_bloginfo( 'description' ),
            'site_url'          => home_url(),
            'wordpress_version' => get_bloginfo( 'version' ),
            'language'          => get_bloginfo( 'language' ),
            'timezone'          => wp_timezone_string(),
            'active_theme'      => wp_get_theme()->get( 'Name' ),
            'counts'            => [
                'published_posts'   => (int) $post_counts->publish,
                'draft_posts'       => (int) $post_counts->draft,
                'published_pages'   => (int) $page_counts->publish,
                'approved_comments' => (int) $comment_counts->approved,
                'pending_comments'  => (int) $comment_counts->moderated,
                'users'             => (int) $user_counts['total_users'],
            ],
            'public_post_types' => array_values( get_post_types( [ 'public' => true ] ) ),
            'public_taxonomies' => $taxonomies,
        ];
    }
}
