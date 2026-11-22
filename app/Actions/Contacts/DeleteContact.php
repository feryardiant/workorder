<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\FileStorage;
use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\RelationshipPeriodReason;
use App\Events\Contacts\ContactDeleted;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteContact
{
    public function __construct(
        private FileStorage $fileStorage,
        private FindContact $findContact,
        private RelationshipTimeline $timeline,
    ) {
        // .
    }

    /**
     * Delete a tenant contact.
     *
     * The relationship is always detached. The entity (and an organization's
     * contact person) is soft-deleted only when it is not shared with another
     * tenant. Store paths are deleted after the transaction commits.
     *
     * @return list<string>
     */
    public function handle(Organization $tenant, int $contactId, bool $cleanupFiles = true, ?User $actor = null): array
    {
        $contact = $this->findContact->byId($tenant, $contactId);
        $stakeholder = $contact->stakeholder;

        abort_if($stakeholder === null, 404);

        $paths = [];

        DB::transaction(function () use ($tenant, $contact, $stakeholder, $actor, &$paths): void {
            // Serialize with a concurrent reuse or credential change.
            $stakeholder = $stakeholder->newQuery()
                ->withTrashed()
                ->whereKey($stakeholder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($stakeholder instanceof Organization) {
                $contactPerson = $stakeholder->primaryContact()->lockForUpdate()->first();
                $stakeholder->setRelation('primaryContact', $contactPerson);
            }

            $contact->setRelation('stakeholder', $stakeholder);

            $activeProjects = Project::query()
                ->where('owner_organization_id', $tenant->getKey())
                ->where('customer_type', $stakeholder->getMorphClass())
                ->where('customer_id', $stakeholder->getKey())
                ->whereNull('finished_date')
                ->whereNull('canceled_date')
                ->count();

            if ($activeProjects > 0) {
                throw ValidationException::withMessages([
                    'contact' => __('This customer still has :count active projects. Reassign or archive them before deleting.', ['count' => $activeProjects]),
                ]);
            }

            $removeOwned = $contact->canDeleteEntityFor($tenant);

            if ($removeOwned) {
                $paths = $stakeholder instanceof Organization
                    ? [
                        $stakeholder->getRawOriginal('logo_url'),
                        $stakeholder->primaryContact?->getRawOriginal('photo_url'),
                    ]
                    : [$stakeholder->getRawOriginal('photo_url')];
            }

            $period = $this->timeline->close($contact, RelationshipPeriodReason::Deleted, $actor);

            OrganizationRelationship::query()
                ->forOrganization($tenant)
                ->external()
                ->whereKey($contact->getKey())
                ->delete();

            event(new ContactDeleted($tenant, $actor, $stakeholder, ! $removeOwned, $period));

            if (! $removeOwned) {
                return;
            }

            if ($stakeholder instanceof Organization) {
                $contactPerson = $stakeholder->primaryContact;

                $stakeholder->delete();
                $contactPerson?->delete();

                return;
            }

            $stakeholder->delete();
        });

        $paths = array_values(array_filter($paths));

        if ($cleanupFiles) {
            foreach ($paths as $path) {
                $this->fileStorage->delete($path);
            }
        }

        return $paths;
    }

    /**
     * Delete several tenant contacts in one transaction.
     *
     * @param  array<int, int|string>  $contactIds
     */
    public function handleBulk(Organization $tenant, array $contactIds, ?User $actor = null): int
    {
        $ids = array_values(array_unique(array_map('intval', $contactIds)));

        if ($ids === []) {
            return 0;
        }

        $paths = [];
        $deleted = 0;

        DB::transaction(function () use ($tenant, $ids, $actor, &$paths, &$deleted): void {
            foreach ($ids as $id) {
                $paths = array_merge(
                    $paths,
                    $this->handle($tenant, $id, cleanupFiles: false, actor: $actor),
                );

                $deleted++;
            }
        });

        foreach ($paths as $path) {
            $this->fileStorage->delete($path);
        }

        return $deleted;
    }
}
