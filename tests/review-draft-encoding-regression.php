<?php
/** Encoding preservation through the real parser and packaged-document converter. */
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/class-lunara-debrief-contract.php';
require dirname( __DIR__ ) . '/includes/class-lunara-review-draft-parser.php';
require dirname( __DIR__ ) . '/includes/class-lunara-review-draft-document.php';

$checks = 0;
$failures = array();
function encoding_same( $expected, $actual, $label ) {
    global $checks, $failures;
    $checks++;
    if ( $expected !== $actual ) {
        $failures[] = $label;
        fwrite( STDERR, "FAIL: {$label}\n" );
    }
}
function encoding_zip( $html, $name = 'index.html', $deflate = false, $declared_size = null ) {
    $size = null === $declared_size ? strlen( $html ) : $declared_size;
    $crc = crc32( $html );
    $payload = $deflate ? gzdeflate( $html ) : $html;
    $method = $deflate ? 8 : 0;
    $local = "PK\x03\x04" . pack( 'vvvvvVVVvv', 20, 0, $method, 0, 0, $crc, strlen( $payload ), $size, strlen( $name ), 0 ) . $name . $payload;
    $central = "PK\x01\x02" . pack( 'vvvvvvVVVvvvvvVV', 20, 20, 0, $method, 0, 0, $crc, strlen( $payload ), $size, strlen( $name ), 0, 0, 0, 0, 0, 0 ) . $name;
    return base64_encode( $local . $central . "PK\x05\x06" . pack( 'vvvvVVv', 0, 0, 1, 1, strlen( $central ), strlen( $local ), 0 ) );
}
function encoding_legacy( $text ) {
    // Fixed fixture bytes, independent of the runtime decoder or mbstring.
    return strtr( $text, array(
        '’' => "\x92", '“' => "\x93", '”' => "\x94", '—' => "\x97", '…' => "\x85",
        'é' => "\xE9", 'ï' => "\xEF", 'à' => "\xE0", 'è' => "\xE8", 'ç' => "\xE7", 'â' => "\xE2", 'ô' => "\xF4",
    ) );
}
function encoding_utf16( $text, $little_endian ) {
    $format = $little_endian ? 'v' : 'n';
    $units = '';
    foreach ( preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $character ) {
        $bytes = array_values( unpack( 'C*', $character ) );
        $point = $bytes[0] & ( count( $bytes ) > 1 ? ( 0x7F >> count( $bytes ) ) : 0x7F );
        for ( $index = 1; $index < count( $bytes ); $index++ ) {
            $point = ( $point << 6 ) | ( $bytes[ $index ] & 0x3F );
        }
        if ( $point > 0xFFFF ) {
            $point -= 0x10000;
            $units .= pack( $format, 0xD800 + ( $point >> 10 ) ) . pack( $format, 0xDC00 + ( $point & 0x3FF ) );
        } else {
            $units .= pack( $format, $point );
        }
    }
    return ( $little_endian ? "\xFF\xFE" : "\xFE\xFF" ) . $units;
}
function encoding_text( $html ) {
    return html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}
$body = 'It’s “Amélie” — café, naïve, déjà vu…';
$emoji = ' 🎬 🧑🏽‍🎨 é';
$html = '<!-- Amélie (2001) -- tt0211915 -->' . "\n"
    . '<em>' . $body . '</em><p>' . $body . '</p><hr><strong>LUNARA DEBRIEF</strong><ul>'
    . '<li><strong>Score:</strong> 4.5/5</li><li><strong>Where to Watch:</strong> Streaming</li>'
    . '<li><strong>Theme Echo:</strong> Cléo (1962) [tt0055852] -- ' . $body . '</li>'
    . '<li><strong>Counter Program:</strong> Counter Film (1999) [tt2222222] -- ' . $body . '</li>'
    . '<li><strong>Career Context:</strong> Career Film (1991) [tt3333333] -- ' . $body . '</li></ul>'
    . '<!-- LUNARA EXCERPT: ' . $body . ' -->'
    . '<!-- LUNARA METADATA' . "\nDirector: René\nRuntime: 122 min\n-->";
$expected = Lunara_Review_Draft_Parser::parse( $html );
encoding_same( true, $expected['valid'], 'UTF-8 fixture is a complete supported draft' );
encoding_same( 'Amélie', $expected['title'], 'UTF-8 title survives' );
encoding_same( $body, $expected['standfirst'], 'UTF-8 standfirst survives' );
$legacy = encoding_legacy( $html );
encoding_same( $expected, Lunara_Review_Draft_Parser::parse( $legacy ), 'Windows-1252 HTML preserves every parsed field' );
$mixed = str_replace( '<p>', '<p>' . $emoji, $legacy );
$mixedExpected = Lunara_Review_Draft_Parser::parse( str_replace( '<p>', '<p>' . $emoji, $html ) );
encoding_same( $mixedExpected, Lunara_Review_Draft_Parser::parse( $mixed ), 'Legacy bytes do not corrupt valid UTF-8 emoji or combining accents' );
encoding_same(
    Lunara_Review_Draft_Parser::parse_embedded_debrief( $html ),
    Lunara_Review_Draft_Parser::parse_embedded_debrief( $legacy ),
    'Classic Editor Debrief harvesting shares encoding preservation'
);
$zipText = $body . $emoji;
foreach ( array(
    'UTF-8 without a charset' => '<html><body><p>' . $zipText . '</p></body></html>',
    'UTF-8 with a charset' => '<html><head><meta charset="utf-8"></head><body><p>' . $zipText . '</p></body></html>',
    'UTF-8 with a stale ANSI declaration' => '<html><head><meta charset="windows-1252"></head><body><p>' . $zipText . '</p></body></html>',
) as $label => $document ) {
    $converted = Lunara_Review_Draft_Document::convert( encoding_zip( $document ), 'zip' );
    encoding_same( array(), $converted['errors'], $label . ' ZIP converts' );
    encoding_same( $zipText, html_entity_decode( strip_tags( $converted['html'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $label . ' ZIP keeps all characters' );
}
$legacyZip = encoding_legacy( '<html><head><meta charset="windows-1252"></head><body><p>' . $body . '</p></body></html>' );
$converted = Lunara_Review_Draft_Document::convert( encoding_zip( $legacyZip ), 'zip' );
encoding_same( $body, html_entity_decode( strip_tags( $converted['html'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'Declared Windows-1252 ZIP retains original characters' );

$unicode = str_replace( $body, $body . $emoji, $html );
$unicode = str_replace( array( 'René', 'Cléo' ), array( 'René' . $emoji, 'Cléo' . $emoji ), $unicode );
$unicode = str_replace( 'Runtime: 122 min', "Runtime: 122 min\nWriter: Chloé\nCast: Béatrice, François\nCountry: Côte d’Ivoire", $unicode );
$unicodeExpected = Lunara_Review_Draft_Parser::parse( $unicode );
encoding_same( true, $unicodeExpected['valid'], 'Complex Unicode fixture is valid' );
encoding_same( $body . $emoji, $unicodeExpected['standfirst'], 'Joined emoji, modifier and combining accent stay byte-exact' );
encoding_same( $body . $emoji, $unicodeExpected['excerpt'], 'Excerpt preserves complex Unicode' );
encoding_same( array( 'director' => 'René' . $emoji, 'runtime' => '122 min', 'writer' => 'Chloé', 'cast' => 'Béatrice, François', 'country' => 'Côte d’Ivoire' ), $unicodeExpected['metadata'], 'All metadata fields retain their original text' );
encoding_same( 'Cléo' . $emoji, $unicodeExpected['pairings']['theme_echo']['title'], 'Pairing identity preserves complex Unicode' );
encoding_same( array_fill( 0, 3, $body . $emoji ), array_column( $unicodeExpected['pairings'], 'reason' ), 'Every pairing argument retains complex Unicode' );
foreach ( array(
    'UTF-8 BOM' => "\xEF\xBB\xBF" . $unicode,
    'UTF-16 little-endian BOM' => encoding_utf16( $unicode, true ),
    'UTF-16 big-endian BOM' => encoding_utf16( $unicode, false ),
    'Mixed Windows-1252 and UTF-8' => encoding_legacy( $unicode ),
) as $label => $input ) {
    encoding_same( $unicodeExpected, Lunara_Review_Draft_Parser::parse( $input ), $label . ' preserves every parsed field' );
    encoding_same( Lunara_Review_Draft_Parser::parse_embedded_debrief( $unicode ), Lunara_Review_Draft_Parser::parse_embedded_debrief( $input ), $label . ' Classic Editor Debrief harvesting matches' );
}

$document = '<html><head></head><body><p>' . $zipText . '</p></body></html>';
foreach ( array(
    'UTF-8 with a stale http-equiv declaration' => str_replace( '<head>', '<head><meta http-equiv="Content-Type" content="text/html; charset=windows-1252">', $document ),
    'UTF-8 with stale XML encoding' => '<?xml version="1.0" encoding="iso-8859-1"?>' . $document,
    'UTF-8 BOM' => "\xEF\xBB\xBF" . $document,
    'UTF-16 little-endian BOM with stale ANSI declaration' => encoding_utf16( str_replace( '<head>', '<head><meta charset="windows-1252">', $document ), true ),
    'UTF-16 big-endian BOM' => encoding_utf16( $document, false ),
    'Mixed Windows-1252 and UTF-8' => encoding_legacy( $document ),
    'Windows-1252 with a stale UTF-8 declaration' => encoding_legacy( str_replace( '<head>', '<head><meta charset="utf-8">', $document ) ),
) as $label => $input ) {
    $converted = Lunara_Review_Draft_Document::convert( encoding_zip( $input ), 'zip' );
    encoding_same( array(), $converted['errors'], $label . ' ZIP converts' );
    encoding_same( $zipText, encoding_text( $converted['html'] ), $label . ' ZIP keeps all characters' );
}
if ( function_exists( 'iconv' ) || function_exists( 'mb_convert_encoding' ) ) {
    $japanese = '<html><head><meta charset="shift_jis"></head><body><p>' . "\x93\xFA\x96\x7B" . '</p></body></html>';
    $converted = Lunara_Review_Draft_Document::convert( encoding_zip( $japanese ), 'zip' );
    encoding_same( array(), $converted['errors'], 'Declared Shift-JIS converts when its decoder is available' );
    encoding_same( '日本', encoding_text( $converted['html'] ), 'Declared Shift-JIS retains its characters' );
    $invalid = str_replace( "\x93\xFA\x96\x7B", "\x93", $japanese );
    encoding_same( array( 'invalid_document_encoding' ), Lunara_Review_Draft_Document::convert( encoding_zip( $invalid ), 'zip' )['errors'], 'Malformed known legacy bytes fail without replacement characters' );
    $unknown = encoding_legacy( str_replace( '<head>', '<head><meta charset="x-unknown-export-label">', $document ) );
    encoding_same( $zipText, encoding_text( Lunara_Review_Draft_Document::convert( encoding_zip( $unknown ), 'zip' )['html'] ), 'Unknown charset labels follow the Windows-1252 fallback' );
}
$fullConverted = Lunara_Review_Draft_Document::convert( encoding_zip( '<html><head><meta charset="windows-1252"></head><body>' . $unicode . '</body></html>' ), 'zip' );
encoding_same( $unicodeExpected, Lunara_Review_Draft_Parser::parse( $fullConverted['html'] ), 'Packaged draft retains every field through conversion and parsing' );
foreach ( array( '<meta charset="windows-1252">', '<meta charset="">', '<?xml version="1.0" encoding="windows-1252"?>' ) as $declaration ) {
    encoding_same( $unicodeExpected, Lunara_Review_Draft_Parser::parse( $declaration . $unicode ), 'Top-level encoding declaration does not consume the standfirst: ' . $declaration );
    encoding_same( Lunara_Review_Draft_Parser::parse_embedded_debrief( $unicode ), Lunara_Review_Draft_Parser::parse_embedded_debrief( $declaration . $unicode ), 'Classic Editor strips only the top-level encoding declaration: ' . $declaration );
}
$comment = '<!-- LUNARA METADATA' . "\nSource Note: Keep <meta charset=windows-1252> exactly.\n-->";
$commentDocument = '<html><body><p>' . $zipText . '</p>' . $comment . '</body></html>';
$commentConverted = Lunara_Review_Draft_Document::convert( encoding_zip( $commentDocument ), 'zip' );
encoding_same( true, false !== strpos( $commentConverted['html'], $comment ), 'Packaged HTML preserves literal encoding examples inside editorial comments byte-for-byte' );
foreach ( array(
    '<!-- <meta charset="shift_jis"> -->',
    '<meta name="review-settings" data-charset="shift_jis">',
    '<meta name="description" content="charset=shift_jis">',
    '<meta http-equiv="refresh" content="charset=shift_jis">',
    '<meta http-equiv="Content-Type" content="text/html; data-charset=shift_jis">',
    '<div data-note="<meta charset=shift_jis>"></div>',
    '<script>var example = \'<meta charset=shift_jis>\';</script>',
    '<textarea><meta charset=shift_jis></textarea>',
    '<metaphor charset="shift_jis">',
) as $prefix ) {
    $original = $prefix . '<p>cafélie</p>';
    encoding_same( $original, Lunara_Review_Draft_Encoding::decode( encoding_legacy( $original ) ), 'Only real encoding attributes select a decoder: ' . $prefix );
    encoding_same( $original, Lunara_Review_Draft_Encoding::strip_html_encoding( $original ), 'Encoding cleanup preserves unrelated markup and quoted examples: ' . $prefix );
}
foreach ( array( '<meta charset="windows-1252" charset="shift_jis">', '<meta charset="windows&#45;1252">', '<meta http-equiv="Content-Type" content="text/html;charset=windows-1252">' ) as $declaration ) {
    $original = $declaration . '<p>cafélie</p>';
    encoding_same( $original, Lunara_Review_Draft_Encoding::decode( encoding_legacy( $original ) ), 'Actual encoding declaration respects entities and first duplicate attribute: ' . $declaration );
}
if ( function_exists( 'iconv' ) || function_exists( 'mb_convert_encoding' ) ) {
    $turkish = '<html><head><meta charset="iso-8859-9"></head><body><p>' . "It\x92s \xF0" . '</p></body></html>';
    $converted = Lunara_Review_Draft_Document::convert( encoding_zip( $turkish ), 'zip' );
    encoding_same( 'It’s ğ', encoding_text( $converted['html'] ), 'Turkish HTML alias uses browser-compatible Windows punctuation' );
}

$xml = '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'
    . htmlspecialchars( $zipText, ENT_XML1 | ENT_NOQUOTES, 'UTF-8' ) . '</w:t></w:r></w:p></w:body></w:document>';
foreach ( array( 'UTF-8 XML' => $xml, 'UTF-16 XML' => encoding_utf16( str_replace( 'encoding="UTF-8"', 'encoding="UTF-16"', $xml ), true ) ) as $label => $input ) {
    $converted = Lunara_Review_Draft_Document::convert( encoding_zip( $input, 'word/document.xml' ), 'docx' );
    encoding_same( array(), $converted['errors'], $label . ' DOCX converts' );
    encoding_same( $zipText, encoding_text( $converted['html'] ), $label . ' DOCX retains complex Unicode' );
}

foreach ( array( 'parse', 'parse_embedded_debrief' ) as $method ) {
    encoding_same( array( 'input_too_large' ), Lunara_Review_Draft_Parser::$method( str_repeat( "\x97", 400000 ) )['errors'], $method . ' rejects decoded expansion above the input limit' );
    encoding_same( array( 'input_too_large' ), Lunara_Review_Draft_Parser::$method( str_repeat( "\x00", Lunara_Review_Draft_Parser::MAX_INPUT_BYTES + 1 ) )['errors'], $method . ' checks source size before decoding' );
    encoding_same( array( 'unsafe_element' ), Lunara_Review_Draft_Parser::$method( $html . "\0" )['errors'], $method . ' still rejects null bytes' );
    encoding_same( array( 'unsafe_element' ), Lunara_Review_Draft_Parser::$method( encoding_utf16( $html . '<script>alert(1)</script>', true ) )['errors'], $method . ' rejects executable markup after Unicode decoding' );
    encoding_same( array( 'invalid_input_encoding' ), Lunara_Review_Draft_Parser::$method( "\xEF\xBB\xBF\x92" )['errors'], $method . ' rejects a malformed UTF-8 BOM source' );
    encoding_same( array( 'invalid_input_encoding' ), Lunara_Review_Draft_Parser::$method( "\xFF\xFE\x00\xD8" )['errors'], $method . ' rejects an unmatched UTF-16 surrogate' );
}
$converted = Lunara_Review_Draft_Document::convert( encoding_zip( encoding_utf16( '<html><body><p>' . $zipText . '</p><script>alert(1)</script><iframe src="https://invalid.example/"></iframe></body></html>', true ) ), 'zip' );
encoding_same( $zipText, encoding_text( $converted['html'] ), 'UTF-16 package strips executable elements after decoding' );
encoding_same( false, false !== strpos( $converted['html'], '<script' ) || false !== strpos( $converted['html'], '<iframe' ), 'Converted output contains no executable elements' );
encoding_same( array( 'invalid_html_document' ), Lunara_Review_Draft_Document::convert( encoding_zip( $document . "\0" ), 'zip' )['errors'], 'Packaged HTML null bytes are rejected' );
encoding_same( array( 'invalid_document_encoding' ), Lunara_Review_Draft_Document::convert( encoding_zip( "\xFE\xFF\x00" ), 'zip' )['errors'], 'Truncated UTF-16 is rejected before DOM conversion' );
encoding_same( array( 'converted_document_too_large' ), Lunara_Review_Draft_Document::convert( encoding_zip( '<p>' . str_repeat( "\x97", 400000 ) . '</p>' ), 'zip' )['errors'], 'Decoded package expansion remains bounded' );
encoding_same( array( 'converted_document_too_large' ), Lunara_Review_Draft_Document::convert( encoding_zip( '<p>' . str_repeat( '>', 400000 ) . '</p>' ), 'zip' )['errors'], 'Serialized package output remains bounded' );
encoding_same( array( 'missing_html_document' ), Lunara_Review_Draft_Document::convert( encoding_zip( str_repeat( 'x', Lunara_Review_Draft_Document::MAX_OUTPUT_BYTES + 1 ), 'index.html', true, 1024 ), 'zip' )['errors'], 'Compressed content cannot evade the unpacked size limit' );
encoding_same( array( 'malformed_markup' ), Lunara_Review_Draft_Parser::parse( $html . '<meta title="' . str_repeat( 'x', 400000 ) )['errors'], 'A large unterminated tag remains a rejected draft without losing its source' );
fwrite( STDOUT, sprintf( "Review encoding: %d checks, %d failures.\n", $checks, count( $failures ) ) );
exit( empty( $failures ) ? 0 : 1 );
