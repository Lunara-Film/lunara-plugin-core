<?php
/**
 * Dependency-free regression checks for private Debrief suggestions.
 *
 * Run with: php tests/debrief-suggestions-regression.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lunara_suggestion_test'] = array(
    'posts' => array(
        10 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Source Film' ),
        11 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Director Film' ),
        12 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Cast Film' ),
        13 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Studio Only Film' ),
        14 => array( 'post_type' => 'movie', 'post_status' => 'draft', 'title' => 'Draft Director Film' ),
        15 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'No Permalink Film', 'permalink' => '' ),
        16 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Already Selected Film' ),
        17 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Director and Cast Film' ),
        18 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Sparse Source Film' ),
        19 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Duplicate Source A' ),
        20 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Duplicate Source B' ),
        21 => array( 'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Conflicted Candidate' ),
        97 => array( 'post_type' => 'review', 'post_status' => 'draft', 'title' => 'Ambiguous Review' ),
        98 => array( 'post_type' => 'review', 'post_status' => 'draft', 'title' => 'Sparse Review' ),
        99 => array( 'post_type' => 'review', 'post_status' => 'draft', 'title' => 'Source Review' ),
        100 => array( 'post_type' => 'person', 'post_status' => 'publish', 'title' => 'Director One' ),
        200 => array( 'post_type' => 'person', 'post_status' => 'publish', 'title' => 'Performer One' ),
    ),
    'meta' => array(
        10 => array(
            'imdb_title_id' => 'tt0000010',
            'release_year'  => '2024',
            'directors'     => array( 100 ),
            'principal_cast' => array( 200 ),
        ),
        11 => array(
            'imdb_title_id' => 'tt0000011',
            'release_year'  => '2020',
            'directors'     => array( 100 ),
        ),
        12 => array(
            'imdb_title_id' => 'tt0000012',
            'release_year'  => '2019',
            'principal_cast' => array( 200 ),
        ),
        13 => array( 'imdb_title_id' => 'tt0000013', 'release_year' => '2018' ),
        14 => array( 'imdb_title_id' => 'tt0000014', 'release_year' => '2017', 'directors' => array( 100 ) ),
        15 => array( 'imdb_title_id' => 'tt0000015', 'release_year' => '2016', 'directors' => array( 100 ) ),
        16 => array( 'imdb_title_id' => 'tt0000016', 'release_year' => '2015', 'directors' => array( 100 ) ),
        17 => array(
            'imdb_title_id' => 'tt0000017',
            'release_year'  => '2014',
            'directors'     => array( 100 ),
            'principal_cast' => array( 200 ),
        ),
        18 => array( 'imdb_title_id' => 'tt0000018', 'release_year' => '2013' ),
        19 => array( 'imdb_title_id' => 'tt0000019', 'release_year' => '2012', 'directors' => array( 100 ) ),
        20 => array( 'imdb_title_id' => 'tt0000019', 'release_year' => '2011', 'directors' => array( 100 ) ),
        21 => array(
            'imdb_title_id'    => 'tt0000021',
            '_lunara_entity_id' => 'tt0000022',
            'release_year'     => '2010',
            'directors'        => array( 100 ),
        ),
        97 => array( '_lunara_imdb_title_id' => 'tt0000019' ),
        98 => array( '_lunara_imdb_title_id' => 'tt0000018' ),
        99 => array(
            '_lunara_imdb_title_id' => 'tt0000010',
            'theme_echo_movie'      => 16,
            'theme_echo_note'       => 'Already selected.',
        ),
    ),
    'studios' => array(
        10 => array( 300 ),
        11 => array( 300 ),
        12 => array( 300 ),
        13 => array( 300 ),
        15 => array( 300 ),
        16 => array( 300 ),
        17 => array( 300 ),
    ),
    'terms' => array(
        300 => 'Studio One',
    ),
    'queries' => array(),
    'writes' => array(),
    'network_calls' => array(),
    'provider_calls' => array(),
    'assertions' => 0,
);

for ( $movie_id = 1000; $movie_id < 1205; ++$movie_id ) {
    $GLOBALS['lunara_suggestion_test']['posts'][ $movie_id ] = array(
        'post_type'   => 'movie',
        'post_status' => 'publish',
        'title'       => 'Pool Film ' . $movie_id,
    );
    $GLOBALS['lunara_suggestion_test']['meta'][ $movie_id ] = array(
        'imdb_title_id' => 'tt' . str_pad( (string) $movie_id, 7, '0', STR_PAD_LEFT ),
        'release_year'  => '2000',
        'directors'     => array( 100 ),
    );
}

function __( $text ) {
    return $text;
}

function absint( $value ) {
    return abs( (int) $value );
}

function get_post_type( $post_id ) {
    return $GLOBALS['lunara_suggestion_test']['posts'][ $post_id ]['post_type'] ?? '';
}

function get_post_status( $post_id ) {
    return $GLOBALS['lunara_suggestion_test']['posts'][ $post_id ]['post_status'] ?? '';
}

function get_post_meta( $post_id, $key ) {
    return $GLOBALS['lunara_suggestion_test']['meta'][ $post_id ][ $key ] ?? '';
}

function get_the_title( $post_id ) {
    return $GLOBALS['lunara_suggestion_test']['posts'][ $post_id ]['title'] ?? '';
}

function get_permalink( $post_id ) {
    if ( array_key_exists( 'permalink', $GLOBALS['lunara_suggestion_test']['posts'][ $post_id ] ?? array() ) ) {
        return $GLOBALS['lunara_suggestion_test']['posts'][ $post_id ]['permalink'];
    }
    return isset( $GLOBALS['lunara_suggestion_test']['posts'][ $post_id ] )
        ? 'https://example.test/film/' . absint( $post_id ) . '/'
        : '';
}

function get_posts( $args ) {
    $GLOBALS['lunara_suggestion_test']['queries'][] = $args;
    $ids = array();
    $statuses = (array) ( $args['post_status'] ?? 'publish' );

    foreach ( $GLOBALS['lunara_suggestion_test']['posts'] as $post_id => $post ) {
        if ( $post['post_type'] !== ( $args['post_type'] ?? '' ) || ! in_array( $post['post_status'], $statuses, true ) ) {
            continue;
        }

        // WordPress's exact title query still uses the database collation.
        // Model its common case/accent-insensitive comparison, without turning
        // it into a substring search or collapsing internal whitespace.
        if ( isset( $args['title'] ) && lunara_suggestion_sql_title( $post['title'] ) !== lunara_suggestion_sql_title( $args['title'] ) ) {
            continue;
        }

        if ( isset( $args['meta_key'] ) ) {
            $actual = $GLOBALS['lunara_suggestion_test']['meta'][ $post_id ][ $args['meta_key'] ] ?? '';
            if ( (string) $actual !== (string) ( $args['meta_value'] ?? '' ) ) {
                continue;
            }
        }

        if ( ! empty( $args['meta_query'] ) ) {
            $matches = array();
            foreach ( $args['meta_query'] as $condition ) {
                if ( ! is_array( $condition ) || empty( $condition['key'] ) ) {
                    continue;
                }
                $actual = $GLOBALS['lunara_suggestion_test']['meta'][ $post_id ][ $condition['key'] ] ?? '';
                if ( 'LIKE' === ( $condition['compare'] ?? '' ) ) {
                    $needle = trim( (string) ( $condition['value'] ?? '' ), '"' );
                    $values = is_array( $actual ) ? array_map( 'strval', $actual ) : array( (string) $actual );
                    $matches[] = in_array( $needle, $values, true );
                } else {
                    $matches[] = ! is_array( $actual ) && (string) $actual === (string) ( $condition['value'] ?? '' );
                }
            }
            $matched = 'OR' === ( $args['meta_query']['relation'] ?? 'AND' )
                ? in_array( true, $matches, true )
                : ! empty( $matches ) && ! in_array( false, $matches, true );
            if ( ! $matched ) {
                continue;
            }
        }

        $ids[] = $post_id;
    }

    sort( $ids, SORT_NUMERIC );
    $limit = (int) ( $args['posts_per_page'] ?? count( $ids ) );
    return $limit > -1 ? array_slice( $ids, 0, $limit ) : $ids;
}

function lunara_suggestion_sql_title( $title ) {
    return strtolower( rtrim( strtr( (string) $title, array( 'é' => 'e', 'É' => 'E' ) ) ) );
}

function lunara_suggestion_forbidden_write( $operation ) {
    $GLOBALS['lunara_suggestion_test']['writes'][] = $operation;
    throw new RuntimeException( 'Unexpected application write: ' . $operation );
}

function update_post_meta() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function add_post_meta() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function delete_post_meta() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function update_field() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function delete_field() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function wp_insert_post() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function wp_update_post() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function wp_set_object_terms() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function update_option() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function delete_option() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }
function set_transient() { return lunara_suggestion_forbidden_write( __FUNCTION__ ); }

function lunara_suggestion_forbidden_request( $operation ) {
    $GLOBALS['lunara_suggestion_test']['network_calls'][] = $operation;
    throw new RuntimeException( 'Unexpected remote request: ' . $operation );
}

function wp_remote_request() { return lunara_suggestion_forbidden_request( __FUNCTION__ ); }
function wp_remote_get() { return lunara_suggestion_forbidden_request( __FUNCTION__ ); }
function wp_remote_post() { return lunara_suggestion_forbidden_request( __FUNCTION__ ); }
function wp_safe_remote_request() { return lunara_suggestion_forbidden_request( __FUNCTION__ ); }
function wp_safe_remote_get() { return lunara_suggestion_forbidden_request( __FUNCTION__ ); }
function wp_safe_remote_post() { return lunara_suggestion_forbidden_request( __FUNCTION__ ); }

function get_transient() {
    $GLOBALS['lunara_suggestion_test']['provider_calls'][] = 'provider_cache_read';
    throw new RuntimeException( 'Suggestion reports must not invoke the provider gateway.' );
}

function lunara_suggestion_data_snapshot() {
    return array_intersect_key( $GLOBALS['lunara_suggestion_test'], array_flip( array( 'posts', 'meta', 'studios', 'terms' ) ) );
}

function lunara_suggestion_person_queries() {
    return array_values( array_filter( $GLOBALS['lunara_suggestion_test']['queries'], static function ( $query ) {
        return 'person' === ( $query['post_type'] ?? '' );
    } ) );
}

function wp_get_object_terms( $post_id, $taxonomy, $args ) {
    unset( $taxonomy, $args );
    return $GLOBALS['lunara_suggestion_test']['studios'][ $post_id ] ?? array();
}

function is_wp_error() {
    return false;
}

function get_term( $term_id, $taxonomy ) {
    unset( $taxonomy );
    if ( ! isset( $GLOBALS['lunara_suggestion_test']['terms'][ $term_id ] ) ) {
        return null;
    }
    return (object) array(
        'term_id' => $term_id,
        'name'    => $GLOBALS['lunara_suggestion_test']['terms'][ $term_id ],
    );
}

function lunara_suggestion_assert_same( $expected, $actual, $message ) {
    ++$GLOBALS['lunara_suggestion_test']['assertions'];
    if ( $expected !== $actual ) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
        );
    }
}

function lunara_suggestion_assert_true( $condition, $message ) {
    ++$GLOBALS['lunara_suggestion_test']['assertions'];
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

class WP_CLI {
    public static $lines = array();

    public static function line( $line ) {
        self::$lines[] = (string) $line;
    }

    public static function error( $message ) {
        throw new RuntimeException( (string) $message );
    }

    public static function add_command() {}
}

require dirname( __DIR__ ) . '/includes/class-lunara-debrief-contract.php';
require dirname( __DIR__ ) . '/includes/class-lunara-debrief-migration.php';
require dirname( __DIR__ ) . '/includes/class-lunara-debrief-suggestions.php';
require dirname( __DIR__ ) . '/includes/class-lunara-debrief-cli.php';

$initial_snapshot = lunara_suggestion_data_snapshot();

$report = Lunara_Debrief_Suggestions::for_review(
    99,
    array(
        'role'  => 'career_context',
        'limit' => 2,
    )
);

lunara_suggestion_assert_same( Lunara_Debrief_Suggestions::SCHEMA_VERSION, $report['schema'], 'Suggestion schema must be explicit.' );
lunara_suggestion_assert_same( 0, $report['writes_performed'], 'Suggestion reports must advertise zero writes.' );
lunara_suggestion_assert_same( 'ready', $report['source_status'], 'A unique public source Movie must pass strict source resolution.' );
lunara_suggestion_assert_same( 200, $report['candidate_pool']['scanned'], 'Candidate scans must stop at the hard pool cap.' );
lunara_suggestion_assert_true( $report['candidate_pool']['truncated'], 'A capped pool must disclose truncation.' );
lunara_suggestion_assert_same( array( 16 ), $report['selected_movie_ids'], 'Saved companion relationships must be excluded.' );
lunara_suggestion_assert_same(
    array( 17, 11 ),
    array_column( array_column( $report['roles']['career_context']['candidates'], 'film' ), 'movie_id' ),
    'Career candidates must sort by structured score and then stable Movie ID.'
);
lunara_suggestion_assert_same( 135, $report['roles']['career_context']['candidates'][0]['score'], 'Director, cast, and studio evidence must use documented weights.' );
lunara_suggestion_assert_same(
    array( 'shared_director', 'shared_principal_cast', 'shared_studio' ),
    array_column( $report['roles']['career_context']['candidates'][0]['evidence'], 'code' ),
    'Every score contribution must remain explainable.'
);
lunara_suggestion_assert_true(
    false !== strpos( $report['roles']['career_context']['candidates'][0]['explanation'], 'Director One' ),
    'Local relationship labels must appear in the operator explanation.'
);
lunara_suggestion_assert_true(
    ! in_array( 10, array_column( array_column( $report['roles']['career_context']['candidates'], 'film' ), 'movie_id' ), true ),
    'The reviewed film must never be suggested as its own companion.'
);
lunara_suggestion_assert_true(
    ! in_array( 16, array_column( array_column( $report['roles']['career_context']['candidates'], 'film' ), 'movie_id' ), true ),
    'An already selected companion must never be suggested again.'
);

$all_roles = Lunara_Debrief_Suggestions::for_review( 99 );
lunara_suggestion_assert_same( 'insufficient_evidence', $all_roles['roles']['theme_echo']['status'], 'Theme Echo must abstain without controlled theme metadata.' );
lunara_suggestion_assert_same( array(), $all_roles['roles']['theme_echo']['candidates'], 'Theme Echo abstention must return no invented candidates.' );
lunara_suggestion_assert_same( 'insufficient_evidence', $all_roles['roles']['counter_program']['status'], 'Counter-Program must abstain without controlled theme and tone metadata.' );
lunara_suggestion_assert_same( array(), $all_roles['roles']['counter_program']['candidates'], 'Counter-Program abstention must return no invented candidates.' );
lunara_suggestion_assert_same( 'ready', $all_roles['roles']['career_context']['status'], 'Career Context may use existing structured graph evidence.' );
lunara_suggestion_assert_true(
    ! in_array( 21, array_column( array_column( $all_roles['roles']['career_context']['candidates'], 'film' ), 'movie_id' ), true ),
    'A Movie with conflicting canonical identity fields must never become a suggestion.'
);

$repeated = Lunara_Debrief_Suggestions::for_review( 99 );
lunara_suggestion_assert_same( $all_roles['suggestion_hash'], $repeated['suggestion_hash'], 'Identical local evidence must produce an identical suggestion hash.' );

$pool_query_count_before_sparse = count( array_filter( $GLOBALS['lunara_suggestion_test']['queries'], static function ( $query ) {
    return 201 === (int) ( $query['posts_per_page'] ?? 0 );
} ) );
$sparse = Lunara_Debrief_Suggestions::for_review( 98, array( 'role' => 'career_context' ) );
lunara_suggestion_assert_same( 'insufficient_evidence', $sparse['roles']['career_context']['status'], 'Career Context must abstain when source relationships are missing.' );
lunara_suggestion_assert_same( array( 'source_career_relationships_missing' ), $sparse['roles']['career_context']['reason_codes'], 'Career abstention must explain the missing signal.' );
$pool_query_count_after_sparse = count( array_filter( $GLOBALS['lunara_suggestion_test']['queries'], static function ( $query ) {
    return 201 === (int) ( $query['posts_per_page'] ?? 0 );
} ) );
lunara_suggestion_assert_same( $pool_query_count_before_sparse, $pool_query_count_after_sparse, 'Sparse sources must abstain before a candidate-pool query.' );

$ambiguous = Lunara_Debrief_Suggestions::for_review( 97, array( 'role' => 'career_context' ) );
lunara_suggestion_assert_same( 'unavailable', $ambiguous['source_status'], 'Duplicate source Movie identities must fail closed.' );
lunara_suggestion_assert_same( array( 'source_movie_ambiguous' ), $ambiguous['source_reason_codes'], 'Ambiguous source resolution must be explicit.' );
lunara_suggestion_assert_same( array(), $ambiguous['roles']['career_context']['candidates'], 'Ambiguous source identities must never produce candidates.' );

foreach ( array(
    array( 'review_id' => '99,98', 'args' => array() ),
    array( 'review_id' => 999, 'args' => array() ),
    array( 'review_id' => 99, 'args' => array( 'role' => 'theme' ) ),
    array( 'review_id' => 99, 'args' => array( 'limit' => 0 ) ),
    array( 'review_id' => 99, 'args' => array( 'limit' => 13 ) ),
) as $invalid ) {
    $rejected = false;
    try {
        Lunara_Debrief_Suggestions::for_review( $invalid['review_id'], $invalid['args'] );
    } catch ( InvalidArgumentException $error ) {
        $rejected = true;
    }
    lunara_suggestion_assert_true( $rejected, 'Invalid suggestion scope must fail closed.' );
}

$pool_queries = array_values( array_filter( $GLOBALS['lunara_suggestion_test']['queries'], static function ( $query ) {
    return 201 === (int) ( $query['posts_per_page'] ?? 0 );
} ) );
$source_queries = array_values( array_filter( $GLOBALS['lunara_suggestion_test']['queries'], static function ( $query ) {
    return 2 === (int) ( $query['posts_per_page'] ?? 0 );
} ) );
lunara_suggestion_assert_true( ! empty( $source_queries ), 'Source resolution must use a bounded ambiguity check.' );
lunara_suggestion_assert_same( false, $source_queries[0]['cache_results'], 'Source identity checks must not rely on warmed query state.' );
lunara_suggestion_assert_true( ! empty( $pool_queries ), 'Suggestions must issue a bounded candidate query.' );
lunara_suggestion_assert_same( 'publish', $pool_queries[0]['post_status'], 'Only published Movies may enter the candidate pool.' );
lunara_suggestion_assert_same( true, $pool_queries[0]['no_found_rows'], 'Candidate discovery must avoid pagination counts.' );
lunara_suggestion_assert_same( 'OR', $pool_queries[0]['meta_query']['relation'], 'Candidate discovery must target source career relationships.' );

WP_CLI::$lines = array();
$cli = new Lunara_Debrief_CLI();
$cli->suggest(
    array(),
    array(
        'review-id' => '99',
        'role'      => 'career_context',
        'limit'     => '2',
        'format'    => 'summary',
    )
);
$cli_summary = implode( "\n", WP_CLI::$lines );
lunara_suggestion_assert_true( false !== strpos( $cli_summary, 'Review ID: 99' ), 'Suggestion CLI must report its exact bounded Review.' );
lunara_suggestion_assert_true( false !== strpos( $cli_summary, 'Writes performed: 0' ), 'Suggestion CLI must state its zero-write guarantee.' );

$apply_rejected = false;
try {
    $cli->suggest( array(), array( 'review-id' => '99', 'apply' => true ) );
} catch ( RuntimeException $error ) {
    $apply_rejected = false !== strpos( $error->getMessage(), 'not available' );
}
lunara_suggestion_assert_true( $apply_rejected, 'Suggestion CLI must reject every apply attempt.' );

$missing_id_rejected = false;
try {
    $cli->suggest( array(), array( 'role' => 'career_context' ) );
} catch ( RuntimeException $error ) {
    $missing_id_rejected = false !== strpos( $error->getMessage(), '--review-id' );
}
lunara_suggestion_assert_true( $missing_id_rejected, 'WP-CLI must reject a missing Review ID before discovery.' );
lunara_suggestion_assert_same( $initial_snapshot, lunara_suggestion_data_snapshot(), 'Local suggestions and rejected CLI apply must leave all content, relationships and editorial notes unchanged.' );

// More than 200 people precede these directors: exact lookup must not depend on
// the former first-200-person scan. The movie candidate pool retains its own cap.
for ( $person_id = 5000; $person_id < 5205; ++$person_id ) {
    $GLOBALS['lunara_suggestion_test']['posts'][ $person_id ] = array(
        'post_type' => 'person', 'post_status' => 'publish', 'title' => 'Unrelated Person ' . $person_id,
    );
}
foreach ( array(
    9000 => array( 'Provider Director', 'publish', 700001 ),
    9001 => array( 'Second Director', 'publish', 700002 ),
    9002 => array( 'Draft Director', 'draft', 700003 ),
    9004 => array( 'Duplicate Name', 'publish', 700005 ),
    9005 => array( 'Duplicate Name', 'publish', 700006 ),
    9006 => array( 'ID Duplicate One', 'publish', 700007 ),
    9007 => array( 'ID Duplicate Two', 'publish', 700007 ),
    9008 => array( 'Jose River', 'publish', 700008 ),
    9009 => array( 'José River', 'publish', 700009 ),
    9010 => array( 'Provider Spaced', 'publish', 700010 ),
    9011 => array( 'René Exact', 'publish', 700011 ),
) as $person_id => $person ) {
    $GLOBALS['lunara_suggestion_test']['posts'][ $person_id ] = array(
        'post_type' => 'person', 'post_status' => $person[1], 'title' => $person[0],
    );
    $GLOBALS['lunara_suggestion_test']['meta'][ $person_id ] = array( 'tmdb_person_id' => $person[2] );
}
$GLOBALS['lunara_suggestion_test']['posts'][9003] = array( 'post_type' => 'company', 'post_status' => 'publish', 'title' => 'Not a Person' );
$GLOBALS['lunara_suggestion_test']['meta'][9003] = array( 'tmdb_person_id' => 700004 );
foreach ( array( 1300 => 9000, 1301 => 9001 ) as $movie_id => $person_id ) {
    $GLOBALS['lunara_suggestion_test']['posts'][ $movie_id ] = array(
        'post_type' => 'movie', 'post_status' => 'publish', 'title' => 'Provider Career Film ' . $movie_id,
    );
    $GLOBALS['lunara_suggestion_test']['meta'][ $movie_id ] = array(
        'imdb_title_id' => 'tt000' . $movie_id, 'release_year' => '2010', 'directors' => array( $person_id ),
    );
}

$provider_snapshot = lunara_suggestion_data_snapshot();
$provider_candidate = array(
    'imdb_title_id' => 'tt0000018',
    'directors' => array( array( 'name' => 'Provider Director', 'tmdb_person_id' => 700001 ) ),
);

// Each exercise checks state and input immutability, and that the service returns
// a report rather than printing into an editor response. Query receipts are kept
// so tests distinguish exact matching from a broad, accidentally passing scan.
$probe = static function ( $candidate, $review_id = 98, $args = array( 'role' => 'career_context' ) ) {
    $before = lunara_suggestion_data_snapshot();
    $candidate_before = $candidate;
    $GLOBALS['lunara_suggestion_test']['queries'] = array();
    ob_start();
    try {
        $result = Lunara_Debrief_Suggestions::for_review( $review_id, $args, $candidate );
    } finally {
        $output = ob_get_clean();
    }
    lunara_suggestion_assert_same( '', $output, 'Suggestion service must not print into its caller response.' );
    lunara_suggestion_assert_same( $before, lunara_suggestion_data_snapshot(), 'Provider enrichment must remain report-only; posts, metadata and editorial notes cannot change.' );
    lunara_suggestion_assert_same( $candidate_before, $candidate, 'Caller-owned provider candidate must remain unchanged.' );
    lunara_suggestion_assert_same( 0, $result['writes_performed'], 'Enriched suggestions must retain the zero-write contract.' );
    return $result;
};

$enriched = $probe( $provider_candidate );
lunara_suggestion_assert_same( 'ready', $enriched['roles']['career_context']['status'], 'Structured provider directors must recover Career Context for a sparse source.' );
lunara_suggestion_assert_same( array( 9000 ), $enriched['source_signals']['directors']['ids'], 'Exact TMDb identity must find a published Person beyond the first 200 records.' );
lunara_suggestion_assert_same( 'provider_candidate', $enriched['source_signals']['directors']['origin'], 'Enriched director evidence must disclose its origin.' );
lunara_suggestion_assert_same( array( 1300 ), array_column( array_column( $enriched['roles']['career_context']['candidates'], 'film' ), 'movie_id' ), 'Only movies sharing the resolved local director may be suggested.' );
lunara_suggestion_assert_same( 100, $enriched['roles']['career_context']['candidates'][0]['score'], 'Provider-assisted identity must use the existing director weight.' );
lunara_suggestion_assert_same( array( 'Provider Director' ), $enriched['roles']['career_context']['candidates'][0]['evidence'][0]['labels'], 'Explanations must use the local published Person label.' );
$person_queries = lunara_suggestion_person_queries();
lunara_suggestion_assert_same( 1, count( $person_queries ), 'One supplied director must require one exact person query.' );
lunara_suggestion_assert_same( '700001', (string) $person_queries[0]['meta_query'][0]['value'], 'Identity-backed lookup must query the exact provider person ID.' );
lunara_suggestion_assert_same( 'tmdb_person_id', $person_queries[0]['meta_query'][0]['key'], 'Provider IDs must resolve through the canonical local identity field.' );

foreach ( array(
    array( 'Provider Director' ),
    array( array( 'name' => 'Provider Director' ) ),
    array( array( 'name' => 'Provider Director', 'tmdb_person_id' => 0 ) ),
    array( array( 'name' => 'Provider Director', 'tmdb_person_id' => '0' ) ),
) as $directors ) {
    $legacy_candidate = array( 'imdb_title_id' => 'tt0000018', 'directors' => $directors );
    $legacy = $probe( $legacy_candidate );
    lunara_suggestion_assert_same( $enriched['source_signals'], $legacy['source_signals'], 'Legacy scalar names and absent/zero provider IDs must match the same unique local Person.' );
    lunara_suggestion_assert_same( $enriched['suggestion_hash'], $legacy['suggestion_hash'], 'Equivalent resolved identities must produce the same report hash.' );
    lunara_suggestion_assert_same( 'Provider Director', lunara_suggestion_person_queries()[0]['title'], 'Name-only matching must use an exact title query.' );
}

foreach ( array(
    array( 'tmdb_person_id' => 700001 ),
    array( 'name' => 'Director One', 'tmdb_person_id' => '700001' ),
) as $director ) {
    $identity_only = $probe( array( 'imdb_title_id' => 'tt0000018', 'directors' => array( $director ) ) );
    lunara_suggestion_assert_same( array( 9000 ), $identity_only['source_signals']['directors']['ids'], 'A valid provider identity must take precedence over a missing or conflicting label.' );
    lunara_suggestion_assert_true( ! isset( lunara_suggestion_person_queries()[0]['title'] ), 'Identity-backed resolution must not fall back to names.' );
}

$spaced = $probe( array( 'imdb_title_id' => 'tt0000018', 'directors' => array( "  provider\t  SPACED \n" ) ) );
lunara_suggestion_assert_same( array( 9010 ), $spaced['source_signals']['directors']['ids'], 'Name-only resolution must trim, collapse whitespace and compare case consistently.' );
lunara_suggestion_assert_same( 'provider SPACED', lunara_suggestion_person_queries()[0]['title'], 'The bounded exact query must receive normalized whitespace.' );

foreach ( array(
    'duplicate name' => array( 'name' => 'Duplicate Name' ),
    'duplicate provider ID' => array( 'name' => 'ID Duplicate One', 'tmdb_person_id' => 700007 ),
    'collation ambiguity' => array( 'name' => 'Jose River' ),
    'single collation mismatch' => array( 'name' => 'Rene Exact' ),
    'missing person name' => array( 'name' => 'Unavailable Director' ),
    'draft person name' => array( 'name' => 'Draft Director' ),
    'draft person ID' => array( 'name' => 'Draft Director', 'tmdb_person_id' => 700003 ),
    'wrong post type' => array( 'name' => 'Not a Person', 'tmdb_person_id' => 700004 ),
    'positive ID miss with existing name' => array( 'name' => 'Provider Director', 'tmdb_person_id' => 700099 ),
) as $label => $director ) {
    $rejected = $probe( array( 'imdb_title_id' => 'tt0000018', 'directors' => array( $director ) ) );
    lunara_suggestion_assert_same( array(), $rejected['source_signals']['directors']['ids'], $label . ' must not invent or substitute a Person relationship.' );
    lunara_suggestion_assert_same( 'insufficient_evidence', $rejected['roles']['career_context']['status'], $label . ' must abstain safely.' );
    lunara_suggestion_assert_same( 1, count( lunara_suggestion_person_queries() ), $label . ' must use one bounded lookup with no fallback.' );
}

$identified_duplicate_name = $probe( array( 'imdb_title_id' => 'tt0000018', 'directors' => array( array( 'name' => 'Duplicate Name', 'tmdb_person_id' => 700005 ) ) ) );
lunara_suggestion_assert_same( array( 9004 ), $identified_duplicate_name['source_signals']['directors']['ids'], 'A unique provider ID may resolve a name that would be ambiguous without identity.' );

foreach ( array( -1, '-1', 1.5, '1.5', '7e5', true, false, array( 700001 ), (object) array( 'id' => 700001 ), '700001suffix', '9999999999999999999999' ) as $invalid_id ) {
    $rejected = $probe( array( 'imdb_title_id' => 'tt0000018', 'directors' => array( array( 'name' => 'Provider Director', 'tmdb_person_id' => $invalid_id ) ) ) );
    lunara_suggestion_assert_same( array(), $rejected['source_signals']['directors']['ids'], 'Malformed provider identity must never fall back to a matching name.' );
    lunara_suggestion_assert_same( array(), lunara_suggestion_person_queries(), 'Malformed provider IDs must be rejected before querying people.' );
}

foreach ( array( true, false, 700001, 1.5, '1234', '', '!!!', array(), array( 'name' => array( 'Provider Director' ) ), (object) array( 'name' => 'Provider Director' ), str_repeat( 'A', 601 ), "Bad\xC3\x28Label" ) as $invalid_director ) {
    $rejected = $probe( array( 'imdb_title_id' => 'tt0000018', 'directors' => array( $invalid_director ) ) );
    lunara_suggestion_assert_same( array(), $rejected['source_signals']['directors']['ids'], 'Invalid labels and malformed director rows must not become relationships.' );
    lunara_suggestion_assert_same( array(), lunara_suggestion_person_queries(), 'Invalid labels must be rejected before a database query.' );
}

$never_call = new class {
    public function get_candidate_by_imdb() {
        $GLOBALS['lunara_suggestion_test']['provider_calls'][] = __FUNCTION__;
        throw new RuntimeException( 'Provider objects are not accepted by this report API.' );
    }
};
$never_invoke = static function () {
    $GLOBALS['lunara_suggestion_test']['provider_calls'][] = 'callback';
    throw new RuntimeException( 'Provider callbacks are not accepted by this report API.' );
};
foreach ( array(
    null, false, 'Provider Director', $never_call, $never_invoke,
    array(), array( 'directors' => array( 'Provider Director' ) ),
    array( 'imdb_title_id' => 'tt0000010', 'directors' => array( 'Provider Director' ) ),
    array( 'imdb_title_id' => 'tt0000018 tt0000010', 'directors' => array( 'Provider Director' ) ),
    array( 'imdb_title_id' => 'https://www.imdb.com/title/tt0000018/', 'directors' => array( 'Provider Director' ) ),
    array( 'imdb_title_id' => 'Film: tt0000018', 'directors' => array( 'Provider Director' ) ),
    array( 'imdb_title_id' => 18, 'directors' => array( 'Provider Director' ) ),
    array( 'imdb_title_id' => 'tt0000018', 'directors' => 'Provider Director' ),
) as $invalid_candidate ) {
    $rejected = $probe( $invalid_candidate );
    lunara_suggestion_assert_same( array(), $rejected['source_signals']['directors']['ids'], 'Absent, mismatched or malformed candidate envelopes must leave sparse sources unchanged.' );
    lunara_suggestion_assert_same( array(), lunara_suggestion_person_queries(), 'Invalid candidate envelopes must not initiate person lookup or provider work.' );
}

$bounded = $provider_candidate;
$bounded['directors'] = array_fill( 0, 20, $provider_candidate['directors'][0] );
$twenty = $probe( $bounded );
lunara_suggestion_assert_same( array( 9000 ), $twenty['source_signals']['directors']['ids'], 'A permitted 20-row input must deduplicate identities.' );
lunara_suggestion_assert_true( count( lunara_suggestion_person_queries() ) <= 20, 'Provider person lookup work must remain bounded by director count.' );
$bounded['directors'][] = $provider_candidate['directors'][0];
$oversized = $probe( $bounded );
lunara_suggestion_assert_same( array(), $oversized['source_signals']['directors']['ids'], 'Over-limit director lists must be rejected whole rather than silently truncated.' );
lunara_suggestion_assert_same( array(), lunara_suggestion_person_queries(), 'Over-limit input must stop before any person query.' );

$multi_candidate = array(
    'imdb_title_id' => 'tt0000018',
    'directors' => array(
        array( 'name' => 'Second Director', 'tmdb_person_id' => 700002 ),
        $provider_candidate['directors'][0],
        $provider_candidate['directors'][0],
    ),
);
$multi = $probe( $multi_candidate );
lunara_suggestion_assert_same( array( 9000, 9001 ), $multi['source_signals']['directors']['ids'], 'Multiple valid identities must be deduplicated and sorted.' );
lunara_suggestion_assert_same( array( 1300, 1301 ), array_column( array_column( $multi['roles']['career_context']['candidates'], 'film' ), 'movie_id' ), 'Equal-score provider-assisted suggestions must retain stable movie ordering.' );
foreach ( lunara_suggestion_person_queries() as $query ) {
    lunara_suggestion_assert_same( 2, $query['posts_per_page'], 'Person queries must fetch only enough records to detect ambiguity.' );
    lunara_suggestion_assert_same( 'publish', $query['post_status'], 'Person identity lookup must exclude private and draft records.' );
    lunara_suggestion_assert_same( 'ids', $query['fields'], 'Person lookup must avoid loading complete posts.' );
    lunara_suggestion_assert_same( true, $query['no_found_rows'], 'Person lookup must avoid pagination counts.' );
    lunara_suggestion_assert_same( false, $query['cache_results'], 'Exact person identity must not depend on warmed query state.' );
}
$multi_candidate['directors'] = array_reverse( $multi_candidate['directors'] );
lunara_suggestion_assert_same( $multi['suggestion_hash'], $probe( $multi_candidate )['suggestion_hash'], 'Provider order and duplicates must not alter deterministic suggestion identity.' );

// Skip enrichment outside Career Context or when the existing local graph can
// already explain a result. Candidate data never overrides the local graph.
foreach ( array( 'theme_echo', 'counter_program' ) as $role ) {
    $role_only = $probe( $provider_candidate, 98, array( 'role' => $role ) );
    lunara_suggestion_assert_same( array(), $role_only['source_signals']['directors']['ids'], 'A non-career request must not enrich unrelated source signals.' );
    lunara_suggestion_assert_same( array(), lunara_suggestion_person_queries(), 'Non-career reports must not query provider-supplied people.' );
}
$local_candidate = $provider_candidate;
$local_candidate['imdb_title_id'] = 'tt0000010';
$local = $probe( $local_candidate, 99, array() );
lunara_suggestion_assert_same( $all_roles, $local, 'Existing local director/cast evidence must preserve the entire default report and hash.' );
lunara_suggestion_assert_same( array(), lunara_suggestion_person_queries(), 'Existing local evidence must bypass enrichment.' );

$GLOBALS['lunara_suggestion_test']['meta'][18]['principal_cast'] = array( 200 );
$cast_baseline = Lunara_Debrief_Suggestions::for_review( 98, array( 'role' => 'career_context' ) );
$cast_only = $probe( $provider_candidate );
lunara_suggestion_assert_same( $cast_baseline, $cast_only, 'A local cast-only signal must not be overwritten or inflated by provider directors.' );
lunara_suggestion_assert_same( array(), lunara_suggestion_person_queries(), 'Cast-only local evidence must bypass provider enrichment.' );
unset( $GLOBALS['lunara_suggestion_test']['meta'][18]['principal_cast'] );

$GLOBALS['lunara_suggestion_test']['meta'][18]['directors'] = array( 9000 );
$local_same_evidence = Lunara_Debrief_Suggestions::for_review( 98, array( 'role' => 'career_context' ) );
lunara_suggestion_assert_same( $enriched['roles'], $local_same_evidence['roles'], 'Local and provider-assisted matching identities must rank the same films.' );
lunara_suggestion_assert_true( $enriched['suggestion_hash'] !== $local_same_evidence['suggestion_hash'], 'A report hash must distinguish provider-assisted from local evidence even when the ranking matches.' );
unset( $GLOBALS['lunara_suggestion_test']['meta'][18]['directors'] );

$ambiguous_candidate = $provider_candidate;
$ambiguous_candidate['imdb_title_id'] = 'tt0000019';
$ambiguous_provider = $probe( $ambiguous_candidate, 97 );
lunara_suggestion_assert_same( 'unavailable', $ambiguous_provider['source_status'], 'Provider data cannot repair an ambiguous source movie identity.' );
lunara_suggestion_assert_same( array(), lunara_suggestion_person_queries(), 'Ambiguous source identity must stop before director discovery.' );
$GLOBALS['lunara_suggestion_test']['posts'][18]['post_status'] = 'draft';
$draft_source = $probe( $provider_candidate );
lunara_suggestion_assert_same( 'unavailable', $draft_source['source_status'], 'Provider data cannot expose a draft source movie.' );
lunara_suggestion_assert_same( array(), lunara_suggestion_person_queries(), 'Private source movies must not trigger person lookup.' );
$GLOBALS['lunara_suggestion_test']['posts'][18]['post_status'] = 'publish';

// Actual gateway normalization integration, without its transport, credentials,
// cache, or live provider calls. This guards the structured shape that originally
// failed while keeping the suggestion service itself entirely local and read-only.
require dirname( __DIR__ ) . '/includes/class-lunara-movie-provider-gateway.php';
$normalizer = new ReflectionMethod( Lunara_Movie_Provider_Gateway::class, 'normalize_candidate' );
$normalizer->setAccessible( true );
$normalized_candidate = $normalizer->invoke(
    null,
    'tt0000018',
    array( 'data' => array( 'Title' => 'Sparse Source Film', 'Year' => '2013' ) ),
    array( 'data' => array( 'movie_results' => array( array( 'id' => 8018 ) ) ) ),
    array( 'data' => array(
        'title' => 'Sparse Source Film',
        'credits' => array( 'crew' => array( array(
            'id' => 700001, 'name' => 'Provider Director', 'department' => 'Directing', 'job' => 'Director',
        ) ) ),
    ) ),
    8018
);
$normalized_report = $probe( $normalized_candidate );
lunara_suggestion_assert_same( $enriched, $normalized_report, 'Actual gateway-normalized director objects must bridge into the same explainable suggestion report.' );
$all_provider_roles = $probe( $normalized_candidate, 98, array() );
lunara_suggestion_assert_same( $enriched['roles']['career_context'], $all_provider_roles['roles']['career_context'], 'An all-role request must still enrich its Career Context result.' );
lunara_suggestion_assert_same( 'insufficient_evidence', $all_provider_roles['roles']['theme_echo']['status'], 'Provider career facts must not invent thematic evidence.' );
lunara_suggestion_assert_same( 'insufficient_evidence', $all_provider_roles['roles']['counter_program']['status'], 'Provider career facts must not invent contrasting editorial arguments.' );
$gateway = new Lunara_Movie_Provider_Gateway( $never_invoke, $never_invoke, $never_invoke, $never_invoke );
$object_report = $probe( $gateway );
lunara_suggestion_assert_same( array(), $object_report['source_signals']['directors']['ids'], 'A real gateway object must be ignored without calling its transport, cache or clock dependencies.' );

WP_CLI::$lines = array();
$cli->suggest( array(), array( 'review-id' => '98', 'role' => 'career_context', 'format' => 'json' ) );
$sparse_cli = json_decode( implode( "\n", WP_CLI::$lines ), true );
lunara_suggestion_assert_same( 'insufficient_evidence', $sparse_cli['roles']['career_context']['status'], 'Default CLI must stay local-only and abstain on sparse source relationships.' );
lunara_suggestion_assert_same( array(), $sparse_cli['source_signals']['directors']['ids'], 'CLI must not implicitly obtain provider directors.' );
lunara_suggestion_assert_same( $provider_snapshot, lunara_suggestion_data_snapshot(), 'All provider-assisted and CLI scenarios must leave persisted fixture data unchanged.' );
lunara_suggestion_assert_same( array(), $GLOBALS['lunara_suggestion_test']['writes'], 'No exercised suggestion or CLI path may call a write API.' );
lunara_suggestion_assert_same( array(), $GLOBALS['lunara_suggestion_test']['network_calls'], 'No exercised suggestion or CLI path may issue a remote request.' );
lunara_suggestion_assert_same( array(), $GLOBALS['lunara_suggestion_test']['provider_calls'], 'Candidates are data only: provider objects, callbacks and caches must never be invoked.' );

echo 'Debrief suggestion regression checks passed (' . $GLOBALS['lunara_suggestion_test']['assertions'] . " assertions).\n";
