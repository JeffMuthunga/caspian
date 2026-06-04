<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_query_proxies_to_ontology_with_nafdac_nmra_id(): void
    {
        Http::fake([
            '*/ai/query' => Http::response([
                'answer'  => 'Active recall found [Recall RWANDA_FDA:some-uuid].',
                'sources' => [['object_id' => 'some-uuid', 'chunk_text' => 'Recall data.']],
            ], 200),
        ]);

        $this->postJson('/api/ai/query', ['question' => 'Are there any active recalls?'])
             ->assertStatus(200)
             ->assertJsonStructure(['answer', 'sources'])
             ->assertJsonPath('answer', 'Active recall found [Recall RWANDA_FDA:some-uuid].');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/ai/query')
                && $request['nmra_id'] === 'NAFDAC'
                && $request['question'] === 'Are there any active recalls?';
        });
    }

    public function test_ai_query_requires_question_field(): void
    {
        $this->postJson('/api/ai/query', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['question']);
    }
}
