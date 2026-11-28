<?php

declare(strict_types=1);

/*
| LeadAppointment configuration.
|
| Business facts live here, not in code: the three metros, their timezones, the
| duplicate window, the public throttle, and the external providers' credentials.
| Secrets are env-only; the defaults are safe for local/tests.
*/

return [
    'dedupe_window_days' => (int) env('LEAD_DEDUPE_WINDOW_DAYS', 30),

    /*
    | One crew, one inspection per day: 7 h on site + 1 h travel = 8 h of the
    | 9 h window. The travel buffer is blocked, so a 16:00 start can never
    | become a 23:00 finish.
    */
    'inspection_duration_minutes' => 420,
    'travel_buffer_minutes' => 60,
    'daily_inspection_capacity' => 1,

    /*
    | Region used when neither the lead nor the request carries a metro. The
    | inspection stays valid; the office can correct the region later.
    */
    'default_region' => env('LEAD_APPOINTMENT_DEFAULT_REGION', 'houston'),

    'calendar_max_days' => 92,

    'availability_rules' => [
        'max_active' => (int) env('AVAILABILITY_RULES_MAX_ACTIVE', 50),
        'max_per_weekday' => (int) env('AVAILABILITY_RULES_MAX_PER_WEEKDAY', 10),
        'max_trashed_read' => (int) env('AVAILABILITY_RULES_MAX_TRASHED_READ', 100),
        'throttle_per_minute' => (int) env('AVAILABILITY_RULES_THROTTLE_PER_MINUTE', 20),
    ],

    // The source report is a cohort aggregate over the lead table. One year is
    // the largest window that stays a bounded read on the current indexes.
    'report_max_days' => 366,

    /*
    | Version identifier of the SMS consent disclosure currently published on
    | the landing page. The form may send its own version; this is the fallback
    | used when it does not. Bump it whenever the disclosure text changes.
    */
    'sms_consent_version' => env('SMS_CONSENT_VERSION', 'sms-v1-2026-11-20'),

    'public' => [
        'throttle_per_minute' => 5,
        'throttle_per_day' => 100,
        'honeypot_throttle_per_minute' => 30,
    ],

    'spam' => [
        'threshold' => 5,
        'max_links' => 1,
        'keywords' => [
            'seo' => 3,
            'crypto' => 3,
            'casino' => 3,
            'loan' => 3,
            'viagra' => 5,
            'backlink' => 3,
            'guest post' => 3,
            'work from home' => 3,
        ],

        /*
        | Placeholder and reserved domains. RFC 2606/6761 reserves example.* and
        | .test/.invalid/.localhost; the rest are common form placeholders. These
        | are rejected on the public endpoint and scored by the action.
        */
        'blocked_email_domains' => [
            'example.com',
            'example.net',
            'example.org',
            'example.edu',
            'test.com',
            'company.com',
            'company.net',
            'company.org',
            'domain.com',
            'yourdomain.com',
            'mydomain.com',
            'site.com',
            'website.com',
            'mywebsite.com',
        ],

        /*
        | Known disposable/temporary inbox providers. Keep this list curated;
        | a maintained package can replace it later without touching the guard.
        */
        'disposable_email_domains' => [
            '10minutemail.com',
            '33mail.com',
            'anonbox.net',
            'burnermail.io',
            'deadaddress.com',
            'discard.email',
            'dispostable.com',
            'emailfake.com',
            'emailondeck.com',
            'fakeinbox.com',
            'generator.email',
            'getnada.com',
            'guerrillamail.com',
            'inboxkitten.com',
            'mailcatch.com',
            'maildrop.cc',
            'mailinator.com',
            'mailnesia.com',
            'mailtemp.info',
            'mailtemporarily.com',
            'mintemail.com',
            'moakt.com',
            'mytemp.email',
            'sharklasers.com',
            'spamfree24.org',
            'spamgourmet.com',
            'spamgourmet.net',
            'spamgourmet.org',
            'spamherelots.com',
            'spamhereplease.com',
            'spamspot.com',
            'spamthis.co.uk',
            'spamthisplease.com',
            'superrito.com',
            'teleworm.us',
            'tempail.com',
            'tempinbox.com',
            'tempmail.com',
            'tempmailo.com',
            'temp-mail.org',
            'tempsky.com',
            'throwawaymail.com',
            'tmail.ws',
            'tmailinator.com',
            'tmpmail.net',
            'tmpmail.org',
            'trash-amil.com',
            'trash-mail.at',
            'trash-mail.com',
            'trash-mail.de',
            'trash2009.com',
            'trashdevil.com',
            'trashemail.de',
            'trashmail.at',
            'trashmail.com',
            'trashmail.de',
            'trashmail.io',
            'trashmail.me',
            'trashmail.net',
            'trashmail.org',
            'trashmail.ws',
            'trashymail.com',
            'trashymail.net',
            'trbvm.com',
            'tyldd.com',
            'uggsrock.com',
            'veryrealemail.com',
            'viditag.com',
            'viewcastmedia.com',
            'viewcastmedia.net',
            'viewcastmedia.org',
            'vomoto.com',
            'webemail.me',
            'webm4il.info',
            'wegwerfmail.de',
            'wegwerfmail.net',
            'wegwerfmail.org',
            'wh4f.org',
            'whyspam.me',
            'willselfdestruct.com',
            'winemaven.info',
            'wronghead.com',
            'wuzup.net',
            'xagloo.com',
            'xemaps.com',
            'xents.com',
            'xmaily.com',
            'xoxy.net',
            'yep.it',
            'yogamaven.com',
            'yopmail.com',
            'yopmail.fr',
            'yopmail.net',
            'yuurok.com',
            'zehnminuten.de',
            'zehnminutenmail.de',
            'zetmail.com',
            'zippymail.info',
            'zoaxe.com',
            'zoemail.com',
            'zoemail.net',
            'zoemail.org',
        ],

        /*
        | Role mailboxes are not rejected: a legitimate business may use info@.
        | They add score so the office can review the lead.
        */
        'role_email_prefixes' => [
            'admin',
            'administracion',
            'administrator',
            'billing',
            'careers',
            'contact',
            'contacto',
            'demo',
            'email',
            'hello',
            'hostmaster',
            'info',
            'jobs',
            'legal',
            'mail',
            'marketing',
            'media',
            'newsletter',
            'no-reply',
            'noreply',
            'office',
            'postmaster',
            'press',
            'privacy',
            'root',
            'sales',
            'security',
            'soporte',
            'spam',
            'support',
            'team',
            'test',
            'user',
            'ventas',
            'webmaster',
        ],
    ],

    'google_calendar' => [
        // Reuses the package config (`config/google-calendar.php`); kept here so
        // the module can decide whether sync is on without reading a vendor key.
        'enabled' => (bool) env('GOOGLE_CALENDAR_SYNC', false),
    ],

    'meta' => [
        'graph_version' => env('META_GRAPH_VERSION', 'v27.0'),
        'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
        'app_secret' => env('META_APP_SECRET'),
        'verify_token' => env('META_VERIFY_TOKEN'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
    ],
];
