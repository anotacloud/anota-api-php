<?php

declare(strict_types=1);

/**
 * End-to-end walkthrough: create a form, add a field, publish it, record a
 * submission, then list submissions. Run with your key in the environment:
 *
 *   ANOTA_API_KEY=anota_sk_... php examples/end-to-end.php
 */

require __DIR__ . '/../src/AnotaApiError.php';
require __DIR__ . '/../src/AnotaClient.php';

use Anota\AnotaApiError;
use Anota\AnotaClient;

$apiKey = getenv('ANOTA_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Set ANOTA_API_KEY to run this example.\n");
    exit(1);
}

$client = new AnotaClient($apiKey);

try {
    echo "Creating form...\n";
    $form = $client->createForm('Contact us', [
        ['type' => 'text', 'label' => 'Name', 'required' => true],
    ], 'Created by the PHP SDK example');
    $formId = $form['id'];
    echo "  form id: {$formId}\n";

    echo "Adding an email field...\n";
    $client->addFields($formId, [
        ['type' => 'email', 'label' => 'Email', 'required' => true],
    ]);

    echo "Publishing...\n";
    $client->publishForm($formId);

    echo "Creating a submission...\n";
    $submission = $client->createSubmission($formId, [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    echo "  submission id: {$submission['id']}\n";

    echo "Listing submissions...\n";
    $submissions = $client->listSubmissions($formId);
    $count = is_array($submissions['items'] ?? null) ? count($submissions['items']) : 0;
    echo "  {$count} submission(s) so far\n";

    echo "Done.\n";
} catch (AnotaApiError $e) {
    fwrite(STDERR, "API error {$e->status}: {$e->getMessage()}\n");
    exit(1);
}
