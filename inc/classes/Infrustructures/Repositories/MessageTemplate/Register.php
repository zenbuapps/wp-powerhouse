<?php

declare (strict_types = 1);

namespace J7\Powerhouse\Infrustructures\Repositories\MessageTemplate;

/** Class Register */
final class Register {
	private const POST_TYPE = 'ph_message_tpl';

	/** Register hooks */
	public static function register_hooks(): void {
		\add_action('init', [ __CLASS__, 'register_cpt' ]);
	}

	/** Register cpt */
	public static function register_cpt(): void {

		$args = [
			'labels'             => self::labels(),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => [ 'title', 'custom-fields' ],
		];

		// @phpstan-ignore-next-line
		\register_post_type(self::POST_TYPE, $args);
	}

	/** @return array<string, string> Get post_type labels */
	public static function labels(): array {
		return [
			'name'                  => \_x('Message Template', 'Post type general name', 'power_funnel'),
			'singular_name'         => \_x('Message Template', 'Post type singular name', 'power_funnel'),
			'menu_name'             => \_x('Message Templates', 'Admin Menu text', 'power_funnel'),
			'name_admin_bar'        => \_x('Message Template', 'Add New on Toolbar', 'power_funnel'),
			'add_new'               => \__('Add New', 'power_funnel'),
			'add_new_item'          => \__('Add New Message Template', 'power_funnel'),
			'new_item'              => \__('New Message Template', 'power_funnel'),
			'edit_item'             => \__('Edit Message Template', 'power_funnel'),
			'view_item'             => \__('View Message Template', 'power_funnel'),
			'all_items'             => \__('All Message Templates', 'power_funnel'),
			'search_items'          => \__('Search Message Templates', 'power_funnel'),
			'parent_item_colon'     => \__('Parent Message Templates:', 'power_funnel'),
			'not_found'             => \__('No Message Templates found.', 'power_funnel'),
			'not_found_in_trash'    => \__('No Message Templates found in Trash.', 'power_funnel'),
			'featured_image'        => \_x('Message Template Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'power_funnel'),
			'set_featured_image'    => \_x('Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'power_funnel'),
			'remove_featured_image' => \_x('Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'power_funnel'),
			'use_featured_image'    => \_x('Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'power_funnel'),
			'archives'              => \_x('Message Template archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'power_funnel'),
			'insert_into_item'      => \_x('Insert into Message Template', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'power_funnel'),
			'uploaded_to_this_item' => \_x('Uploaded to this Message Template', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'power_funnel'),
			'filter_items_list'     => \_x('Filter Message Templates list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'power_funnel'),
			'items_list_navigation' => \_x('Message Templates list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'power_funnel'),
			'items_list'            => \_x('Message Templates list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'power_funnel'),
		];
	}

	/** Get post_type */
	public static function post_type(): string {
		return self::POST_TYPE;
	}

	/** Get the post_type label */
	public static function label(): string {
		return self::labels()['name'];
	}


	/** @return bool 是否為活動報名 post */
	public static function match( \WP_Post $post ): bool {
		return $post->post_type === self::POST_TYPE;
	}
}
