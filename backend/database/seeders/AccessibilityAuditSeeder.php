<?php

namespace Database\Seeders;

use App\Actions\TransitionChallengeStatusAction;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Company;
use App\Models\CompetencyAssessmentLink;
use App\Models\EmotionalCheckIn;
use App\Models\Employee;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\FeedbackEntry;
use App\Models\FeedbackThread;
use App\Models\MediaAsset;
use App\Models\MentorshipRelationship;
use App\Models\Message;
use App\Models\NeurocognitiveObservation;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\PersonalityAssessment;
use App\Models\Plan;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\SkillTag;
use App\Models\Subscription;
use App\Models\SystemicMap;
use App\Models\SystemicMapEdge;
use App\Models\SystemicMapNode;
use App\Models\User;
use App\Models\UserSkill;
use App\Support\AuditTargetGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Audit fixtures for spec 019 (RF-019.1/RF-019.2).
 *
 * Runs only against an empty `*_audit` database (see AuditTargetGuard) and
 * requires A11Y_SEED_PASSWORD. Mirrors the fixture table documented in
 * specs/019-verificacion-accesible/fixtures.md.
 */
class AccessibilityAuditSeeder extends Seeder
{
    private const EMAIL_DOMAIN = '@audit.mis.test';

    public function run(): void
    {
        $reason = AuditTargetGuard::refusal(
            app()->environment(),
            (string) DB::connection()->getDatabaseName(),
            User::query()->count(),
        );

        if ($reason !== null) {
            $message = "Refusing to seed accessibility audit data: {$reason}.";
            $this->command?->error($message);

            throw new RuntimeException($message);
        }

        $password = env('A11Y_SEED_PASSWORD');
        if (! is_string($password) || $password === '') {
            throw new RuntimeException('A11Y_SEED_PASSWORD is required to seed the audit accounts.');
        }

        $now = CarbonImmutable::now();
        $passwordHash = Hash::make($password);
        $created = [];

        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            PlanSeeder::class,
            SkillTagSeeder::class,
            EvaluationCatalogSeeder::class,
        ]);

        // A personal audit plan WITHOUT the mentor workspace entitlement:
        // every PlanSeeder personal plan enables it, so kai needs its own.
        $lockedPlan = Plan::create([
            'code' => 'audit_personal_locked',
            'client_type' => 'personal',
            'name' => ['es' => 'Audit personal sin espacio', 'en' => 'Audit personal locked', 'pt' => 'Audit pessoal bloqueado'],
            'description' => ['es' => 'Plan de auditoría sin espacio del mentor.'],
            'price_monthly' => 1,
            'price_yearly' => 10,
            'max_mentors' => 1,
            'entitlements' => ['mentor_workspace' => false],
            'is_active' => true,
        ]);

        $premiumPlan = Plan::query()->where('code', 'personal_premium')->firstOrFail();
        $familyPlan = Plan::query()->where('code', 'family')->firstOrFail();
        $groupPlan = Plan::query()->where('code', 'group')->firstOrFail();
        $startupPlan = Plan::query()->where('code', 'startup')->firstOrFail();

        // ── Accounts ─────────────────────────────────────────────────────
        $admin = $this->auditUser('admin.audit', 'admin', null, $passwordHash);

        $ana = $this->auditUser('ana.audit', 'mentee', 'personal', $passwordHash);
        $kai = $this->auditUser('kai.audit', 'mentee', 'personal', $passwordHash);
        $carlos = $this->auditUser('carlos.audit', 'mentee', 'familiar', $passwordHash);
        $diana = $this->auditUser('diana.audit', 'mentee', 'grupal', $passwordHash);
        $mentor = $this->auditUser('mentor.audit', 'mentor', 'personal', $passwordHash);

        $companyAdmin = $this->auditUser('empresa.admin.audit', 'company_admin', 'empresa', $passwordHash);
        $employeeUser = $this->auditUser('empleado.audit', 'employee', 'empresa', $passwordHash);
        $employeeUser2 = $this->auditUser('empleado2.audit', 'employee', 'empresa', $passwordHash);
        $this->auditUser('empresa.setup.audit', 'company_admin', 'empresa', $passwordHash);

        $fillers = collect(range(1, 25))->map(
            fn (int $n): User => $this->auditUser(sprintf('relleno.%02d.audit', $n), 'mentee', 'personal', $passwordHash)
        );

        $created['users'] = User::query()->count();

        // ── Mentor skills (the User model has no bio column; skills only) ──
        SkillTag::query()->orderBy('name')->limit(3)->pluck('id')->each(
            fn (string $tagId) => UserSkill::create(['user_id' => $mentor->id, 'skill_tag_id' => $tagId, 'level' => 'expert'])
        );
        $created['user_skills'] = UserSkill::query()->count();

        // ── Subscriptions ────────────────────────────────────────────────
        $this->subscribe('user', $ana->id, $premiumPlan, $now);
        $this->subscribe('user', $kai->id, $lockedPlan, $now);

        // ── Collective sources ───────────────────────────────────────────
        $family = FamilyGroup::create([
            'head_user_id' => $carlos->id,
            'family_name' => 'Familia Audit',
            'description' => 'Grupo familiar de auditoría.',
            'plan_id' => $familyPlan->id,
        ]);
        FamilyMember::create([
            'family_group_id' => $family->id,
            'user_id' => $carlos->id,
            'full_name' => $carlos->full_name,
            'relationship' => 'head',
        ]);
        $this->subscribe('family_group', $family->id, $familyPlan, $now);

        $cohort = Cohort::create([
            'created_by' => $diana->id,
            'name' => 'Cohorte Audit',
            'description' => 'Cohorte de auditoría.',
            'max_members' => 10,
            'plan_id' => $groupPlan->id,
            'status' => 'active',
        ]);
        CohortMember::create([
            'cohort_id' => $cohort->id,
            'user_id' => $diana->id,
            'status' => 'active',
        ]);
        $this->subscribe('cohort', $cohort->id, $groupPlan, $now);

        $company = Company::create([
            'legal_name' => 'Audit Corp',
            'industry' => 'Servicios profesionales',
            'size' => 'sme',
            'admin_id' => $companyAdmin->id,
            'plan_id' => $startupPlan->id,
            'subscription_status' => 'active',
        ]);
        $companyAdmin->forceFill(['company_id' => $company->id])->save();
        $this->subscribe('company', $company->id, $startupPlan, $now);

        foreach ([[$employeeUser, 'Analista'], [$employeeUser2, 'Coordinador']] as [$employee, $position]) {
            $employee->forceFill(['company_id' => $company->id])->save();
            Employee::create([
                'company_id' => $company->id,
                'user_id' => $employee->id,
                'position' => $position,
                'department' => 'Auditoría',
                'status' => 'active',
                'invited_at' => $now->subDays(30),
                'activated_at' => $now->subDays(25),
            ]);
        }
        $created['employees'] = Employee::query()->count();

        // ── Personality assessments ──────────────────────────────────────
        $anaAssessment = PersonalityAssessment::factory()->create([
            'user_id' => $ana->id,
            'completed_at' => $now->subDays(20),
        ]);
        // kai: deliberately none (locked personality test state).
        $created['personality_assessments'] = PersonalityAssessment::query()->count();

        // ── Mentorship relationships ─────────────────────────────────────
        $anaRel = $this->relationship('personal', 'user', $ana->id, $mentor->id, $now->subDays(30));
        $this->relationship('personal', 'user', $kai->id, $mentor->id, $now->subDays(28));
        $this->relationship('familiar', 'family_group', $family->id, $mentor->id, $now->subDays(14));
        $created['mentorship_relationships'] = MentorshipRelationship::query()->count();

        // ── Sessions for ana ─────────────────────────────────────────────
        $sessions = [
            'future' => $this->session($anaRel, $now->addDays(3), 'scheduled'),
            'window_open' => $this->session($anaRel, $now->subHours(4), 'scheduled'),
            'past_uncompleted' => $this->session($anaRel, $now->subHours(26), 'scheduled'),
            'completed' => $this->session($anaRel, $now->subDays(7), 'completed'),
        ];

        foreach (array_keys($sessions) as $key) {
            foreach ([$ana, $mentor] as $attendee) {
                SessionAttendee::create([
                    'session_id' => $sessions[$key]->id,
                    'user_id' => $attendee->id,
                    'attended' => $key === 'completed',
                    'attendance_status' => $key === 'completed' ? 'attended' : 'not_confirmed',
                    'confirmed_at' => $key === 'completed' ? $now->subDays(7) : null,
                ]);
            }
        }
        $created['sessions'] = Session::query()->count();
        $created['session_attendees'] = SessionAttendee::query()->count();

        // ── Conversation ana <-> mentor ──────────────────────────────────
        foreach ([
            [$ana, 'Hola, ya preparé los materiales de esta semana.'],
            [$mentor, 'Perfecto, los reviso antes de la sesión.'],
            [$ana, '¿Repasamos también el objetivo de foco?'],
            [$mentor, 'Sí, y traeré una dinámica nueva para el reto.'],
            [$ana, 'Genial, nos vemos entonces.'],
            [$mentor, 'Nos vemos. Buen trabajo esta semana.'],
        ] as $i => [$sender, $content]) {
            $message = Message::create([
                'relationship_id' => $anaRel->id,
                'sender_id' => $sender->id,
                'content' => $content,
                'type' => 'text',
                'read_at' => $i < 4 ? $now->subHours(20 - $i) : null,
            ]);
            $message->forceFill(['created_at' => $now->subHours(24 - $i), 'updated_at' => $now->subHours(24 - $i)])->save();
        }
        $created['messages'] = Message::query()->count();

        // ── Challenge + submission + feedback (with attachment) ─────────
        $challenge = Challenge::factory()->forRelationship($anaRel)->create([
            'mentor_id' => $mentor->id,
            'mentee_id' => $ana->id,
            'title' => 'Reto de auditoría semanal',
            'status' => 'assigned',
        ]);
        app(TransitionChallengeStatusAction::class)->execute($challenge, $mentor, 'in_progress', $now->subDays(2));

        $submission = ChallengeSubmission::factory()->forChallenge($challenge)->create([
            'relationship_id' => $anaRel->id,
            'mentee_id' => $ana->id,
            'status' => 'submitted',
            'submitted_at' => $now->subDay(),
        ]);

        $thread = FeedbackThread::create([
            'relationship_id' => $anaRel->id,
            'challenge_id' => $challenge->id,
            'mentor_id' => $mentor->id,
            'mentee_id' => $ana->id,
            'status' => 'open',
        ]);
        $entry = FeedbackEntry::create([
            'feedback_thread_id' => $thread->id,
            'author_id' => $mentor->id,
            'body' => 'Entrega sólida: el razonamiento quedó claro y medible.',
        ]);

        $attachmentPath = "audit/feedback-{$entry->id}.txt";
        Storage::disk('local')->put($attachmentPath, "Feedback de auditoría adjunto.\n");
        MediaAsset::create([
            'relationship_id' => $anaRel->id,
            'uploaded_by' => $mentor->id,
            'mediaable_type' => $entry->getMorphClass(),
            'mediaable_id' => $entry->id,
            'purpose' => 'mentor_feedback',
            'disk' => 'local',
            'path' => $attachmentPath,
            'original_name' => 'feedback-adjunto.txt',
            'mime_type' => 'text/plain',
            'size' => Storage::disk('local')->size($attachmentPath),
        ]);

        $created['challenges'] = Challenge::query()->count();
        $created['challenge_submissions'] = ChallengeSubmission::query()->count();
        $created['feedback_entries'] = FeedbackEntry::query()->count();
        $created['media_assets'] = MediaAsset::query()->count();

        // ── Workspace records for ana ────────────────────────────────────
        NeurocognitiveObservation::factory()->create([
            'relationship_id' => $anaRel->id,
            'mentor_id' => $mentor->id,
            'mentee_id' => $ana->id,
            'observed_at' => $now->subDays(5),
        ]);
        EmotionalCheckIn::factory()->create([
            'relationship_id' => $anaRel->id,
            'mentee_id' => $ana->id,
            'checked_in_at' => $now->subDays(4),
        ]);

        $map = SystemicMap::factory()->create([
            'relationship_id' => $anaRel->id,
            'mentor_id' => $mentor->id,
            'mentee_id' => $ana->id,
        ]);
        $nodeA = SystemicMapNode::factory()->create(['systemic_map_id' => $map->id]);
        $nodeB = SystemicMapNode::factory()->create(['systemic_map_id' => $map->id]);
        SystemicMapEdge::factory()->create([
            'systemic_map_id' => $map->id,
            'from_node_id' => $nodeA->id,
            'to_node_id' => $nodeB->id,
        ]);

        $created['neurocognitive_observations'] = NeurocognitiveObservation::query()->count();
        $created['emotional_check_ins'] = EmotionalCheckIn::query()->count();
        $created['systemic_maps'] = SystemicMap::query()->count();

        // ── Objectives for ana ───────────────────────────────────────────
        $objectiveWithEvidence = Objective::factory()->individual($ana)->create([
            'relationship_id' => $anaRel->id,
            'created_by' => $mentor->id,
            'status' => 'in_progress',
            'title' => 'Objetivo de auditoría con evidencia',
        ]);
        ObjectiveEvidence::factory()->create([
            'objective_id' => $objectiveWithEvidence->id,
            'user_id' => $ana->id,
        ]);

        $objectiveWithHistory = Objective::factory()->individual($ana)->create([
            'relationship_id' => $anaRel->id,
            'created_by' => $mentor->id,
            'status' => 'not_started',
            'title' => 'Objetivo de auditoría con historial',
        ]);
        $objectiveWithHistory->update(['status' => 'in_progress']);
        $objectiveWithHistory->update(['status' => 'achieved', 'achieved_at' => $now->subDays(3)]);

        $created['objectives'] = Objective::query()->count();
        $created['objective_evidences'] = ObjectiveEvidence::query()->count();

        // ── Competency link + answered/available evaluations ─────────────
        CompetencyAssessmentLink::factory()
            ->forPersonalityAssessment($anaAssessment)
            ->forOceanFactor('O')
            ->create(['user_id' => $ana->id]);

        $catalog = Assessment::query()->where('is_active', true)->orderBy('slug')->limit(2)->get();
        if ($catalog->isNotEmpty()) {
            AssessmentResult::create([
                'user_id' => $ana->id,
                'assessment_id' => $catalog->first()->id,
                'answers' => [['question_id' => 1, 'option_index' => 0]],
                'score' => 80,
                'passed' => true,
                'completed_at' => $now->subDays(10),
            ]);
        }
        // Second catalog assessment stays unanswered: ana's "available" state.

        $created['competency_assessment_links'] = CompetencyAssessmentLink::query()->count();
        $created['assessment_results'] = AssessmentResult::query()->count();
        $created['filler_users'] = $fillers->count();

        $summary = collect($created)->map(fn (int $n, string $k): string => "{$k}: {$n}")->implode(', ');
        $this->command?->info("Accessibility audit data seeded. {$summary}");
    }

    private function auditUser(string $localPart, string $role, ?string $clientType, string $passwordHash): User
    {
        $user = User::factory()->create([
            'email' => "{$localPart}" . self::EMAIL_DOMAIN,
            'password' => $passwordHash,
            'role' => $role,
            'client_type' => $clientType,
            'locale' => 'es',
        ]);

        $user->syncApplicationRole($role);

        return $user;
    }

    private function subscribe(string $subscriberType, string $subscriberId, Plan $plan, CarbonImmutable $now): void
    {
        Subscription::create([
            'subscriber_type' => $subscriberType,
            'subscriber_id' => $subscriberId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $now->subYear(),
            'ends_at' => $now->addYear(),
        ]);
    }

    private function relationship(string $type, string $sourceType, string $sourceId, string $mentorId, CarbonImmutable $startedAt): MentorshipRelationship
    {
        return MentorshipRelationship::create([
            'type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'mentor_id' => $mentorId,
            'status' => 'active',
            'objectives' => 'Acompañamiento de auditoría.',
            'started_at' => $startedAt,
        ]);
    }

    private function session(MentorshipRelationship $relationship, CarbonImmutable $scheduledAt, string $status): Session
    {
        return Session::factory()->create([
            'relationship_id' => $relationship->id,
            'session_type' => 'individual',
            'title' => 'Sesión de auditoría',
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 60,
            'status' => $status,
        ]);
    }
}
