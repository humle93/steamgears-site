<?php
/**
 * Plugin Name: Steamgears lead capture
 * Description: A small opt-in form for the free starter kit and the thrifting guide, with a REST
 * endpoint that stores the address on the site. No third party, no form plugin.
 */
if (!defined('ABSPATH')) { exit; }

add_action('rest_api_init', function () {
    register_rest_route('sgap/v1', '/lead', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $r) {
            $email = sanitize_email((string) $r->get_param('email'));
            $name = sanitize_text_field((string) $r->get_param('name'));
            $source = sanitize_text_field((string) $r->get_param('source'));
            if (!is_email($email)) {
                return new WP_REST_Response(['ok' => false, 'error' => 'That email address does not look right.'], 400);
            }
            $leads = get_option('sgap_leads', []);
            if (!is_array($leads)) { $leads = []; }
            if (isset($leads[$email])) {
                return new WP_REST_Response(['ok' => true, 'repeat' => true, 'count' => count($leads)], 200);
            }
            $leads[$email] = ['name' => $name, 'source' => $source, 'at' => current_time('mysql')];
            update_option('sgap_leads', $leads, false);
            return new WP_REST_Response(['ok' => true, 'count' => count($leads)], 200);
        },
    ]);
});

add_shortcode('sgap_lead_form', function ($atts) {
    $a = shortcode_atts(['source' => 'lead', 'label' => 'Send me the kit'], $atts);
    $endpoint = esc_url_raw(rest_url('sgap/v1/lead'));
    $id = 'sgap-lead-' . wp_rand(1000, 9999);
    return '<form class="sgap-lead" id="' . esc_attr($id) . '" method="post" action="' . esc_attr($endpoint) . '">'
        . '<p><label>Your name <input type="text" name="name" autocomplete="name"></label></p>'
        . '<p><label>Email <input type="email" name="email" required autocomplete="email"></label></p>'
        . '<input type="hidden" name="source" value="' . esc_attr($a['source']) . '">'
        . '<p><button type="submit" class="wp-block-button__link">' . esc_html($a['label']) . '</button></p>'
        . '<p class="sgap-lead-out" role="status"></p></form>'
        . '<script>(function(){var f=document.getElementById(' . wp_json_encode($id) . ');if(!f)return;'
        . 'f.addEventListener("submit",function(e){e.preventDefault();var o=f.querySelector(".sgap-lead-out");'
        . 'fetch(f.action,{method:"POST",body:new URLSearchParams(new FormData(f))})'
        . '.then(function(r){return r.json();}).then(function(j){o.textContent=j&&j.ok?"Thank you - it is on its way.":(j&&j.error?j.error:"Could not save that.");'
        . 'if(j&&j.ok){f.reset();}}).catch(function(){o.textContent="Could not reach the site. Try again.";});});})();</script>';
});
