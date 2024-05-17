<?php

require_once __DIR__ . '/src/schema.php';
require_once __DIR__ . '/src/registrations.php';


// Ajouter une nouvelle colonne "Registrations" dans l'administration des évènements
add_filter('manage_edit-event_columns', 'add_registrations_column');
function add_registrations_column($columns) {
    $columns['registrations'] = __('Registrations', 'textdomain');
    return $columns;
}

// Remplir la colonne "Registrations" avec les données
add_action('manage_event_posts_custom_column', 'fill_registrations_column', 10, 2);
function fill_registrations_column($column, $post_id) {
    if ($column === 'registrations') {
        $registrations_count = get_event_registrations_count($post_id);
        echo intval($registrations_count);
    }
}

// Fonction pour obtenir le nombre d'inscrits pour un événement
function get_event_registrations_count($event_id) {
    // Remplacer cette partie par la logique réelle pour obtenir le nombre d'inscrits
    // Exemple :
    $registrations = get_post_meta($event_id, '_event_registrations', true);
    if (empty($registrations)) {
        return 0;
    } else {
        return count($registrations);
    }
}

add_action('template_redirect', 'block_registration_pages');

function block_registration_pages() {
    if (is_singular('registration')) {
        // Vous pouvez affiner cette condition pour correspondre exactement à votre structure d'URL
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        include(get_query_template('404'));
        exit;
    }
}
