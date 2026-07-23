<?php

use App\Models\Certificate;
use App\Models\Company;
use App\Models\Employee;
use App\Models\MentorshipRelationship;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Session;
use App\Models\User;

uses()->group('security');

it('prevents accessing another user relationship by id', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
    ]);

    $token = $other->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson("/api/v1/relationships/{$relationship->id}")->assertForbidden();
    $this->withToken($token)->putJson("/api/v1/relationships/{$relationship->id}/status", [
        'status' => 'completed',
    ])->assertForbidden();
    $this->withToken($token)->deleteJson("/api/v1/relationships/{$relationship->id}")->assertForbidden();
});

it('prevents accessing another user session by id', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
    ]);
    $session = Session::factory()->create(['relationship_id' => $relationship->id]);

    $token = $other->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson("/api/v1/sessions/{$session->id}")->assertForbidden();
    $this->withToken($token)->putJson("/api/v1/sessions/{$session->id}/status", [
        'status' => 'completed',
    ])->assertForbidden();
});

it('prevents sending and reading messages in another relationship', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
    ]);
    $message = Message::create([
        'relationship_id' => $relationship->id,
        'sender_id' => $owner->id,
        'content' => 'Private message',
        'type' => 'text',
    ]);

    $token = $other->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/messages', [
        'relationship_id' => $relationship->id,
        'content' => 'I should not be here',
    ])->assertForbidden();

    $this->withToken($token)->getJson("/api/v1/messages/{$message->id}")->assertForbidden();
    $this->withToken($token)->putJson("/api/v1/messages/{$message->id}/read")->assertForbidden();
});

it('prevents accessing another company and employees', function () {
    $admin = User::factory()->create(['client_type' => 'empresa', 'role' => 'company_admin']);
    $other = User::factory()->create();
    $employeeUser = User::factory()->create(['client_type' => 'empresa', 'role' => 'employee']);
    $company = Company::create([
        'legal_name' => 'Private Company',
        'admin_id' => $admin->id,
        'subscription_status' => 'trial',
    ]);
    $employee = Employee::create([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'status' => 'active',
    ]);

    $token = $other->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson("/api/v1/companies/{$company->id}")->assertForbidden();
    $this->withToken($token)->putJson("/api/v1/companies/{$company->id}", [
        'legal_name' => 'Changed',
    ])->assertForbidden();
    $this->withToken($token)->getJson("/api/v1/companies/{$company->id}/employees")->assertForbidden();
    $this->withToken($token)->putJson("/api/v1/employees/{$employee->id}", [
        'position' => 'Changed',
    ])->assertForbidden();
});

it('prevents marking another user notification as read', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $notification = Notification::create([
        'user_id' => $owner->id,
        'type' => 'session_reminder',
        'data' => [],
    ]);

    $token = $other->createToken('test')->plainTextToken;

    $this->withToken($token)->putJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
});

it('prevents accessing another user certificate', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->completed()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
    ]);
    $certificate = Certificate::factory()->create([
        'user_id' => $owner->id,
        'relationship_id' => $relationship->id,
    ]);

    $token = $other->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson("/api/v1/certificates/{$certificate->id}")->assertForbidden();
});
