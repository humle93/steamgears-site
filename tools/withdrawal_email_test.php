<?php
/**
 * withdrawal_email_test.php — tester fortrydelsesblokken i ordremailen uden WordPress.
 *
 * Kør:  ./.tools/php tools/withdrawal_email_test.php
 * Ud:   PASS/FAIL pr. påstand; exit 0 kun hvis alle består.
 *
 * Testen kalder den samme funktion WooCommerce kalder, med en stub-mail der kun har de
 * metoder hooket bruger (get_id, get_content_type). Den kan derfor ikke bestå af sig selv:
 * hver påstand læser den tekst kunden faktisk ville få.
 */

$GLOBALS['hooks'] = [];
$GLOBALS['filters'] = [];

function add_action($hook, $callback, $priority = 10, $args = 1) {
    $GLOBALS['hooks'][$hook][] = ['callback' => $callback, 'priority' => $priority, 'args' => $args];
}
function apply_filters($hook, $value, ...$rest) {
    foreach ($GLOBALS['filters'][$hook] ?? [] as $callback) {
        $value = $callback($value, ...$rest);
    }
    return $value;
}
function esc_html($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// The mu-plugin bails out without WordPress, exactly as it should on a direct hit.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/fixtures/');
}
require_once __DIR__ . '/../wordpress/mu-plugins/sgap-withdrawal-email.php';

$failures = 0;

function t(string $label, bool $ok, $detail = null): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label;
    if (!$ok && $detail !== null) {
        echo '  -> ' . (is_string($detail) ? $detail : json_encode($detail));
    }
    echo PHP_EOL;
}

/** The order, with just the method the block asks for. */
class SGAP_Test_Order
{
    public function __construct(private string $number = '1099') {}
    public function get_order_number(): string { return $this->number; }
}

/** The email object WooCommerce hands to the hook. */
class SGAP_Test_Email
{
    public function __construct(private string $id, private string $content_type = 'text/html') {}
    public function get_id(): string { return $this->id; }
    public function get_content_type(): string { return $this->content_type; }
}

function render($email, $order = null, bool $plain = false, bool $admin = false): string
{
    ob_start();
    sgap_withdrawal_email_block($order ?? new SGAP_Test_Order(), $admin, $plain, $email);
    return (string) ob_get_clean();
}

/* --- hook and scope ---------------------------------------------------------------- */

$hook = $GLOBALS['hooks']['woocommerce_email_after_order_table'][0] ?? null;
t('hook: blokken hænger på WooCommerce-mailen', $hook !== null);
t('hook: den kører efter ordretabellen (prioritet 20)', ($hook['priority'] ?? 0) === 20);
t('hook: den får ordre, admin-flag, plain-flag og mail-objekt', ($hook['args'] ?? 0) === 4);
t('hook: callbacken er funktionen fra filen', ($hook['callback'] ?? '') === 'sgap_withdrawal_email_block');

$processing = render(new SGAP_Test_Email('customer_processing_order'));
$completed = render(new SGAP_Test_Email('customer_completed_order'));
$onhold = render(new SGAP_Test_Email('customer_on_hold_order'));
$admin_mail = render(new SGAP_Test_Email('new_order'));
$customer_note = render(new SGAP_Test_Email('customer_note'));
$invoice = render(new SGAP_Test_Email('customer_invoice'));

t('scope: bekræftelsen (processing) får blokken', $processing !== '');
t('scope: en ordre der markeres complete beholder den', $completed !== '');
t('scope: on-hold-mailen får den også', $onhold !== '');
t('scope: adminens egen new_order-mail får den ikke', $admin_mail === '');
t('scope: en kundenote er ikke en ordrebekræftelse', $customer_note === '');
t('scope: fakturaen er ikke bekræftelsen og får den ikke', $invoice === '');
t('scope: sent_to_admin slår den fra, uanset mail-id', render(new SGAP_Test_Email('customer_processing_order'), null, false, true) === '');

/* --- what the customer actually reads ---------------------------------------------- */

t('indhold: 14 dage fra modtagelsen, ikke fra ordren', str_contains($processing, 'within 14 days of receiving the goods'));
t('indhold: ingen begrundelse kræves', str_contains($processing, 'no reason needed'));
t('indhold: ordrenummeret står i mailen', str_contains($processing, 'Your order number is 1099'));
t('indhold: kunden skal skrive først', str_contains($processing, 'Tell us before you send anything'));
t('indhold: mailadressen er den sitet selv angiver', str_contains($processing, SGAP_WITHDRAWAL_EMAIL));
t('indhold: der loves ikke en returadresse butikken ikke har givet', str_contains($processing, 'correct return address') && !preg_match('/return to:.*\d{4}/i', $processing));
t('indhold: kunden må ikke sende før svar', str_contains($processing, 'do not send an item back before you have heard from us'));
t('indhold: linket til siden og EU-formularen er med', str_contains($processing, SGAP_WITHDRAWAL_PAGE) && str_contains($processing, 'standard EU withdrawal form'));
t('indhold: returfragten ligger hos kunden, undtagen fejl', str_contains($processing, 'You pay the return shipping unless the item is faulty'));
t('indhold: tilbagebetaling inden 14 dage fra meddelelsen', str_contains($processing, 'refund within 14 days of receiving your withdrawal notice'));
t('indhold: samme betalingsmetode og oprindelig fragt nævnes', str_contains($processing, 'same payment method') && str_contains($processing, 'standard shipping cost of the original order'));
t('indhold: hygiejneundtagelsen står der', str_contains($processing, 'hygiene seal is removed'));
t('indhold: ingen opdigtet fragtpris', !preg_match('/(free shipping|shipping[^.]{0,20}(0[.,]00|€|\\$|DKK|EUR))/i', $processing));
t('indhold: overskriften er med', str_contains($processing, 'Right of withdrawal'));

/* --- the two renderings must not drift apart --------------------------------------- */

$plain = render(new SGAP_Test_Email('customer_processing_order', 'text/plain'));
$plain_with_flag = render(new SGAP_Test_Email('customer_processing_order'), null, true);
t('plain: content-type text/plain giver ren tekst', !str_contains($plain, '<p') && !str_contains($plain, '<div'));
t('plain: plain-flaget giver samme rene tekst', !str_contains($plain_with_flag, '<p') && !str_contains($plain_with_flag, '<div'));
t('plain: teksten har stadig alle afsnit', substr_count($plain, "\n\n") >= 5);
t('plain: og de samme fakta som HTML-versionen', str_contains($plain, 'within 14 days of receiving the goods') && str_contains($plain, SGAP_WITHDRAWAL_PAGE));
t('html: blokken er sin egen sektion med overskrift', str_contains($processing, 'class="sgap-withdrawal"') && str_contains($processing, '<h2'));

/* --- escaping and the filter seam --------------------------------------------------- */

$nasty = render(new SGAP_Test_Email('customer_processing_order'), new SGAP_Test_Order('<script>alert(1)</script>'));
t('sikkerhed: ordrenummeret escapes', !str_contains($nasty, '<script>') && str_contains($nasty, '&lt;script&gt;'));
t('sikkerhed: linket skrives som tekst, ikke som rå HTML', substr_count($nasty, '<a ') === 0);

$GLOBALS['filters']['sgap_withdrawal_email_html'] = [fn($html, $order) => $html . '<p>Ekstra linje</p>'];
t('filter: sgap_withdrawal_email_html kan udvide blokken', str_contains(render(new SGAP_Test_Email('customer_processing_order')), 'Ekstra linje'));
unset($GLOBALS['filters']['sgap_withdrawal_email_html']);

$GLOBALS['filters']['sgap_withdrawal_email_ids'] = [fn($ids) => ['customer_invoice']];
t('filter: mail-id-listen kan ændres', render(new SGAP_Test_Email('customer_invoice')) !== '' && render(new SGAP_Test_Email('customer_processing_order')) === '');
unset($GLOBALS['filters']['sgap_withdrawal_email_ids']);

$GLOBALS['filters']['sgap_withdrawal_paragraphs'] = [function ($paragraphs) {
    $paragraphs[] = 'Testlinje fra filteret.';
    return $paragraphs;
}];
t('filter: afsnittene er ét sted, så begge formater følger med',
    str_contains(render(new SGAP_Test_Email('customer_processing_order')), 'Testlinje fra filteret.')
    && str_contains(render(new SGAP_Test_Email('customer_processing_order'), null, true), 'Testlinje fra filteret.'));
unset($GLOBALS['filters']['sgap_withdrawal_paragraphs']);

t('gentagelse: to kald giver to blokke, ingen skjult tilstand', substr_count(render(new SGAP_Test_Email('customer_processing_order')) . render(new SGAP_Test_Email('customer_processing_order')), 'Right of withdrawal') === 2);

echo PHP_EOL;
echo $failures === 0 ? "OK — alle påstande består\n" : "FEJL — $failures påstand(e) fejlede\n";
exit($failures === 0 ? 0 : 1);
