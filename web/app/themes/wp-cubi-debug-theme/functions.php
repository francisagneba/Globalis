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

// Ajouter un filtre pour vérifier les URL des pages des inscrits
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


// Étape 1 : Action pour envoyer l'email lors de la création d'une inscription
add_action('save_post', 'send_registration_email', 10, 3);

function send_registration_email($post_id, $post, $update) {
    // Vérifier si c'est un type de publication 'inscription'
    if ($post->post_type != 'registration') {
        return;
    }

    // Ne pas envoyer l'email lors de l'update
    if ($update) {
        return;
    }

    // Récupérer l'email de l'inscrit
    $email = get_post_meta($post_id, 'inscrit_email', true);

    // Récupérer les informations de l'événement
    $event_id = get_post_meta($post_id, 'event_id', true);
    $event_title = get_the_title($event_id);
    $event_date = get_post_meta($event_id, 'event_date', true);

    // Générer le billet PDF
    $pdf_path = generate_event_ticket_pdf($event_title, $event_date, $post_id);

    // Envoyer l'email
    $subject = 'Votre billet pour ' . $event_title;
    $message = 'Bonjour, voici votre billet pour l\'événement ' . $event_title . ' qui aura lieu le ' . $event_date . '.';
    wp_mail_with_attachment($email, $subject, $message, $pdf_path);
}

// Étape 2 : Générer un PDF du billet d'entrée
require('web/app/fpdf.php'); // Indiquez le bon chemin vers fpdf.php

function generate_event_ticket_pdf($event_title, $event_date, $registration_id) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(40, 10, 'Billet d\'entree');
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(40, 10, 'Evenement: ' . $event_title);
    $pdf->Ln();
    $pdf->Cell(40, 10, 'Date: ' . $event_date);
    $pdf->Ln();
    $pdf->Cell(40, 10, 'Inscription ID: ' . $registration_id);
    $pdf->Ln();

    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['path'] . '/ticket-' . $registration_id . '.pdf';
    $pdf->Output('F', $pdf_path);

    return $pdf_path;
}

// Étape 3 : Envoyer un email avec le PDF en pièce-jointe
function wp_mail_with_attachment($to, $subject, $message, $attachment_path) {
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $attachments = array($attachment_path);

    wp_mail($to, $subject, $message, $headers, $attachments);
}

