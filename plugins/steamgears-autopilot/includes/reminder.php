<?php
/**
 * Reminders — the queue item the API cannot post, handed to the owner instead.
 *
 * P1.2: a queue item can be flagged "remind me instead of posting". Formats the
 * networks only accept through their own app (a story, a carousel, a native
 * upload, a manual pin) used to fail after three attempts, burn the retries and
 * end up in the failed list — the work was done twice anyway, once by the
 * autopilot and once by the owner reading the error.
 *
 * With the flag set, the item is neither sent to Publer nor released into the
 * RSS feed. It is mailed to the owner with everything needed to post it by hand,
 * and its status becomes `reminded` — a terminal state, so nothing retries it
 * and no failure alert fires.
 *
 * A reminder is not an alert: it is the post itself. It is therefore sent even
 * when Settings → Alerts is set to `off`, but it uses the same recipient
 * (`alert_email`, falling back to the admin address). The same queue item is
 * never mailed twice inside REPEAT.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Reminder {
    const LAST = 'sgap_reminders_last';
    const REPEAT = 6 * HOUR_IN_SECONDS;
    const KEEP = 200;

    /** Formats where the network only takes the post from its own app. */
    const MANUAL = ['story', 'carousel', 'reel'];

    /** Why this item is not going out on its own. */
    public static function reason(string $channel = '', string $format = ''): string {
        $channel = $channel !== '' ? $channel : 'this network';
        if ($format !== '' && in_array($format, self::MANUAL, true)) {
            return "A {$format} on {$channel} has to be created in {$channel}'s own app; the API does not take it from here.";
        }
        return "This item is marked “remind me”, so the autopilot leaves it to you instead of posting it on {$channel}.";
    }

    public static function subject(array $row): string {
        $channel = (string) ($row['channel'] ?? 'post');
        $format = (string) ($row['format'] ?? '');
        return 'Steamgears: post this ' . ($format !== '' ? $format . ' ' : '') . 'on ' . $channel . ' by hand';
    }

    /** Everything needed to post it by hand, in the order it is needed. */
    public static function message(array $row): string {
        $id = (int) ($row['id'] ?? 0);
        $channel = (string) ($row['channel'] ?? '');
        $format = (string) ($row['format'] ?? '');
        $lines = [
            self::reason($channel, $format),
            '',
            'Channel: ' . ($channel !== '' ? $channel : '(none)'),
        ];
        if ($format !== '') {
            $lines[] = 'Format: ' . $format;
        }
        if (!empty($row['title'])) {
            $lines[] = 'Title: ' . (string) $row['title'];
        }
        if (!empty($row['scheduled_at'])) {
            $lines[] = 'Was due: ' . (string) $row['scheduled_at'];
        }
        $lines[] = '';
        $lines[] = 'Text:';
        $lines[] = !empty($row['body']) ? (string) $row['body'] : '(no caption was written)';
        $lines[] = '';
        $lines[] = 'Image: ' . (!empty($row['image_url']) ? (string) $row['image_url'] : 'none — pick one from the media library');
        $link = (string) (($row['utm_url'] ?? '') !== '' ? $row['utm_url'] : ($row['destination_url'] ?? ''));
        $lines[] = 'Link: ' . ($link !== '' ? $link : '(none)');
        $lines[] = '';
        $lines[] = 'Queue item #' . $id . ': ' . admin_url('admin.php?page=sgap-queue');
        $lines[] = '';
        $lines[] = 'The item is now “reminded”: nothing else happens to it, and it is not counted as a failure.';
        $lines[] = 'Re-queue it on the Queue page if you want the autopilot to try again, or delete it once you have posted it.';
        return implode("\n", $lines);
    }

    /**
     * Mails the reminder. Returns whether a mail actually left the site; the
     * item is marked either way, so a broken mail setup cannot leave it spinning.
     */
    public static function send(array $row): bool {
        $id = (int) ($row['id'] ?? 0);
        $to = SGAP_Alerts::recipient();
        if ($to === '' || !is_email($to)) {
            return false;
        }
        $now = time();
        $last = (array) get_option(self::LAST, []);
        foreach ($last as $k => $ts) {
            if ($now - (int) $ts > DAY_IN_SECONDS) {
                unset($last[$k]);
            }
        }
        if (isset($last[(string) $id]) && ($now - (int) $last[(string) $id]) < self::REPEAT) {
            return false;
        }
        $sent = wp_mail($to, self::subject($row), self::message($row));
        if ($sent) {
            $last[(string) $id] = $now;
            if (count($last) > self::KEEP) {
                $last = array_slice($last, -self::KEEP, null, true);
            }
            update_option(self::LAST, $last, false);
        }
        return (bool) $sent;
    }

    /**
     * Marks the item as reminded and mails it. Called from both publishers, so
     * the API path and the RSS path behave the same way.
     *
     * @return array{id:int,status:?string,mailed:bool,message:string}
     */
    public static function deliver(array $row): array {
        $id = (int) ($row['id'] ?? 0);
        $mailed = self::send($row);
        $status = SGAP_Queue::mark($id, 'reminded');
        $message = $mailed
            ? 'Reminder mailed to ' . SGAP_Alerts::recipient()
            : 'No reminder mail left the site (mail off, no address, or already mailed) — the item is still marked reminded';
        return ['id' => $id, 'status' => $status, 'mailed' => $mailed, 'message' => $message];
    }
}
