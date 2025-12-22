<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create pivot table for many-to-many relationship
        if (!Schema::hasTable('contact_list_subscriber')) {
            Schema::create('contact_list_subscriber', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
                $table->string('status')->default('active'); // status per list (active, unsubscribed, bounced)
                $table->timestamp('subscribed_at')->useCurrent();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();
                
                $table->unique(['contact_list_id', 'subscriber_id']);
            });
        }

        // 2. Add new columns to subscribers table
        Schema::table('subscribers', function (Blueprint $table) {
            if (!Schema::hasColumn('subscribers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('subscribers', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('subscribers', 'gender')) {
                $table->string('gender')->nullable()->after('last_name'); // male, female, other
            }
            if (!Schema::hasColumn('subscribers', 'is_active_global')) {
                $table->boolean('is_active_global')->default(true)->after('status'); // Global active status
            }
        });

        // 3. Migrate Data: Mege duplicate subscribers (same email per user)
        // Only run if contact_list_id still exists (meaning we haven't fully migrated yet)
        if (Schema::hasColumn('subscribers', 'contact_list_id')) {
            // Groups of subscribers with same email need to be merged into one.
            // The first one found will be the "master", others will be attached to it via pivot.

            // Get all unique user_ids from contact_lists that have subscribers
            $users = DB::table('contact_lists')
                ->join('subscribers', 'contact_lists.id', '=', 'subscribers.contact_list_id')
                ->select('contact_lists.user_id')
                ->distinct()
                ->pluck('user_id');

            // Also process subscribers that might have no list but handle existing data
            // For this migration, we assume valid foreign keys exist as per schema.

            $subscribers = DB::table('subscribers')
                ->join('contact_lists', 'subscribers.contact_list_id', '=', 'contact_lists.id')
                ->select(
                    'subscribers.*', 
                    'contact_lists.user_id as list_owner_id',
                    'contact_lists.id as original_list_id'
                )
                ->orderBy('subscribers.id') // Process in order
                ->get();

            // Map: "user_id|email" -> Master Subscriber ID
            $masterMap = [];

            foreach ($subscribers as $sub) {
                $key = $sub->list_owner_id . '|' . strtolower(trim($sub->email));

                if (!isset($masterMap[$key])) {
                    // This is the first time we see this email for this user.
                    // Promote this record to be the Master.
                    $masterMap[$key] = $sub->id;

                    // Update the master record with the user_id
                    DB::table('subscribers')
                        ->where('id', $sub->id)
                        ->update(['user_id' => $sub->list_owner_id]);
                    
                    // Add entry to pivot table for this list association
                    // Check if pivot entry already exists (idempotency)
                    $pivotExists = DB::table('contact_list_subscriber')
                        ->where('contact_list_id', $sub->original_list_id)
                        ->where('subscriber_id', $sub->id)
                        ->exists();
                        
                    if (!$pivotExists) {
                        DB::table('contact_list_subscriber')->insert([
                            'contact_list_id' => $sub->original_list_id,
                            'subscriber_id' => $sub->id,
                            'status' => $sub->status,
                            'created_at' => $sub->created_at,
                            'updated_at' => $sub->updated_at,
                        ]);
                    }

                } else {
                    // We have already seen this email for this user.
                    // This is a duplicate record.
                    $masterId = $masterMap[$key];

                    // Add entry to pivot table linking the Master to THIS list
                    // Check if already exists (in case of double subscription to same list - unlikely but possible in dirty data)
                    $exists = DB::table('contact_list_subscriber')
                        ->where('contact_list_id', $sub->original_list_id)
                        ->where('subscriber_id', $masterId)
                        ->exists();

                    if (!$exists) {
                        DB::table('contact_list_subscriber')->insert([
                            'contact_list_id' => $sub->original_list_id,
                            'subscriber_id' => $masterId,
                            'status' => $sub->status,
                            'created_at' => $sub->created_at,
                            'updated_at' => $sub->updated_at,
                        ]);
                    }

                    // Delete the duplicate record
                    // We use DELETE directly. 
                    // Note: If you have other tables referencing subscribers.id, you would need to update them to $masterId first.
                    // Assuming simple schema for now or cascading deletes if strictly set up.
                    // However, let's check for other relations like 'subscriber_field_values' or 'subscriber_tag'.
                    
                    // Update related tables to point to masterId before deleting
                    if (Schema::hasTable('subscriber_field_values')) {
                        DB::table('subscriber_field_values')->where('subscriber_id', $sub->id)->update(['subscriber_id' => $masterId]);
                    }
                    if (Schema::hasTable('subscriber_tag')) {
                        DB::table('subscriber_tag')->where('subscriber_id', $sub->id)->update(['subscriber_id' => $masterId]);
                    }
                    // Automation logs etc?
                    // For safety in this task, we assume standard relations.
                    
                    // Delete the duplicate
                    DB::table('subscribers')->where('id', $sub->id)->delete();
                }
            }

            // 4. Clean up schema details
            Schema::table('subscribers', function (Blueprint $table) {
                // Drop foreign key if it exists
                try {
                    $table->dropForeign(['contact_list_id']);
                } catch (\Exception $e) {
                    // Ignore if FK doesn't exist
                }
                
                if (Schema::hasColumn('subscribers', 'contact_list_id')) {
                    $table->dropColumn('contact_list_id');
                }
                
                // Add unique constraint per user
                // Check if index already exists? MySQL might error.
                // Best effort
                 try {
                    $table->unique(['user_id', 'email']);
                } catch (\Exception $e) {
                    // Ignore if unique index exists
                }
            });
        } elseif (Schema::hasColumn('subscribers', 'user_id')) {
             // If migrated but unique constraint missing?
             Schema::table('subscribers', function (Blueprint $table) {
                try {
                    $table->unique(['user_id', 'email']);
                } catch (\Exception $e) {
                    // Ignore
                }
             });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive migration (merges data). Reversing fully is complex.
        // We will try to restore structure but data splits might be lost.
        
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'email']);
            $table->foreignId('contact_list_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'phone', 'gender', 'is_active_global']);
        });
        
        // We'd ideally need to re-create duplicates based on pivot table, but for now we just drop pivot.
        Schema::dropIfExists('contact_list_subscriber');
    }
};
