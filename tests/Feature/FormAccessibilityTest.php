<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\FieldAria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FormAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role(Role::VoucherAdmin)->create();
    }

    public function test_clean_form_has_no_invalid_markers_but_links_hints(): void
    {
        $html = $this->actingAs($this->admin())->get('/brands/create')->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-invalid', $html);
        // Slug has a hint: control points at it, and the hint carries that id.
        $this->assertMatchesRegularExpression('/<input[^>]*name="slug"[^>]*aria-describedby="slug-hint"/', $html);
        $this->assertStringContainsString('id="slug-hint"', $html);
        // Name has no hint and no error: nothing to describe it with.
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="name"[^>]*aria-describedby/', $html);
    }

    public function test_validation_error_is_linked_to_its_control(): void
    {
        $html = $this->actingAs($this->admin())
            ->from('/brands/create')
            ->followingRedirects()
            ->post('/brands', ['name' => '', 'code_prefix' => 'OK'])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="name"[^>]*aria-describedby="name-error"[^>]*aria-invalid="true"/', $html);
        $this->assertMatchesRegularExpression('/<p id="name-error"[^>]*>/', $html);
        // A valid sibling field is not marked invalid.
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="code_prefix"[^>]*aria-invalid/', $html);
    }

    public function test_hint_and_error_are_both_referenced(): void
    {
        $errors = (new ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['slug' => ['Slug dipakai.']]));
        view()->share('errors', $errors);

        $this->assertSame('aria-describedby="slug-hint slug-error" aria-invalid="true"', (string) FieldAria::attrs('slug', true));
        $this->assertSame('aria-describedby="slug-error" aria-invalid="true"', (string) FieldAria::attrs('slug'));
        $this->assertSame('aria-describedby="name-hint"', (string) FieldAria::attrs('name', true));
        $this->assertSame('', (string) FieldAria::attrs('name'));
    }

    public function test_conditional_hint_is_only_referenced_when_rendered(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();
        $other = User::factory()->create();

        // Creating: the "Kosongkan bila tidak diganti" hint is absent, so must not be referenced.
        $create = $this->actingAs($admin)->get('/users/create')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="password-hint"', $create);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="password"[^>]*aria-describedby="password-hint"/', $create);

        // Editing: hint rendered and referenced.
        $edit = $this->get("/users/{$other->id}/edit")->assertOk()->getContent();
        $this->assertStringContainsString('id="password-hint"', $edit);
        $this->assertMatchesRegularExpression('/<input[^>]*name="password"[^>]*aria-describedby="password-hint"/', $edit);
    }

    public function test_array_field_names_map_to_dotted_error_keys(): void
    {
        $errors = (new ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['menu_items.0.name' => ['Wajib diisi.']]));
        view()->share('errors', $errors);

        $this->assertSame(
            'aria-describedby="menu_items[0][name]-error" aria-invalid="true"',
            (string) FieldAria::attrs('menu_items[0][name]'),
        );
    }
}
