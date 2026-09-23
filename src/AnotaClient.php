<?php

declare(strict_types=1);

namespace Anota;

/**
 * A thin, dependency-free PHP client over the anota REST API.
 *
 * Every method returns the API's JSON decoded into PHP arrays (or `null` for an
 * empty body). Non-2xx responses raise {@see AnotaApiError}. Create an API key
 * at https://anota.cloud/api-keys and pass it to the constructor.
 */
class AnotaClient
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://anota.cloud/api/v1')
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException(
                'apiKey is required (create one at https://anota.cloud/api-keys)'
            );
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    // ----- testable pure helpers -----

    /**
     * Build the full request URL, dropping any query params whose value is null.
     */
    public function buildUrl(string $path, array $query = []): string
    {
        $url = $this->baseUrl . $path;
        $filtered = array_filter($query, static fn ($v): bool => $v !== null);
        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered);
        }

        return $url;
    }

    /**
     * Extract a human-readable message from an error response body: the
     * problem-details `detail`, falling back to `title`, then the raw body.
     */
    public static function extractMessage(string $body): string
    {
        $problem = json_decode($body, true);
        if (is_array($problem)) {
            if (isset($problem['detail']) && is_string($problem['detail'])) {
                return $problem['detail'];
            }
            if (isset($problem['title']) && is_string($problem['title'])) {
                return $problem['title'];
            }
        }

        return $body;
    }

    // ----- transport -----

    /**
     * @param  array<string, mixed>|null  $body   JSON request body, or null for none.
     * @param  array<string, mixed>       $query  Query params (null values omitted).
     * @return mixed  Decoded JSON, or null for an empty body.
     */
    private function request(string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $url = $this->buildUrl($path, $query);

        $headers = ['Authorization: Bearer ' . $this->apiKey];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            // Network-level failure surfaces as a native exception, not AnotaApiError.
            throw new \RuntimeException('anota API request failed: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            throw new AnotaApiError($status, self::extractMessage((string) $responseBody));
        }

        return $responseBody === '' ? null : json_decode((string) $responseBody, true);
    }

    // ----- forms -----

    public function listForms(): mixed
    {
        return $this->request('GET', '/forms');
    }

    public function createForm(string $title, array $fields, ?string $description = null): mixed
    {
        $body = ['title' => $title, 'fields' => $fields];
        if ($description !== null) {
            $body['description'] = $description;
        }

        return $this->request('POST', '/forms', $body);
    }

    public function getForm(string $formId): mixed
    {
        return $this->request('GET', '/forms/' . $formId);
    }

    public function addFields(string $formId, array $fields): mixed
    {
        return $this->request('POST', '/forms/' . $formId . '/fields', ['fields' => $fields]);
    }

    public function editField(string $formId, string $fieldId, array $field): mixed
    {
        return $this->request(
            'PATCH',
            '/forms/' . $formId . '/fields/' . rawurlencode($fieldId),
            ['field' => $field]
        );
    }

    public function deleteField(string $formId, string $fieldId): mixed
    {
        return $this->request(
            'DELETE',
            '/forms/' . $formId . '/fields/' . rawurlencode($fieldId)
        );
    }

    public function publishForm(string $formId): mixed
    {
        return $this->request('POST', '/forms/' . $formId . '/publish');
    }

    public function renameForm(string $formId, string $title): mixed
    {
        return $this->request('PATCH', '/forms/' . $formId, ['title' => $title]);
    }

    public function setPdfTemplate(string $formId, string $key): mixed
    {
        return $this->request('PUT', '/forms/' . $formId . '/pdf-template', ['key' => $key]);
    }

    public function deleteForm(string $formId): mixed
    {
        return $this->request('DELETE', '/forms/' . $formId);
    }

    public function cloneForm(string $formId): mixed
    {
        return $this->request('POST', '/forms/' . $formId . '/clone');
    }

    // ----- logic rules -----

    public function addLogicRules(string $formId, array $rules): mixed
    {
        return $this->request('POST', '/forms/' . $formId . '/logic-rules', ['rules' => $rules]);
    }

    public function editLogicRule(string $formId, string $ruleId, array $rule): mixed
    {
        return $this->request(
            'PUT',
            '/forms/' . $formId . '/logic-rules/' . rawurlencode($ruleId),
            ['rule' => $rule]
        );
    }

    public function deleteLogicRule(string $formId, string $ruleId): mixed
    {
        return $this->request(
            'DELETE',
            '/forms/' . $formId . '/logic-rules/' . rawurlencode($ruleId)
        );
    }

    // ----- submissions -----

    public function listSubmissions(string $formId, int $page = 1, int $pageSize = 25, ?string $status = null): mixed
    {
        return $this->request(
            'GET',
            '/forms/' . $formId . '/submissions',
            null,
            ['page' => $page, 'pageSize' => $pageSize, 'status' => $status]
        );
    }

    public function getSubmission(string $submissionId): mixed
    {
        return $this->request('GET', '/submissions/' . $submissionId);
    }

    public function createSubmission(string $formId, array $answers): mixed
    {
        return $this->request('POST', '/forms/' . $formId . '/submissions', ['answers' => $answers]);
    }

    public function setSubmissionStatus(string $submissionId, string $status): mixed
    {
        return $this->request('PATCH', '/submissions/' . $submissionId . '/status', ['status' => $status]);
    }

    public function deleteSubmission(string $submissionId): mixed
    {
        return $this->request('DELETE', '/submissions/' . $submissionId);
    }

    public function submissionStats(string $formId): mixed
    {
        return $this->request('GET', '/forms/' . $formId . '/stats');
    }

    // ----- templates -----

    public function listTemplates(string $language = 'es'): mixed
    {
        return $this->request('GET', '/templates', null, ['language' => $language]);
    }

    public function createFormFromTemplate(string $templateId): mixed
    {
        return $this->request('POST', '/forms/from-template/' . $templateId);
    }

    // ----- webhooks -----

    /**
     * Lists a form's webhooks. Each row has id, url, events, enabled, secretHint and secretNote.
     * The full signing secret is never returned here: secretHint is a masked form
     * ("whsec_…" + last 4 characters, or just "whsec_…" for short secrets) that identifies
     * which secret a receiver holds, and secretNote explains the show-once rule. To replace a
     * lost secret, delete the webhook and add it again.
     */
    public function listWebhooks(string $formId): mixed
    {
        return $this->request('GET', '/forms/' . $formId . '/webhooks');
    }

    /**
     * Registers a webhook URL that receives submission.created events. The response
     * (id, formId, url, secret, note) is the ONLY place the full signing secret appears:
     * store it now, it cannot be read back later (listWebhooks shows only secretHint).
     */
    public function addWebhook(string $formId, string $url): mixed
    {
        return $this->request('POST', '/forms/' . $formId . '/webhooks', ['url' => $url]);
    }

    public function deleteWebhook(string $formId, string $webhookId): mixed
    {
        return $this->request('DELETE', '/forms/' . $formId . '/webhooks/' . $webhookId);
    }
}
