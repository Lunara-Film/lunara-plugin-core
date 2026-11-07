<?php
/** Review scalar storage compatibility with the legacy unified ACF group. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Lunara_Review_Metadata {
    /** Confirmed field keys from the existing, disabled unified Review group. */
    public static function fields() {
        $fields = array(
            'lunara_score' => array( 'number', 'number' ),
            'lunara_year' => array( 'number', 'number' ),
            'lunara_imdb_title_id' => array( 'text', 'text' ),
            'lunara_where' => array( 'link', 'textarea' ),
            'lunara_theme_echo' => array( 'text', 'text' ),
            'lunara_counter_program' => array( 'text', 'text' ),
            'lunara_career_context' => array( 'text', 'text' ),
        );
        foreach ( array( 'hero_banner', 'context_shot', 'visual_evidence', 'thematic_echo' ) as $slot ) {
            $fields[ 'lunara_review_' . $slot ] = array( 'image', 'url' );
            $fields[ 'lunara_review_' . $slot . '_caption' ] = array( 'text', 'text' );
        }
        $definitions = array();
        foreach ( $fields as $name => $types ) {
            $definitions[ 'field_' . $name ] = array( 'name' => $name, 'meta' => '_' . $name, 'source_type' => $types[0], 'type' => $types[1] );
        }
        return $definitions;
    }

    public static function init() {
        foreach ( self::fields() as $key => $definition ) {
            add_filter( 'acf/load_field/key=' . $key, array( __CLASS__, 'load_field' ) );
            add_filter( 'acf/load_value/key=' . $key, array( __CLASS__, 'load_value' ), 20, 3 );
        }
        add_filter( 'acf/pre_load_reference', array( __CLASS__, 'load_reference' ), 20, 3 );
        add_filter( 'acf/pre_update_value', array( __CLASS__, 'update_value' ), 20, 4 );
        add_filter( 'acf/pre_delete_metadata', array( __CLASS__, 'delete_old_name' ), 20, 4 );
    }

    private static function definition( $field ) {
        $definitions = self::fields();
        $key = isset( $field['key'] ) ? $field['key'] : '';
        if ( ! isset( $definitions[ $key ] ) ) { return null; }
        $definition = $definitions[ $key ];
        if ( ! in_array( $field['name'] ?? '', array( $definition['name'], $definition['meta'] ), true )
            || ! in_array( $field['type'] ?? '', array( $definition['source_type'], $definition['type'] ), true )
            || ! in_array( (string) ( $field['parent'] ?? '' ), array( '103886', 'group_lunara_review_unified' ), true ) ) { return null; }
        return $definition;
    }

    /** Keep keys and database definitions unchanged; move runtime references away from values. */
    public static function load_field( $field ) {
        $definition = self::definition( $field );
        if ( null === $definition ) { return $field; }
        $field['name'] = $definition['meta'];
        $field['_name'] = $definition['meta'];
        $field['type'] = $definition['type'];
        return $field;
    }

    private static function is_review( $post_id ) {
        return is_numeric( $post_id ) && (int) $post_id > 0 && 'review' === get_post_type( (int) $post_id );
    }

    private static function key_for_meta( $key ) {
        foreach ( self::fields() as $field_key => $definition ) {
            if ( $definition['meta'] === $key ) { return $field_key; }
        }
        return '';
    }

    private static function is_old_reference( $value, $field_key, $definition ) {
        if ( ! is_scalar( $value ) ) { return false; }
        $value = trim( (string) $value );
        return $field_key === $value || ( 'url' === $definition['type'] && 'http://' . $field_key === $value );
    }

    /** Convert the two historical ACF shapes without ever casting an array to text. */
    private static function normalize( $value, $definition ) {
        if ( 'url' === $definition['type'] ) {
            if ( is_array( $value ) ) { $value = $value['ID'] ?? $value['id'] ?? null; }
            if ( is_numeric( $value ) ) {
                if ( 0 === (int) $value ) { return ''; }
                if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( (int) $value ) ) { return null; }
                $value = wp_get_attachment_image_url( (int) $value, 'full' );
                if ( ! $value ) { return null; }
            }
            if ( ! is_scalar( $value ) ) { return null; }
            $value = trim( (string) $value );
            if ( '' === $value ) { return ''; }
            $value = esc_url_raw( $value );
            return preg_match( '#^https?://#i', $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ? $value : null;
        }
        if ( 'textarea' === $definition['type'] && is_array( $value ) ) {
            if ( array_diff( array_keys( $value ), array( 'title', 'url', 'target' ) ) ) { return null; }
            foreach ( $value as $part ) { if ( ! is_scalar( $part ) ) { return null; } }
            $title = sanitize_text_field( $value['title'] ?? '' );
            $url = esc_url_raw( $value['url'] ?? '' );
            if ( '' !== $url && ( ! preg_match( '#^https?://#i', $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) ) { return null; }
            return '' !== $title && '' !== $url ? $title . ' | ' . $url : $title . $url;
        }
        return is_scalar( $value ) ? (string) $value : null;
    }

    /** Read legacy values or actual ACF values, preserving explicit canonical clears. */
    public static function read( $post_id, $key ) {
        $value = get_post_meta( $post_id, $key, true );
        $field_key = self::key_for_meta( $key );
        if ( '' === $field_key ) { return is_scalar( $value ) ? (string) $value : ''; }
        $definition = self::fields()[ $field_key ];
        if ( self::is_old_reference( $value, $field_key, $definition ) ) {
            $value = get_post_meta( $post_id, $definition['name'], true );
            if ( self::is_old_reference( $value, $field_key, $definition ) ) { return ''; }
        }
        $normalized = self::normalize( $value, $definition );
        return null === $normalized ? '' : $normalized;
    }

    /** An explicit canonical clear or malformed value is not an import vacancy. */
    public static function is_empty( $post_id, $key ) {
        $raw = get_post_meta( $post_id, $key, true );
        if ( ! is_scalar( $raw ) ) { return false; }
        $field_key = self::key_for_meta( $key );
        if ( '' !== $field_key ) {
            if ( '' === (string) $raw && $field_key === get_post_meta( $post_id, '_' . $key, true ) ) { return false; }
            $definition = self::fields()[ $field_key ];
            if ( self::is_old_reference( $raw, $field_key, $definition ) && ! is_scalar( get_post_meta( $post_id, $definition['name'], true ) ) ) { return false; }
        }
        return '' === trim( self::read( $post_id, $key ) );
    }

    public static function load_value( $value, $post_id, $field ) {
        $definition = self::definition( $field );
        return null !== $definition && self::is_review( $post_id ) ? self::read( (int) $post_id, $definition['meta'] ) : $value;
    }

    /** Alias old selectors, including after the old reference key becomes a real value. */
    public static function load_reference( $reference, $name, $post_id ) {
        if ( null !== $reference || ! self::is_review( $post_id ) ) { return $reference; }
        foreach ( self::fields() as $key => $definition ) {
            if ( $name === $definition['name'] || $name === $definition['meta'] ) { return $key; }
        }
        return $reference;
    }

    /** Explicit native editor submissions own their duplicate scalar controls. */
    private static function native_value( $post_id, $definition, &$submitted ) {
        $submitted = false;
        if ( (int) ( $_POST['post_ID'] ?? 0 ) !== (int) $post_id || ! current_user_can( 'edit_post', $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return null; }
        $name = $definition['name'];
        if ( isset( $_POST['lunara_debrief_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lunara_debrief_nonce'] ) ), 'lunara_debrief_nonce' ) && array_key_exists( $name, $_POST ) ) {
            $submitted = true;
            return wp_unslash( $_POST[ $name ] );
        }
        if ( isset( $_POST['lunara_review_editorial_controls_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lunara_review_editorial_controls_nonce'] ) ), 'lunara_review_editorial_controls_nonce' ) && 'lunara_review_hero_banner' === $name && array_key_exists( $name, $_POST ) ) {
            $submitted = true;
            return wp_unslash( $_POST[ $name ] );
        }
        if ( ! isset( $_POST['lunara_review_image_studio_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lunara_review_image_studio_nonce'] ) ), 'lunara_review_image_studio_save' ) ) { return null; }
        foreach ( array( 'hero_banner', 'context_shot', 'visual_evidence', 'thematic_echo' ) as $slot ) {
            if ( 'lunara_review_' . $slot . '_caption' === $name && isset( $_POST['lunara_review_image_caption'] ) && is_array( $_POST['lunara_review_image_caption'] ) && array_key_exists( $slot, $_POST['lunara_review_image_caption'] ) ) {
                $submitted = true;
                return wp_unslash( $_POST['lunara_review_image_caption'][ $slot ] );
            }
            if ( 'lunara_review_' . $slot === $name && isset( $_POST['lunara_review_image_mode'][ $slot ] ) ) {
                $submitted = true;
                if ( ! empty( $_POST['lunara_review_image_clear'][ $slot ] ) ) { return ''; }
                $attachment_id = $_POST['lunara_review_image_id'][ $slot ] ?? 0;
                return is_scalar( $attachment_id ) && (int) $attachment_id > 0 ? (int) $attachment_id : self::read( $post_id, $definition['meta'] );
            }
        }
        return null;
    }

    private static function flush( $post_id, $definition ) {
        if ( function_exists( 'acf_flush_value_cache' ) ) {
            acf_flush_value_cache( $post_id, $definition['name'] );
            acf_flush_value_cache( $post_id, $definition['meta'] );
        }
    }

    /** Persist canonical data and its separate reference only during an authorized write. */
    public static function write( $post_id, $key, $value ) {
        $field_key = self::key_for_meta( $key );
        if ( '' === $field_key || ! self::is_review( $post_id ) ) { return false; }
        $definition = self::fields()[ $field_key ];
        if ( self::is_old_reference( $value, $field_key, $definition ) ) { return false; }
        $value = self::normalize( $value, $definition );
        if ( null === $value ) { return false; }
        $value = 'textarea' === $definition['type'] ? sanitize_textarea_field( $value ) : ( 'url' === $definition['type'] ? $value : sanitize_text_field( $value ) );
        update_post_meta( $post_id, $key, wp_slash( $value ) );
        if ( (string) get_post_meta( $post_id, $key, true ) !== $value ) { return false; }
        update_post_meta( $post_id, '_' . $key, $field_key );
        self::flush( $post_id, $definition );
        return $field_key === get_post_meta( $post_id, '_' . $key, true );
    }

    /** ACF old-name lookups can overwrite field.name; route every confirmed key explicitly. */
    public static function update_value( $check, $value, $post_id, $field ) {
        if ( null !== $check || ! self::is_review( $post_id ) || ! isset( self::fields()[ $field['key'] ?? '' ] ) ) { return $check; }
        $definition = self::definition( $field );
        if ( null === $definition ) { return false; }
        // ACF form values retain WordPress request slashes; API values do not.
        if ( function_exists( 'acf_get_data' ) && acf_get_data( 'acf_saving_submitted_values' ) ) {
            $value = wp_unslash( $value );
        }
        $native = self::native_value( $post_id, $definition, $submitted );
        if ( $submitted ) { $value = $native; }
        // Null is ACF's explicit delete operation; empty strings remain saved clears.
        if ( null === $value ) {
            delete_post_meta( $post_id, $definition['meta'] );
            delete_post_meta( $post_id, '_' . $definition['meta'] );
            self::flush( $post_id, $definition );
            return '' === get_post_meta( $post_id, $definition['meta'], true );
        }
        return self::write( $post_id, $definition['meta'], $value );
    }

    /** Old-name delete_field must not delete the canonical value as its reference. */
    public static function delete_old_name( $check, $post_id, $name, $hidden ) {
        if ( null !== $check || ! self::is_review( $post_id ) ) { return $check; }
        foreach ( self::fields() as $definition ) {
            if ( $name !== $definition['name'] ) { continue; }
            $key = ( $hidden ? '_' : '' ) . $definition['meta'];
            $result = delete_post_meta( $post_id, $key );
            self::flush( $post_id, $definition );
            return $result;
        }
        return $check;
    }
}
