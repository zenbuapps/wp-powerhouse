<?php
/**
 * 顯示當前文章下一層的子文章
 */

global $post;

/** @var array{post: WP_Post|null, post_type: string|null, title: string|null} $args */
@[
	'post'      => $the_post,
	'post_type' => $the_post_type,
	'title'     => $the_title,
] = $args;

$the_post = ( $the_post instanceof \WP_Post ) ? $the_post : $post;

if ( ! ( $the_post instanceof \WP_Post ) ) {
	return;
}

/** @var array<int, WP_Post> $children_posts */
$children_posts = \get_children(
	[
		'post_parent' => $the_post->ID,
		'post_type'   => !empty($the_post_type) ? (string) $the_post_type : $the_post->post_type,
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => [
			'menu_order' => 'ASC',
			'ID'         => 'ASC',
			'date'       => 'ASC',
		],
	]
);


if (!$children_posts) {
	return;
}

$the_title = $the_title ?? '章節內容';
echo '<h3 class="text-lg md:text-2xl font-black mb-4">' . $the_title . '</h3>';
echo '<div class="grid grid-cols-2 xl:grid-cols-4 gap-2 md:gap-4 mb-12">';

foreach ($children_posts as $child_post) {
	printf(
	/*html*/'
	<a href="%1$s" class="group w-full rounded-box border border-solid border-base-content/30 px-4 py-2 md:py-4">
		<p class="m-0 text-sm md:text-base text-base-content group-hover:text-primary">%2$s</p>
	</a>
	',
	get_the_permalink($child_post->ID),
	$child_post->post_title
	);
}

echo '</div>';
