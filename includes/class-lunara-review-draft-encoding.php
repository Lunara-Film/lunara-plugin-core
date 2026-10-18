<?php
/** Byte decoding shared by local Review drafts and packaged HTML exports. */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lunara_Review_Draft_Encoding {

    /**
     * Decode a bounded input once, without normalizing Unicode graphemes.
     *
     * BOMs win; valid UTF-8 beats stale export charset declarations. Otherwise
     * use a declared legacy encoding, or repair Windows-1252 bytes while leaving
     * valid UTF-8 runs intact (an old paste can contain both).
     *
     * @return string|false False means a known encoding cannot decode safely.
     */
    public static function decode( $bytes ) {
        if ( 0 === strncmp( $bytes, "\xEF\xBB\xBF", 3 ) ) {
            $utf8 = substr( $bytes, 3 );
            return 1 === preg_match( '//u', $utf8 ) ? $utf8 : false;
        }

        if ( 0 === strncmp( $bytes, "\xFF\xFE", 2 ) || 0 === strncmp( $bytes, "\xFE\xFF", 2 ) ) {
            return self::decode_utf16( substr( $bytes, 2 ), "\xFF" === $bytes[0] );
        }

        if ( '' === $bytes || 1 === preg_match( '//u', $bytes ) ) {
            return $bytes;
        }

        $charset = self::declared_charset( substr( $bytes, 0, 4096 ) );
        if ( false === $charset ) {
            return false;
        }
        if ( '' !== $charset ) {
            // HTML treats these labels as Windows-1252. Repairing only invalid
            // bytes also protects UTF-8 already pasted into a legacy document.
            $windows_labels = array( 'windows-1252', 'cp1252', 'x-cp1252', 'iso-8859-1', 'iso_8859-1', 'iso_8859-1:1987', 'iso8859-1', 'iso88591', 'latin1', 'l1', 'ascii', 'us-ascii', 'cp819', 'ibm819', 'csisolatin1' );
            $utf8_labels = array( 'utf-8', 'utf8', 'unicode-1-1-utf-8' );
            if ( ! in_array( $charset, $windows_labels, true ) && ! in_array( $charset, $utf8_labels, true ) ) {
                // HTML legacy aliases use the browser-compatible Windows
                // supersets, including smart punctuation in Turkish exports.
                $aliases = array(
                    'iso-8859-9' => 'Windows-1254', 'iso8859-9' => 'Windows-1254', 'iso88599' => 'Windows-1254',
                    'iso_8859-9' => 'Windows-1254', 'iso_8859-9:1989' => 'Windows-1254',
                    'latin5' => 'Windows-1254', 'l5' => 'Windows-1254', 'csisolatin5' => 'Windows-1254',
                    'iso-8859-11' => 'Windows-874', 'tis-620' => 'Windows-874',
                    'gb2312' => 'GBK', 'gb_2312-80' => 'GBK', 'chinese' => 'GBK',
                    'shift_jis' => 'CP932', 'shift-jis' => 'CP932', 'sjis' => 'CP932', 'x-sjis' => 'CP932',
                );
                $charset = isset( $aliases[ $charset ] ) ? $aliases[ $charset ] : $charset;
                $decoded = false;
                if ( function_exists( 'iconv' ) ) {
                    if ( false !== @iconv( $charset, 'UTF-8', '' ) ) {
                        $decoded = @iconv( $charset, 'UTF-8', $bytes );
                        if ( false === $decoded ) {
                            return false;
                        }
                    }
                } elseif ( function_exists( 'mb_check_encoding' ) && function_exists( 'mb_convert_encoding' ) ) {
                    try {
                        if ( ! mb_check_encoding( $bytes, $charset ) ) {
                            return false;
                        }
                        $decoded = mb_convert_encoding( $bytes, 'UTF-8', $charset );
                    } catch ( ValueError $error ) {
                        // An unknown export label follows the normal fallback.
                    }
                } else {
                    // UTF-8, UTF-16 BOMs and Windows-1252 need no extension.
                    // Other explicit encodings need a decoder, never guessing.
                    return false;
                }
                if ( is_string( $decoded ) && 1 === preg_match( '//u', $decoded ) ) {
                    return $decoded;
                }
            }
        }

        $decoded = preg_replace_callback(
            '/[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|([\x80-\xFF])/',
            static function ( $match ) {
                if ( ! isset( $match[1] ) ) {
                    return $match[0];
                }
                static $windows = array(
                    0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E,
                    0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6,
                    0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
                    0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C,
                    0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
                    0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
                    0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
                );
                $byte = ord( $match[1] );
                return self::utf8_character( isset( $windows[ $byte ] ) ? $windows[ $byte ] : $byte );
            },
            $bytes
        );
        return is_string( $decoded ) ? $decoded : false;
    }

    /** Remove actual encoding declarations without changing editorial comments. */
    public static function strip_html_encoding( $html ) {
        $stripped = preg_replace_callback(
            self::html_token_pattern(),
            static function ( $match ) {
                if ( 0 === strncasecmp( $match[0], '<?xml', 5 ) || null !== self::meta_charset( $match[0] ) ) {
                    return '';
                }
                return $match[0];
            },
            $html
        );
        return is_string( $stripped ) ? $stripped : false;
    }

    /** Skip comments and raw text so quoted examples never declare an encoding. */
    private static function html_token_pattern() {
        return '~<!--(?:[^-]++|-(?!->))*+(?:-->|$)|<(script|style|textarea|title)\b[^>]*>.*?(?:</\1\s*>|$)|<\?xml\b[^>]*\?>|<[!/]?[a-z][a-z0-9:-]*(?=[\s/>])(?:[^>"\x27]++|"[^"]*"|\x27[^\x27]*\x27)*+>~is';
    }

    private static function declared_charset( $html ) {
        if ( false === preg_match_all( self::html_token_pattern(), $html, $tokens ) ) {
            return false;
        }
        foreach ( $tokens[0] as $tag ) {
            $charset = self::meta_charset( $tag );
            if ( null !== $charset && '' !== $charset ) {
                return $charset;
            }
        }
        return '';
    }

    private static function meta_charset( $tag ) {
        if ( ! preg_match( '~^<meta(?=[\s/>])~i', $tag ) ) {
            return null;
        }
        preg_match_all( '/([^\s=\/>]+)(?:\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>]+)))?/', substr( $tag, 5, -1 ), $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL );
        $attributes = array();
        foreach ( $matches as $attribute ) {
            $name = strtolower( $attribute[1] );
            if ( ! array_key_exists( $name, $attributes ) ) {
                $value = $attribute[2] ?? $attribute[3] ?? $attribute[4] ?? '';
                $attributes[ $name ] = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            }
        }
        if ( isset( $attributes['charset'] ) ) {
            return strtolower( trim( $attributes['charset'] ) );
        }
        if ( isset( $attributes['http-equiv'], $attributes['content'] ) && 'content-type' === strtolower( trim( $attributes['http-equiv'] ) )
            && preg_match( '/(?:^|;)\s*charset\s*=\s*["\x27]?\s*([a-z0-9._:-]+)/i', $attributes['content'], $charset ) ) {
            return strtolower( $charset[1] );
        }
        return null;
    }

    /** UTF-16 BOM exports must work even when mbstring/iconv are unavailable. */
    private static function decode_utf16( $bytes, $little_endian ) {
        $length = strlen( $bytes );
        if ( 0 !== $length % 2 ) {
            return false;
        }
        $output = '';
        for ( $offset = 0; $offset < $length; $offset += 2 ) {
            $unit = self::utf16_unit( $bytes, $offset, $little_endian );
            if ( $unit >= 0xD800 && $unit <= 0xDBFF ) {
                if ( $offset + 3 >= $length ) {
                    return false;
                }
                $low = self::utf16_unit( $bytes, $offset + 2, $little_endian );
                if ( $low < 0xDC00 || $low > 0xDFFF ) {
                    return false;
                }
                $unit = 0x10000 + ( ( $unit - 0xD800 ) << 10 ) + $low - 0xDC00;
                $offset += 2;
            } elseif ( $unit >= 0xDC00 && $unit <= 0xDFFF ) {
                return false;
            }
            $output .= self::utf8_character( $unit );
        }
        return $output;
    }

    private static function utf16_unit( $bytes, $offset, $little_endian ) {
        $first  = ord( $bytes[ $offset ] );
        $second = ord( $bytes[ $offset + 1 ] );
        return $little_endian ? $first | ( $second << 8 ) : ( $first << 8 ) | $second;
    }

    private static function utf8_character( $point ) {
        if ( $point < 0x80 ) {
            return chr( $point );
        }
        if ( $point < 0x800 ) {
            return chr( 0xC0 | ( $point >> 6 ) ) . chr( 0x80 | ( $point & 0x3F ) );
        }
        if ( $point < 0x10000 ) {
            return chr( 0xE0 | ( $point >> 12 ) ) . chr( 0x80 | ( ( $point >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $point & 0x3F ) );
        }
        return chr( 0xF0 | ( $point >> 18 ) ) . chr( 0x80 | ( ( $point >> 12 ) & 0x3F ) ) . chr( 0x80 | ( ( $point >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $point & 0x3F ) );
    }
}
