# anota-api-php · Official PHP client for the [anota](https://anota.cloud) API

**[Léeme en español](README.es.md)** · [Interactive API reference](https://anota.cloud/developers) · [All SDKs](https://github.com/anotacloud/anota-api)

![CI](https://github.com/anotacloud/anota-api-php/actions/workflows/ci.yml/badge.svg)

Create and publish forms, edit fields and conditional logic, read and write
submissions, and wire webhooks — everything the anota REST API can do, from PHP.

Thin and dependency-free: a single curl-based client, no framework required.
Every method returns the API's JSON decoded into PHP arrays.

## Install

Add this repository to your `composer.json` and require the package:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/anotacloud/anota-api-php" }
    ],
    "require": {
        "anotacloud/anota-api": "^1.0"
    }
}
```

```bash
composer update anotacloud/anota-api
```

Or download the [ZIP](https://github.com/anotacloud/anota-api-php/archive/refs/heads/main.zip) /
[Tarball](https://github.com/anotacloud/anota-api-php/archive/refs/heads/main.tar.gz) and
`require` the two files in `src/` directly — there are no dependencies.

Requires PHP 8.1+ with the `curl` and `json` extensions.

## Quickstart

```php
<?php

require 'vendor/autoload.php'; // or require the two files in src/ directly

use Anota\AnotaClient;

$client = new AnotaClient(getenv('ANOTA_API_KEY'));

$form = $client->createForm('Contact us', [
    ['type' => 'text', 'label' => 'Name', 'required' => true],
]);

$client->publishForm($form['id']);

$submissions = $client->listSubmissions($form['id']);
print_r($submissions);
```

## Authentication

Create an API key in your workspace at https://anota.cloud/api-keys and pass it to the
client. Keys look like `anota_sk_…` and also power the Claude MCP connector.

```php
$client = new AnotaClient('anota_sk_…');
// Point at a different base URL if needed:
$client = new AnotaClient('anota_sk_…', 'https://anota.cloud/api/v1');
```

## All methods

Return values are PHP arrays decoded from the API's JSON (or `null` for an empty body).
`$fields` / `$field` are associative arrays like `['type' => …, 'label' => …, 'required' => …]`.
`$rules` / `$rule` follow `['match' => 'all'|'any', 'if' => [...], 'then' => [...]]`.
`$answers` is keyed by field id, values string or array of strings.

| # | Method | HTTP |
|---|---|---|
| 1 | `listForms()` | `GET /forms` |
| 2 | `createForm($title, $fields, $description = null)` | `POST /forms` |
| 3 | `getForm($formId)` | `GET /forms/{formId}` |
| 4 | `addFields($formId, $fields)` | `POST /forms/{formId}/fields` |
| 5 | `editField($formId, $fieldId, $field)` | `PATCH /forms/{formId}/fields/{fieldId}` |
| 6 | `deleteField($formId, $fieldId)` | `DELETE /forms/{formId}/fields/{fieldId}` |
| 7 | `publishForm($formId)` | `POST /forms/{formId}/publish` |
| 8 | `renameForm($formId, $title)` | `PATCH /forms/{formId}` |
| 9 | `setPdfTemplate($formId, $key)` | `PUT /forms/{formId}/pdf-template` |
| 10 | `deleteForm($formId)` | `DELETE /forms/{formId}` |
| 11 | `cloneForm($formId)` | `POST /forms/{formId}/clone` |
| 12 | `addLogicRules($formId, $rules)` | `POST /forms/{formId}/logic-rules` |
| 13 | `editLogicRule($formId, $ruleId, $rule)` | `PUT /forms/{formId}/logic-rules/{ruleId}` |
| 14 | `deleteLogicRule($formId, $ruleId)` | `DELETE /forms/{formId}/logic-rules/{ruleId}` |
| 15 | `listSubmissions($formId, $page = 1, $pageSize = 25, $status = null)` | `GET /forms/{formId}/submissions` |
| 16 | `getSubmission($submissionId)` | `GET /submissions/{submissionId}` |
| 17 | `createSubmission($formId, $answers)` | `POST /forms/{formId}/submissions` |
| 18 | `setSubmissionStatus($submissionId, $status)` | `PATCH /submissions/{submissionId}/status` |
| 19 | `deleteSubmission($submissionId)` | `DELETE /submissions/{submissionId}` |
| 20 | `submissionStats($formId)` | `GET /forms/{formId}/stats` |
| 21 | `listTemplates($language = 'es')` | `GET /templates?language=` |
| 22 | `createFormFromTemplate($templateId)` | `POST /forms/from-template/{templateId}` |
| 23 | `listWebhooks($formId)` | `GET /forms/{formId}/webhooks` |
| 24 | `addWebhook($formId, $url)` | `POST /forms/{formId}/webhooks` |
| 25 | `deleteWebhook($formId, $webhookId)` | `DELETE /forms/{formId}/webhooks/{webhookId}` |

A full runnable walkthrough lives in [`examples/end-to-end.php`](examples/end-to-end.php).

## Errors

Non-2xx responses raise `Anota\AnotaApiError` with the HTTP status and the server's message:

```php
use Anota\AnotaApiError;

try {
    $client->publishForm('does-not-exist');
} catch (AnotaApiError $e) {
    echo "$e->status: {$e->getMessage()}\n"; // e.g. 404: Form not found
}
```

Network-level failures surface as PHP's native `RuntimeException`, not `AnotaApiError`.

Note: once a form has been published, its existing fields are locked (`editField` /
`deleteField` return 400); you can always `addFields`.

## License

MIT
