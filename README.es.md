# anota-api-php · Cliente oficial de PHP para la API de [anota](https://anota.cloud)

**[Read this in English](README.md)** · [Referencia interactiva de la API](https://anota.cloud/developers) · [Todos los SDK](https://github.com/anotacloud/anota-api)

![CI](https://github.com/anotacloud/anota-api-php/actions/workflows/ci.yml/badge.svg)

Crea y publica formularios, edita campos y lógica condicional, lee y escribe
respuestas, y conecta webhooks: todo lo que puede hacer la API REST de anota, desde PHP.

Ligero y sin dependencias: un único cliente basado en curl, sin necesidad de framework.
Cada método devuelve el JSON de la API decodificado en arreglos de PHP.

## Instalación

Agrega este repositorio a tu `composer.json` y requiere el paquete:

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

O descarga el [ZIP](https://github.com/anotacloud/anota-api-php/archive/refs/heads/main.zip) /
[Tarball](https://github.com/anotacloud/anota-api-php/archive/refs/heads/main.tar.gz) y
haz `require` directamente de los dos archivos en `src/`: no hay dependencias.

Requiere PHP 8.1+ con las extensiones `curl` y `json`.

## Inicio rápido

```php
<?php

require 'vendor/autoload.php'; // o requiere directamente los dos archivos en src/

use Anota\AnotaClient;

$client = new AnotaClient(getenv('ANOTA_API_KEY'));

$form = $client->createForm('Contáctanos', [
    ['type' => 'text', 'label' => 'Nombre', 'required' => true],
]);

$client->publishForm($form['id']);

$submissions = $client->listSubmissions($form['id']);
print_r($submissions);
```

## Autenticación

Crea una clave de API en tu workspace en https://anota.cloud/api-keys y pásala al
cliente. Las claves tienen el formato `anota_sk_…` y también habilitan el conector MCP de Claude.

```php
$client = new AnotaClient('anota_sk_…');
// Apunta a otra URL base si lo necesitas:
$client = new AnotaClient('anota_sk_…', 'https://anota.cloud/api/v1');
```

## Todos los métodos

Los valores de retorno son arreglos de PHP decodificados del JSON de la API (o `null` si el
cuerpo está vacío). `$fields` / `$field` son arreglos asociativos como
`['type' => …, 'label' => …, 'required' => …]`.
`$rules` / `$rule` siguen `['match' => 'all'|'any', 'if' => [...], 'then' => [...]]`.
`$answers` está indexado por id de campo, con valores de tipo string o arreglo de strings.

| # | Método | HTTP |
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

**El secreto de firma del webhook se muestra una sola vez.** `addWebhook($formId, $url)` devuelve el `secret` completo (`whsec_…`) en su respuesta (`id`, `formId`, `url`, `secret`, `note`): guárdalo en ese momento. `listWebhooks($formId)` nunca lo devuelve: cada fila trae `secretHint` (`whsec_…` más los últimos 4 caracteres, o solo `whsec_…` si el secreto es corto) y `secretNote` en lugar de `secret`. Si lo pierdes, elimina el webhook y vuelve a agregarlo para obtener un secreto nuevo. Consulta [CHANGELOG.md](CHANGELOG.md).

Un recorrido completo y ejecutable está en [`examples/end-to-end.php`](examples/end-to-end.php).

## Errores

Las respuestas que no son 2xx lanzan `Anota\AnotaApiError` con el código de estado HTTP y el
mensaje del servidor:

```php
use Anota\AnotaApiError;

try {
    $client->publishForm('no-existe');
} catch (AnotaApiError $e) {
    echo "$e->status: {$e->getMessage()}\n"; // p. ej. 404: Form not found
}
```

Los fallos de red se manifiestan como la excepción nativa `RuntimeException` de PHP, no como
`AnotaApiError`.

Nota: una vez que un formulario ha sido publicado, sus campos existentes quedan bloqueados
(`editField` / `deleteField` devuelven 400); siempre puedes usar `addFields`.

## Licencia

MIT
