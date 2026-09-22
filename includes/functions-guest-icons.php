<?php
/** Shared, decorative line icons for guest-facing navigation. */
defined('ABSPATH') || exit;

function domilocus_guest_icon_allowed_html() {
    return array(
        'svg' => array_fill_keys(array('class', 'xmlns', 'width', 'height', 'viewbox', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'aria-hidden', 'focusable'), true),
        'path' => array('d' => true),
        'circle' => array('cx' => true, 'cy' => true, 'r' => true),
        'rect' => array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true),
    );
}

function domilocus_guest_icon($name) {
    // Only trusted, fixed SVG geometry is rendered; no user-supplied markup.
    $icons = array(
        'welcome' => '<path d="M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/><path d="M10 9h4"/>',
        'wifi' => '<path d="M2 8a16 16 0 0 1 20 0M5 12a11 11 0 0 1 14 0M8.5 16a5.5 5.5 0 0 1 7 0"/><circle cx="12" cy="20" r=".7"/>',
        'arrival' => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>',
        'rules' => '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m8 11 1 1 2-2m2 1h3m-8 6 1 1 2-2m2 1h3"/>',
        'location' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'transport' => '<rect x="5" y="3" width="14" height="16" rx="3"/><path d="M5 11h14M8 19v2m8-2v2M9 6h6"/><circle cx="8" cy="15" r=".7"/><circle cx="16" cy="15" r=".7"/>',
        'events' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18m-14 5h3m4 0h3m-10 3h3"/>',
        'contacts' => '<path d="m7 3 3 5-3 2a15 15 0 0 0 7 7l2-3 5 3v3a1 1 0 0 1-1 1C10 21 3 14 3 4a1 1 0 0 1 1-1Z"/>',
        'shops' => '<path d="M5 7h14l2 14H3ZM8 8V6a4 4 0 0 1 8 0v2"/>',
        'manuals' => '<path d="M12 5C9 3 5 3 2 4v16c3-1 7-1 10 1 3-2 7-2 10-1V4c-3-1-7-1-10 1Zm0 0v16"/>',
        'essentials' => '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V3h8v4m-4 4v6m-3-3h6"/>',
        'checkout' => '<rect x="5" y="6" width="14" height="15" rx="2"/><path d="M9 6V3h6v3M9 10v7m6-7v7M8 21v1m8-1v1"/>',
        'checkin' => '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m8 13 3 3 5-6"/>',
        'done' => '<circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/>',
        'receipt' => '<path d="M5 3h14v19l-3-2-4 2-4-2-3 2ZM8 7h8m-8 4h8m-8 4h5"/>',
        'payment' => '<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/>',
        'locked' => '<rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v3"/>',
        'key' => '<circle cx="8" cy="8" r="5"/><path d="m11.5 11.5 9 9H23v-4h-4v-4h-4"/><circle cx="7" cy="7" r=".7"/>',
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    );
    if (!isset($icons[$name])) {
        return '';
    }
    return '<svg class="domilocus-line-icon" xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
}
