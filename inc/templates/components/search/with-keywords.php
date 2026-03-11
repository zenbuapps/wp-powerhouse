<?php

use J7\Powerhouse\Plugin;
use J7\Powerhouse\Domains\Post\Utils\CRUD as PostCRUD;

global $post;

/** @var array{post: \WP_Post|null} $args */
@[
	'post' => $the_post,
] = $args;

$the_post = ( $the_post instanceof \WP_Post ) ? $the_post : $post;

if ( ! ( $the_post instanceof \WP_Post ) ) {
	echo 'search keywords 區塊錯誤：$the_post 不是 WP_Post 實例';
	return;
}

$top_parent_id = PostCRUD::get_top_post_id( $the_post->ID );

/** @var array<array{id: string, title: string}>|'' $badges */
$badges = get_post_meta( $top_parent_id, 'pd_keywords', true );
$badges = is_array( $badges ) ? $badges : [];


$badge_html = sprintf(
	/*html*/'<span class="pc-keywords pc-label-text-alt text-base-300 text-left"><span class="mr-2">%s</span>',
	(string) get_post_meta( $top_parent_id, 'pd_keywords_label', true )
);

foreach ($badges as $badge) {
	$badge_title = $badge['title'];
	if ($badge_title) {
		$badge_html .= sprintf(
		'<div class="pc-badge pc-badge-ghost pc-badge-sm mr-2 mb-2">%s</div>',
		\esc_html( $badge_title )
		);
	}
}
$badge_html .= '</span>';


// HERO 區塊
printf(
	/*html*/'
	<label class="pc-form-control">
		%1$s
		<div class="pc-label px-0">
			%2$s
			<div></div>
		</div>
	</label>
	',
	Plugin::load_template('search', [], false),
	$badges ? $badge_html : '',
	);


?>
<script type="module" async>
	(function($){
		$(document).ready(function(){
			$('.pc-form-control .pc-keywords').on('click', '.pc-badge', function(){
				const keyword = $(this).text();
				const $form = $(this).closest('.pc-form-control');
				const $input = $form.find('.pc-search-input');
				$input.val(keyword);
			})
		})
	})(jQuery)

</script>
