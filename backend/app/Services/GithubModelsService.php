<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GithubModelsService
{
    public function extractStructuredData(string $userMessage, array $conversationHistory = []): array
    {
        $contextNote = '';
        if (!empty($conversationHistory)) {
            $contextNote = "\n\nPrevious conversation context:\n";
            foreach (array_slice($conversationHistory, -4) as $msg) {
                $role = $msg['role'] === 'assistant' ? 'Assistant' : 'User';
                $contextNote .= "{$role}: {$msg['content']}\n";
            }
            $contextNote .= "\nUse this context to understand what the user previously mentioned about their lost item.";
        }

        $systemPrompt = <<<PROMPT
You are an assistant for a university lost-and-found system.

Extract structured data from the user's message. Pay attention to details they mentioned earlier in the conversation.{$contextNote}

Return ONLY JSON:
{
  "item_type": string|null,
  "category": string|null,
  "color": string|null,
  "brand": string|null,
  "location": string|null,
  "keywords": string[],
  "attributes": string[],
  "needs_followup": boolean,
  "followup_question": string|null
}
PROMPT;

        $url = config('services.github_models.url');
        $token = config('services.github_models.token');
        $model = config('services.github_models.model');

        if (!$url || !$token) {
            return $this->defaultStructuredData();
        }

        
        $verifyOption = app()->environment('local')
            ? base_path('certs/cacert.pem')
            : true;

        $response = Http::withToken($token)
            ->withOptions([
                'verify' => $verifyOption,
            ])
            ->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'temperature' => 0.2,
            ]);

        if (!$response->successful()) {
            return $this->defaultStructuredData();
        }

        $content = data_get($response->json(), 'choices.0.message.content', '');
        $decoded = json_decode($content, true);

        return is_array($decoded)
            ? $decoded
            : $this->defaultStructuredData();
    }

    public function summarizeMatches(string $userMessage, array $structuredData, array $matches): string
    {
        $url = config('services.github_models.url');
        $token = config('services.github_models.token');
        $model = config('services.github_models.model');

        if (!$url || !$token) {
            return 'Unable to generate AI response.';
        }

        $verifyOption = app()->environment('local')
            ? base_path('certs/cacert.pem')
            : true;

        $payload = [
            'user_message' => $userMessage,
            'structured_data' => $structuredData,
            'matches' => $matches,
        ];

        $response = Http::withToken($token)
            ->withOptions([
                'verify' => $verifyOption,
            ])
            ->post($url, [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Summarize matching lost items for the user in a short helpful way.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($payload),
                    ],
                ],
                'temperature' => 0.4,
            ]);

        if (!$response->successful()) {
            return 'Found some possible matches. Please check the list below.';
        }

        return data_get($response->json(), 'choices.0.message.content', 'Check the results below.');
    }

    private function defaultStructuredData(): array
    {
        return [
            'item_type' => null,
            'category' => null,
            'color' => null,
            'brand' => null,
            'location' => null,
            'keywords' => [],
            'attributes' => [],
            'needs_followup' => true,
            'followup_question' => 'Can you provide more details?',
        ];
    }
}