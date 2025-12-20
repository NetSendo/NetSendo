<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TemplateImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_export_template()
    {
        $user = User::factory()->create();
        $template = Template::create([
            'user_id' => $user->id,
            'name' => 'Test Template',
            'content' => '<html></html>',
            'json_structure' => ['block' => 'test'],
            'settings' => ['color' => '#000'],
        ]);

        $response = $this->actingAs($user)->get(route('templates.export', $template));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
        
        $data = $response->json();
        $this->assertEquals('Test Template', $data['name']);
        $this->assertEquals(['block' => 'test'], $data['json_structure']);
    }

    public function test_can_import_template()
    {
        $user = User::factory()->create();
        
        $jsonContent = json_encode([
            'name' => 'Imported Theme',
            'json_structure' => ['block' => 'new'],
            'settings' => ['font' => 'Arial'],
        ]);
        
        $file = UploadedFile::fake()->createWithContent('theme.json', $jsonContent);

        $response = $this->actingAs($user)->post(route('templates.import'), [
            'file' => $file
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('templates', [
            'user_id' => $user->id,
            'name' => 'Imported Theme (import)',
        ]);
        
        $template = Template::where('name', 'Imported Theme (import)')->first();
        $this->assertEquals(['block' => 'new'], $template->json_structure);
    }

    public function test_cannot_export_others_template()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $template = Template::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Template',
        ]);

        $response = $this->actingAs($user)->get(route('templates.export', $template));

        $response->assertStatus(403);
    }
}
