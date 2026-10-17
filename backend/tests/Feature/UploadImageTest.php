<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uploads_an_image_and_returns_a_public_url(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/uploads', [
            'image' => UploadedFile::fake()->image('photo.jpg', 400, 300),
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['url', 'path', 'name'],
            ]);

        Storage::disk('public')->assertExists($response->json('data.path'));
    }
}
