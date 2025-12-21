<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Separates system pages (HTML pages) from system emails (email templates).
     */
    public function up(): void
    {
        // Step 1: Rename system_messages to system_pages
        Schema::rename('system_messages', 'system_pages');
        
        // Step 2: Add access column for visibility control
        Schema::table('system_pages', function (Blueprint $table) {
            $table->enum('access', ['public', 'private'])->default('public')->after('content');
        });

        // Step 3: Create system_emails table for email templates
        Schema::create('system_emails', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->comment('e.g., new_subscriber_notification');
            $table->foreignId('contact_list_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name')->comment('Internal name');
            $table->string('subject')->comment('Email subject line');
            $table->longText('content')->nullable()->comment('Email HTML content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['slug', 'contact_list_id']);
        });

        // Step 4: Seed default system emails
        $emails = [
            [
                'slug' => 'new_subscriber_notification',
                'name' => 'Powiadomienie o nowym subskrybencie',
                'subject' => 'Nowy subskrybent na liście [[list-name]]',
                'content' => '<h2>Nowy subskrybent!</h2><p>Na listę <strong>[[list-name]]</strong> zapisał się nowy subskrybent:</p><p><strong>Email:</strong> [[email]]</p><p><strong>Data:</strong> [[date]]</p>',
            ],
            [
                'slug' => 'activation_email',
                'name' => 'Email aktywacyjny',
                'subject' => 'Potwierdź swój adres email',
                'content' => '<h2>Potwierdź subskrypcję</h2><p>Kliknij poniższy link, aby potwierdzić swój adres email:</p><p><a href="[[activation-link]]">Potwierdź adres email</a></p>',
            ],
            [
                'slug' => 'welcome_email',
                'name' => 'Email powitalny',
                'subject' => 'Witamy na liście [[list-name]]!',
                'content' => '<h2>Witaj!</h2><p>Dziękujemy za zapisanie się na listę <strong>[[list-name]]</strong>.</p>',
            ],
            [
                'slug' => 'unsubscribe_confirmation_email',
                'name' => 'Potwierdzenie wypisu',
                'subject' => 'Zostałeś wypisany z listy',
                'content' => '<h2>Potwierdzenie wypisu</h2><p>Zostałeś wypisany z listy <strong>[[list-name]]</strong>.</p>',
            ],
        ];

        foreach ($emails as $email) {
            DB::table('system_emails')->insert([
                'slug' => $email['slug'],
                'contact_list_id' => null,
                'name' => $email['name'],
                'subject' => $email['subject'],
                'content' => $email['content'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Step 5: Update system_pages with proper page slugs (not email slugs)
        // Remove email-related entries that were incorrectly in system_messages
        DB::table('system_pages')
            ->where('slug', 'new_subscriber_notification')
            ->whereNull('contact_list_id')
            ->delete();

        // Ensure all page slugs are correct
        $pages = [
            [
                'slug' => 'signup_success',
                'name' => 'Zapis poprawny',
                'title' => 'Dziękujemy za zapisanie się!',
                'content' => '<h1>Dziękujemy za zapisanie się!</h1><p>Twój adres email został dodany do naszej listy mailingowej.</p>',
            ],
            [
                'slug' => 'signup_error',
                'name' => 'Błąd zapisu',
                'title' => 'Zapis nie powiódł się',
                'content' => '<h1>Wystąpił błąd</h1><p>Przepraszamy, nie udało się dodać Twojego adresu email. Spróbuj ponownie później.</p>',
            ],
            [
                'slug' => 'signup_exists',
                'name' => 'Adres już istnieje',
                'title' => 'Adres email już istnieje',
                'content' => '<h1>Już jesteś na liście</h1><p>Ten adres email jest już zapisany do naszej bazy.</p>',
            ],
            [
                'slug' => 'signup_exists_active',
                'name' => 'Adres aktywny już istnieje',
                'title' => 'Adres email już istnieje (aktywny)',
                'content' => '<h1>Już jesteś na liście</h1><p>Ten adres email jest już aktywny w naszej bazie.</p>',
            ],
            [
                'slug' => 'signup_exists_inactive',
                'name' => 'Adres nieaktywny już istnieje',
                'title' => 'Adres email już istnieje (nieaktywny)',
                'content' => '<h1>Adres wymaga aktywacji</h1><p>Ten adres email jest w naszej bazie, ale wymaga aktywacji. Sprawdź swoją skrzynkę email.</p>',
            ],
            [
                'slug' => 'activation_success',
                'name' => 'Aktywacja poprawna',
                'title' => 'Aktywacja przebiegła poprawnie',
                'content' => '<h1>Konto aktywowane!</h1><p>Twój adres email został pomyślnie zweryfikowany.</p>',
            ],
            [
                'slug' => 'activation_error',
                'name' => 'Błąd aktywacji',
                'title' => 'Aktywacja nie powiodła się',
                'content' => '<h1>Błąd aktywacji</h1><p>Link aktywacyjny jest nieprawidłowy lub wygasł.</p>',
            ],
            [
                'slug' => 'unsubscribe_success',
                'name' => 'Wypis poprawny',
                'title' => 'Wypisanie się przebiegło poprawnie',
                'content' => '<h1>Zostałeś wypisany</h1><p>Twój adres email został usunięty z naszej listy mailingowej.</p>',
            ],
            [
                'slug' => 'unsubscribe_error',
                'name' => 'Błąd wypisu',
                'title' => 'Wypisanie nie powiodło się',
                'content' => '<h1>Wystąpił błąd</h1><p>Nie udało się usunąć Twojego adresu z listy. Skontaktuj się z administratorem.</p>',
            ],
            [
                'slug' => 'unsubscribe_confirm',
                'name' => 'Potwierdzenie wypisu',
                'title' => 'Potwierdź wypisanie się',
                'content' => '<h1>Wymagane potwierdzenie</h1><p>Czy na pewno chcesz się wypisać z tej listy?</p><p><a href="[[unsubscribe-link]]">Tak, wypisz mnie</a></p>',
            ],
        ];

        foreach ($pages as $page) {
            $exists = DB::table('system_pages')
                ->where('slug', $page['slug'])
                ->whereNull('contact_list_id')
                ->exists();
                
            if (!$exists) {
                DB::table('system_pages')->insert([
                    'slug' => $page['slug'],
                    'contact_list_id' => null,
                    'name' => $page['name'],
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'access' => 'public',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Update existing with Polish names
                DB::table('system_pages')
                    ->where('slug', $page['slug'])
                    ->whereNull('contact_list_id')
                    ->update([
                        'name' => $page['name'],
                        'title' => $page['title'],
                        'content' => $page['content'],
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop system_emails table
        Schema::dropIfExists('system_emails');
        
        // Remove access column from system_pages
        Schema::table('system_pages', function (Blueprint $table) {
            $table->dropColumn('access');
        });
        
        // Rename back
        Schema::rename('system_pages', 'system_messages');
    }
};
