<?php
/**
 * 分頁
 * 接受 url 參數 to 決定去哪一頁
 * TODO 應該可以優化更為通用
 */

/** @var array{query: \WP_Query|null} $args */
@[
	'query' => $query,
] = $args;

if (!$query instanceof \WP_Query) {
	echo '$query 不是 WP_Query 實例';
	return;
}



// 取得分頁資訊
$total_posts  = $query->found_posts; // 總文章數
$total_pages  = $query->max_num_pages; // 總頁數
$current_page = (int) ($_GET['to'] ?? 1); // phpcs:ignore

// ---------- render ---------- //

// 總頁數 <= 1 不顯示分頁
if ($total_pages <= 1) {
	return;
}
$html  = '<form action="" method="get">';
$html .= '<div class="pc-join [&_button.pc-btn]:!text-base-content [&_button.pc-btn]:!pc-btn-sm [&_button.pc-btn]:md:!pc-btn-md">'; // START pc-join


// 遍歷 current 前後 1 頁
$from = max(1, $current_page - 1); // current 前一頁，或 1
$to   = min($total_pages, $current_page + 1); // current 後一頁，或總頁數

// 如果 current 前一頁 > 1，則顯示 ..., 第一頁
if ($from > 1) {
	$html .= '<button class="pc-join-item pc-btn bg-base-200 !rounded-l-btn" type="submit" name="to" value="1">1</button>';
	$html .= '<button class="pc-join-item pc-btn !pointer-events-none">...</button>';
}

for ($i = $from; $i <= $to; $i++) {
	$html .= sprintf(
	/*html*/'
	<button class="pc-join-item pc-btn pc-btn-square %2$s" type="submit" name="to" value="%1$s">%1$s</button>
	',
	$i,
	$i === $current_page ? 'pc-btn-primary' : 'bg-base-200',
	);
}

// 如果 current 後一頁 < 總頁數，則顯示 ..., 最後一頁,
if ($to < $total_pages) {
	$html .= '<button class="pc-join-item pc-btn !pointer-events-none">...</button>';
	$html .= sprintf(
	/*html*/'
	<button class="pc-join-item pc-btn bg-base-200 !rounded-r-btn" type="submit" name="to" value="%1$s">%1$s</button>
	',
	$total_pages,
	);
}

$html .= sprintf(
/*html*/'<input type="hidden" name="search" value="%1$s" />
',
	(string) ($_GET['search'] ?? ''), // phpcs:ignore
); // 原本 search 參數
$html .= '</div>'; // END pc-join
$html .= '</form>';
echo $html;
