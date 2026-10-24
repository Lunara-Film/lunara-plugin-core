<?php
/**
 * Brand name: what a person reads is "Lunara Film", never "Lunara" alone.
 *
 * Dalton's rule, 2026-10-23: "it's never just LUNARA. It's always LUNARA
 * FILM." This reads the plugin's shipped source (PHP, JavaScript and any
 * block.json; not tests, docs or tools that are not deployed) and fails when
 * a string a person can see carries a bare "Lunara" or "LUNARA": gettext
 * calls and every other string literal, PHP template text, block.json titles
 * and descriptions, and the plugin header's Plugin Name and Description.
 * Identifiers (lunara_x, Lunara_Class, Lunara\Ns, Lunara-Slug), regular
 * expressions and comments are not copy and are not read.
 *
 * A string that must keep the bare word because code stores or matches it
 * belongs in $brand_lint_keep below, with the reason.
 */

// @brand-lint-config-start
$brand_lint_main = 'lunara-core.php';
$brand_lint_keep = array(
	// Draft-format markers. The Review importer finds these exact words in a
	// pasted or uploaded draft (class-lunara-review-draft-parser.php), so the
	// help text names them as the document spells them.
	'LUNARA DEBRIEF',
	'Lunara metadata section',
);
// @brand-lint-config-end

$brand_lint_root = dirname( __DIR__ );
$brand_lint_skip = array( '.git', '.github', 'node_modules', 'vendor', 'tests', 'docs' );

/**
 * Return the first bare brand word in a piece of copy, or null.
 *
 * @param string            $text Decoded copy.
 * @param array<int,string> $keep Exact phrases allowed to keep the bare word.
 * @return string|null
 */
function brand_lint_bare( $text, array $keep ) {
	foreach ( $keep as $phrase ) {
		$text = str_replace( $phrase, ' ', $text );
	}
	$pattern = '/(?<![A-Za-z0-9_\\\\$@.\/])(Lunara|LUNARA)'
		. '(?![A-Za-z0-9_\\\\])'          // lunara_x, LunaraApp, Lunara\Ns, \s in a pattern
		. '(?!-[A-Z])'                     // Lunara-Film, Lunara-Core-Importer slugs
		. '(?!(?:\s|\x{00A0}|&nbsp;|&#160;|-)+(?:Film|FILM)\b)/u';
	if ( preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) {
		preg_match( '/.{0,40}$/us', substr( $text, 0, $match[0][1] ), $before );
		preg_match( '/^.{0,70}/us', substr( $text, $match[0][1] ), $after );
		return trim( preg_replace( '/\s+/', ' ', $before[0] . $after[0] ) );
	}
	return null;
}

/**
 * Decode a PHP string literal token.
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
	return strtr( $raw, array( '\\n' => "\n", '\\t' => "\t", '\\r' => "\r", '\\"' => '"', '\\$' => '$', '\\\\' => '\\' ) );
}

/**
 * Yield the copy-bearing pieces of a PHP file: literals and template text.
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
 * Yield the string literals of a JavaScript file, skipping comments and
 * regular expression literals.
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
					$map     = array( 'n' => "\n", 't' => "\t", 'r' => "\r" );
					if ( 'u' === $escaped && preg_match( '/^[0-9a-fA-F]{4}/', substr( $source, $i + 2, 4 ) ) ) {
						$text .= html_entity_decode( '&#x' . substr( $source, $i + 2, 4 ) . ';', ENT_QUOTES, 'UTF-8' );
						$i    += 6;
						continue;
					}
					$text .= isset( $map[ $escaped ] ) ? $map[ $escaped ] : $escaped;
					$i    += 2;
					continue;
				}
				if ( '`' === $c && '$' === $source[ $i ] && $i + 1 < $length && '{' === $source[ $i + 1 ] ) {
					// Keep template text on both sides of an interpolation apart.
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
 * Collect visible block.json strings.
 *
 * @param mixed  $node JSON node.
 * @param string $key  Key that holds the node.
 * @return array<int,string>
 */
function brand_lint_block_json_strings( $node, $key = '' ) {
	$visible = array( 'title', 'description', 'keywords', 'label' );
	$found   = array();
	if ( is_array( $node ) ) {
		foreach ( $node as $child_key => $child ) {
			$found = array_merge( $found, brand_lint_block_json_strings( $child, is_int( $child_key ) ? $key : (string) $child_key ) );
		}
	} elseif ( is_string( $node ) && in_array( $key, $visible, true ) ) {
		$found[] = $node;
	}
	return $found;
}

/**
 * Lint one plugin tree.
 *
 * @param string            $root Plugin root.
 * @param string            $main Main plugin file, relative to the root.
 * @param array<int,string> $keep Allowed phrases.
 * @param array<int,string> $skip Directory names that do not ship.
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
		$hit = brand_lint_bare( trim( $match[1] ), $keep );
		if ( null !== $hit ) {
			$failures[] = $main . ' header ' . $field . ': "' . $hit . '"';
		}
	}

	$walker = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			function ( $file ) use ( $skip, $root ) {
				if ( $file->isDir() ) {
					$relative = ltrim( substr( $file->getPathname(), strlen( $root ) ), '/' );
					return ! in_array( $relative, $skip, true ) && ! in_array( $file->getFilename(), array( '.git', 'node_modules', 'vendor' ), true );
				}
				return true;
			}
		)
	);
	$paths = array();
	foreach ( $walker as $file ) {
		$paths[] = $file->getPathname();
	}
	sort( $paths );

	foreach ( $paths as $path ) {
		$relative = ltrim( substr( $path, strlen( $root ) ), '/' );
		$name     = basename( $path );
		if ( preg_match( '/\.min\.(js|css)$/', $name ) ) {
			continue;
		}
		$source = (string) file_get_contents( $path );
		if ( preg_match( '/\.php$/', $name ) ) {
			$found = brand_lint_php_pieces( $source );
		} elseif ( preg_match( '/\.(js|mjs)$/', $name ) ) {
			$found = brand_lint_js_pieces( $source );
		} elseif ( 'block.json' === $name ) {
			$found = array();
			$json  = json_decode( $source, true );
			foreach ( brand_lint_block_json_strings( is_array( $json ) ? $json : array() ) as $text ) {
				$found[] = array( 0, $text );
			}
		} else {
			continue;
		}
		$files++;
		foreach ( $found as $piece ) {
			$pieces++;
			$hit = brand_lint_bare( $piece[1], $keep );
			if ( null !== $hit ) {
				$failures[] = $relative . ':' . $piece[0] . ': "' . $hit . '"';
			}
		}
	}

	return array(
		'failures' => $failures,
		'files'    => $files,
		'pieces'   => $pieces,
	);
}

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
$brand_lint_errors = array();
foreach ( $brand_lint_self as $case ) {
	$caught = null !== brand_lint_bare( $case[0], array() );
	if ( $caught !== $case[1] ) {
		$brand_lint_errors[] = 'detector ' . ( $case[1] ? 'missed' : 'flagged' ) . ': ' . json_encode( $case[0] );
	}
}
$brand_lint_js = brand_lint_js_pieces( "// Lunara comment\nvar a = __( 'Lunara planted', 'x' ); /* LUNARA */ var r = /LUNARA x/; var t = `LUNARA \${ x } FILM`;" );
if ( 3 !== count( $brand_lint_js ) || 'Lunara planted' !== $brand_lint_js[0][1] || null === brand_lint_bare( $brand_lint_js[2][1], array() ) ) {
	$brand_lint_errors[] = 'JavaScript reader returned ' . json_encode( $brand_lint_js );
}
$brand_lint_php = brand_lint_php_pieces( "<?php\n// Lunara comment\n\$x = __( 'Lunara planted', 'x' );\n?>\n<h1>LUNARA</h1>\n" );
if ( 3 !== count( $brand_lint_php ) || 'Lunara planted' !== $brand_lint_php[0][1] || false === strpos( $brand_lint_php[2][1], 'LUNARA' ) ) {
	$brand_lint_errors[] = 'PHP reader returned ' . json_encode( $brand_lint_php );
}

$brand_lint_result = brand_lint_tree( $brand_lint_root, $brand_lint_main, $brand_lint_keep, $brand_lint_skip );
foreach ( $brand_lint_result['failures'] as $failure ) {
	$brand_lint_errors[] = 'bare brand name in ' . $failure;
}
foreach ( $brand_lint_keep as $phrase ) {
	if ( ! preg_match( '/(?<![A-Za-z0-9_])(Lunara|LUNARA)(?![A-Za-z0-9_])/', $phrase ) ) {
		$brand_lint_errors[] = 'kept phrase without the brand word: ' . json_encode( $phrase );
	}
}

if ( array() !== $brand_lint_errors ) {
	fwrite( STDERR, "Brand name lint failed: say \"Lunara Film\", never \"Lunara\" alone.\n" );
	foreach ( $brand_lint_errors as $error ) {
		fwrite( STDERR, '  - ' . $error . "\n" );
	}
	exit( 1 );
}

echo 'Brand name lint passed: ' . $brand_lint_result['pieces'] . ' strings in ' . $brand_lint_result['files'] . " shipped files read Lunara Film.\n";
