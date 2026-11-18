<?php

declare(strict_types=1);

namespace App\Livewire\ProjectKinds;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\ProjectKind;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, ProjectKind> $kinds
 */
class Index extends Component
{
    use HasCurrentOrganization, WithPagination;

    /**
     * @return LengthAwarePaginator<int, ProjectKind>
     */
    #[Computed]
    public function kinds(): LengthAwarePaginator
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return new LengthAwarePaginator([], 0, 15);
        }

        return ProjectKind::query()
            ->where('owner_organization_id', $organization->getKey())
            ->withCount('tasks')
            ->latest('created_at')
            ->paginate(15);
    }

    public function render(): ViewContract
    {
        /** @disregard P1013 */
        return view('livewire.project-kinds.index', [
            'kinds' => $this->kinds,
        ])->title(__('Project kinds'));
    }
}
