<?php
/** Exercise the actual storage hooks and native save method with WordPress boundaries. */
define( 'ABSPATH', __DIR__ . '/' );
$storage = array(); $storage_hooks = array(); $storage_writes = array(); $storage_flushes = array(); $storage_can_edit = true; $storage_checks = 0;
function __( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['storage_hooks'][ $hook ][] = array( $callback, $accepted ); }
function storage_filter( $hook, ...$args ) { foreach ( $GLOBALS['storage_hooks'][ $hook ] ?? array() as $filter ) { $args[0] = call_user_func_array( $filter[0], array_slice( $args, 0, $filter[1] ) ); } return $args[0]; }
function get_post_type( $post_id ) { return 20 === (int) $post_id ? 'movie' : 'review'; }
function get_post_meta( $post_id, $key, $single = true ) { return $GLOBALS['storage'][ $post_id ][ $key ] ?? ''; }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function update_post_meta( $post_id, $key, $value ) { $value = wp_unslash( $value ); $GLOBALS['storage_writes'][] = array( $post_id, $key, $value ); if ( ( $GLOBALS['storage_fail_key'] ?? '' ) === $key ) { return false; } $GLOBALS['storage'][ $post_id ][ $key ] = $value; return true; }
function delete_post_meta( $post_id, $key ) { $exists = array_key_exists( $key, $GLOBALS['storage'][ $post_id ] ?? array() ); unset( $GLOBALS['storage'][ $post_id ][ $key ] ); return $exists; }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ) : ''; }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function esc_url_raw( $value ) { return is_string( $value ) && preg_match( '#^https?://#', $value ) ? $value : ''; }
function wp_get_attachment_image_url( $id, $size ) { return 101 === (int) $id ? 'https://example.test/selected-art.jpg' : false; }
function wp_attachment_is_image( $id ) { return 101 === (int) $id; }
function current_user_can( $cap, $post_id ) { return $GLOBALS['storage_can_edit']; }
function wp_verify_nonce( $nonce, $action ) { return 'valid:' . $action === $nonce; }
function acf_flush_value_cache( $post_id, $name ) { $GLOBALS['storage_flushes'][] = array( $post_id, $name ); }
function acf_get_data( $name ) { return 'acf_saving_submitted_values' === $name && ! empty( $GLOBALS['storage_saving_submitted'] ); }
function storage_assert( $ok, $message ) { ++$GLOBALS['storage_checks']; if ( ! $ok ) { throw new RuntimeException( $message ); } }
require dirname( __DIR__ ) . '/includes/class-lunara-review-metadata.php';
Lunara_Review_Metadata::init();

$fields = array();
foreach ( Lunara_Review_Metadata::fields() as $key => $definition ) {
    $raw = array( 'key' => $key, 'name' => $definition['name'], '_name' => $definition['name'], 'type' => $definition['source_type'], 'parent' => 103886, 'return_format' => 'array' );
    $field = storage_filter( 'acf/load_field/key=' . $key, $raw );
    $fields[ $key ] = $field;
    storage_assert( $key === $field['key'] && $definition['meta'] === $field['name'] && $field['name'] === $field['_name'], 'A confirmed field keeps its stable key and uses canonical value storage: ' . $key );
    storage_assert( $definition['type'] === $field['type'] && 103886 === $field['parent'], 'Runtime field shape matches scalar storage without changing group ownership: ' . $key );
}
$year_field = $fields['field_lunara_year'];
$wrong = $year_field; $wrong['parent'] = 'unrelated_group';
storage_assert( $wrong === Lunara_Review_Metadata::load_field( $wrong ), 'The same key in an unexpected group is not remapped.' );
$wrong = $year_field; $wrong['name'] = 'unrelated_name';
storage_assert( $wrong === Lunara_Review_Metadata::load_field( $wrong ), 'A mismatched field name is not remapped.' );
storage_assert( 15 === count( $fields ), 'Only the fifteen confirmed scalar fields are mapped.' );

$storage[7] = array( '_lunara_year' => 'field_lunara_year', 'lunara_year' => '2026', '_lunara_where' => 'field_lunara_where', 'lunara_where' => array( 'title' => 'Theatrical', 'url' => 'https://example.test/showtimes', 'target' => '_blank' ), '_lunara_review_hero_banner' => 'http://field_lunara_review_hero_banner', 'lunara_review_hero_banner' => '101' );
$before = $storage; $before_writes = $storage_writes;
storage_assert( '2026' === Lunara_Review_Metadata::read( 7, '_lunara_year' ), 'An exact old reference reads its actual saved scalar.' );
storage_assert( 'Theatrical | https://example.test/showtimes' === Lunara_Review_Metadata::read( 7, '_lunara_where' ), 'An old ACF link array preserves its label and URL as native viewing text.' );
storage_assert( 'https://example.test/selected-art.jpg' === Lunara_Review_Metadata::read( 7, '_lunara_review_hero_banner' ), 'The known sanitized image-reference wrapper reads the old image attachment as a URL.' );
storage_assert( $before === $storage && $before_writes === $storage_writes, 'Metadata reads and conversions never write.' );
storage_assert( 'field_lunara_year' === storage_filter( 'acf/pre_load_reference', null, 'lunara_year', 7 ), 'The old ACF selector resolves by the verified stable key.' );
storage_assert( 'field_lunara_year' === storage_filter( 'acf/pre_load_reference', null, '_lunara_year', 7 ), 'The canonical ACF selector resolves by the same stable key.' );
$old_selector_field = $year_field; $old_selector_field['name'] = 'lunara_year';
storage_assert( '2026' === storage_filter( 'acf/load_value/key=field_lunara_year', 'stale old value', 7, $old_selector_field ), 'ACF old-name lookup cannot bypass canonical read routing after resetting field.name.' );
storage_assert( null === storage_filter( 'acf/pre_load_reference', null, 'lunara_year', 20 ) && null === storage_filter( 'acf/pre_load_reference', null, 'lunara_year', 'option_7' ), 'Aliases do not apply to Movies or non-post ACF object identifiers.' );

storage_assert( true === storage_filter( 'acf/pre_update_value', null, '2027', 7, $old_selector_field ), 'A programmatic old-name update succeeds through the canonical writer.' );
storage_assert( '2027' === $storage[7]['_lunara_year'] && 'field_lunara_year' === $storage[7]['__lunara_year'] && '2026' === $storage[7]['lunara_year'], 'The actual value and ACF reference occupy separate keys; the old saved value is untouched.' );
storage_assert( '2027' === storage_filter( 'acf/load_value/key=field_lunara_year', '2026', 7, $old_selector_field ), 'An old-name read after update returns the current canonical value rather than stale storage.' );
storage_assert( in_array( array( 7, 'lunara_year' ), $storage_flushes, true ) && in_array( array( 7, '_lunara_year' ), $storage_flushes, true ), 'Intercepted writes flush both ACF selector cache names.' );
storage_assert( true === storage_filter( 'acf/pre_update_value', null, '0', 7, $fields['field_lunara_score'] ) && '0' === $storage[7]['_lunara_score'], 'Numeric zero remains a real saved value.' );
storage_assert( true === storage_filter( 'acf/pre_update_value', null, array( 'title' => 'Rent', 'url' => 'https://example.test/rent' ), 7, $fields['field_lunara_where'] ), 'A stable-key update accepts the old ACF link shape.' );
storage_assert( 'Rent | https://example.test/rent' === $storage[7]['_lunara_where'], 'The old link shape is converted before scalar persistence.' );
storage_assert( true === storage_filter( 'acf/pre_update_value', null, 101, 7, $fields['field_lunara_review_hero_banner'] ), 'A stable-key image update accepts a verified image attachment.' );
storage_assert( 'url' === $fields['field_lunara_review_hero_banner']['type'] && 'https://example.test/selected-art.jpg' === $storage[7]['_lunara_review_hero_banner'], 'The ACF input and native image contract both reopen as a URL.' );
$before = $storage;
storage_assert( false === storage_filter( 'acf/pre_update_value', null, array( 'nested' => array( 'bad' ) ), 7, $fields['field_lunara_where'] ) && $before === $storage, 'A malformed link value is rejected without replacing a valid saved value.' );
storage_assert( false === storage_filter( 'acf/pre_update_value', null, 999, 7, $fields['field_lunara_review_hero_banner'] ) && $before === $storage, 'A missing or non-image attachment does not erase selected artwork.' );
storage_assert( false === storage_filter( 'acf/pre_update_value', null, 'field_lunara_year', 7, $year_field ) && $before === $storage, 'A technical reference cannot be persisted as its own value.' );
storage_assert( null === storage_filter( 'acf/pre_update_value', null, '2028', 20, $year_field ) && $before === $storage, 'Other post types retain ACF default behavior.' );
$unknown = $year_field; $unknown['key'] = 'field_unrelated';
storage_assert( null === storage_filter( 'acf/pre_update_value', null, 'value', 7, $unknown ), 'Unrelated ACF fields retain their original writer.' );

$_POST = array( 'post_ID' => 7, 'lunara_debrief_nonce' => 'valid:lunara_debrief_nonce', 'lunara_year' => '2030' );
storage_assert( true === storage_filter( 'acf/pre_update_value', null, '', 7, $year_field ) && '2030' === $storage[7]['_lunara_year'], 'A submitted native year wins over a stale empty duplicate ACF input.' );
$_POST['lunara_year'] = '';
storage_assert( true === storage_filter( 'acf/pre_update_value', null, '2029', 7, $year_field ) && '' === $storage[7]['_lunara_year'], 'An explicit native clear wins over a stale nonempty ACF duplicate.' );
$_POST['lunara_debrief_nonce'] = 'invalid';
storage_assert( true === storage_filter( 'acf/pre_update_value', null, '2031', 7, $year_field ) && '2031' === $storage[7]['_lunara_year'], 'Invalid native nonce cannot override an authorized ACF save.' );
$_POST = array( 'post_ID' => 7, 'lunara_review_image_studio_nonce' => 'valid:lunara_review_image_studio_save', 'lunara_review_image_mode' => array( 'hero_banner' => 'custom' ), 'lunara_review_image_id' => array( 'hero_banner' => '101' ), 'lunara_review_image_caption' => array( 'hero_banner' => 'Native caption' ) );
$storage[7]['_lunara_review_image_hero_banner_id'] = 101;
storage_assert( true === storage_filter( 'acf/pre_update_value', null, '', 7, $fields['field_lunara_review_hero_banner'] ) && 'https://example.test/selected-art.jpg' === $storage[7]['_lunara_review_hero_banner'], 'Image Studio selected attachment wins over stale ACF image input.' );
storage_assert( true === storage_filter( 'acf/pre_update_value', null, '', 7, $fields['field_lunara_review_hero_banner_caption'] ) && 'Native caption' === $storage[7]['_lunara_review_hero_banner_caption'], 'Image Studio caption wins over its stale ACF duplicate.' );
storage_assert( 101 === $storage[7]['_lunara_review_image_hero_banner_id'], 'The storage bridge preserves the Image Studio selection and mode contract.' );
$_POST = array();
storage_assert( true === storage_filter( 'acf/pre_delete_metadata', null, 7, 'lunara_year', false ), 'An explicit old-name delete removes the canonical value.' );
storage_assert( 'field_lunara_year' === $storage[7]['__lunara_year'], 'Deleting an old-name value does not mistake the canonical value key for an ACF reference.' );
storage_assert( true === storage_filter( 'acf/pre_delete_metadata', null, 7, 'lunara_year', true ) && ! isset( $storage[7]['__lunara_year'] ), 'The corresponding delete removes the separate canonical reference.' );
storage_assert( '' === storage_filter( 'acf/load_value/key=field_lunara_year', '2026', 7, $old_selector_field ), 'Explicit delete cannot resurrect an old unprefixed value.' );

// Execute the real native save method, not a rewritten save loop.
$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/lunara-core.php' ) );
foreach ( $tokens as $i => $token ) {
    if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
    $j = $i + 1; while ( isset( $tokens[$j] ) && ( ! is_array( $tokens[$j] ) || T_STRING !== $tokens[$j][0] ) ) { ++$j; }
    if ( 'save_debrief_meta' !== ( $tokens[$j][1] ?? '' ) ) { continue; }
    $code = ''; $depth = 0; $opened = false;
    for ( $k = $i; $k < count( $tokens ); ++$k ) {
        $part = is_array( $tokens[$k] ) ? $tokens[$k][1] : $tokens[$k];
        $code .= $k === $j ? 'storage_native_save' : $part;
        if ( ! is_array( $tokens[$k] ) && '{' === $part ) { ++$depth; $opened = true; }
        elseif ( ! is_array( $tokens[$k] ) && '}' === $part && 0 === --$depth && $opened ) { eval( $code ); break 2; }
    }
}
$_POST = array( 'post_ID' => 7, 'lunara_debrief_nonce' => 'valid:lunara_debrief_nonce', 'lunara_year' => '' );
storage_native_save( 7 );
$_POST = array();
storage_assert( '' === $storage[7]['_lunara_year'] && 'field_lunara_year' === $storage[7]['__lunara_year'], 'The real native clear records a separate ACF reference marker.' );
storage_assert( ! Lunara_Review_Metadata::is_empty( 7, '_lunara_year' ), 'A later unrelated importer sees the native clear as intentional rather than a vacancy.' );
$storage[8] = array( '_lunara_year' => 'field_lunara_year', 'lunara_year' => '' );
storage_assert( Lunara_Review_Metadata::is_empty( 8, '_lunara_year' ), 'An old empty ACF reference remains a genuine import vacancy.' );
$storage[8]['lunara_year'] = array( 'malformed' );
storage_assert( ! Lunara_Review_Metadata::is_empty( 8, '_lunara_year' ), 'Malformed existing values are preserved for inspection instead of silently overwritten.' );
$storage[7]['_lunara_review_hero_banner_caption'] = 'A field_lunara_year label in the scene.';
storage_assert( 'A field_lunara_year label in the scene.' === Lunara_Review_Metadata::read( 7, '_lunara_review_hero_banner_caption' ), 'Ordinary field-like caption prose is unchanged.' );
$caption_text = "Jessie's café — C:\\films\\stills";
$storage_saving_submitted = true;
storage_assert( true === storage_filter( 'acf/pre_update_value', null, wp_slash( $caption_text ), 7, $fields['field_lunara_review_hero_banner_caption'] ) && $caption_text === $storage[7]['_lunara_review_hero_banner_caption'], 'ACF form values cross the WordPress slashing boundary once, preserving Unicode, quotes and literal backslashes.' );
$storage_saving_submitted = false;
storage_assert( true === storage_filter( 'acf/pre_update_value', null, $caption_text, 7, $fields['field_lunara_review_hero_banner_caption'] ) && $caption_text === $storage[7]['_lunara_review_hero_banner_caption'], 'Programmatic ACF values preserve the same text without request unslashing.' );

require dirname( __DIR__ ) . '/includes/class-lunara-review-image-studio.php';
$_POST = array( 'post_ID' => 7, 'lunara_review_image_studio_nonce' => 'valid:lunara_review_image_studio_save', 'lunara_review_image_mode' => array( 'hero_banner' => 'custom' ), 'lunara_review_image_caption' => array( 'hero_banner' => '' ) );
Lunara_Review_Image_Studio::save( 7, (object) array( 'post_type' => 'review' ), true );
$_POST = array();
storage_assert( '' === $storage[7]['_lunara_review_hero_banner_caption'] && 'field_lunara_review_hero_banner_caption' === $storage[7]['__lunara_review_hero_banner_caption'], 'The actual Image Studio save records an explicit caption clear separately from its reference.' );
storage_assert( 101 === $storage[7]['_lunara_review_image_hero_banner_id'] && 'custom' === $storage[7]['_lunara_review_image_mode_hero_banner'], 'Clearing a caption preserves the selected custom hero attachment and mode.' );
echo "Review metadata storage: {$storage_checks} checks passed.\n";
