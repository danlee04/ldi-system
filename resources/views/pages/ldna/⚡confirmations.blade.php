<?php

use App\Actions\Ldna\CountLdnaConfirmationsDue;
use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('LDNA confirmations')] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->decidesOnTrainings(), 403);
    }

    #[Computed]
    public function cycle(): ?LdnaCycle
    {
        return LdnaCycle::current();
    }

    /**
     * @return Collection<int, LdnaAssessment>
     */
    #[Computed]
    public function assessments(): Collection
    {
        return $this->cycle === null
            ? collect()
            : app(CountLdnaConfirmationsDue::class)->confirmees(auth()->user(), $this->cycle);
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('LDNA confirmations') }}</flux:heading>

        @if ($this->cycle)
            <flux:text>
                {{ __('LDNA :year · closes on :date', ['year' => $this->cycle->year, 'date' => $this->cycle->closes_on->format('M j, Y')]) }}
            </flux:text>
        @endif
    </div>

    @if ($this->cycle === null)
        <flux:callout icon="clipboard-document-check" variant="secondary">
            {{ __('No LDNA is open right now.') }}
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Section') }}</flux:table.column>
                <flux:table.column>{{ __('Their assessment') }}</flux:table.column>
                <flux:table.column>{{ __('Your confirmation') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->assessments as $assessment)
                    <flux:table.row :key="$assessment->id">
                        <flux:table.cell>
                            <div class="w-56 truncate" title="{{ $assessment->employee->full_name }}">
                                <flux:link :href="route('ldna.review', $assessment)" wire:navigate>
                                    {{ $assessment->employee->listing_name }}
                                </flux:link>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="w-40 truncate" title="{{ $assessment->employee->section?->name }}">
                                {{ $assessment->employee->section?->name ?? '—' }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$assessment->isSelfSubmitted() ? 'green' : 'zinc'">
                                {{ $assessment->isSelfSubmitted() ? __('Submitted') : __('Not yet') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{-- Nothing is owed on somebody who has not answered
                                 yet, so their row is not marked as waiting. --}}
                            @if ($assessment->isConfirmed())
                                <flux:badge size="sm" color="green">{{ __('Confirmed') }}</flux:badge>
                            @elseif ($assessment->isSelfSubmitted())
                                <flux:badge size="sm" color="amber">{{ __('To confirm') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Waiting on them') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm"
                                :variant="$assessment->isSelfSubmitted() && ! $assessment->isConfirmed() ? 'primary' : 'ghost'"
                                :href="route('ldna.review', $assessment)" wire:navigate>
                                {{ $assessment->isConfirmed() ? __('Review') : __('Open') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">{{ __('Nobody is waiting on you.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif
</div>
