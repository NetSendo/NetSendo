<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Pixel live visitors channel - only the owner can listen
 */
Broadcast::channel('pixel.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
