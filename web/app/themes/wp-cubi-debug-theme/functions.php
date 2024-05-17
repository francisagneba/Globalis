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


// Fonctionnalité 2 Étape 1 : Ajouter une colonne "Export" dans la vue liste des évènements

// Ajouter la colonne "Export" dans la liste des événements
add_filter('manage_edit-event_columns', 'add_export_column');
function add_export_column($columns) {
    $columns['export'] = __('Export', 'textdomain');
    return $columns;
}

// Remplir la colonne "Export" avec un bouton
add_action('manage_event_posts_custom_column', 'fill_export_column', 10, 2);
function fill_export_column($column, $post_id) {
    if ($column === 'export') {
        echo '<a href="' . admin_url('admin-ajax.php?action=export_registrations&event_id=' . $post_id) . '" class="button button-primary">Export</a>';
    }
}

// Étape 2 : Ajouter un action pour exporter les données en Excel

// Inclure la bibliothèque OpenSpout
require_once '/openspout/autoload.php'; 

use Box\Spout\Writer\Common\Creator\WriterEntityFactory;

// Ajouter une action pour exporter les inscriptions
add_action('wp_ajax_export_registrations', 'export_registrations');

function export_registrations() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $event_id = intval($_GET['event_id']);
    if (!$event_id) {
        wp_die(__('Invalid event ID.'));
    }

    // Récupérer les inscrits pour l'événement
    $registrations = get_post_meta($event_id, '_registrations', true);
    if (empty($registrations)) {
        wp_die(__('No registrations found.'));
    }

    // Chemin vers le fichier temporaire
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/registrations-' . $event_id . '.xlsx';

    // Créer le fichier Excel
    $writer = WriterEntityFactory::createXLSXWriter();
    $writer->openToFile($file_path);

    // Ajouter les en-têtes
    $headerRow = WriterEntityFactory::createRowFromArray(['Nom', 'Prénom', 'Email', 'Téléphone']);
    $writer->addRow($headerRow);

    // Ajouter les lignes des inscrits
    foreach ($registrations as $registration) {
        $row = WriterEntityFactory::createRowFromArray([
            $registration['nom'],
            $registration['prenom'],
            $registration['email'],
            $registration['telephone']
        ]);
        $writer->addRow($row);
    }

    $writer->close();

    // Forcer le téléchargement du fichier
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);

    // Supprimer le fichier temporaire après téléchargement
    unlink($file_path);
    exit;
}
