<?php
/**
 * Brand name: what a person reads is "Lunara Film", never "Lunara" alone.
 *
 * Dalton's rule, 2026-10-23: "it's never just LUNARA. It's always LUNARA
 * FILM." This reads the plugin's shipped source (PHP, JavaScript, and JSON
 * such as block.json or an OpenAPI schema; not tests or docs) and fails when
 * a string a person can see carries a bare "Lunara" or "LUNARA": gettext
 * calls and every other string literal, PHP template text, JSON titles,
 * descriptions and labels, and the plugin header's Plugin Name and
 * Description. Identifiers (lunara_x, Lunara_Class, Lunara\Ns, Lunara-Slug),
 * regular expressions and comments are not copy and are not read.
 *
 * A string that must keep the bare word because code stores or matches it
 * goes in $brand_lint_keep, under the file that holds it, with the reason and
 * the number of times it may appear: 'phrase' => n allows the phrase inside
 * strings of that file, '=text' => n allows a string that is exactly that
 * text. The plugin header never has an exception. A kept entry that no
 * longer appears, or appears more often than allowed, fails the lint.
 *
 * Run: php tests/brand-name-regression.php
 */

// @brand-lint-config-start
$brand_lint_main = 'lunara-core.php';
$brand_lint_keep = array(
	// Draft-format markers. The Review importer matches these exact words in
	// a pasted or uploaded draft (class-lunara-review-draft-parser.php), and
	// its help text names them as the draft spells them. Renaming a marker is
	// a format change for the importer and the drafts, not a copy change.
	'includes/class-lunara-review-draft-import-admin.php' => array( 'LUNARA DEBRIEF' => 4 ),
	'assets/js/lunara-review-draft-import-admin.js'       => array( 'LUNARA DEBRIEF' => 2, 'Lunara metadata section' => 2 ),
);
// @brand-lint-config-end

$brand_lint_root = dirname( __DIR__ );
$brand_lint_skip = array( '.git', '.github', 'node_modules', 'vendor', 'tests', 'docs' );

/**
 * Find every bare brand word in a piece of copy.
 *
 * @param string            $text    Decoded copy.
 * @param array<int,string> $phrases Phrases whose brand word is allowed.
 * @return array<int,array{0:int,1:string}> Newlines before each hit, and an excerpt.
 */
function brand_lint_hits( $text, array $phrases = array() ) {
	foreach ( $phrases as $phrase ) {
		$text = str_replace( $phrase, str_repeat( ' ', strlen( $phrase ) ), $text );
	}
	// Bytes, not /u: a stray non-UTF-8 string must never hide a hit.
	$pattern = '/(?<![A-Za-z0-9_\\\\$@.\/])(Lunara|LUNARA)'
		. '(?![A-Za-z0-9_\\\\])'          // lunara_x, LunaraApp, Lunara\Ns, \s in a pattern
		. '(?!-[A-Z])'                     // Lunara-Film, Lunara-Core-Importer slugs
		. '(?!(?:\s|\xC2\xA0|&nbsp;|&#160;|-)+(?:Film|FILM)\b)/';
	$hits = array();
	if ( preg_match_all( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $matches[0] as $match ) {
			preg_match( '/[^\n]{0,40}$/', substr( $text, 0, $match[1] ), $before );
			preg_match( '/^[^\n]{0,70}/', substr( $text, $match[1] ), $after );
			$excerpt = preg_replace( '/^[\x80-\xBF]+|[\xC0-\xFF][\x80-\xBF]*$/', '', $before[0] . $after[0] );
			$hits[]  = array(
				substr_count( substr( $text, 0, $match[1] ), "\n" ),
				trim( preg_replace( '/\s+/', ' ', (string) $excerpt ) ),
			);
		}
	}
	return $hits;
}

/**
 * Decode a PHP string literal token. An escaped line break reads as a space,
 * so a hit keeps its source line.
 *
 * @param string $raw Token text.
 * @return string
 */
function brand_lint_php_literal( $raw ) {
	if ( strlen( $raw ) >= 2 && "'" === $raw[0] ) {
		return strtr( substr( $raw, 1, -1 ), array( "\\'" => "'", '\\\\' => '\\' ) );
	}
	if ( strlen( $raw ) >= 2 && '"' === $raw[0] ) {
		$raw = substr( $raw, 1, -1 );
	}
	return strtr( $raw, array( '\\n' => ' ', '\\t' => ' ', '\\r' => ' ', '\\"' => '"', '\\$' => '$', '\\\\' => '\\' ) );
}

/**
 * The copy-bearing pieces of a PHP file: string literals and template text.
 *
 * @param string $source PHP source.
 * @return array<int,array{0:int,1:string}> Line and text.
 */
function brand_lint_php_pieces( $source ) {
	$pieces = array();
	foreach ( token_get_all( $source ) as $token ) {
		if ( ! is_array( $token ) ) {
			continue;
		}
		if ( T_CONSTANT_ENCAPSED_STRING === $token[0] || T_ENCAPSED_AND_WHITESPACE === $token[0] ) {
			$pieces[] = array( $token[2], brand_lint_php_literal( $token[1] ) );
		} elseif ( T_INLINE_HTML === $token[0] ) {
			$pieces[] = array( $token[2], $token[1] );
		}
	}
	return $pieces;
}

/**
 * The string literals of a JavaScript file, skipping comments and regular
 * expression literals.
 *
 * @param string $source JavaScript source.
 * @return array<int,array{0:int,1:string}> Line and decoded text.
 */
function brand_lint_js_pieces( $source ) {
	$pieces = array();
	$length = strlen( $source );
	$line   = 1;
	$prev   = '';
	$i      = 0;
	while ( $i < $length ) {
		$c    = $source[ $i ];
		$next = $i + 1 < $length ? $source[ $i + 1 ] : '';
		if ( "\n" === $c ) {
			$line++;
			$i++;
			continue;
		}
		if ( '/' === $c && '/' === $next ) {
			$end = strpos( $source, "\n", $i );
			$i   = false === $end ? $length : $end;
			continue;
		}
		if ( '/' === $c && '*' === $next ) {
			$end   = strpos( $source, '*/', $i + 2 );
			$end   = false === $end ? $length : $end + 2;
			$line += substr_count( $source, "\n", $i, $end - $i );
			$i     = $end;
			continue;
		}
		if ( "'" === $c || '"' === $c || '`' === $c ) {
			$start_line = $line;
			$text       = '';
			$i++;
			while ( $i < $length && $source[ $i ] !== $c ) {
				if ( '\\' === $source[ $i ] && $i + 1 < $length ) {
					$escaped = $source[ $i + 1 ];
					if ( 'u' === $escaped && preg_match( '/^[0-9a-fA-F]{4}/', substr( $source, $i + 2, 4 ) ) ) {
						$text .= html_entity_decode( '&#x' . substr( $source, $i + 2, 4 ) . ';', ENT_QUOTES, 'UTF-8' );
						$i    += 6;
						continue;
					}
					$text .= false !== strpos( 'ntr', $escaped ) ? ' ' : $escaped;
					$i    += 2;
					continue;
				}
				if ( '`' === $c && '$' === $source[ $i ] && $i + 1 < $length && '{' === $source[ $i + 1 ] ) {
					// An interpolation separates the template text on either side.
					$depth = 1;
					$i    += 2;
					while ( $i < $length && $depth > 0 ) {
						if ( '{' === $source[ $i ] ) {
							$depth++;
						} elseif ( '}' === $source[ $i ] ) {
							$depth--;
						} elseif ( "\n" === $source[ $i ] ) {
							$line++;
						}
						$i++;
					}
					$text .= "\0";
					continue;
				}
				if ( "\n" === $source[ $i ] ) {
					$line++;
				}
				$text .= $source[ $i ];
				$i++;
			}
			$i++;
			$pieces[] = array( $start_line, $text );
			$prev     = 'literal';
			continue;
		}
		if ( '/' === $c && ( '' === $prev || false !== strpos( '(,=:[!&|?{};+-*%<>~^', $prev ) ) ) {
			// A regular expression literal: skip it, including its classes.
			$i++;
			$class = false;
			while ( $i < $length && "\n" !== $source[ $i ] ) {
				if ( '\\' === $source[ $i ] ) {
					$i += 2;
					continue;
				}
				if ( '[' === $source[ $i ] ) {
					$class = true;
				} elseif ( ']' === $source[ $i ] ) {
					$class = false;
				} elseif ( '/' === $source[ $i ] && ! $class ) {
					break;
				}
				$i++;
			}
			$i++;
			$prev = 'literal';
			continue;
		}
		if ( ! ctype_space( $c ) ) {
			$prev = ctype_alnum( $c ) || '_' === $c || '$' === $c || ')' === $c || ']' === $c ? 'word' : $c;
		}
		$i++;
	}
	return $pieces;
}

/**
 * The visible strings of a JSON document (block.json, an OpenAPI schema).
 *
 * @param mixed  $node JSON node.
 * @param string $key  Key that holds the node.
 * @return array<int,string>
 */
function brand_lint_json_strings( $node, $key = '' ) {
	$visible = array( 'title', 'description', 'keywords', 'label', 'summary', 'bearerFormat' );
	$found   = array();
	if ( is_array( $node ) ) {
		foreach ( $node as $child_key => $child ) {
			$found = array_merge( $found, brand_lint_json_strings( $child, is_int( $child_key ) ? $key : (string) $child_key ) );
		}
	} elseif ( is_string( $node ) && in_array( $key, $visible, true ) ) {
		$found[] = $node;
	}
	return $found;
}

/**
 * The copy-bearing pieces of one shipped file, or null for other files.
 *
 * @param string $path File path.
 * @return array<int,array{0:int,1:string}>|null
 */
function brand_lint_file_pieces( $path ) {
	$name = basename( $path );
	if ( preg_match( '/\.min\.(js|css)$/', $name ) ) {
		return null;
	}
	if ( preg_match( '/\.php$/', $name ) ) {
		return brand_lint_php_pieces( (string) file_get_contents( $path ) );
	}
	if ( preg_match( '/\.(js|mjs)$/', $name ) ) {
		return brand_lint_js_pieces( (string) file_get_contents( $path ) );
	}
	if ( preg_match( '/\.json$/', $name ) && ! in_array( $name, array( 'package.json', 'package-lock.json', 'composer.json', 'composer.lock' ), true ) ) {
		$source = (string) file_get_contents( $path );
		$json   = json_decode( $source, true );
		$pieces = array();
		foreach ( brand_lint_json_strings( is_array( $json ) ? $json : array() ) as $text ) {
			$at       = strpos( $source, substr( (string) json_encode( $text, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 1, -1 ) );
			$pieces[] = array( false === $at ? 0 : 1 + substr_count( substr( $source, 0, $at ), "\n" ), $text );
		}
		return $pieces;
	}
	return null;
}

/**
 * Lint one plugin tree.
 *
 * @param string                          $root Plugin root.
 * @param string                          $main Main plugin file, relative to the root.
 * @param array<string,array<string,int>> $keep Kept entries by file.
 * @param array<int,string>               $skip Top-level directories that do not ship.
 * @return array{failures:array<int,string>,files:int,pieces:int}
 */
function brand_lint_tree( $root, $main, array $keep, array $skip ) {
	$failures = array();
	$files    = 0;
	$pieces   = 0;

	$header = (string) file_get_contents( $root . '/' . $main, false, null, 0, 8192 );
	foreach ( array( 'Plugin Name', 'Description' ) as $field ) {
		if ( ! preg_match( '/^[ \t\/*#@]*' . $field . ':(.*)$/mi', $header, $match ) ) {
			$failures[] = $main . ': the plugin header has no ' . $field . ' line';
			continue;
		}
		$pieces++;
		foreach ( brand_lint_hits( trim( $match[1] ) ) as $hit ) {
			$failures[] = 'bare name in the ' . $main . ' header ' . $field . ': "' . $hit[1] . '"';
		}
	}

	$walker = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			function ( $file ) use ( $skip, $root ) {
				if ( ! $file->isDir() ) {
					return true;
				}
				$relative = ltrim( substr( $file->getPathname(), strlen( $root ) ), '/' );
				return ! in_array( $relative, $skip, true ) && ! in_array( $file->getFilename(), array( '.git', 'node_modules', 'vendor' ), true );
			}
		)
	);
	$paths = array();
	foreach ( $walker as $file ) {
		$paths[] = $file->getPathname();
	}
	sort( $paths );

	$used = array();
	foreach ( $paths as $path ) {
		$relative = ltrim( substr( $path, strlen( $root ) ), '/' );
		$found    = brand_lint_file_pieces( $path );
		if ( null === $found ) {
			continue;
		}
		$files++;
		$entries = isset( $keep[ $relative ] ) ? $keep[ $relative ] : array();
		foreach ( $found as $piece ) {
			$pieces++;
			$text    = $piece[1];
			$phrases = array();
			$exact   = false;
			foreach ( $entries as $entry => $allowed ) {
				$key = $relative . "\0" . $entry;
				if ( ! isset( $used[ $key ] ) ) {
					$used[ $key ] = 0;
				}
				if ( '=' === substr( $entry, 0, 1 ) ) {
					if ( substr( $entry, 1 ) === $text ) {
						$used[ $key ]++;
						$exact = true;
					}
				} elseif ( false !== strpos( $text, $entry ) ) {
					$used[ $key ] += substr_count( $text, $entry );
					$phrases[]     = $entry;
				}
			}
			if ( $exact ) {
				continue;
			}
			foreach ( brand_lint_hits( $text, $phrases ) as $hit ) {
				$failures[] = 'bare name at ' . $relative . ':' . ( $piece[0] + $hit[0] ) . ': "' . $hit[1] . '"';
			}
		}
	}

	foreach ( $keep as $kept_file => $entries ) {
		foreach ( $entries as $entry => $allowed ) {
			$count = isset( $used[ $kept_file . "\0" . $entry ] ) ? $used[ $kept_file . "\0" . $entry ] : 0;
			if ( 0 === $count ) {
				$failures[] = $kept_file . ': kept entry is gone, remove it from $brand_lint_keep: "' . $entry . '"';
			} elseif ( $count > (int) $allowed ) {
				$failures[] = $kept_file . ': kept entry appears ' . $count . ' times, ' . (int) $allowed . ' allowed: "' . $entry . '"';
			}
		}
	}

	return array(
		'failures' => $failures,
		'files'    => $files,
		'pieces'   => $pieces,
	);
}

$brand_lint_errors = array();

// The detector itself: what it must catch and what it must leave alone.
$brand_lint_self = array(
	array( 'Lunara Core', true ),
	array( 'LUNARA AI Assistant', true ),
	array( 'Settings > LUNARA AI', true ),
	array( "Lunara's editors", true ),
	array( 'the Lunara-owned boxes', true ),
	array( 'Lunara Films', true ),
	array( "Intro\nLUNARA voice", true ),
	array( 'Lunara Film Core', false ),
	array( 'LUNARA FILM AI Assistant', false ),
	array( 'Lunara&nbsp;Film', false ),
	array( 'lunara_core_version', false ),
	array( 'Lunara_Core_Site_Studio_Bridge', false ),
	array( 'Lunara\\Core\\Bridge', false ),
	array( 'https://lunarafilm.com/Lunara-Film', false ),
	array( 'Lunara-Core-Movie-Importer', false ),
	array( '/<!--\\s*LUNARA\\s+METADATA/i', false ),
	array( 'window.LunaraDesk', false ),
);
foreach ( $brand_lint_self as $case ) {
	$caught = array() !== brand_lint_hits( $case[0] );
	if ( $caught !== $case[1] ) {
		$brand_lint_errors[] = 'detector ' . ( $case[1] ? 'missed' : 'flagged' ) . ': ' . json_encode( $case[0] );
	}
}
if ( array() !== brand_lint_hits( 'A LUNARA DEBRIEF section', array( 'LUNARA DEBRIEF' ) ) || 2 !== count( brand_lint_hits( "Lunara one\nand Lunara two" ) ) ) {
	$brand_lint_errors[] = 'kept phrases or repeated hits are not handled';
}
$brand_lint_js = brand_lint_js_pieces( "// Lunara comment\nvar a = __( 'Lunara planted', 'x' ); /* LUNARA */ var r = /LUNARA x/; var t = `LUNARA \${ x } FILM`;" );
if ( 3 !== count( $brand_lint_js ) || 'Lunara planted' !== $brand_lint_js[0][1] || array() === brand_lint_hits( $brand_lint_js[2][1] ) ) {
	$brand_lint_errors[] = 'JavaScript reader returned ' . json_encode( $brand_lint_js );
}
$brand_lint_php = brand_lint_php_pieces( "<?php\n// Lunara comment\n\$x = __( 'Lunara planted', 'x' );\n?>\n<h1>LUNARA</h1>\n" );
if ( 3 !== count( $brand_lint_php ) || 'Lunara planted' !== $brand_lint_php[0][1] || false === strpos( $brand_lint_php[2][1], 'LUNARA' ) ) {
	$brand_lint_errors[] = 'PHP reader returned ' . json_encode( $brand_lint_php );
}

$brand_lint_result = brand_lint_tree( $brand_lint_root, $brand_lint_main, $brand_lint_keep, $brand_lint_skip );
foreach ( $brand_lint_result['failures'] as $failure ) {
	$brand_lint_errors[] = $failure;
}

if ( array() !== $brand_lint_errors ) {
	fwrite( STDERR, "Brand name lint failed: say \"Lunara Film\", never \"Lunara\" alone.\n" );
	foreach ( $brand_lint_errors as $error ) {
		fwrite( STDERR, '  - ' . $error . "\n" );
	}
	exit( 1 );
}

echo 'Brand name lint passed: ' . $brand_lint_result['pieces'] . ' strings in ' . $brand_lint_result['files'] . " shipped files read Lunara Film.\n";
