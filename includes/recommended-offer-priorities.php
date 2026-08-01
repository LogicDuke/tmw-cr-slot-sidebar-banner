<?php
/**
 * Runtime-only CrakRevenue recommended-offer priorities.
 *
 * The array order is the global recommendation order. Keeping the catalog here
 * prevents recommendation metadata from being duplicated or persisted.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gets the filtered recommended priority for an offer.
 *
 * @param mixed $offer_id CrakRevenue offer ID.
 *
 * @return int|null One-based priority, or null when the offer is not recommended.
 */
function tmw_cr_get_recommended_offer_priority( $offer_id ) {
    $offer_id = trim( (string) $offer_id );
    if ( '' === $offer_id || ! ctype_digit( $offer_id ) ) {
        return null;
    }
    $offer_id = (string) (int) $offer_id;

    $catalog = array(
        // AI.
        '10335', '10139', '10407', '9022', '10022', '10224',
        // Cam.
        '8780', '3778', '153', '6224', '8266', '10292',
        // Fansite.
        '8835', '9293', '8837', '9768', '9927', '9048',
        // Adult Paysite / VOD.
        '10093', '9248', '9976',
    );

    /**
     * Filters the ordered runtime-only list of recommended CrakRevenue offer IDs.
     *
     * @param array<int,string> $catalog Ordered offer IDs.
     */
    $catalog = apply_filters( 'tmw_cr_slot_banner_recommended_offer_priorities', $catalog );
    $catalog = is_array( $catalog ) ? $catalog : array();
    $rank    = 0;
    $seen    = array();

    foreach ( $catalog as $candidate ) {
        $candidate = trim( (string) $candidate );
        if ( '' === $candidate || ! ctype_digit( $candidate ) ) {
            continue;
        }
        $candidate = (string) (int) $candidate;
        if ( isset( $seen[ $candidate ] ) ) {
            continue;
        }
        $seen[ $candidate ] = true;
        ++$rank;
        if ( $candidate === $offer_id ) {
            return $rank;
        }
    }

    return null;
}
