<?php

namespace App\Jobs;

use App\Models\ContactList;
use App\Models\User;
use App\Notifications\ExportReady;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use SplTempFileObject;

class ExportSubscribersCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ContactList $contactList,
        public User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $filename = 'export_subscribers_' . $this->contactList->id . '_' . now()->format('Y_m_d_His') . '.csv';
        $path = 'exports/' . $filename;

        // Create CSV Writer
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        
        // Add Header
        $csv->insertOne([
            'Email',
            'First Name',
            'Last Name',
            'Status',
            'Subscribed At',
            'Verified At',
            'IP Address',
            'Source'
        ]);

        // Fetch subscribers efficiently
        $query = $this->contactList->subscribers()
            ->select(['email', 'first_name', 'last_name', 'status', 'subscribed_at', 'confirmed_at', 'ip_address', 'source'])
            ->cursor();

        foreach ($query as $subscriber) {
            $csv->insertOne([
                $subscriber->email,
                $subscriber->first_name,
                $subscriber->last_name,
                $subscriber->status,
                $subscriber->subscribed_at?->toIso8601String(),
                $subscriber->confirmed_at?->toIso8601String(),
                $subscriber->ip_address,
                $subscriber->source,
            ]);
        }

        // Store file
        Storage::put($path, $csv->toString());

        // Notify user
        $this->user->notify(new ExportReady($path, $this->contactList->name));
    }
}
