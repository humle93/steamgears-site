<?php
/**
 * Invitations to Command Center.
 *
 * Command Center has no accounts of its own. A login *is* a WordPress user with an
 * application password, and every `sgap/v1` route requires `manage_options`, so an
 * invitation has to create an administrator. That is the whole feature — and the
 * reason it is admin-only and does not get a public REST route: the endpoints in
 * `SGAP_REST` are reachable with an application password, and one of them must not
 * be able to mint new administrators.
 *
 * What the invitation does *not* do is create or email an application password.
 * WordPress shows that password exactly once, to the person who is logged in, and
 * mailing it would put a credential in an inbox forever. The invitee gets WordPress'
 * own new-user email with the set-password link and makes the application password
 * under Users → Profile.
 *
 * The invitation record lives in one option (`sgap_command_center_invites`) instead
 * of a new table: it is a handful of rows, read rarely, and adding a table would mean
 * a migration on the live site for bookkeeping.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Invites {
    const OPTION = 'sgap_command_center_invites';
    const ROLE = 'administrator';
    const MAX = 50;

    /** Seeded on first load so the owner's address is visible and can be sent with one call. */
    const DEFAULT_EMAILS = ['cpaleadsis@gmail.com'];

    const STATUSES = ['pending', 'invited', 'exists', 'failed', 'blocked'];

    public static function init(): void {
        add_action('init', [__CLASS__, 'seed']);
    }

    /**
     * Adds the default addresses as pending invitations, once. It never sends
     * anything and never overwrites a record that already has a state: re-running
     * the seed must not turn an accepted invitation back into "pending".
     */
    public static function seed(): void {
        $stored = get_option(self::OPTION, null);
        if (is_array($stored)) {
            return; // already seeded, even if every record has moved on since
        }
        $records = [];
        foreach (self::DEFAULT_EMAILS as $email) {
            $email = sanitize_email($email);
            if ($email === '' || !is_email($email)) {
                continue;
            }
            $records[$email] = self::blank($email, 'Seeded with the plugin. Nothing has been sent yet.');
        }
        update_option(self::OPTION, $records, false);
    }

    private static function blank(string $email, string $note = ''): array {
        return [
            'email' => $email,
            'status' => 'pending',
            'user_id' => 0,
            'note' => $note,
            'invited_at' => null,
            'updated_at' => current_time('mysql')
        ];
    }

    /** @return array email => record */
    public static function all(): array {
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) {
            return [];
        }
        $records = [];
        foreach ($stored as $email => $record) {
            if (!is_array($record) || !isset($record['email'])) {
                continue;
            }
            $records[(string) $record['email']] = $record + self::blank((string) $record['email']);
        }
        return $records;
    }

    /** Records as a list, newest change first — what an ability returns. */
    public static function records(): array {
        $records = array_values(self::all());
        usort($records, fn($a, $b) => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));
        return $records;
    }

    public static function get(string $email): ?array {
        return self::all()[strtolower($email)] ?? null;
    }

    private static function save(array $record): void {
        $records = self::all();
        $record['updated_at'] = current_time('mysql');
        $records[strtolower($record['email'])] = $record;
        if (count($records) > self::MAX) {
            $records = array_slice($records, -self::MAX, null, true);
        }
        update_option(self::OPTION, $records, false);
    }

    /**
     * Invites one email: creates the administrator, has WordPress send its own
     * new-user email, and records the outcome.
     *
     * Idempotent per address — a second call on an invited address creates nothing
     * and reports the existing record, so a retry after a timeout is safe.
     *
     * @return array|WP_Error the record (with `created` and `application_passwords_available`)
     */
    public static function invite(string $email) {
        if (!current_user_can('create_users')) {
            return new WP_Error('sgap_forbidden', 'Only a user who may create users can invite.', ['status' => 403]);
        }

        $email = sanitize_email(trim($email));
        if ($email === '' || !is_email($email)) {
            return new WP_Error('sgap_invalid_email', 'That is not a valid email address.', ['status' => 400]);
        }

        $record = self::get($email) ?? self::blank($email);

        // Already invited and the user still exists: report, create nothing.
        if ($record['status'] === 'invited' && (int) $record['user_id'] > 0
            && get_user_by('id', (int) $record['user_id'])) {
            return $record + [
                'created' => false,
                'application_passwords_available' => self::app_passwords_available(),
                'message' => 'Already invited. Nothing new was created.'
            ];
        }

        $existing = get_user_by('email', $email);
        if ($existing) {
            $record['status'] = 'exists';
            $record['user_id'] = (int) $existing->ID;
            $record['note'] = 'The address already has a WordPress user, so nothing was created. '
                . 'That user can make an application password under Users → Profile.';
            self::save($record);
            return $record + [
                'created' => false,
                'application_passwords_available' => self::app_passwords_available(),
                'message' => 'The address already has a WordPress user.'
            ];
        }

        // Without application passwords there is no way for the app to log in, and
        // creating an administrator nobody can use is worse than saying no.
        if (!self::app_passwords_available()) {
            $record['status'] = 'blocked';
            $record['note'] = 'WordPress only offers application passwords over HTTPS, and this site does not, '
                . 'so Command Center could not log in. Nothing was created.';
            self::save($record);
            return $record + [
                'created' => false,
                'application_passwords_available' => false,
                'message' => 'Application passwords are not available on this site.'
            ];
        }

        $user_id = wp_insert_user([
            'user_login' => self::login_for($email),
            'user_email' => $email,
            'user_pass' => wp_generate_password(32, true, true),
            'display_name' => ucfirst((string) strtok($email, '@')),
            'role' => self::ROLE
        ]);

        if (is_wp_error($user_id)) {
            $record['status'] = 'failed';
            $record['note'] = 'WordPress could not create the user: ' . $user_id->get_error_message();
            self::save($record);
            return $record + [
                'created' => false,
                'application_passwords_available' => true,
                'message' => 'WordPress could not create the user.'
            ];
        }

        // WordPress' own new-user email: set-password link, no credentials in the mail.
        wp_send_new_user_notifications((int) $user_id, 'both');

        $record['status'] = 'invited';
        $record['user_id'] = (int) $user_id;
        $record['invited_at'] = current_time('mysql');
        $record['note'] = 'WordPress sent the new-user email with the set-password link. '
            . 'The invitee makes the application password under Users → Profile, then signs in to Command Center.';
        self::save($record);

        return $record + [
            'created' => true,
            'application_passwords_available' => true,
            'role' => self::ROLE,
            'message' => 'Invited as administrator. WordPress emailed the set-password link.'
        ];
    }

    /** A login that is readable and free, derived from the address rather than invented. */
    public static function login_for(string $email): string {
        $base = sanitize_user((string) strtok($email, '@'), true);
        if ($base === '') {
            $base = 'commandcenter';
        }
        $login = $base;
        for ($i = 2; $i <= 20 && username_exists($login); $i++) {
            $login = $base . $i;
        }
        if (username_exists($login)) {
            $login = $base . substr(md5($email), 0, 6);
        }
        return $login;
    }

    public static function app_passwords_available(): bool {
        return function_exists('wp_is_application_passwords_available')
            ? (bool) wp_is_application_passwords_available()
            : false;
    }
}
