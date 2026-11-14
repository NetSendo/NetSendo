<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_custom_field_can_be_created_using_factory_and_persisted(): void
    {
        $field = CustomField::factory()->create();

        $this->assertNotNull($field);
        $this->assertModelExists($field);
        $this->assertDatabaseHas('custom_fields', [
            'id' => $field->id,
            'user_id' => $field->user_id,
            'name' => $field->name,
            'type' => 'text',
            'scope' => 'global',
            'contact_list_id' => null,
        ]);

        $this->assertTrue(CustomField::isValidName($field->name));
        $this->assertFalse(CustomField::isReservedName($field->name));
    }

    public function test_custom_field_user_relationship_loads_correctly(): void
    {
        $field = CustomField::factory()->create();

        $this->assertInstanceOf(User::class, $field->user);
        $this->assertSame($field->user_id, $field->user->id);
    }

    public function test_supported_field_type_states(): void
    {
        $text = CustomField::factory()->text()->create();
        $this->assertSame('text', $text->type);

        $number = CustomField::factory()->number()->create();
        $this->assertSame('number', $number->type);

        $date = CustomField::factory()->date()->create();
        $this->assertSame('date', $date->type);

        $select = CustomField::factory()->select()->create();
        $this->assertSame('select', $select->type);

        $checkbox = CustomField::factory()->checkbox()->create();
        $this->assertSame('checkbox', $checkbox->type);

        $radio = CustomField::factory()->radio()->create();
        $this->assertSame('radio', $radio->type);
    }

    public function test_select_and_radio_options_format(): void
    {
        $select = CustomField::factory()->select(['Small', 'Medium', 'Large'])->create();
        $this->assertSame('select', $select->type);
        $this->assertSame(['Small', 'Medium', 'Large'], $select->options);

        $radio = CustomField::factory()->radio(['Yes', 'No'])->create();
        $this->assertSame('radio', $radio->type);
        $this->assertSame(['Yes', 'No'], $radio->options);
    }

    public function test_list_scoped_field_creates_valid_contact_list_with_same_user(): void
    {
        $field = CustomField::factory()->forList()->create();

        $this->assertSame('list', $field->scope);
        $this->assertNotNull($field->contact_list_id);
        $this->assertInstanceOf(ContactList::class, $field->contactList);
        $this->assertSame($field->user_id, $field->contactList->user_id);
        $this->assertDatabaseHas('custom_fields', [
            'id' => $field->id,
            'scope' => 'list',
            'contact_list_id' => $field->contact_list_id,
            'user_id' => $field->user_id,
        ]);
    }

    public function test_list_scoped_field_with_existing_list(): void
    {
        $existingList = ContactList::factory()->create();
        $field = CustomField::factory()->forList($existingList)->create();

        $this->assertSame($existingList->id, $field->contact_list_id);
        $this->assertSame($existingList->user_id, $field->user_id);
        $this->assertSame('list', $field->scope);
    }
}
