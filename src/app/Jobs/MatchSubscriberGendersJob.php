<?php

namespace App\Jobs;

use App\Models\Subscriber;
use App\Models\User;
use App\Notifications\GenderMatchingCompleted;
use App\Services\GenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class MatchSubscriberGendersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes max
    public int $tries = 1;

    protected int $userId;
    protected string $country;
    protected string $cacheKey;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $country = 'PL')
    {
        $this->userId = $userId;
        $this->country = $country;
        $this->cacheKey = "gender_matching_progress_{$userId}";
    }

    /**
     * Execute the job.
     */
    public function handle(GenderService $genderService): void
    {
        // Initialize progress
        $this->updateProgress([
            'status' => 'running',
            'progress' => 0,
            'total' => 0,
            'processed' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'errors' => 0,
            'started_at' => now()->toIso8601String(),
        ]);

        try {
            // Get all subscribers to process
            $subscribers = Subscriber::where('user_id', $this->userId)
                ->whereNull('gender')
                ->whereNotNull('first_name')
                ->where('first_name', '!=', '')
                ->get();

            $total = $subscribers->count();
            $processed = 0;
            $matched = 0;
            $unmatched = 0;
            $errors = 0;
            $matchedDetails = [];

            // Process in batches
            foreach ($subscribers->chunk(100) as $batch) {
                foreach ($batch as $subscriber) {
                    try {
                        $detectedGender = $genderService->detectGender(
                            $subscriber->first_name,
                            $this->country,
                            $this->userId
                        );

                        if ($detectedGender) {
                            $subscriber->gender = $detectedGender;
                            $subscriber->save();
                            $matched++;

                            // Store first 100 matched for results
                            if (count($matchedDetails) < 100) {
                                $matchedDetails[] = [
                                    'id' => $subscriber->id,
                                    'email' => $subscriber->email,
                                    'first_name' => $subscriber->first_name,
                                    'gender' => $detectedGender,
                                ];
                            }
                        } else {
                            $unmatched++;
                        }
                    } catch (\Exception $e) {
                        $errors++;
                    }

                    $processed++;

                    // Update progress every 50 subscribers
                    if ($processed % 50 === 0) {
                        $this->updateProgress([
                            'status' => 'running',
                            'progress' => $total > 0 ? round(($processed / $total) * 100) : 0,
                            'total' => $total,
                            'processed' => $processed,
                            'matched' => $matched,
                            'unmatched' => $unmatched,
                            'errors' => $errors,
                        ]);
                    }
                }
            }

            // Final update
            $this->updateProgress([
                'status' => 'completed',
                'progress' => 100,
                'total' => $total,
                'processed' => $processed,
                'matched' => $matched,
                'unmatched' => $unmatched,
                'errors' => $errors,
                'matched_details' => $matchedDetails,
                'completed_at' => now()->toIso8601String(),
            ]);

            // Send notification to user
            $user = User::find($this->userId);
            if ($user) {
                $user->notify(new GenderMatchingCompleted($matched, $unmatched, $errors));
            }

        } catch (\Exception $e) {
            $this->updateProgress([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);

            throw $e;
        }
    }

    /**
     * Update progress in cache
     */
    protected function updateProgress(array $data): void
    {
        $current = Cache::get($this->cacheKey, []);
        Cache::put($this->cacheKey, array_merge($current, $data), now()->addHours(1));
    }

    /**
     * Get progress cache key
     */
    public static function getProgressKey(int $userId): string
    {
        return "gender_matching_progress_{$userId}";
    }
}
