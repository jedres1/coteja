<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'dte_engine' => [
        'node' => env('DTE_ENGINE_NODE', 'node'),
    ],

    'purchase_invoice_mailbox' => [
        'host' => env('PURCHASE_INVOICE_MAIL_HOST', 'imap.gmail.com'),
        'port' => (int) env('PURCHASE_INVOICE_MAIL_PORT', 993),
        'username' => env('PURCHASE_INVOICE_MAIL_USERNAME', 'facturacioncoteja@gmail.com'),
        'password' => env('PURCHASE_INVOICE_MAIL_PASSWORD'),
        'mailbox' => env('PURCHASE_INVOICE_MAILBOX', 'INBOX'),
        'only_unseen' => (bool) env('PURCHASE_INVOICE_MAIL_ONLY_UNSEEN', true),
        'limit' => (int) env('PURCHASE_INVOICE_MAIL_LIMIT', 25),
    ],

];
