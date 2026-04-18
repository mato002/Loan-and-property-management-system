<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Property STK Webhook Testing

The property STK callback endpoint is:

- `POST /webhooks/property/payments/stk-callback`
- Header: `X-Property-Webhook-Secret: <PROPERTY_WEBHOOK_SECRET>`

Set your env value first:

```env
PROPERTY_WEBHOOK_SECRET=replace-with-a-long-random-secret
```

Then clear config cache if needed:

```bash
php artisan config:clear
```

### Example: success callback

```bash
curl -X POST "http://127.0.0.1:8000/webhooks/property/payments/stk-callback" \
  -H "Content-Type: application/json" \
  -H "X-Property-Webhook-Secret: replace-with-a-long-random-secret" \
  -d '{
    "payment_id": 123,
    "status": "success",
    "external_ref": "QKJ8S92M",
    "paid_at": "2026-03-23 14:22:00",
    "message": "STK paid"
  }'
```

### Example: failed callback

```bash
curl -X POST "http://127.0.0.1:8000/webhooks/property/payments/stk-callback" \
  -H "Content-Type: application/json" \
  -H "X-Property-Webhook-Secret: replace-with-a-long-random-secret" \
  -d '{
    "payment_id": 123,
    "status": "failed",
    "external_ref": "QKJ8S92M",
    "message": "Customer canceled STK prompt"
  }'
```

### Notes

- Callback processing is idempotent for already-settled payments.
- `success` marks payment completed, allocates to open invoices (oldest first), and posts accounting entries.
- `failed` marks payment failed and stores callback metadata.

## Property SMS Ingest Webhook Testing

The SMS ingest endpoint is:

- `POST /webhooks/property/payments/sms-ingest`
- Header: `X-Property-Sms-Secret: <PROPERTY_SMS_INGEST_SECRET>`

Set your env value first:

```env
PROPERTY_SMS_INGEST_SECRET=replace-with-a-long-random-secret
```

Then clear config cache if needed:

```bash
php artisan config:clear
```

### Example: Equity SMS format (from forwarded inbox)

```bash
curl -X POST "http://127.0.0.1:8000/webhooks/property/payments/sms-ingest" \
  -H "Content-Type: application/json" \
  -H "X-Property-Sms-Secret: replace-with-a-long-random-secret" \
  -d '{
    "provider": "equity",
    "source_device": "android-forwarder-01",
    "raw_message": "Confirmed. KES. 6,300.00 from NOBERT OKARE OMOLLO Phone No. 254799544357 received via M-Pesa Ref. UCVKMB3UG9 on 31-03-2026 at 23:48. Thank you."
  }'
```

### Example: M-Pesa style (provider explicit)

```bash
curl -X POST "http://127.0.0.1:8000/webhooks/property/payments/sms-ingest" \
  -H "Content-Type: application/json" \
  -H "X-Property-Sms-Secret: replace-with-a-long-random-secret" \
  -d '{
    "provider": "mpesa",
    "source_device": "android-forwarder-01",
    "raw_message": "UD192BBN4X Confirmed. Ksh2,000.00 received from SAMUEL NDUNGU 254728580788 on 01/04/26 at 10:24 AM."
  }'
```

### Response outcomes

- `status: matched` -> payment posted to tenant payment pipeline.
- `status: unmatched` -> payment stored in unmatched queue for manual assignment.
- `status: duplicate` -> transaction already processed; no re-post.
- `status: ignored` -> SMS skipped (unsupported provider or non-payment message).

## Loan SMS Ingest Webhook

Point the same SMS forwarder app at the loan endpoint when you want collections to land in **Loan book → Payments (unposted)** instead of property.

- `POST /webhooks/loan/payments/sms-ingest`
- Header: `X-Loan-Sms-Secret: <LOAN_SMS_INGEST_SECRET>` (or `secret` in query/body, same as property)

```env
LOAN_SMS_INGEST_SECRET=replace-with-a-long-random-secret
```

### Feeding both Property + Loan from one source

- If the forwarder posts to **either** property or loan SMS ingest endpoint, the app mirrors the same payload to the other module.
- To avoid double loops, mirroring uses an internal guard header and remains idempotent by transaction code.
- Secret behavior:
  - Recommended: set both `PROPERTY_SMS_INGEST_SECRET` and `LOAN_SMS_INGEST_SECRET`.
  - Shortcut: if `LOAN_SMS_INGEST_SECRET` is blank, Loan ingest falls back to `PROPERTY_SMS_INGEST_SECRET`.

The JSON body matches the property examples (`provider`, `source_device`, `raw_message`, optional `amount`, `paid_at`, `payer_phone`, `payload`). When the payer phone matches a **loan client** phone and that client has an **active** or **pending disbursement** loan, an **unposted** `loan_book_payments` row is created (`channel` `mpesa`, receipt = transaction code). Otherwise the ingest row is stored as **unmatched** for follow-up.

### Response outcomes (loan)

- `status: matched` -> unposted loan payment created; staff can post from the loan portal.
- `status: unmatched` -> ingest logged; no payment (no matching client phone / no eligible loan).
- `status: duplicate` -> receipt already linked to a loan payment or this ingest was already processed.
- `status: ignored` -> same rules as property (non-payment SMS or unsupported provider).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
