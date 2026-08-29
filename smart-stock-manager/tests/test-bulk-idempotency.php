<?php

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['ssm_test_options'] = array();
$GLOBALS['ssm_test_products'] = array();

function add_action() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function wp_next_scheduled() { return false; }
function wp_schedule_event() {}
function wp_unschedule_event() {}
function absint($value) { return abs((int) $value); }
function apply_filters($tag, $value) { return $value; }
function get_current_user_id() { return 1; }
function current_time($format) { return $format === 'mysql' ? '2026-08-11 22:00:00' : gmdate($format); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_term($id, $taxonomy) {
    return $id === 1 ? (object) array('slug' => 'test-category') : false;
}
function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['ssm_test_options']) ? $GLOBALS['ssm_test_options'][$key] : $default;
}
function update_option($key, $value) {
    $GLOBALS['ssm_test_options'][$key] = $value;
    return true;
}
function wc_format_decimal($value) {
    if ($value === '' || $value === null || !is_numeric($value)) {
        return '';
    }
    return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
}
function wc_get_product($id) {
    return isset($GLOBALS['ssm_test_products'][$id]) ? $GLOBALS['ssm_test_products'][$id] : false;
}

class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}

class Fake_WPDB {
    public $prefix = 'wp_';
    public $postmeta = 'wp_postmeta';
    public $item_states = array();
    private $last_args = array();

    public function prepare($query, ...$args) {
        $this->last_args = $args;
        return $query;
    }

    public function query($query) {
        return 1;
    }

    public function get_results($query, $output = null) {
        if (strpos($query, $this->postmeta) === false) {
            return array();
        }
        $product_id = isset($this->last_args[0]) ? (int) $this->last_args[0] : 0;
        $product = wc_get_product($product_id);
        if (!$product) {
            return array();
        }
        return array(
            array('meta_key' => '_regular_price', 'meta_value' => $product->get_regular_price('edit')),
            array('meta_key' => '_sale_price', 'meta_value' => $product->get_sale_price('edit')),
        );
    }

    public function get_var($query) {
        if (strpos($query, 'ssm_bulk_job_items') !== false && strpos($query, 'SELECT state') !== false) {
            $item_id = isset($this->last_args[0]) ? (int) $this->last_args[0] : 0;
            return isset($this->item_states[$item_id]) ? $this->item_states[$item_id] : null;
        }
        return null;
    }

    public function update($table, $data, $where, $formats = null, $where_formats = null) {
        if (strpos($table, 'ssm_bulk_job_items') !== false) {
            $item_id = isset($where['id']) ? (int) $where['id'] : 0;
            $expected = isset($where['state']) ? $where['state'] : null;
            if (!isset($this->item_states[$item_id]) || ($expected !== null && $this->item_states[$item_id] !== $expected)) {
                return 0;
            }
            $this->item_states[$item_id] = $data['state'];
            return 1;
        }
        return 1;
    }
}

$GLOBALS['wpdb'] = new Fake_WPDB();

class Mock_Product {
    private $id;
    private $regular;
    private $sale;
    public $save_count = 0;

    public function __construct($id, $regular, $sale = '') {
        $this->id = $id;
        $this->regular = (string) $regular;
        $this->sale = (string) $sale;
    }

    public function get_id() { return $this->id; }
    public function get_name() { return 'Test product'; }
    public function get_sku() { return 'SKU-' . $this->id; }
    public function get_type() { return 'simple'; }
    public function get_status() { return 'publish'; }
    public function get_regular_price($context = 'view') { return $this->regular; }
    public function get_sale_price($context = 'view') { return $this->sale; }
    public function set_regular_price($value) { $this->regular = (string) $value; }
    public function set_sale_price($value) { $this->sale = (string) $value; }
    public function set_price($value) {}
    public function is_on_sale($context = 'view') {
        return $this->sale !== '' && (float) $this->sale < (float) $this->regular;
    }
    public function save() { $this->save_count++; }
}

require dirname(__DIR__) . '/smart-stock-manager.php';

function invoke_private($object, $method, array $args = array()) {
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($object, $args);
}

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$manager = new Smart_Stock_Manager();
$increase = array(
    'field' => 'regular',
    'mode' => 'percent',
    'store_amount' => 15,
    'direction' => 'increase',
    'empty_sale' => 'skip',
);

assert_same('115', invoke_private($manager, 'calc_adjusted_price', array('100', $increase)), '15 percent target is calculated once');

$_POST = array(
    'price_field' => 'regular',
    'direction' => 'increase',
    'mode' => 'percent',
    'amount' => '15',
    'unit' => 'toman',
    'scope' => 'tampered-scope',
    'category' => '0',
    'empty_sale' => 'skip',
);
$invalid_scope = invoke_private($manager, 'parse_bulk_price_request');
assert_same(true, is_wp_error($invalid_scope), 'invalid scope fails closed instead of targeting all products');

$_POST['scope'] = 'all';
$_POST['category'] = 'not-a-number';
$invalid_category = invoke_private($manager, 'parse_bulk_price_request');
assert_same(true, is_wp_error($invalid_category), 'invalid category fails closed instead of becoming all products');

$_POST['category'] = '0';
$_POST['mode'] = 'fixed';
$_POST['amount'] = '1e309';
$infinite_amount = invoke_private($manager, 'parse_bulk_price_request');
assert_same(true, is_wp_error($infinite_amount), 'infinite fixed amount is rejected');

$invalid_range = invoke_private($manager, 'parse_recovery_time_range', array('2026-99-99', '99:99', '23:59'));
assert_same(true, is_wp_error($invalid_range), 'invalid recovery date and time fail without a fatal error');

$malformed_log = invoke_private(
    $manager,
    'parse_legacy_bulk_log_message',
    array('Product #20 price_update: not-a-price → 115 by user #1 (bulk_price)', '2026-08-11 20:00:00', 'regular')
);
assert_same(true, is_wp_error($malformed_log), 'malformed legacy price values fail closed');

$product = new Mock_Product(10, '100', '90');
$GLOBALS['ssm_test_products'][10] = $product;
$snapshot = invoke_private($manager, 'snapshot_bulk_item', array(array('product_id' => 10), $increase));
assert_same('100', $snapshot['old_regular'], 'snapshot stores original regular price');
assert_same('115', $snapshot['new_regular'], 'snapshot stores immutable target price');
assert_same('90', $snapshot['new_sale'], 'valid sale price is preserved');

$GLOBALS['wpdb']->item_states[1] = 'ready';
$item = array_merge(array('id' => 1, 'job_id' => 1, 'product_id' => 10), $snapshot);
$first = invoke_private($manager, 'apply_bulk_target', array($item, 'job-test', false));
assert_same('applied', $first['state'], 'first application succeeds');
assert_same('115', $product->get_regular_price(), 'first application reaches fixed target');
assert_same(1, $product->save_count, 'first application saves once');

$retry = invoke_private($manager, 'apply_bulk_target', array($item, 'job-test', false));
assert_same('applied', $retry['state'], 'retry after interrupted cursor update is idempotent');
assert_same('115', $product->get_regular_price(), 'retry does not compound to 132.25');
assert_same(1, $product->save_count, 'idempotent retry performs no second save');

$external_target = new Mock_Product(13, '115', '');
$GLOBALS['ssm_test_products'][13] = $external_target;
$GLOBALS['wpdb']->item_states[4] = 'ready';
$external_item = array(
    'id' => 4,
    'job_id' => 1,
    'product_id' => 13,
    'old_regular' => '100',
    'new_regular' => '115',
    'old_sale' => '',
    'new_sale' => '',
);
$external_claim = invoke_private($manager, 'apply_bulk_target', array($external_item, 'job-test', false));
assert_same('conflict', $external_claim['state'], 'fresh item already at target is not claimed by this operation');
assert_same('conflict', $GLOBALS['wpdb']->item_states[4], 'externally-owned target remains non-rollbackable');
assert_same(0, $external_target->save_count, 'externally-owned target is not rewritten');

$rollback = invoke_private($manager, 'apply_bulk_target', array($item, 'job-test', true));
assert_same('rolled_back', $rollback['state'], 'rollback succeeds');
assert_same('100', $product->get_regular_price(), 'rollback restores exact original price');
assert_same('90', $product->get_sale_price(), 'rollback restores original sale price');

$rollback_retry = invoke_private($manager, 'apply_bulk_target', array($item, 'job-test', true));
assert_same('rolled_back', $rollback_retry['state'], 'rollback retry is idempotent');
assert_same(2, $product->save_count, 'rollback retry performs no extra save');

$discounted = new Mock_Product(11, '100', '90');
$GLOBALS['ssm_test_products'][11] = $discounted;
$decrease = $increase;
$decrease['direction'] = 'decrease';
$decrease['store_amount'] = 30;
$discount_snapshot = invoke_private($manager, 'snapshot_bulk_item', array(array('product_id' => 11), $decrease));
assert_same('70', $discount_snapshot['new_regular'], 'decrease target is snapshotted');
assert_same('', $discount_snapshot['new_sale'], 'invalid sale price is snapshotted for clearing');

$conflicted = new Mock_Product(12, '105', '');
$GLOBALS['ssm_test_products'][12] = $conflicted;
$conflict_item = array(
    'id' => 2,
    'job_id' => 1,
    'product_id' => 12,
    'old_regular' => '100',
    'new_regular' => '115',
    'old_sale' => '',
    'new_sale' => '',
);
$GLOBALS['wpdb']->item_states[2] = 'ready';
$conflict = invoke_private($manager, 'apply_bulk_target', array($conflict_item, 'job-test', false));
assert_same('conflict', $conflict['state'], 'external edits are detected as conflicts');
assert_same('105', $conflicted->get_regular_price(), 'conflicting manual edit is not overwritten');
assert_same(0, $conflicted->save_count, 'conflict performs no save');

$legacy_one = invoke_private(
    $manager,
    'parse_legacy_bulk_log_message',
    array('Product #20 price_update: 100 → 115 by user #1 (bulk_price)', '2026-08-11 20:00:00', 'regular')
);
$legacy_two = invoke_private(
    $manager,
    'parse_legacy_bulk_log_message',
    array('Product #20 price_update: 115 → 132.25 by user #1 (bulk_price)', '2026-08-11 20:01:00', 'regular')
);
$legacy_sale = invoke_private(
    $manager,
    'parse_legacy_bulk_log_message',
    array('Product #20 price_update: 90 →  by user #1 (bulk_price)', '2026-08-11 20:01:01', 'regular')
);
$legacy_group = invoke_private($manager, 'group_legacy_bulk_events', array(array($legacy_one, $legacy_two, $legacy_sale)));
assert_same('100', $legacy_group[20]['regular_price']['old'], 'legacy recovery keeps price before first run');
assert_same('132.25', $legacy_group[20]['regular_price']['new'], 'legacy recovery tracks price after repeated runs');
assert_same('sale_price', $legacy_sale['field'], 'empty target log is recognized as cleared sale price');

$broken_event = invoke_private(
    $manager,
    'parse_legacy_bulk_log_message',
    array('Product #20 price_update: 999 → 1200 by user #1 (bulk_price)', '2026-08-11 20:02:00', 'regular')
);
$broken_chain = invoke_private($manager, 'group_legacy_bulk_events', array(array($legacy_one, $broken_event)));
assert_same(true, is_wp_error($broken_chain), 'ambiguous legacy log chains fail closed');

$legacy_product = new Mock_Product(20, '132.25', '');
$GLOBALS['ssm_test_products'][20] = $legacy_product;
$legacy_item = array(
    'id' => 3,
    'job_id' => 2,
    'product_id' => 20,
    'old_regular' => '100',
    'new_regular' => '132.25',
    'old_sale' => '90',
    'new_sale' => '',
);
$GLOBALS['wpdb']->item_states[3] = 'applied';
$legacy_rollback = invoke_private($manager, 'apply_bulk_target', array($legacy_item, 'legacy-job', true));
assert_same('rolled_back', $legacy_rollback['state'], 'legacy recovery reverses repeated percentage applications');
assert_same('100', $legacy_product->get_regular_price(), 'legacy recovery restores pre-operation regular price');
assert_same('90', $legacy_product->get_sale_price(), 'legacy recovery restores sale price cleared by operation');

echo "All bulk idempotency tests passed.\n";
