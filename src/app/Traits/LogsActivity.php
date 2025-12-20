<?php

namespace App\Traits;

use App\Models\ActivityLog;

/**
 * Trait for automatic activity logging on model events
 * 
 * Usage:
 * 1. Add `use LogsActivity;` to your model
 * 2. Optionally override $activityLogAttributes to specify which attributes to log
 * 3. Optionally override $activityLogName to customize the action prefix
 */
trait LogsActivity
{
    /**
     * Boot the trait
     */
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });

        static::updated(function ($model) {
            if ($model->wasChanged()) {
                $model->logActivity('updated', [
                    'old' => array_intersect_key($model->getOriginal(), $model->getChanges()),
                    'new' => $model->getChanges(),
                ]);
            }
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }

    /**
     * Log activity for this model
     */
    public function logActivity(string $event, array $properties = []): void
    {
        $actionPrefix = $this->getActivityLogName();
        $action = "{$actionPrefix}.{$event}";

        // Get loggable attributes if defined
        if (!empty($properties) && property_exists($this, 'activityLogAttributes')) {
            $allowedKeys = $this->activityLogAttributes;
            
            if (isset($properties['old'])) {
                $properties['old'] = array_intersect_key($properties['old'], array_flip($allowedKeys));
            }
            if (isset($properties['new'])) {
                $properties['new'] = array_intersect_key($properties['new'], array_flip($allowedKeys));
            }
        }

        // Add identifying information
        if (method_exists($this, 'getActivityLogDescription')) {
            $properties['description'] = $this->getActivityLogDescription();
        } elseif (isset($this->name)) {
            $properties['name'] = $this->name;
        } elseif (isset($this->email)) {
            $properties['email'] = $this->email;
        } elseif (isset($this->subject)) {
            $properties['subject'] = $this->subject;
        }

        ActivityLog::log($action, $this, $properties);
    }

    /**
     * Get the action name prefix (e.g., 'subscriber', 'message')
     */
    protected function getActivityLogName(): string
    {
        if (property_exists($this, 'activityLogName')) {
            return $this->activityLogName;
        }

        // Generate from class name: App\Models\Subscriber -> subscriber
        $className = class_basename($this);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    /**
     * Manually log a custom activity
     */
    public function logCustomActivity(string $action, array $properties = []): void
    {
        ActivityLog::log($action, $this, $properties);
    }
}
