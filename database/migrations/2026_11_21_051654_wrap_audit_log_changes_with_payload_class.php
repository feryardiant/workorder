<?php

declare(strict_types=1);

use App\Actions\Approvals\Audit\ApprovalPayload;
use App\Actions\Audit\FeatureToggledPayload;
use App\Actions\Contacts\Audit\ContactDeletedPayload;
use App\Actions\Contacts\Audit\ContactRelationPayload;
use App\Actions\Contacts\Audit\ContactUpdatedPayload;
use App\Actions\Memberships\Audit\MemberAddedPayload;
use App\Actions\Memberships\Audit\MemberLeftPayload;
use App\Actions\Memberships\Audit\MemberRemovedPayload;
use App\Actions\Memberships\Audit\MemberRoleChangedPayload;
use App\Actions\Organizations\Audit\OrganizationCreatedPayload;
use App\Actions\Organizations\Audit\OrganizationUpdatedPayload;
use App\Actions\Projects\Audit\AttachmentChangedPayload;
use App\Actions\Projects\Audit\CancellationPayload;
use App\Actions\Projects\Audit\ProjectChangedPayload;
use App\Actions\Projects\Audit\ProjectKindChangedPayload;
use App\Actions\Projects\Audit\TaskChangedPayload;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite(fn (string $event, array $payload): ?array => $this->wrap($event, $payload));
    }

    public function down(): void
    {
        $this->rewrite(fn (string $event, array $payload): ?array => $this->unwrap($payload));
    }

    /**
     * @param  callable(string, array<mixed>): ?array<mixed>  $rewrite
     */
    private function rewrite(callable $rewrite): void
    {
        DB::table('audit_logs')
            ->whereNotNull('changes')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($rewrite): void {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->changes, true);

                    if (! \is_array($decoded) || $decoded === []) {
                        continue;
                    }

                    $changes = $rewrite((string) $row->event, $decoded);

                    if ($changes === null) {
                        continue;
                    }

                    DB::table('audit_logs')
                        ->where('id', $row->id)
                        ->update(['changes' => json_encode($changes, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    /**
     * @param  array<mixed>  $payload
     * @return ?array{class-string, array<mixed>}
     */
    private function wrap(string $event, array $payload): ?array
    {
        $class = $this->classFor($event);

        return $class === null ? null : [$class, $payload];
    }

    /**
     * @param  array<mixed>  $payload
     * @return ?array<mixed>
     */
    private function unwrap(array $payload): ?array
    {
        if (! array_is_list($payload) || \count($payload) !== 2 || ! \is_array($payload[1])) {
            return null;
        }

        return $payload[1];
    }

    /**
     * @return ?class-string
     */
    private function classFor(string $event): ?string
    {
        return match ($event) {
            'member.added' => MemberAddedPayload::class,
            'member.role_changed' => MemberRoleChangedPayload::class,
            'member.removed' => MemberRemovedPayload::class,
            'member.left' => MemberLeftPayload::class,
            'contact.created', 'contact.renewed' => ContactRelationPayload::class,
            'contact.updated' => ContactUpdatedPayload::class,
            'contact.deleted' => ContactDeletedPayload::class,
            'organization.created' => OrganizationCreatedPayload::class,
            'organization.updated' => OrganizationUpdatedPayload::class,
            'feature.toggled' => FeatureToggledPayload::class,
            'approval.requested', 'approval.accepted', 'approval.declined', 'approval.canceled' => ApprovalPayload::class,
            'project.changed' => ProjectChangedPayload::class,
            'project_kind.changed' => ProjectKindChangedPayload::class,
            'project.canceled', 'task.canceled' => CancellationPayload::class,
            'task.changed' => TaskChangedPayload::class,
            'attachment.changed' => AttachmentChangedPayload::class,
            default => null,
        };
    }
};
