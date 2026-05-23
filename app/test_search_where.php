<?php
/**
 * TDD テスト: _build_search_where() の動作検証
 *
 * 実行: docker exec harvest-training-web php test_search_where.php
 */

require_once __DIR__ . '/bootstrap.php';

// プライベートメソッドをテスト可能にするサブクラス
class InformationManagerTest extends InformationManager
{
    public function callBuildSearchWhere()
    {
        return $this->_build_search_where();
    }

    public function setParam($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    public function clearParams()
    {
        $this->parameters = array();
    }
}

// ---- テストユーティリティ ----

$passed = 0;
$failed = 0;

function assert_equal($expected, $actual, $label)
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  PASS: {$label}\n";
        $passed++;
    } else {
        echo "  FAIL: {$label}\n";
        echo "    Expected: " . var_export($expected, true) . "\n";
        echo "    Actual:   " . var_export($actual, true) . "\n";
        $failed++;
    }
}

function assert_contains($needle, $haystack, $label)
{
    global $passed, $failed;
    if (strpos($haystack, $needle) !== false) {
        echo "  PASS: {$label}\n";
        $passed++;
    } else {
        echo "  FAIL: {$label}\n";
        echo "    Expected to contain: " . var_export($needle, true) . "\n";
        echo "    In:                  " . var_export($haystack, true) . "\n";
        $failed++;
    }
}

function assert_not_contains($needle, $haystack, $label)
{
    global $passed, $failed;
    if (strpos($haystack, $needle) === false) {
        echo "  PASS: {$label}\n";
        $passed++;
    } else {
        echo "  FAIL: {$label}\n";
        echo "    Expected NOT to contain: " . var_export($needle, true) . "\n";
        echo "    In:                      " . var_export($haystack, true) . "\n";
        $failed++;
    }
}

function run_test($label, $callable)
{
    global $passed, $failed;
    echo "\n[{$label}]\n";
    try {
        $callable();
    } catch (Error $e) {
        echo "  FAIL: " . $e->getMessage() . "\n";
        $failed++;
    } catch (Exception $e) {
        echo "  FAIL: " . $e->getMessage() . "\n";
        $failed++;
    }
}

// ---- テスト本体 ----

$mgr = new InformationManagerTest();

run_test('パラメータなしは空文字列を返す', function () use ($mgr) {
    $mgr->clearParams();
    assert_equal('', $mgr->callBuildSearchWhere(), 'WHERE 句が空文字列');
});

run_test('search_text → ILIKE で件名と本文を OR 検索', function () use ($mgr) {
    $mgr->clearParams();
    $mgr->setParam('search_text', '朝礼');
    $where = $mgr->callBuildSearchWhere();
    assert_contains('ILIKE', $where, 'ILIKE を使用している');
    assert_contains('info_subject', $where, 'info_subject を対象にしている');
    assert_contains('info_text', $where, 'info_text を対象にしている');
    assert_contains('朝礼', $where, 'キーワードが WHERE 句に含まれる');
    assert_contains('%朝礼%', $where, '部分一致（%...%）になっている');
});

run_test('search_date_from → info_date >= で絞り込み', function () use ($mgr) {
    $mgr->clearParams();
    $mgr->setParam('search_date_from', '2026-04-15');
    $where = $mgr->callBuildSearchWhere();
    assert_contains('info_date >=', $where, 'info_date >= を使用している');
    assert_contains('2026-04-15', $where, '開始日が WHERE 句に含まれる');
});

run_test('search_date_to → info_date <= で絞り込み', function () use ($mgr) {
    $mgr->clearParams();
    $mgr->setParam('search_date_to', '2026-04-30');
    $where = $mgr->callBuildSearchWhere();
    assert_contains('info_date <=', $where, 'info_date <= を使用している');
    assert_contains('2026-04-30', $where, '終了日が WHERE 句に含まれる');
});

run_test('全パラメータ指定 → AND で結合', function () use ($mgr) {
    $mgr->clearParams();
    $mgr->setParam('search_text',      '会議');
    $mgr->setParam('search_date_from', '2026-04-01');
    $mgr->setParam('search_date_to',   '2026-04-30');
    $where = $mgr->callBuildSearchWhere();
    assert_contains('AND', $where, '複数条件は AND で結合される');
    assert_contains('会議', $where, '全パラメータ: キーワードが含まれる');
    assert_contains('2026-04-01', $where, '全パラメータ: 開始日が含まれる');
    assert_contains('2026-04-30', $where, '全パラメータ: 終了日が含まれる');
});

run_test('SQLインジェクション文字列がエスケープされる', function () use ($mgr) {
    $mgr->clearParams();
    $mgr->setParam('search_text', "'; DROP TABLE information; --");
    $where = $mgr->callBuildSearchWhere();
    // pg_escape_string は ' を '' に変換する（PostgreSQL の標準エスケープ）
    // LIKE パターン内で '' になっているので SQL として安全
    assert_contains("''; DROP", $where, "シングルクォートが '' に変換されている（pg_escape_string 済み）");
});

// ---- 結果表示 ----

echo "\n----------------------------------------\n";
echo "結果: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
