<?php
// 動作確認用テストスクリプト
// 実行: docker exec harvest-training-web php test_search.php
chdir('/var/www/html');
require_once __DIR__ . '/bootstrap.php';

function run_test($label, $params, $must_contain, $must_not_contain = array())
{
    $im = new InformationManager();
    foreach ($params as $k => $v) {
        $im->set_param($k, $v);
    }
    $im->set_param('list_tpl', '@FILE:templates/info_list.tpl');
    $im->set_param('page', 1);
    $im->set_param('sort', '');
    $result = $im->run();

    $pass = true;
    foreach ($must_contain as $text) {
        if (strpos($result, $text) === false) {
            echo "FAIL [$label]: 「{$text}」が出力に見つかりません\n";
            $pass = false;
        }
    }
    foreach ($must_not_contain as $text) {
        if (strpos($result, $text) !== false) {
            echo "FAIL [$label]: 「{$text}」が出力に含まれてはいけません\n";
            $pass = false;
        }
    }
    if ($pass) {
        echo "PASS [$label]\n";
    }
}

// 注: info_date DESC ソートのため全件表示ページ1は id22(最新)〜id3。
//   id1(新年のご挨拶), id2(節分イベント) はページ2。

// 1. 全件表示（条件なし）: ページ1に最新20件が表示される
run_test(
    '全件表示',
    array('mode' => 'info_list'),
    array('お疲れ様でした', 'バレンタイン売上速報') // id22(最新), id3(20番目)
);

// 2. search_text=朝礼: id18(本日の朝礼), id1(新年のご挨拶/本文) がヒット→2件のみ
//    未実装時: id12(社員旅行)がページ1に存在するためFAIL
run_test(
    'search_text=朝礼',
    array('mode' => 'info_list', 'search_text' => '朝礼'),
    array('本日の朝礼'),        // id18: ヒットすべき
    array('社員旅行')           // id12: 2026-04-18、朝礼に無関係→除外されるべき
);

// 3. search_date_from=2026-04-15: id11〜id22(12件)がヒット
//    未実装時: 3月の販売スケジュール(id4, 2026-03-01)がページ1に存在するためFAIL
run_test(
    'search_date_from=2026-04-15',
    array('mode' => 'info_list', 'search_date_from' => '2026-04-15'),
    array('新メーカー取り扱い開始'),   // id11: 2026-04-15(境界値、含む)
    array('3月の販売スケジュール')     // id4: 2026-03-01、範囲外→除外されるべき
);

// 4. 日付範囲（2026-04-01〜2026-04-15）: id7〜id11(5件)がヒット
//    未実装時: 社員旅行(id12, 2026-04-18)がページ1に存在するためFAIL
run_test(
    'date_from=2026-04-01 to=2026-04-15',
    array('mode' => 'info_list', 'search_date_from' => '2026-04-01', 'search_date_to' => '2026-04-15'),
    array('新年度開始', '新メーカー取り扱い開始'), // id7(境界値), id11(境界値)
    array('社員旅行')                              // id12: 2026-04-18、範囲外→除外されるべき
);

// 5. search_date_to=2026-02-28: id1〜id3(3件)がヒット、全てページ1に収まる
//    未実装時: 節分イベント(id2)はページ2のためFAIL
run_test(
    'search_date_to=2026-02-28',
    array('mode' => 'info_list', 'search_date_to' => '2026-02-28'),
    array('バレンタイン売上速報', '節分イベント'), // id3(2/14), id2(2/3): 範囲内
    array('新年度開始')                             // id7: 2026-04-01、範囲外→除外されるべき
);

echo "\n--- JSON endpoint ---\n";
$im = new InformationManager();
$im->set_param('mode', 'json_get_info_data');
$im->set_param('search_text', '研修');
$im->set_param('page', 1);
$json = $im->run();
$data = json_decode($json, true);
$subjects = array();
foreach ($data['data'] as $row) {
    $subjects[] = $row['info_subject'];
}
$expected = array('春の研修について', 'システム研修のご案内');
foreach ($expected as $s) {
    if (in_array($s, $subjects)) {
        echo "PASS [JSON search_text=研修]: 「{$s}」を確認\n";
    } else {
        echo "FAIL [JSON search_text=研修]: 「{$s}」が見つかりません\n";
    }
}
if (in_array('在庫棚卸しのお知らせ', $subjects)) {
    echo "FAIL [JSON search_text=研修]: 「在庫棚卸しのお知らせ」が含まれてはいけません\n";
} else {
    echo "PASS [JSON search_text=研修]: 「在庫棚卸し」が除外されていることを確認\n";
}
