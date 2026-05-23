<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>社内お知らせ一覧（研修用）</title>
<link rel="stylesheet" href="static/style.css">
</head>
<body>
<header>
	<h1>社内お知らせ一覧</h1>
	<p class="subtitle">研修用環境（Information Search Training）</p>
</header>

<form method="get" action="" class="search-form">
	<input type="hidden" name="mode" value="info_list">
	<label>件名・本文：<input type="text" name="search_text" value="[+search_text+]" placeholder="キーワード"></label>
	<label>投稿日 開始：<input type="date" name="search_date_from" value="[+search_date_from+]"></label>
	<label>投稿日 終了：<input type="date" name="search_date_to" value="[+search_date_to+]"></label>
	<button type="submit">検索</button>
	<a href="?mode=info_list" class="reset">リセット</a>
</form>

<div class="page-navi-top">[+page_navi+]</div>

<table class="info-list">
	<thead>
		<tr>
			<th class="col-day">投稿日</th>
			<th class="col-subject">件名</th>
			<th class="col-text">本文</th>
			<th class="col-user">投稿者</th>
			<th class="col-checked">既読</th>
		</tr>
	</thead>
	<tbody>

<!-- start -->
		<tr class="[+class+]">
			<td class="col-day">[+info_day+]</td>
			<td class="col-subject">[+info_subject+]</td>
			<td class="col-text">[+info_text+]</td>
			<td class="col-user">[+reg_user+]</td>
			<td class="col-checked">[+checked+]</td>
		</tr>
<!-- end -->
[+no_results_msg+]
	</tbody>
</table>

<div class="page-navi-bottom">[+page_navi+]</div>

</body>
</html>
