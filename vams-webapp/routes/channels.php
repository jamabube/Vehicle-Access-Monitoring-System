<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Live gate activity pushed to the dashboard.
 *
 * Private, and gated on the same permission as the dashboard itself — a
 * WebSocket is another way into the data, so it gets the same authorization
 * as the HTTP route rather than being left open (manuscript §1.2.2 objective 6).
 */
Broadcast::channel('gate-activity', function (User $user) {
    return $user->isActive() && $user->can('dashboard.view');
});
