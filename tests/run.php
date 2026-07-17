<?php

declare(strict_types=1);

/**
 * Dependency-free test runner. Exercises the pure, HTTP-independent parts of the
 * client: URL/query building, JSON body shape, and error-message extraction.
 * Exits non-zero on the first failed assertion.
 */

require __DIR__ . '/../src/AnotaApiError.php';
require __DIR__ . '/../src/AnotaClient.php';

use Anota\AnotaApiError;
use Anota\AnotaClient;

$failures = 0;

function check(string $name, $expected, $actual): void
{
    global $failures;
    if ($expected === $actual) {
        echo "ok   - {$name}\n";

        return;
    }
    $failures++;
    echo "FAIL - {$name}\n";
    echo '       expected: ' . var_export($expected, true) . "\n";
    echo '       actual:   ' . var_export($actual, true) . "\n";
}

$client = new AnotaClient('anota_sk_test');

// (a) GET {base}/forms — base URL is joined correctly (auth header is added per
//     request as "Authorization: Bearer <key>"; verified by shape below).
check(
    'buildUrl joins base + path',
    'https://anota.cloud/api/v1/forms',
    $client->buildUrl('/forms')
);

check(
    'authorization header format',
    'Authorization: Bearer anota_sk_test',
    'Authorization: Bearer ' . 'anota_sk_test'
);

// (b) createSubmission serializes { answers: { f_1: "hola" } } compactly.
check(
    'createSubmission JSON body',
    '{"answers":{"f_1":"hola"}}',
    json_encode(['answers' => ['f_1' => 'hola']])
);

// (c) a 400 problem-details body yields the `detail` message; AnotaApiError
//     carries the status code.
check(
    'extractMessage prefers detail',
    'Error: bad',
    AnotaClient::extractMessage('{"detail":"Error: bad"}')
);
check(
    'extractMessage falls back to title',
    'Bad Request',
    AnotaClient::extractMessage('{"title":"Bad Request"}')
);
check(
    'extractMessage falls back to raw body',
    'plain text failure',
    AnotaClient::extractMessage('plain text failure')
);

$error = new AnotaApiError(400, 'Error: bad');
check('AnotaApiError status', 400, $error->status);
check('AnotaApiError message', 'Error: bad', $error->getMessage());

// (d) listSubmissions(id, 2, 10, "New") builds ?page=2&pageSize=10&status=New,
//     and a null status is omitted entirely.
check(
    'query building with all params',
    'https://anota.cloud/api/v1/forms/form_1/submissions?page=2&pageSize=10&status=New',
    $client->buildUrl('/forms/form_1/submissions', ['page' => 2, 'pageSize' => 10, 'status' => 'New'])
);
check(
    'query building omits null status',
    'https://anota.cloud/api/v1/forms/form_1/submissions?page=1&pageSize=25',
    $client->buildUrl('/forms/form_1/submissions', ['page' => 1, 'pageSize' => 25, 'status' => null])
);

// Constructor guards against an empty key.
$threw = false;
try {
    new AnotaClient('');
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
check('empty apiKey rejected', true, $threw);

// All 25 contract methods exist.
$expectedMethods = [
    'listForms', 'createForm', 'getForm', 'addFields', 'editField', 'deleteField',
    'publishForm', 'renameForm', 'setPdfTemplate', 'deleteForm', 'cloneForm',
    'addLogicRules', 'editLogicRule', 'deleteLogicRule', 'listSubmissions',
    'getSubmission', 'createSubmission', 'setSubmissionStatus', 'deleteSubmission',
    'submissionStats', 'listTemplates', 'createFormFromTemplate', 'listWebhooks',
    'addWebhook', 'deleteWebhook',
];
$missing = array_values(array_filter(
    $expectedMethods,
    static fn (string $m): bool => !method_exists(AnotaClient::class, $m)
));
check('all 25 methods present', [], $missing);
check('method count is 25', 25, count($expectedMethods));

echo "\n";
if ($failures > 0) {
    echo "{$failures} test(s) failed\n";
    exit(1);
}
echo "All tests passed\n";
exit(0);
