<?php
/**
 * Built-in CrakRevenue recommended offer priorities.
 *
 * This catalog is runtime-only: it is never persisted to WordPress options and
 * contains offer IDs only (no destinations or tracking data).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the filtered CR recommended offer catalog, keyed by string offer ID.
 *
 * Lower positive values rank first. Invalid IDs/ranks are discarded so filters
 * can safely replace, remove, or reorder the entire catalog.
 *
 * @return array<int|string,int> Numeric-string keys are represented as integers by PHP arrays.
 */
function tmw_cr_get_recommended_offer_priorities() {
    $priorities = array(
        // AI.
        '10335' => 1,
        '10139' => 2,
        '10407' => 3,
        '9022'  => 4,
        '10022' => 5,
        '10224' => 6,
        // Cam.
        '8780'  => 7,
        '3778'  => 8,
        '153'   => 9,
        '6224'  => 10,
        '8266'  => 11,
        '10292' => 12,
        // Fansite.
        '8835'  => 13,
        '9293'  => 14,
        '8837'  => 15,
        '9768'  => 16,
        '9927'  => 17,
        '9048'  => 18,
        // Adult Paysite / VOD.
        '10093' => 19,
        '9248'  => 20,
        '9976'  => 21,
    );

    $filtered = apply_filters( 'tmw_cr_slot_banner_recommended_offer_priorities', $priorities );
    $clean    = array();

    foreach ( (array) $filtered as $offer_id => $priority ) {
        $offer_id = trim( sanitize_text_field( (string) $offer_id ) );
        $priority = (int) $priority;
        if ( '' === $offer_id || $priority < 1 ) {
            continue;
        }
        $clean[ $offer_id ] = $priority;
    }

    return $clean;
}

/**
 * Returns an offer's CR recommendation rank, or null when not recommended.
 *
 * @param string|int $offer_id CrakRevenue offer ID.
 *
 * @return int|null
 */
function tmw_cr_get_recommended_offer_priority( $offer_id ) {
    $offer_id   = trim( sanitize_text_field( (string) $offer_id ) );
    $priorities = tmw_cr_get_recommended_offer_priorities();

    return isset( $priorities[ $offer_id ] ) ? $priorities[ $offer_id ] : null;
}
