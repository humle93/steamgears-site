<?php
// P3.1 — `labor_rate` blev registreret i 7.15.0 og laest af intet. Den bruges nu i ROI-kortet
// som "Labour equivalent": hvad omsaetningen er vaerd i ejerens egne arbejdstimer.
SGAP_Settings::set('labor_rate', 250);
check('roi: hours divide the amount by the hourly rate', SGAP_ROI::hours(1000, 250) === 4.0 && SGAP_ROI::hours(250, 250) === 1.0);
check('roi: a zero rate gives null instead of infinity', SGAP_ROI::hours(1000, 0) === null && SGAP_ROI::hours(0, 0) === null);
check('roi: the result is rounded to one decimal', SGAP_ROI::hours(100, 300) === 0.3);
$wpdb->next = [1000.0];
ob_start(); do_action('page_sgap-reports'); $roi_html = ob_get_clean();
check('roi: the card reads labor_rate back and shows the labour equivalent', str_contains($roi_html, 'Labour equivalent:</strong> 4 h at 250.00/h') && str_contains($roi_html, 'budget &asymp; 4 h'));
SGAP_Settings::set('labor_rate', 0);
$wpdb->next = [1000.0];
ob_start(); do_action('page_sgap-reports'); $roi_off = ob_get_clean();
check('roi: a zero rate says so instead of dividing by zero', str_contains($roi_off, 'Labour equivalent:</strong> off (set your hourly rate to see it)'));
SGAP_Settings::set('labor_rate', 250);
